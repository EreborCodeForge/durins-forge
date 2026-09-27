<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tests\Unit\Console;

use EreborCodeForge\Durin\Forge\Console\Commands\MakeFeatureCommand;
use EreborCodeForge\Durin\Forge\Tooling\Generators\FeatureGenerator;
use EreborCodeForge\Durin\Forge\Tooling\Generators\GeneratorRunner;
use EreborCodeForge\Durin\Forge\Tooling\Generators\NameInflector;
use EreborCodeForge\Durin\Core\Mutation\ScaffoldWriter;
use PHPUnit\Framework\TestCase;

final class MakeFeatureCommandTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'durin_make_feature_' . uniqid('', true);
        mkdir($this->tempRoot, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->tempRoot);
        parent::tearDown();
    }

    public function test_make_feature_with_http_and_tests(): void
    {
        $cwd = getcwd();
        chdir($this->tempRoot);

        try {
            $command = new MakeFeatureCommand(
                new GeneratorRunner(new ScaffoldWriter()),
                new FeatureGenerator(),
                new NameInflector(),
            );
            $command->setArgs(['Catalog/ListItems', '--http', '--tests']);

            ob_start();
            $code = $command->execute();
            $output = (string) ob_get_clean();

            $this->assertSame(0, $code);
            $this->assertStringContainsString('Created feature Catalog/ListItems', $output);
            $this->assertFileExists($this->tempRoot . '/src/Modules/Catalog/module.php');
            $this->assertFileExists(
                $this->tempRoot . '/src/Modules/Catalog/Http/ListItemsController.php'
            );
            $this->assertFileExists(
                $this->tempRoot . '/tests/Feature/Modules/Catalog/ListItemsTest.php'
            );
        } finally {
            if (is_string($cwd)) {
                chdir($cwd);
            }
        }
    }

    public function test_rejects_deferred_repository_flag(): void
    {
        $cwd = getcwd();
        chdir($this->tempRoot);

        try {
            $command = new MakeFeatureCommand(
                new GeneratorRunner(new ScaffoldWriter()),
                new FeatureGenerator(),
                new NameInflector(),
            );
            $command->setArgs(['Billing/CreateInvoice', '--repository']);

            ob_start();
            $code = $command->execute();
            $output = (string) ob_get_clean();

            $this->assertSame(2, $code);
            $this->assertStringContainsString('--repository is not supported yet', $output);
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
