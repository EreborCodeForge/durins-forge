<?php

declare(strict_types=1);

namespace App\Tooling\Graph;

use App\Tooling\Project\Project;

/**
 * Loads compiled route descriptors from var/cache/routes.php when present.
 */
final class CompiledRoutesLoader
{
    public function load(Project $project): array
    {
        $path = $project->paths->join('var', 'cache', 'routes.php');
        if (!is_file($path)) {
            return ['static' => [], 'dynamic' => []];
        }

        $data = require $path;

        return is_array($data) ? $data : ['static' => [], 'dynamic' => []];
    }
}
