<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tooling\Generators;

use App\Tooling\Generators\FeatureGenerator;
use App\Tooling\Generators\GeneratorRequest;
use App\Tooling\Generators\GeneratorRunner;
use App\Tooling\Scaffold\ScaffoldWriter;
use PHPUnit\Framework\TestCase;

final class FeatureGeneratorTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'durin_feature_' . uniqid('', true);
        mkdir($this->tempRoot, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->tempRoot);
        parent::tearDown();
    }

    public function test_composes_module_and_usecase_in_single_write(): void
    {
        $runner = new GeneratorRunner(new ScaffoldWriter());
        $result = $runner->run(
            new FeatureGenerator(),
            new GeneratorRequest('Billing/CreateInvoice', $this->tempRoot),
        );

        $this->assertTrue($result->ok);
        $this->assertFileExists($this->tempRoot . '/src/Modules/Billing/module.php');
        $this->assertFileExists(
            $this->tempRoot . '/src/Modules/Billing/Application/CreateInvoice/CreateInvoice.php'
        );
        $this->assertFileExists(
            $this->tempRoot . '/src/Modules/Billing/Application/CreateInvoice/CreateInvoiceInput.php'
        );
        $this->assertFileDoesNotExist(
            $this->tempRoot . '/src/Modules/Billing/Http/CreateInvoiceController.php'
        );
        $this->assertFileDoesNotExist(
            $this->tempRoot . '/tests/Feature/Modules/Billing/CreateInvoiceTest.php'
        );
    }

    public function test_http_and_tests_flags_add_artifacts(): void
    {
        $runner = new GeneratorRunner(new ScaffoldWriter());
        $result = $runner->run(
            new FeatureGenerator(),
            new GeneratorRequest('Billing/CreateInvoice', $this->tempRoot, [
                'http' => true,
                'tests' => true,
            ]),
        );

        $this->assertTrue($result->ok);

        $controller = $this->tempRoot . '/src/Modules/Billing/Http/CreateInvoiceController.php';
        $this->assertFileExists($controller);
        $body = (string) file_get_contents($controller);
        $this->assertStringContainsString('final class CreateInvoiceController', $body);
        $this->assertStringContainsString('use App\\Modules\\Billing\\Application\\CreateInvoice\\CreateInvoice;', $body);

        $test = $this->tempRoot . '/tests/Feature/Modules/Billing/CreateInvoiceTest.php';
        $this->assertFileExists($test);
        $testBody = (string) file_get_contents($test);
        $this->assertStringContainsString('final class CreateInvoiceTest', $testBody);
    }

    public function test_rejects_name_without_module(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new FeatureGenerator())->plan(new GeneratorRequest('CreateInvoice', $this->tempRoot));
    }

    public function test_refuses_overwrite_when_contents_differ(): void
    {
        $runner = new GeneratorRunner(new ScaffoldWriter());
        $request = new GeneratorRequest('Orders/PlaceOrder', $this->tempRoot);
        $this->assertTrue($runner->run(new FeatureGenerator(), $request)->ok);

        file_put_contents(
            $this->tempRoot . '/src/Modules/Orders/Application/PlaceOrder/PlaceOrder.php',
            "<?php\n// changed\n",
        );
        $second = $runner->run(new FeatureGenerator(), $request);
        $this->assertFalse($second->ok);
        $this->assertNotEmpty($second->conflicts);
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
