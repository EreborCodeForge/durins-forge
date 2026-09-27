<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tests\Unit\Console;

use EreborCodeForge\Durin\Forge\Console\Commands\MakeModuleCommand;
use EreborCodeForge\Durin\Forge\Tooling\Generators\GeneratorRunner;
use EreborCodeForge\Durin\Forge\Tooling\Generators\ModuleGenerator;
use EreborCodeForge\Durin\Forge\Tooling\Generators\NameInflector;
use EreborCodeForge\Durin\Core\Mutation\ScaffoldWriter;
use PHPUnit\Framework\TestCase;

final class MakeModuleCommandTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'durin_make_module_' . uniqid('', true);
        mkdir($this->tempRoot, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->tempRoot);
        parent::tearDown();
    }

    public function test_make_module_command_creates_module(): void
    {
        $cwd = getcwd();
        chdir($this->tempRoot);

        try {
            $command = new MakeModuleCommand(
                new GeneratorRunner(new ScaffoldWriter()),
                new ModuleGenerator(),
                new NameInflector(),
            );
            $command->setArgs(['Billing']);

            ob_start();
            $code = $command->execute();
            $output = (string) ob_get_clean();

            $this->assertSame(0, $code);
            $this->assertStringContainsString('Created module Billing', $output);
            $this->assertStringContainsString('src/Modules/Billing', $output);
            $this->assertFileExists($this->tempRoot . '/src/Modules/Billing/module.php');
        } finally {
            if (is_string($cwd)) {
                chdir($cwd);
            }
        }
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
