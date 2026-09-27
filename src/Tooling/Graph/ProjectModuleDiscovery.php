<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Graph;

use EreborCodeForge\Durin\Core\Project\Project;

/**
 * Discovers modules from src/Modules/{Name}/module.php (explicit markers only).
 */
final class ProjectModuleDiscovery
{
    /**
     * @return list<array{name: string, path: string}>
     */
    public function discover(Project $project): array
    {
        $modulesDir = $project->paths->join('src', 'Modules');
        if (!is_dir($modulesDir)) {
            return [];
        }

        $found = [];
        $entries = scandir($modulesDir) ?: [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $dir = $modulesDir . DIRECTORY_SEPARATOR . $entry;
            $marker = $dir . DIRECTORY_SEPARATOR . 'module.php';
            if (!is_dir($dir) || !is_file($marker)) {
                continue;
            }

            $meta = require $marker;
            $name = is_array($meta) && isset($meta['name']) && is_string($meta['name']) && $meta['name'] !== ''
                ? $meta['name']
                : $entry;

            $found[] = [
                'name' => $name,
                'path' => 'src/Modules/' . $entry,
            ];
        }

        usort($found, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $found;
    }
}
