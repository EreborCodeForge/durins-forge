<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tests\Unit\Console;

use EreborCodeForge\Durin\Forge\Console\Commands\GraphDependenciesCommand;
use EreborCodeForge\Durin\Forge\Tooling\Graph\DependencyGraphAssembler;
use EreborCodeForge\Durin\Forge\Tooling\Graph\GraphRendererRegistry;
use EreborCodeForge\Durin\Core\Project\ProjectDiscovery;
use PHPUnit\Framework\TestCase;

final class GraphDependenciesCommandTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'durin_graph_cmd_' . uniqid('', true);
        mkdir($this->tempRoot, 0777, true);
        file_put_contents($this->tempRoot . '/composer.json', "{\"name\":\"demo/app\"}\n");

        $moduleDir = $this->tempRoot . '/src/Modules/Billing';
        mkdir($moduleDir, 0777, true);
        file_put_contents($moduleDir . '/module.php', "<?php\nreturn ['name' => 'Billing'];\n");

        mkdir($this->tempRoot . '/var/cache', 0777, true);
        file_put_contents($this->tempRoot . '/var/cache/container.descriptor.php', <<<'PHP'
<?php
return [
    'singletons' => [],
    'factories' => [],
    'bind' => [
        'App\\Modules\\Billing\\Application\\CreateInvoice\\CreateInvoice' => [
            'new' => 'App\\Modules\\Billing\\Application\\CreateInvoice\\CreateInvoice',
            'deps' => ['App\\Modules\\Billing\\Domain\\InvoiceRepository'],
        ],
    ],
    'preloaded' => [],
];
PHP);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->tempRoot);
        parent::tearDown();
    }

    public function test_command_renders_json_with_module_filter(): void
    {
        $cwd = getcwd();
        chdir($this->tempRoot);

        try {
            $command = new GraphDependenciesCommand(
                new ProjectDiscovery(),
                new DependencyGraphAssembler(),
                new GraphRendererRegistry(),
            );
            $command->setArgs(['--format=json', '--module=Billing']);

            ob_start();
            $code = $command->execute();
            $output = (string) ob_get_clean();

            $this->assertSame(0, $code);
            $data = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
            $ids = array_column($data['nodes'], 'id');
            $this->assertContains('module:Billing', $ids);
            $this->assertContains('App\\Modules\\Billing\\Application\\CreateInvoice\\CreateInvoice', $ids);
        } finally {
            if (is_string($cwd)) {
                chdir($cwd);
            }
        }
    }

    public function test_command_rejects_unknown_format(): void
    {
        $cwd = getcwd();
        chdir($this->tempRoot);

        try {
            $command = new GraphDependenciesCommand(
                new ProjectDiscovery(),
                new DependencyGraphAssembler(),
                new GraphRendererRegistry(),
            );
            $command->setArgs(['--format=dot']);

            ob_start();
            $code = $command->execute();
            $output = (string) ob_get_clean();

            $this->assertSame(2, $code);
            $this->assertStringContainsString("Unknown graph format 'dot'", $output);
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
