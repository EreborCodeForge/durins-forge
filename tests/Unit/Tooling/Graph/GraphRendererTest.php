<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tests\Unit\Tooling\Graph;

use EreborCodeForge\Durin\Forge\Tooling\Graph\DependencyGraph;
use EreborCodeForge\Durin\Forge\Tooling\Graph\GraphEdge;
use EreborCodeForge\Durin\Forge\Tooling\Graph\GraphNode;
use EreborCodeForge\Durin\Forge\Tooling\Graph\GraphNodeKind;
use EreborCodeForge\Durin\Forge\Tooling\Graph\GraphRendererRegistry;
use EreborCodeForge\Durin\Forge\Tooling\Graph\JsonGraphRenderer;
use EreborCodeForge\Durin\Forge\Tooling\Graph\MermaidGraphRenderer;
use EreborCodeForge\Durin\Forge\Tooling\Graph\TextGraphRenderer;
use PHPUnit\Framework\TestCase;

final class GraphRendererTest extends TestCase
{
    private function sampleGraph(): DependencyGraph
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
            'App\\Modules\\Billing\\Domain\\InvoiceRepository',
            'InvoiceRepository',
            GraphNodeKind::Service,
            'Billing',
        ));
        $graph->addEdge(new GraphEdge(
            'App\\Modules\\Billing\\Application\\CreateInvoice\\CreateInvoice',
            'App\\Modules\\Billing\\Domain\\InvoiceRepository',
            'depends_on',
        ));

        return $graph;
    }

    public function test_text_renderer_lists_module_tree(): void
    {
        $out = (new TextGraphRenderer())->render($this->sampleGraph());

        $this->assertStringContainsString('Dependency graph', $out);
        $this->assertStringContainsString('Billing', $out);
        $this->assertStringContainsString('CreateInvoice', $out);
        $this->assertStringContainsString('depends_on → InvoiceRepository', $out);
    }

    public function test_mermaid_renderer_emits_flowchart(): void
    {
        $out = (new MermaidGraphRenderer())->render($this->sampleGraph());

        $this->assertStringContainsString('graph LR', $out);
        $this->assertStringContainsString('CreateInvoice', $out);
        $this->assertStringContainsString('depends_on', $out);
        $this->assertStringContainsString('-->', $out);
    }

    public function test_json_renderer_encodes_nodes_and_edges(): void
    {
        $out = (new JsonGraphRenderer())->render($this->sampleGraph());
        $data = json_decode($out, true, 512, JSON_THROW_ON_ERROR);

        $this->assertCount(3, $data['nodes']);
        $this->assertCount(1, $data['edges']);
        $this->assertSame('depends_on', $data['edges'][0]['type']);
    }

    public function test_registry_rejects_unknown_format(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new GraphRendererRegistry())->get('dot');
    }
}
