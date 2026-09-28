<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tests\Unit\Console;

use EreborCodeForge\Durin\Core\Mutation\ScaffoldWriter;
use EreborCodeForge\Durin\Forge\Console\Commands\InitCommand;
use EreborCodeForge\Durin\Forge\Support\ApplicationPath;
use EreborCodeForge\Durin\Forge\Tooling\Init\ApplicationInitializer;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimeProvisioner;
use EreborCodeForge\Durin\Presets\Registry\DefaultPresetRegistryFactory;
use PHPUnit\Framework\TestCase;

final class InitCommandTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'durin_init_' . uniqid('', true);
        mkdir($this->tempRoot, 0777, true);
        ApplicationPath::reset();
    }

    protected function tearDown(): void
    {
        ApplicationPath::reset();
        $this->removeTree($this->tempRoot);
        parent::tearDown();
    }

    public function test_init_omitted_preset_uses_registry_default(): void
    {
        $this->seedNeutralApp('billing');
        ApplicationPath::setRoot($this->tempRoot);

        $command = new InitCommand($this->initializer());
        $command->setArgs(['--skip-runtime-install']);

        ob_start();
        $code = $command->execute();
        $output = (string) ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertFileExists($this->tempRoot . '/durin.yaml');
        $yaml = (string) file_get_contents($this->tempRoot . '/durin.yaml');
        $this->assertStringContainsString('preset: minimal', $yaml);
        $this->assertDirectoryExists($this->tempRoot . '/src/Http');
        $this->assertStringContainsString('minimal', $output);
    }

    public function test_init_explicit_preset_resolves_registry(): void
    {
        $this->seedNeutralApp('billing');
        ApplicationPath::setRoot($this->tempRoot);

        $command = new InitCommand($this->initializer());
        $command->setArgs(['--preset=service', '--skip-runtime-install']);

        ob_start();
        $code = $command->execute();
        ob_end_clean();

        $this->assertSame(0, $code);
        $yaml = (string) file_get_contents($this->tempRoot . '/durin.yaml');
        $this->assertStringContainsString('preset: service', $yaml);
        $this->assertDirectoryExists($this->tempRoot . '/src/Domain');
    }

    public function test_unknown_preset_fails(): void
    {
        $this->seedNeutralApp('billing');
        ApplicationPath::setRoot($this->tempRoot);

        $command = new InitCommand($this->initializer());
        $command->setArgs(['--preset=nope', '--skip-runtime-install']);

        ob_start();
        $code = $command->execute();
        ob_end_clean();

        $this->assertSame(2, $code);
    }

    public function test_jsonl_progress_is_ordered(): void
    {
        $this->seedNeutralApp('billing');
        ApplicationPath::setRoot($this->tempRoot);

        $command = new InitCommand($this->initializer());
        $command->setArgs(['--preset=minimal', '--progress=jsonl', '--skip-runtime-install']);

        ob_start();
        $code = $command->execute();
        $output = (string) ob_get_clean();

        $this->assertSame(0, $code);
        $lines = array_values(array_filter(array_map('trim', explode("\n", trim($output)))));
        $stages = [];
        foreach ($lines as $line) {
            $row = json_decode($line, true);
            $this->assertIsArray($row);
            if (($row['type'] ?? '') === 'progress') {
                $stages[] = $row['stage'];
            }
        }

        $this->assertSame('preset.resolve', $stages[0]);
        $this->assertContains('scaffold.plan', $stages);
        $this->assertContains('scaffold.apply', $stages);
        $this->assertContains('validate', $stages);
        $this->assertStringContainsString('"type":"complete"', $output);
    }

    public function test_same_preset_init_is_idempotent(): void
    {
        $this->seedNeutralApp('billing');
        ApplicationPath::setRoot($this->tempRoot);

        $command = new InitCommand($this->initializer());
        $command->setArgs(['--preset=minimal', '--skip-runtime-install']);

        ob_start();
        $this->assertSame(0, $command->execute());
        ob_end_clean();

        ob_start();
        $code = $command->execute();
        $output = (string) ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Already initialized', $output);
    }

    public function test_different_preset_init_refuses_migration(): void
    {
        $this->seedNeutralApp('billing');
        ApplicationPath::setRoot($this->tempRoot);

        $command = new InitCommand($this->initializer());
        $command->setArgs(['--preset=minimal', '--skip-runtime-install']);
        ob_start();
        $this->assertSame(0, $command->execute());
        ob_end_clean();

        $command->setArgs(['--preset=service', '--skip-runtime-install']);
        ob_start();
        $code = $command->execute();
        $output = (string) ob_get_clean();

        $this->assertSame(1, $code);
        $this->assertStringContainsString('already initialized', $output);
    }

    public function test_eregion_default_when_preset_runner_null(): void
    {
        $registry = (new DefaultPresetRegistryFactory())->create();
        $definition = $registry->definition('service');
        $this->assertNull($definition->runtime()->runner);

        $provisioner = new RuntimeProvisioner(
            installer: new class extends \EreborCodeForge\Durin\Forge\Tooling\Runtime\EregionInstaller {
                public function install(string $applicationRoot, bool $force = false): array
                {
                    return ['path' => '', 'version' => 'test', 'asset' => '', 'action' => 'skipped'];
                }

                public function isInstalled(string $applicationRoot): bool
                {
                    return false;
                }
            },
            configurator: new class extends \EreborCodeForge\Durin\Forge\Tooling\Runtime\EregionConfigurator {
                public function configure(string $applicationRoot, bool $force = false): array
                {
                    return [];
                }
            },
        );
        $this->assertSame('eregion', $provisioner->resolveRunner($definition->runtime()));
        $this->assertTrue($provisioner->shouldInstall($definition->runtime()));
    }

    private function initializer(): ApplicationInitializer
    {
        $installer = new class extends \EreborCodeForge\Durin\Forge\Tooling\Runtime\EregionInstaller {
            public function install(string $applicationRoot, bool $force = false): array
            {
                return ['path' => '', 'version' => 'test', 'asset' => '', 'action' => 'skipped'];
            }

            public function isInstalled(string $applicationRoot): bool
            {
                return true;
            }
        };
        $configurator = new class extends \EreborCodeForge\Durin\Forge\Tooling\Runtime\EregionConfigurator {
            public function configure(string $applicationRoot, bool $force = false): array
            {
                return [['path' => $applicationRoot . '/eregion.yaml', 'action' => 'exists']];
            }
        };

        return new ApplicationInitializer(
            engine: (new DefaultPresetRegistryFactory())->engine(),
            writer: new ScaffoldWriter(),
            runtime: new RuntimeProvisioner($installer, $configurator, defaultInstallRunner: false),
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

