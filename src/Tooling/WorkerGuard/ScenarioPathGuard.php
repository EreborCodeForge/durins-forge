<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\WorkerGuard;

/**
 * Resolves a scenario path and refuses anything outside the project root.
 * Absolute paths are rejected. The file is executable PHP owned by the project.
 */
final class ScenarioPathGuard
{
    public function resolve(string $projectRoot, string $scenario): string
    {
        $root = realpath($projectRoot);
        if ($root === false) {
            throw new WorkerAuditException('project root unavailable');
        }
        if ($scenario === '' || str_contains($scenario, "\0")) {
            throw new WorkerAuditException('invalid scenario path');
        }
        if ($this->isAbsolute($scenario)) {
            throw new WorkerAuditException('absolute scenario paths are not allowed');
        }

        $relative = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $scenario);
        $real = realpath($root . DIRECTORY_SEPARATOR . $relative);
        if ($real === false || !is_file($real)) {
            throw new WorkerAuditException('scenario not found');
        }

        $prefix = rtrim(str_replace('\\', '/', $root), '/') . '/';
        $normalized = str_replace('\\', '/', $real);
        if (!str_starts_with(strtolower($normalized), strtolower($prefix))) {
            throw new WorkerAuditException('scenario path escapes the project root');
        }

        return $real;
    }

    public function load(string $projectRoot, string $scenario): WorkerAuditScenario
    {
        $path = $this->resolve($projectRoot, $scenario);
        $loaded = require $path;
        if (!$loaded instanceof WorkerAuditScenario) {
            throw new WorkerAuditException('scenario file must return a WorkerAuditScenario');
        }

        return $loaded;
    }

    private function isAbsolute(string $path): bool
    {
        if (str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            return true;
        }

        return preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }
}
