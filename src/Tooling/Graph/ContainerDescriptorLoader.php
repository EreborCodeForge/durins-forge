<?php

declare(strict_types=1);

namespace App\Tooling\Graph;

use App\Tooling\Project\Project;

/**
 * Loads DescriptorProvider-shaped metadata from an explicit file when present.
 * Does not invent bindings from AST or compiled closures.
 */
final class ContainerDescriptorLoader
{
    public function load(Project $project): array
    {
        $path = $project->paths->join('var', 'cache', 'container.descriptor.php');
        if (!is_file($path)) {
            return [
                'singletons' => [],
                'factories' => [],
                'bind' => [],
                'preloaded' => [],
            ];
        }

        $data = require $path;

        return is_array($data) ? $data : [
            'singletons' => [],
            'factories' => [],
            'bind' => [],
            'preloaded' => [],
        ];
    }
}
