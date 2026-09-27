<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tooling\Generators;

use App\Tooling\Generators\GeneratorRequest;
use App\Tooling\Generators\GeneratorRunner;
use App\Tooling\Generators\UseCaseGenerator;
use EreborCodeForge\Durin\Core\Mutation\ScaffoldWriter;
use PHPUnit\Framework\TestCase;

final class UseCaseGeneratorTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'durin_usecase_' . uniqid('', true);
        mkdir($this->tempRoot, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->tempRoot);
        parent::tearDown();
    }

    public function test_default_layout_preserves_application_paths(): void
    {
        $runner = new GeneratorRunner(new ScaffoldWriter());
        $result = $runner->run(
            new UseCaseGenerator(),
            new GeneratorRequest('User/CreateUser', $this->tempRoot),
        );

        $this->assertTrue($result->ok);
        $this->assertFileExists($this->tempRoot . '/src/Application/DTOs/User/CreateUserDTO.php');
        $this->assertFileExists($this->tempRoot . '/src/Application/UseCases/User/CreateUserUseCase.php');
        $this->assertFileExists($this->tempRoot . '/src/Application/UseCases/User/CreateUserUseCaseInterface.php');

        $dto = (string) file_get_contents($this->tempRoot . '/src/Application/DTOs/User/CreateUserDTO.php');
        $this->assertStringContainsString('namespace App\\Application\\DTOs\\User;', $dto);
        $this->assertStringContainsString('readonly class CreateUserDTO', $dto);

        $uc = (string) file_get_contents($this->tempRoot . '/src/Application/UseCases/User/CreateUserUseCase.php');
        $this->assertStringContainsString('final class CreateUserUseCase implements CreateUserUseCaseInterface', $uc);
    }

    public function test_module_layout_follows_master_shape(): void
    {
        $runner = new GeneratorRunner(new ScaffoldWriter());
        $result = $runner->run(
            new UseCaseGenerator(),
            new GeneratorRequest('CreateInvoice', $this->tempRoot, ['module' => 'Billing']),
        );

        $this->assertTrue($result->ok);
        $base = $this->tempRoot . '/src/Modules/Billing/Application/CreateInvoice';
        $this->assertFileExists($base . '/CreateInvoice.php');
        $this->assertFileExists($base . '/CreateInvoiceInput.php');
        $this->assertFileDoesNotExist($base . '/CreateInvoiceUseCaseInterface.php');

        $uc = (string) file_get_contents($base . '/CreateInvoice.php');
        $this->assertStringContainsString('namespace App\\Modules\\Billing\\Application\\CreateInvoice;', $uc);
        $this->assertStringContainsString('final class CreateInvoice', $uc);
        $this->assertStringContainsString('CreateInvoiceInput $input', $uc);
    }

    public function test_refuses_overwrite_by_default(): void
    {
        $runner = new GeneratorRunner(new ScaffoldWriter());
        $request = new GeneratorRequest('Order/PlaceOrder', $this->tempRoot);
        $this->assertTrue($runner->run(new UseCaseGenerator(), $request)->ok);

        file_put_contents(
            $this->tempRoot . '/src/Application/UseCases/Order/PlaceOrderUseCase.php',
            "<?php\n// changed\n",
        );
        $second = $runner->run(new UseCaseGenerator(), $request);
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
