<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tests\Unit\Tooling\Init;

use EreborCodeForge\Durin\Core\Mutation\ScaffoldWriter;
use EreborCodeForge\Durin\Forge\Tooling\Init\ApplicationInitializer;
use EreborCodeForge\Durin\Forge\Tooling\Progress\InitProgressReporter;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\EregionConfigurator;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\EregionInstaller;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimePlan;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimeProvisioner;
use EreborCodeForge\Durin\Presets\Registry\DefaultPresetRegistryFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NeutralRootInitTest extends TestCase
{
    /** @var list<string> */
    private array $roots = [];

    protected function tearDown(): void
    {
        foreach ($this->roots as $root) {
            $this->removeTree($root);
        }
        $this->roots = [];
        parent::tearDown();
    }

    #[DataProvider('httpPresetProvider')]
    public function test_http_init_finalizes_runtime_and_merges_composer_env(string $preset): void
    {
        $root = $this->seedNeutralRoot('billing');

        ob_start();
        $result = $this->initializer()->initialize(
            $root,
            $preset,
            new InitProgressReporter(jsonl: true),
            skipRuntimeInstall: true,
        );
        ob_end_clean();

        $this->assertSame($preset, $result['preset']);
        $this->assertSame('mithril-http', $result['runtime']->executionRuntime);
        $this->assertSame('eregion', $result['runtime']->supervisor);

        $yaml = (string) file_get_contents($root . '/durin.yaml');
        $this->assertStringContainsString('preset: ' . $preset, $yaml);
        $this->assertStringContainsString('execution: mithril-http', $yaml);
        $this->assertStringContainsString('supervisor: eregion', $yaml);
        $this->assertStringNotContainsString('state: unresolved', $yaml);

        $composer = json_decode((string) file_get_contents($root . '/composer.json'), true);
        $this->assertIsArray($composer);
        $this->assertSame('acme/billing', $composer['name']);
        $this->assertSame('App\\Kernel', $composer['extra']['mithril']['kernel']);
        $this->assertSame('v0.4.0', $composer['extra']['mithril']['eregion']);
        $this->assertArrayNotHasKey('job_kernel', $composer['extra']['mithril']);

        $env = (string) file_get_contents($root . '/.env.example');
        $this->assertStringContainsString('APP_NAME=billing', $env);
        $this->assertStringContainsString('APP_URL=', $env);
        $this->assertStringContainsString('APP_PORT=', $env);

        $this->assertFileExists($root . '/src/Kernel.php');
        $this->assertFileExists($root . '/routes/web.php');
        $this->assertFileExists($root . '/public/index.php');
        $this->assertFileDoesNotExist($root . '/src/JobKernel.php');
    }

    public function test_worker_init_strips_http_residuals_and_eregion_pin(): void
    {
        $root = $this->seedNeutralRoot('jobs', withHttpStubs: true);

        ob_start();
        $result = $this->initializer()->initialize(
            $root,
            'worker',
            new InitProgressReporter(jsonl: true),
            skipRuntimeInstall: true,
        );
        ob_end_clean();

        $this->assertSame('mithril-job', $result['runtime']->executionRuntime);
        $this->assertNull($result['runtime']->supervisor);

        $yaml = (string) file_get_contents($root . '/durin.yaml');
        $this->assertStringContainsString('execution: mithril-job', $yaml);
        $this->assertStringContainsString('mode: job', $yaml);
        $this->assertStringNotContainsString('supervisor:', $yaml);
        $this->assertStringNotContainsString('server:', $yaml);

        $composer = json_decode((string) file_get_contents($root . '/composer.json'), true);
        $this->assertIsArray($composer);
        $this->assertSame('App\\JobKernel', $composer['extra']['mithril']['job_kernel']);
        $this->assertArrayNotHasKey('kernel', $composer['extra']['mithril']);
        $this->assertArrayNotHasKey('eregion', $composer['extra']['mithril']);

        $this->assertFileExists($root . '/src/JobKernel.php');
        $this->assertFileDoesNotExist($root . '/src/Kernel.php');
        $this->assertDirectoryDoesNotExist($root . '/routes');
        $this->assertDirectoryDoesNotExist($root . '/public');
        $this->assertFileDoesNotExist($root . '/eregion.yaml');
    }

    /**
     * @return list<array{0: string}>
     */
    public static function httpPresetProvider(): array
    {
        return [
            ['minimal'],
            ['service'],
        ];
    }

    private function initializer(): ApplicationInitializer
    {
        return new ApplicationInitializer(
            engine: (new DefaultPresetRegistryFactory())->engine(),
            writer: new ScaffoldWriter(),
            runtime: new RuntimeProvisioner(
                installer: new class extends EregionInstaller {
                    public function install(string $applicationRoot, bool $force = false): array
                    {
                        return ['path' => '', 'version' => 'test', 'asset' => '', 'action' => 'skipped'];
                    }

                    public function isInstalled(string $applicationRoot): bool
                    {
                        return true;
                    }
                },
                configurator: new class extends EregionConfigurator {
                    public function configure(
                        string $applicationRoot,
                        bool $force = false,
                        ?RuntimePlan $plan = null,
                    ): array {
                        return [];
                    }
                },
                defaultInstallRunner: false,
            ),
        );
    }

    private function seedNeutralRoot(string $name, bool $withHttpStubs = false): string
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'durin_neutral_init_' . uniqid('', true);
        $this->roots[] = $root;
        mkdir($root . '/src', 0777, true);
        mkdir($root . '/config', 0777, true);
        mkdir($root . '/var/cache', 0777, true);
        mkdir($root . '/var/runtime', 0777, true);

        file_put_contents($root . '/composer.json', json_encode([
            'name' => 'acme/' . $name,
            'type' => 'project',
            'require' => [
                'php' => '^8.5',
                'ereborcodeforge/durins-forge' => '^0.2.3',
            ],
            'autoload' => ['psr-4' => ['App\\' => 'src/']],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        file_put_contents($root . '/.env.example', "APP_NAME={$name}\nAPP_ENV=development\nAPP_DEBUG=true\n");
        file_put_contents($root . '/durin.yaml', <<<YAML
application:
  name: {$name}
  preset: uninitialized

runtime:
  state: unresolved

features:
  http: false
  messaging: false

architecture:
  modules: false
YAML);

        file_put_contents($root . '/config/app.php', "<?php\nreturn ['name' => '{$name}', 'providers' => []];\n");

        if ($withHttpStubs) {
            mkdir($root . '/routes', 0777, true);
            mkdir($root . '/public', 0777, true);
            file_put_contents($root . '/routes/web.php', "<?php\n// stub\n");
            file_put_contents($root . '/public/index.php', "<?php\nexit(1);\n");
        }

        return $root;
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            if (is_file($path)) {
                @unlink($path);
            }
            return;
        }
        foreach (scandir($path) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $path . DIRECTORY_SEPARATOR . $item;
            is_dir($full) ? $this->removeTree($full) : @unlink($full);
        }
        @rmdir($path);
    }
}
