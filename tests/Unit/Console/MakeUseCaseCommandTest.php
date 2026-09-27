<?php

declare(strict_types=1);

namespace App\Tests\Unit\Console;

use App\Console\Commands\MakeUseCaseCommand;
use App\Tooling\Generators\GeneratorRunner;
use App\Tooling\Generators\NameInflector;
use App\Tooling\Generators\UseCaseGenerator;
use EreborCodeForge\Durin\Core\Mutation\ScaffoldWriter;
use PHPUnit\Framework\TestCase;

final class MakeUseCaseCommandTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'durin_make_usecase_' . uniqid('', true);
        mkdir($this->tempRoot, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->tempRoot);
        parent::tearDown();
    }

    public function test_make_usecase_default_path(): void
    {
        $cwd = getcwd();
        chdir($this->tempRoot);

        try {
            $command = new MakeUseCaseCommand(
                new GeneratorRunner(new ScaffoldWriter()),
                new UseCaseGenerator(),
                new NameInflector(),
            );
            $command->setArgs(['Catalog/ListItems']);

            ob_start();
            $code = $command->execute();
            $output = (string) ob_get_clean();

            $this->assertSame(0, $code);
            $this->assertStringContainsString('Created use case ListItems', $output);
            $this->assertFileExists($this->tempRoot . '/src/Application/UseCases/Catalog/ListItemsUseCase.php');
        } finally {
            if (is_string($cwd)) {
                chdir($cwd);
            }
        }
    }

    public function test_make_usecase_with_module_option(): void
    {
        $cwd = getcwd();
        chdir($this->tempRoot);

        try {
            $command = new MakeUseCaseCommand(
                new GeneratorRunner(new ScaffoldWriter()),
                new UseCaseGenerator(),
                new NameInflector(),
            );
            $command->setArgs(['CreateInvoice', '--module=Billing']);

            ob_start();
            $code = $command->execute();
            $output = (string) ob_get_clean();

            $this->assertSame(0, $code);
            $this->assertStringContainsString('module Billing', $output);
            $this->assertFileExists(
                $this->tempRoot . '/src/Modules/Billing/Application/CreateInvoice/CreateInvoice.php'
            );
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
