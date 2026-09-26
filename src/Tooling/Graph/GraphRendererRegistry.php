<?php

declare(strict_types=1);

namespace App\Tooling\Graph;

final class GraphRendererRegistry
{
    /** @var array<string, GraphRenderer> */
    private array $renderers = [];

    public function __construct(GraphRenderer ...$renderers)
    {
        if ($renderers === []) {
            $renderers = [
                new TextGraphRenderer(),
                new MermaidGraphRenderer(),
                new JsonGraphRenderer(),
            ];
        }

        foreach ($renderers as $renderer) {
            $this->renderers[$renderer->format()] = $renderer;
        }
    }

    public function get(string $format): GraphRenderer
    {
        $key = strtolower(trim($format));
        if (!isset($this->renderers[$key])) {
            $supported = implode(', ', array_keys($this->renderers));
            throw new \InvalidArgumentException(
                "Unknown graph format '{$format}'. Supported: {$supported}."
            );
        }

        return $this->renderers[$key];
    }
}
