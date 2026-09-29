<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Init;

use EreborCodeForge\Durin\Core\Scaffold\ScaffoldPlan;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimePlan;

/**
 * Removes scaffold leftovers incompatible with the resolved RuntimePlan.
 */
final class ResidualRuntimeCleaner
{
    /** @var list<string> */
    private const array HTTP_PATHS = [
        'public',
        'public/index.php',
        'routes',
        'routes/api.php',
        'routes/web.php',
        'src/Kernel.php',
    ];

    /** @var list<string> */
    private const array JOB_PATHS = [
        'src/JobKernel.php',
        'src/Jobs',
    ];

    public function clean(string $applicationRoot, RuntimePlan $runtime, ScaffoldPlan $appliedPlan): void
    {
        $planned = $this->plannedPaths($appliedPlan);

        if (!$this->isHttp($runtime)) {
            $this->removePaths($applicationRoot, self::HTTP_PATHS, $planned);
        }

        if (!$runtime->isJobExecution()) {
            $this->removePaths($applicationRoot, self::JOB_PATHS, $planned);
        }

        // Standalone runtimes must not keep Eregion residuals from a prior supervised plan.
        if (!$runtime->usesEregion()) {
            $this->removePaths($applicationRoot, [
                'eregion.yaml',
                'var/runtime/eregion.json',
            ], $planned);
        }
    }

    private function isHttp(RuntimePlan $runtime): bool
    {
        return $runtime->executionRuntime === 'mithril-http'
            || in_array('persistent-http', $runtime->capabilities, true)
            || ($runtime->mode === 'http' && !$runtime->isJobExecution());
    }

    /**
     * @return array<string, true>
     */
    private function plannedPaths(ScaffoldPlan $plan): array
    {
        $paths = [];
        foreach ($plan->actions() as $action) {
            $paths[$action->relativePath] = true;
        }

        return $paths;
    }

    /**
     * @param list<string> $candidates
     * @param array<string, true> $planned
     */
    private function removePaths(string $root, array $candidates, array $planned): void
    {
        // Longest paths first so files go before parent directories.
        $sorted = $candidates;
        usort($sorted, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        foreach ($sorted as $relative) {
            if (isset($planned[$relative])) {
                continue;
            }
            $absolute = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            if (is_file($absolute)) {
                @unlink($absolute);
                continue;
            }
            if (is_dir($absolute)) {
                $this->removeTree($absolute);
            }
        }
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $path . DIRECTORY_SEPARATOR . $item;
            is_dir($full) ? $this->removeTree($full) : @unlink($full);
        }
        @rmdir($path);
    }
}
