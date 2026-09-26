<?php

declare(strict_types=1);

namespace App\Tooling\Scaffold;

use App\Tooling\Output\ConsoleOutput;
use App\Tooling\Output\NullConsoleOutput;

/**
 * Applies a ScaffoldPlan. Refuses overwrites by default (§43).
 * Force overwrite is intentionally unimplemented until Phase 7.
 */
final class ScaffoldWriter
{
    public function __construct(
        private readonly ConsoleOutput $output = new NullConsoleOutput(),
    ) {}

    public function write(string $targetRoot, ScaffoldPlan $plan): ScaffoldWriteResult
    {
        $root = rtrim($targetRoot, "/\\");
        $conflicts = $this->detectConflicts($root, $plan);
        if ($conflicts !== []) {
            foreach ($conflicts as $conflict) {
                $this->output->error("Conflict: {$conflict->relativePath} ({$conflict->reason})");
            }

            return ScaffoldWriteResult::conflicted($conflicts);
        }

        $created = [];
        foreach ($plan->actions() as $action) {
            $absolute = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $action->relativePath);

            if ($action->type === ScaffoldActionType::CreateDirectory) {
                if (!is_dir($absolute) && !mkdir($absolute, 0777, true) && !is_dir($absolute)) {
                    throw new \RuntimeException("Unable to create directory: {$absolute}");
                }
                $created[] = $action->relativePath;
                $this->output->info("dir  {$action->relativePath}");
                continue;
            }

            $parent = dirname($absolute);
            if (!is_dir($parent) && !mkdir($parent, 0777, true) && !is_dir($parent)) {
                throw new \RuntimeException("Unable to create parent directory: {$parent}");
            }

            if (file_put_contents($absolute, $action->contents ?? '') === false) {
                throw new \RuntimeException("Unable to write file: {$absolute}");
            }

            $created[] = $action->relativePath;
            $this->output->info("file {$action->relativePath}");
        }

        return ScaffoldWriteResult::success($created);
    }

    /**
     * @return list<ScaffoldConflict>
     */
    private function detectConflicts(string $root, ScaffoldPlan $plan): array
    {
        $conflicts = [];

        foreach ($plan->actions() as $action) {
            $absolute = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $action->relativePath);

            if ($action->type === ScaffoldActionType::CreateDirectory) {
                if (is_file($absolute)) {
                    $conflicts[] = new ScaffoldConflict(
                        $action->relativePath,
                        'path exists as a file'
                    );
                }
                continue;
            }

            if (!is_file($absolute)) {
                continue;
            }

            $existing = file_get_contents($absolute);
            if ($existing === false) {
                $conflicts[] = new ScaffoldConflict($action->relativePath, 'unable to read existing file');
                continue;
            }

            // Idempotent when contents are identical
            if ($existing === ($action->contents ?? '')) {
                continue;
            }

            $conflicts[] = new ScaffoldConflict(
                $action->relativePath,
                'file exists with different contents (overwrite refused)'
            );
        }

        return $conflicts;
    }
}
