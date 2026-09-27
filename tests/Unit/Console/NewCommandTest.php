<?php

declare(strict_types=1);

namespace App\Tests\Unit\Console;

use App\Console\Commands\NewCommand;
use EreborCodeForge\Durin\Presets\Registry\DefaultPresetRegistryFactory;
use EreborCodeForge\Durin\Core\Mutation\ScaffoldWriter;
use PHPUnit\Framework\TestCase;

final class NewCommandTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'durin_new_' . uniqid('', true);
        mkdir($this->tempRoot, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->tempRoot);
        parent::tearDown();
    }

    public function test_new_command_scaffolds_minimal_project(): void
    {
        $cwd = getcwd();
        chdir($this->tempRoot);

        try {
            $command = new NewCommand(
                (new DefaultPresetRegistryFactory())->engine(),
                new ScaffoldWriter(),
            );
            $command->setArgs(['demo-api', '--preset=minimal']);

            ob_start();
            $code = $command->execute();
            $output = (string) ob_get_clean();

            $this->assertSame(0, $code);
            $this->assertStringContainsString('Created demo-api', $output);
            $this->assertFileExists($this->tempRoot . '/demo-api/durin.yaml');
            $this->assertDirectoryExists($this->tempRoot . '/demo-api/src/Http');
            $this->assertDirectoryDoesNotExist($this->tempRoot . '/demo-api/src/Domain');
        } finally {
            if (is_string($cwd)) {
                chdir($cwd);
            }
        }
    }

    public function test_new_command_rejects_unknown_preset(): void
    {
        $cwd = getcwd();
        chdir($this->tempRoot);

        try {
            $command = new NewCommand(
                (new DefaultPresetRegistryFactory())->engine(),
                new ScaffoldWriter(),
            );
            $command->setArgs(['x', '--preset=nope']);

            ob_start();
            $code = $command->execute();
            ob_end_clean();

            $this->assertSame(2, $code);
            $this->assertDirectoryDoesNotExist($this->tempRoot . '/x');
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
