<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tests\Integration;

use EreborCodeForge\Durin\Forge\Support\ApplicationPath;
use EreborCodeForge\Durin\Presets\Registry\DefaultPresetRegistryFactory;
use EreborCodeForge\Durin\Core\Contract\ProjectOptions;
use EreborCodeForge\Durin\Core\Mutation\ScaffoldWriter;
use PHPUnit\Framework\TestCase;

/**
 * Proves Forge CLI / generators write into the consumer project, never into the package tree.
 */
final class ConsumerModeIntegrationTest extends TestCase
{
    private string $consumerRoot;

    private string $forgeRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->forgeRoot = dirname(__DIR__, 2);
        $this->consumerRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'durin_consumer_' . uniqid('', true);
        mkdir($this->consumerRoot, 0777, true);
    }

    protected function tearDown(): void
    {
        ApplicationPath::reset();
        $this->removeTree($this->consumerRoot);
        parent::tearDown();
    }

    public function test_generators_write_only_under_consumer_root(): void
    {
        $engine = (new DefaultPresetRegistryFactory())->engine();
        $writer = new ScaffoldWriter();
        $plan = $engine->plan(new ProjectOptions('consumer-app', 'minimal', $this->consumerRoot));
        $this->assertTrue($writer->write($this->consumerRoot, $plan)->ok);
        $this->linkInstalledPackage();

        ApplicationPath::setRoot($this->consumerRoot);

        $php = PHP_BINARY;
        $bin = $this->forgeRoot . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'durin';

        $cwd = getcwd();
        chdir($this->consumerRoot);
        try {
            $moduleOut = [];
            $moduleCode = 0;
            exec(
                escapeshellarg($php) . ' ' . escapeshellarg($bin) . ' make:module Billing 2>&1',
                $moduleOut,
                $moduleCode
            );
            $this->assertSame(0, $moduleCode, implode("\n", $moduleOut));

            $ucOut = [];
            $ucCode = 0;
            exec(
                escapeshellarg($php) . ' ' . escapeshellarg($bin) . ' make:usecase CreateInvoice --module=Billing 2>&1',
                $ucOut,
                $ucCode
            );
            $this->assertSame(0, $ucCode, implode("\n", $ucOut));
        } finally {
            if (is_string($cwd)) {
                chdir($cwd);
            }
        }

        $this->assertDirectoryExists($this->consumerRoot . '/src/Modules/Billing');
        $this->assertFileExists(
            $this->consumerRoot . '/src/Modules/Billing/Application/CreateInvoice/CreateInvoice.php'
        );

        $packageSrc = realpath($this->forgeRoot . '/src');
        $this->assertNotFalse($packageSrc);
        $this->assertDirectoryDoesNotExist($packageSrc . '/Modules');
        $this->assertDirectoryDoesNotExist($packageSrc . '/Modules/Billing');
    }

    public function test_base_path_resolves_consumer_root_not_package(): void
    {
        ApplicationPath::setRoot($this->consumerRoot);
        $this->assertSame(
            (realpath($this->consumerRoot) ?: $this->consumerRoot),
            base_path()
        );
        $this->assertNotSame(
            realpath($this->forgeRoot) ?: $this->forgeRoot,
            base_path()
        );
    }

    public function test_bin_durin_lists_commands_with_consumer_cwd(): void
    {
        file_put_contents($this->consumerRoot . '/composer.json', json_encode([
            'name' => 'app/consumer-smoke',
            'type' => 'project',
            'require' => [
                'ereborcodeforge/durins-forge' => '^0.4',
            ],
        ], JSON_THROW_ON_ERROR));
        file_put_contents($this->consumerRoot . '/durin.yaml', "application:\n  name: consumer-smoke\n");
        $this->linkInstalledPackage();

        $php = PHP_BINARY;
        $bin = $this->forgeRoot . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'durin';
        $cwd = getcwd();
        chdir($this->consumerRoot);
        try {
            $out = [];
            $code = 0;
            exec(escapeshellarg($php) . ' ' . escapeshellarg($bin) . ' 2>&1', $out, $code);
            $joined = implode("\n", $out);
            $this->assertSame(0, $code, $joined);
            $this->assertStringContainsString('doctor', $joined);
            $this->assertStringContainsString('make:module', $joined);
        } finally {
            if (is_string($cwd)) {
                chdir($cwd);
            }
        }
    }

    private function linkInstalledPackage(): void
    {
        $package = $this->consumerRoot
            . DIRECTORY_SEPARATOR . 'vendor'
            . DIRECTORY_SEPARATOR . 'ereborcodeforge'
            . DIRECTORY_SEPARATOR . 'durins-forge';
        mkdir(dirname($package), 0777, true);
        if (!@symlink($this->forgeRoot, $package)) {
            if (PHP_OS_FAMILY !== 'Windows') {
                self::markTestSkipped('symlink() is not available.');
            }
            exec(
                'cmd.exe /C mklink /J ' . escapeshellarg($package) . ' ' . escapeshellarg($this->forgeRoot),
                $output,
                $status,
            );
            if ($status !== 0 || !is_dir($package)) {
                self::markTestSkipped('mklink /J is not available: ' . implode(' ', $output));
            }
        }

        $autoload = $this->forgeRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
        file_put_contents(
            $this->consumerRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php',
            "<?php\nrequire " . var_export($autoload, true) . ";\n",
        );
    }

    private function removeTree(string $dir): void
    {
        if ($this->isLinkedDirectory($dir)) {
            @rmdir($dir);

            return;
        }
        if (!is_dir($dir)) {
            @unlink($dir);
            @rmdir($dir);

            return;
        }
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $this->removeTree($dir . DIRECTORY_SEPARATOR . $item);
        }
        @rmdir($dir);
    }

    private function isLinkedDirectory(string $path): bool
    {
        if (is_link($path)) {
            return true;
        }
        if (PHP_OS_FAMILY !== 'Windows' || !is_dir($path)) {
            return false;
        }
        $real = realpath($path);
        if ($real === false) {
            return false;
        }
        $normalize = static function (string $value): string {
            return rtrim(strtolower(str_replace('\\', '/', $value)), '/');
        };

        return $normalize($path) !== $normalize($real);
    }
}
