<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Doctor\Checks;

use EreborCodeForge\Durin\Forge\Tooling\Doctor\Check;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\CheckResult;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\DoctorContext;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\DoctorExitCode;

final class CompiledArtifactsCheck implements Check
{
    public function id(): string
    {
        return 'optimization.artifacts';
    }

    public function run(DoctorContext $context): array
    {
        $cacheDir = $context->root() . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'cache';
        $results = [];

        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }

        if (!is_dir($cacheDir) || !is_writable($cacheDir)) {
            $results[] = CheckResult::fail(
                'optimization.cache_dir',
                'var/cache',
                'missing or not writable',
                DoctorExitCode::InvalidConfiguration,
            );
        } else {
            $results[] = CheckResult::ok('optimization.cache_dir', 'var/cache', 'writable');
        }

        $container = $cacheDir . DIRECTORY_SEPARATOR . 'container.php';
        $routes = $cacheDir . DIRECTORY_SEPARATOR . 'routes.php';

        $results[] = is_file($container)
            ? CheckResult::ok('optimization.container', 'Container', 'compiled')
            : CheckResult::warning('optimization.container', 'Container', 'not compiled (run durin optimize)');

        $results[] = is_file($routes)
            ? CheckResult::ok('optimization.routes', 'Routes', 'compiled')
            : CheckResult::warning('optimization.routes', 'Routes', 'not compiled (run durin optimize)');

        return $results;
    }
}
