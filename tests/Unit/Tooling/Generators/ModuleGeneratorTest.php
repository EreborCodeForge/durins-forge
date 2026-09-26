<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tooling\Generators;

use App\Tooling\Generators\GeneratorRequest;
use App\Tooling\Generators\GeneratorRunner;
use App\Tooling\Generators\ModuleGenerator;
use App\Tooling\Project\DurinManifestModulesEnabler;
use App\Tooling\Project\DurinManifestParser;
use App\Tooling\Scaffold\ScaffoldWriter;
use PHPUnit\Framework\TestCase;

final class ModuleGeneratorTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'durin_module_' . uniqid('', true);
        mkdir($this->tempRoot, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->tempRoot);
        parent::tearDown();
    }

    public function test_creates_module_marker_without_layer_ceremony(): void
    {
        $runner = new GeneratorRunner(new ScaffoldWriter());
        $result = $runner->run(
            new ModuleGenerator(),
            new GeneratorRequest('Billing', $this->tempRoot),
        );

        $this->assertTrue($result->ok);
        $this->assertFileExists($this->tempRoot . '/src/Modules/Billing/module.php');
        $this->assertDirectoryDoesNotExist($this->tempRoot . '/src/Modules/Billing/Domain');
        $this->assertDirectoryDoesNotExist($this->tempRoot . '/src/Modules/Billing/Application');

        $marker = require $this->tempRoot . '/src/Modules/Billing/module.php';
        $this->assertSame(['name' => 'Billing'], $marker);
    }

    public function test_updates_durin_yaml_modules_flag_when_present(): void
    {
        file_put_contents($this->tempRoot . '/durin.yaml', <<<'YAML'
application:
  name: demo
  preset: service
architecture:
  modules: false
YAML);

        $runner = new GeneratorRunner(new ScaffoldWriter());
        $this->assertTrue($runner->run(
            new ModuleGenerator(),
            new GeneratorRequest('Orders', $this->tempRoot),
        )->ok);

        $enabler = new DurinManifestModulesEnabler();
        $this->assertTrue($enabler->enable($this->tempRoot));

        $manifest = (new DurinManifestParser())->parseFile($this->tempRoot . '/durin.yaml');
        $this->assertTrue($manifest->architecture['modules']);
        $this->assertFileExists($this->tempRoot . '/src/Modules/Orders/module.php');
    }

    public function test_refuses_overwrite_of_existing_module_marker(): void
    {
        $runner = new GeneratorRunner(new ScaffoldWriter());
        $request = new GeneratorRequest('Billing', $this->tempRoot);
        $this->assertTrue($runner->run(new ModuleGenerator(), $request)->ok);

        file_put_contents($this->tempRoot . '/src/Modules/Billing/module.php', "<?php return ['name' => 'Changed'];\n");
        $second = $runner->run(new ModuleGenerator(), $request);

        $this->assertFalse($second->ok);
        $this->assertNotEmpty($second->conflicts);
    }

    public function test_rejects_nested_module_path(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ModuleGenerator())->plan(new GeneratorRequest('Billing/Invoices', $this->tempRoot));
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
