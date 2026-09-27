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
        ], JSON_THROW_ON_ERROR));
        file_put_contents($this->consumerRoot . '/durin.yaml', "application:\n  name: consumer-smoke\n");

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

    private function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($dir);
    }
}
