<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Runtime;

use EreborCodeForge\Durin\Core\Project\Project;
use EreborCodeForge\Durin\Core\Project\ProjectDiscovery;

/**
 * Human-readable summary printed before `durin dev` starts the runtime.
 */
final class DevRuntimeSummary
{
    public function __construct(
        private readonly ProjectDiscovery $discovery = new ProjectDiscovery(),
    ) {}

    public function render(RuntimeOptions $options): string
    {
        $project = $this->safeDiscover($options->workingDirectory);
        $appName = $project?->manifest?->applicationName
            ?? $this->composerName($options->workingDirectory)
            ?? basename($options->workingDirectory);

        $preset = $project?->manifest?->preset ?? 'n/a';
        $env = $options->environment ?? 'development';
        $runtime = $options->preferPhpServer ? 'PHP built-in (forge serve:php)' : 'Eregion (forge serve)';
        $container = is_file($options->workingDirectory . '/var/cache/container.php') ? 'compiled' : 'live';
        $routes = is_file($options->workingDirectory . '/var/cache/routes.php') ? 'compiled' : 'live';

        $lines = [
            "Durin's Forge — development",
            '',
            'Application ........ ' . $appName,
            'Profile ............ ' . $preset,
            'Environment ........ ' . $env,
            'Runtime ............ ' . $runtime,
            'PHP ................ ' . PHP_VERSION,
            'Container .......... ' . $container,
            'Routes ............. ' . $routes,
            '',
            sprintf('http://%s:%d', $options->host, $options->port),
        ];

        if ($options->preferPhpServer) {
            $lines[] = '';
            $lines[] = 'Note: --php uses forge serve:php; UDS worker apps typically return 503 on public/.';
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    private function safeDiscover(string $cwd): ?Project
    {
        try {
            return $this->discovery->discover($cwd);
        } catch (\Throwable) {
            return null;
        }
    }

    private function composerName(string $cwd): ?string
    {
        $path = $cwd . DIRECTORY_SEPARATOR . 'composer.json';
        if (!is_file($path)) {
            return null;
        }
        $json = json_decode((string) file_get_contents($path), true);
        if (!is_array($json) || !isset($json['name']) || !is_string($json['name'])) {
            return null;
        }

        return $json['name'];
    }
}
