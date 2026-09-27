<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tooling\Graph;

use App\Tooling\Graph\ContainerDependencyCollector;
use App\Tooling\Graph\DependencyGraph;
use App\Tooling\Graph\DependencyGraphAssembler;
use App\Tooling\Graph\GraphEdge;
use App\Tooling\Graph\GraphNode;
use App\Tooling\Graph\GraphNodeKind;
use App\Tooling\Graph\ProjectModuleDiscovery;
use App\Tooling\Graph\RouteDependencyCollector;
use EreborCodeForge\Durin\Core\Project\Project;
use EreborCodeForge\Durin\Core\Project\ProjectPaths;
use PHPUnit\Framework\TestCase;

final class DependencyGraphCoreTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'durin_graph_' . uniqid('', true);
        mkdir($this->tempRoot, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->tempRoot);
        parent::tearDown();
    }

    public function test_graph_merge_and_module_filter(): void
    {
        $graph = new DependencyGraph();
        $graph->addNode(new GraphNode('module:Billing', 'Billing', GraphNodeKind::Module, 'Billing'));
        $graph->addNode(new GraphNode(
            'App\\Modules\\Billing\\Application\\CreateInvoice\\CreateInvoice',
            'CreateInvoice',
            GraphNodeKind::Service,
            'Billing',
        ));
        $graph->addNode(new GraphNode(
            'App\\Modules\\Orders\\Application\\PlaceOrder\\PlaceOrder',
            'PlaceOrder',
            GraphNodeKind::Service,
            'Orders',
        ));
        $graph->addEdge(new GraphEdge(
            'App\\Modules\\Billing\\Application\\CreateInvoice\\CreateInvoice',
            'App\\Modules\\Billing\\Domain\\InvoiceRepository',
            'depends_on',
        ));
        $graph->addNode(new GraphNode(
            'App\\Modules\\Billing\\Domain\\InvoiceRepository',
            'InvoiceRepository',
            GraphNodeKind::Service,
            'Billing',
        ));

        $filtered = $graph->filterByModule('Billing');
        $ids = array_map(static fn (GraphNode $n): string => $n->id, $filtered->nodes());

        $this->assertContains('module:Billing', $ids);
        $this->assertContains('App\\Modules\\Billing\\Application\\CreateInvoice\\CreateInvoice', $ids);
        $this->assertNotContains('App\\Modules\\Orders\\Application\\PlaceOrder\\PlaceOrder', $ids);
        $this->assertCount(1, $filtered->edges());
    }

    public function test_module_discovery_reads_markers(): void
    {
        $this->writeModule('Billing');
        $this->writeModule('Orders', 'Orders');

        $project = new Project(new ProjectPaths($this->tempRoot));
        $modules = (new ProjectModuleDiscovery())->discover($project);

        $this->assertSame(
            [
                ['name' => 'Billing', 'path' => 'src/Modules/Billing'],
                ['name' => 'Orders', 'path' => 'src/Modules/Orders'],
            ],
            $modules,
        );
    }

    public function test_container_collector_uses_explicit_deps(): void
    {
        $graph = (new ContainerDependencyCollector())->collect([
            'bind' => [
                'App\\Modules\\Billing\\Application\\CreateInvoice\\CreateInvoice' => [
                    'new' => 'App\\Modules\\Billing\\Application\\CreateInvoice\\CreateInvoice',
                    'deps' => [
                        'App\\Modules\\Billing\\Domain\\InvoiceRepository',
                        'not-a-class',
                    ],
                ],
            ],
        ]);

        $this->assertTrue($graph->hasNode('App\\Modules\\Billing\\Application\\CreateInvoice\\CreateInvoice'));
        $this->assertTrue($graph->hasNode('App\\Modules\\Billing\\Domain\\InvoiceRepository'));
        $this->assertSame('depends_on', $graph->edges()[0]->type);
    }

    public function test_route_collector_links_handlers(): void
    {
        $graph = (new RouteDependencyCollector())->collect([
            'static' => [
                'GET' => [
                    '/api/invoices' => [
                        'handler' => ['App\\Modules\\Billing\\Http\\CreateInvoiceController', 'index'],
                        'middlewares' => ['App\\Core\\Http\\Middleware\\CorsMiddleware'],
                        'path' => '/api/invoices',
                    ],
                ],
            ],
            'dynamic' => [],
        ]);

        $this->assertTrue($graph->hasNode('route:GET /api/invoices'));
        $this->assertTrue($graph->hasNode('App\\Modules\\Billing\\Http\\CreateInvoiceController'));
        $types = array_map(static fn (GraphEdge $e): string => $e->type, $graph->edges());
        $this->assertContains('handles', $types);
        $this->assertContains('middleware', $types);
    }

    public function test_assembler_builds_from_project_fixtures(): void
    {
        $this->writeModule('Billing');
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
        'App\\Modules\\Orders\\Application\\PlaceOrder\\PlaceOrder' => [
            'new' => 'App\\Modules\\Orders\\Application\\PlaceOrder\\PlaceOrder',
            'deps' => [],
        ],
    ],
    'preloaded' => [],
];
PHP);

        file_put_contents($this->tempRoot . '/var/cache/routes.php', <<<'PHP'
<?php
return [
    'static' => [
        'POST' => [
            '/api/billing/invoices' => [
                'handler' => ['App\\Modules\\Billing\\Http\\CreateInvoiceController', '__invoke'],
                'middlewares' => [],
                'path' => '/api/billing/invoices',
            ],
        ],
    ],
    'dynamic' => [],
];
PHP);

        $project = new Project(new ProjectPaths($this->tempRoot));
        $graph = (new DependencyGraphAssembler())->assemble($project, 'Billing');

        $ids = array_map(static fn (GraphNode $n): string => $n->id, $graph->nodes());
        $this->assertContains('module:Billing', $ids);
        $this->assertContains('App\\Modules\\Billing\\Application\\CreateInvoice\\CreateInvoice', $ids);
        $this->assertContains('route:POST /api/billing/invoices', $ids);
        $this->assertNotContains('App\\Modules\\Orders\\Application\\PlaceOrder\\PlaceOrder', $ids);
    }

    private function writeModule(string $dirName, ?string $name = null): void
    {
        $base = $this->tempRoot . '/src/Modules/' . $dirName;
        mkdir($base, 0777, true);
        $label = $name ?? $dirName;
        file_put_contents($base . '/module.php', "<?php\nreturn ['name' => '{$label}'];\n");
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
