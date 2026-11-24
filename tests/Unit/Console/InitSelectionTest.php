<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tests\Unit\Console;

use EreborCodeForge\Durin\Core\Manifest\DurinManifestParser;
use EreborCodeForge\Durin\Forge\Console\Commands\InitCommand;
use EreborCodeForge\Durin\Forge\Console\Commands\NewCommand;
use EreborCodeForge\Durin\Forge\Support\ApplicationPath;
use EreborCodeForge\Durin\Forge\Tooling\Init\ApplicationInitializer;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\EregionInstaller;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimeProvisioner;
use PHPUnit\Framework\TestCase;

final class InitSelectionTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'durin_selection_' . uniqid('', true);
        mkdir($this->tempRoot, 0777, true);
        ApplicationPath::reset();
    }

    protected function tearDown(): void
    {
        ApplicationPath::reset();
        $this->removeTree($this->tempRoot);
        parent::tearDown();
    }

    public function test_http_consumer_capability_fails_before_mutation(): void
    {
        $this->seedNeutralApp('orders');
        $before = $this->fingerprint();
        $code = $this->runInit(['--preset=service', '--with=consumer', '--skip-runtime-install', '--progress=jsonl']);

        $this->assertSame(2, $code['code']);
        $this->assertStringContainsString('restricted to job presets', $code['output']);
        $this->assertStringNotContainsString('"type":"complete"', $code['output']);
        $this->assertSame($before, $this->fingerprint());
        $this->assertFileDoesNotExist($this->tempRoot . '/src/Kernel.php');
    }

    public function test_backlog_and_standalone_scaling_fail_before_mutation(): void
    {
        $this->seedNeutralApp('mail');
        $before = $this->fingerprint();

        $backlog = $this->runInit([
            '--preset=consumer',
            '--supervisor=eregion',
            '--scaling=backlog',
            '--skip-runtime-install',
            '--progress=jsonl',
        ]);
        $this->assertSame(2, $backlog['code']);
        $this->assertStringContainsString('requires a configured backlog provider', $backlog['output']);
        $this->assertStringNotContainsString('"type":"complete"', $backlog['output']);
        $this->assertSame($before, $this->fingerprint());

        $scaling = $this->runInit(['--preset=worker', '--scaling=fixed', '--skip-runtime-install']);
        $this->assertSame(2, $scaling['code']);
        $this->assertStringContainsString('supervisor eregion', $scaling['output']);
        $this->assertSame($before, $this->fingerprint());
    }

    public function test_http_supervisor_none_and_invalid_workers_fail_closed(): void
    {
        $this->seedNeutralApp('api');
        $before = $this->fingerprint();

        $none = $this->runInit(['--preset=producer', '--supervisor=none', '--skip-runtime-install']);
        $this->assertSame(2, $none['code']);
        $this->assertStringContainsString('no standalone HTTP launcher', $none['output']);

        $bounds = $this->runInit([
            '--preset=consumer',
            '--supervisor=eregion',
            '--workers-min=4',
            '--workers-max=1',
            '--skip-runtime-install',
        ]);
        $this->assertSame(2, $bounds['code']);
        $this->assertStringContainsString('min must be less than or equal to max', $bounds['output']);
        $this->assertSame($before, $this->fingerprint());
    }

    public function test_consumer_resources_workload_survives_second_init(): void
    {
        $this->seedNeutralApp('mail');
        $first = $this->runInit([
            '--preset=consumer',
            '--supervisor=eregion',
            '--scaling=resources',
            '--workers-min=1',
            '--workers-max=4',
            '--resource-class=io',
            '--memory-mb=128',
            '--skip-runtime-install',
            '--progress=jsonl',
        ]);

        $this->assertSame(0, $first['code']);
        $this->assertStringContainsString('"type":"complete"', $first['output']);
        $manifest = (new DurinManifestParser())->parseFile($this->tempRoot . '/durin.yaml');
        $this->assertSame('consumer', $manifest->preset);
        $this->assertSame('mithril-job', $manifest->runtime?->execution);
        $this->assertSame('eregion', $manifest->runtime?->supervisor);
        $this->assertTrue($manifest->features['consumer']);
        $this->assertFalse($manifest->features['producer']);
        $this->assertFileExists($this->tempRoot . '/src/JobKernel.php');
        $this->assertFileDoesNotExist($this->tempRoot . '/public/index.php');

        $yaml = (string) file_get_contents($this->tempRoot . '/eregion.yaml');
        $this->assertStringContainsString('strategy: resources', $yaml);
        $this->assertStringContainsString('max: 4', $yaml);
        $this->assertStringContainsString('class: io', $yaml);
        $this->assertStringContainsString('memory_mb: 128', $yaml);
        $hash = hash('sha256', $yaml . (string) file_get_contents($this->tempRoot . '/durin.yaml'));

        $second = $this->runInit(['--preset=consumer', '--skip-runtime-install', '--progress=jsonl']);
        $this->assertSame(0, $second['code']);
        $this->assertStringContainsString('"idempotent":true', $second['output']);
        $again = hash('sha256', (string) file_get_contents($this->tempRoot . '/eregion.yaml') . (string) file_get_contents($this->tempRoot . '/durin.yaml'));
        $this->assertSame($hash, $again);
    }

    public function test_service_with_producer_keeps_http_runtime(): void
    {
        $this->seedNeutralApp('orders');
        $result = $this->runInit(['--preset=service', '--with=producer', '--skip-runtime-install', '--progress=jsonl']);

        $this->assertSame(0, $result['code']);
        $manifest = (new DurinManifestParser())->parseFile($this->tempRoot . '/durin.yaml');
        $this->assertSame('mithril-http', $manifest->runtime?->execution);
        $this->assertSame('eregion', $manifest->runtime?->supervisor);
        $this->assertTrue($manifest->features['producer']);
        $this->assertFalse($manifest->features['consumer']);
        $this->assertFileExists($this->tempRoot . '/src/Infrastructure/Messaging/UnconfiguredPublisher.php');
        $routes = (string) file_get_contents($this->tempRoot . '/routes/api.php');
        $this->assertStringNotContainsString('/publish', $routes);
    }

    public function test_local_new_rejects_workload_flags_without_creating_a_directory(): void
    {
        $command = new NewCommand();
        $command->setArgs(['mail', '--with=producer']);
        $cwd = getcwd();
        chdir($this->tempRoot);
        try {
            ob_start();
            $code = $command->execute();
            $output = (string) ob_get_clean();
        } finally {
            if (is_string($cwd)) {
                chdir($cwd);
            }
        }

        $this->assertSame(2, $code);
        $this->assertStringContainsString('not accepted by durins-forge new', $output);
        $this->assertFileDoesNotExist($this->tempRoot . '/mail');
    }

    /**
     * @param list<string> $args
     * @return array{code: int, output: string}
     */
    private function runInit(array $args): array
    {
        ApplicationPath::setRoot($this->tempRoot);
        $command = new InitCommand($this->initializer());
        $command->setArgs($args);
        ob_start();
        $code = $command->execute();

        return ['code' => $code, 'output' => (string) ob_get_clean()];
    }

    private function initializer(): ApplicationInitializer
    {
        $installer = new class extends EregionInstaller {
            public function install(string $applicationRoot, bool $force = false): array
            {
                return ['path' => '', 'version' => 'test', 'asset' => '', 'action' => 'skipped'];
            }

            public function isInstalled(string $applicationRoot): bool
            {
                return true;
            }
        };

        return new ApplicationInitializer(
            runtime: new RuntimeProvisioner($installer, defaultInstallRunner: false),
        );
    }

    private function seedNeutralApp(string $name): void
    {
        file_put_contents($this->tempRoot . '/composer.json', json_encode([
            'name' => 'acme/' . $name,
            'require' => ['php' => '^8.5'],
            'autoload' => ['psr-4' => ['App\\' => 'src/']],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        mkdir($this->tempRoot . '/src', 0777, true);
        file_put_contents($this->tempRoot . '/.env.example', "APP_NAME={$name}\n");
        file_put_contents($this->tempRoot . '/keep.txt', "untouched\n");
    }

    private function fingerprint(): string
    {
        $paths = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->tempRoot, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $paths[] = str_replace('\\', '/', substr($file->getPathname(), strlen($this->tempRoot)))
                    . ':' . hash('sha256', (string) file_get_contents($file->getPathname()));
            }
        }
        sort($paths);

        return implode("\n", $paths);
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $items = scandir($path) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $path . DIRECTORY_SEPARATOR . $item;
            is_dir($full) ? $this->removeTree($full) : unlink($full);
        }
        rmdir($path);
    }
}
