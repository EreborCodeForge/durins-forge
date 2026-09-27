<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tests\Unit\Tooling\Scaffold;

use EreborCodeForge\Durin\Core\Output\BufferedConsoleOutput;
use EreborCodeForge\Durin\Core\Scaffold\ScaffoldPlan;
use EreborCodeForge\Durin\Core\Mutation\ScaffoldWriter;
use PHPUnit\Framework\TestCase;

final class ScaffoldWriterTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'durin_scaffold_' . uniqid('', true);
        mkdir($this->tempRoot, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->tempRoot);
        parent::tearDown();
    }

    public function test_plan_composition(): void
    {
        $plan = (new ScaffoldPlan())
            ->directory('src')
            ->file('src/hello.txt', "hi\n")
            ->merge((new ScaffoldPlan())->file('durin.yaml', "application:\n  name: x\n  preset: minimal\n"));

        $this->assertCount(3, $plan->actions());
        $this->assertFalse($plan->isEmpty());
    }

    public function test_writer_creates_files_and_directories(): void
    {
        $plan = (new ScaffoldPlan())
            ->directory('src/Http')
            ->file('src/Http/routes.php', "<?php\n")
            ->file('durin.yaml', "application:\n  name: demo\n  preset: minimal\n");

        $output = new BufferedConsoleOutput();
        $result = (new ScaffoldWriter($output))->write($this->tempRoot, $plan);

        $this->assertTrue($result->ok);
        $this->assertFileExists($this->tempRoot . '/src/Http/routes.php');
        $this->assertFileExists($this->tempRoot . '/durin.yaml');
        $this->assertNotEmpty($output->messages());
    }

    public function test_writer_refuses_overwrite_with_different_contents(): void
    {
        file_put_contents($this->tempRoot . '/readme.txt', "old\n");

        $plan = (new ScaffoldPlan())->file('readme.txt', "new\n");
        $result = (new ScaffoldWriter())->write($this->tempRoot, $plan);

        $this->assertFalse($result->ok);
        $this->assertCount(1, $result->conflicts);
        $this->assertSame('readme.txt', $result->conflicts[0]->relativePath);
        $this->assertSame("old\n", file_get_contents($this->tempRoot . '/readme.txt'));
    }

    public function test_writer_is_idempotent_for_identical_contents(): void
    {
        file_put_contents($this->tempRoot . '/readme.txt', "same\n");

        $plan = (new ScaffoldPlan())->file('readme.txt', "same\n");
        $result = (new ScaffoldWriter())->write($this->tempRoot, $plan);

        $this->assertTrue($result->ok);
        $this->assertSame("same\n", file_get_contents($this->tempRoot . '/readme.txt'));
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
            if (is_dir($full)) {
                $this->removeTree($full);
            } else {
                unlink($full);
            }
        }
        rmdir($path);
    }
}
