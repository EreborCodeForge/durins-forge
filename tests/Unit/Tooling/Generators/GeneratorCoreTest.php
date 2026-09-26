<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tooling\Generators;

use App\Tooling\Generators\GeneratorRequest;
use App\Tooling\Generators\GeneratorRunner;
use App\Tooling\Generators\NameInflector;
use App\Tooling\Generators\PlaceholderClassGenerator;
use App\Tooling\Generators\StubTemplate;
use App\Tooling\Scaffold\ScaffoldWriter;
use PHPUnit\Framework\TestCase;

final class GeneratorCoreTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'durin_gen_' . uniqid('', true);
        mkdir($this->tempRoot, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->tempRoot);
        parent::tearDown();
    }

    public function test_name_inflector_parses_paths(): void
    {
        $names = new NameInflector();

        $this->assertSame('CreateUser', $names->className('User/CreateUser'));
        $this->assertSame('User', $names->namespaceSuffix('User/CreateUser'));
        $this->assertSame('User', $names->relativePath('User/CreateUser'));
        $this->assertSame('CreateInvoice', $names->studly('create-invoice'));
        $this->assertSame('App\\Application\\User\\CreateUser', $names->qualify('App\\Application', 'User/CreateUser'));
        $this->assertSame('', $names->namespaceSuffix('HealthCheck'));
    }

    public function test_stub_template_replaces_placeholders(): void
    {
        $out = (new StubTemplate())->render('Hello {{name}}!', ['name' => 'Durin']);
        $this->assertSame('Hello Durin!', $out);
    }

    public function test_generator_produces_plan_and_writer_is_conflict_safe(): void
    {
        $generator = new PlaceholderClassGenerator();
        $request = new GeneratorRequest('Billing/CreateInvoice', $this->tempRoot);
        $plan = $generator->plan($request);

        $paths = array_map(static fn ($a) => $a->relativePath, $plan->actions());
        $this->assertContains('src/Generated/Billing', $paths);
        $this->assertContains('src/Generated/Billing/CreateInvoice.php', $paths);

        $runner = new GeneratorRunner(new ScaffoldWriter());
        $first = $runner->run($generator, $request);
        $this->assertTrue($first->ok);
        $this->assertFileExists($this->tempRoot . '/src/Generated/Billing/CreateInvoice.php');

        $contents = (string) file_get_contents($this->tempRoot . '/src/Generated/Billing/CreateInvoice.php');
        $this->assertStringContainsString('namespace App\\Generated\\Billing;', $contents);
        $this->assertStringContainsString('final class CreateInvoice', $contents);

        // Identical re-run is idempotent (ScaffoldWriter).
        $second = $runner->run($generator, $request);
        $this->assertTrue($second->ok);

        file_put_contents($this->tempRoot . '/src/Generated/Billing/CreateInvoice.php', "changed\n");
        $third = $runner->run($generator, $request);
        $this->assertFalse($third->ok);
        $this->assertNotEmpty($third->conflicts);
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
