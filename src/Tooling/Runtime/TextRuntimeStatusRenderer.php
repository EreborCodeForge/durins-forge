<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Runtime;

final class TextRuntimeStatusRenderer
{
    public function render(RuntimeStatus $status): string
    {
        $lines = [
            'Durin Runtime Status',
            '',
            'State ............... ' . ($status->available ? 'tooling available' : 'unavailable'),
            'Summary ............. ' . $status->summary,
        ];

        if ($status->binaryPath !== null) {
            $lines[] = 'Binary .............. ' . $status->binaryPath;
        }
        if ($status->configPath !== null) {
            $lines[] = 'Config .............. ' . $status->configPath;
        }
        if ($status->manifestPath !== null) {
            $lines[] = 'Manifest ............ ' . $status->manifestPath;
        }

        $details = $status->details;
        $lines[] = 'Protocol ............ ' . (string) ($details['protocol'] ?? 'eregion/1');

        if (isset($details['http_host'], $details['http_port'])) {
            $lines[] = sprintf(
                'HTTP (config) ....... %s:%s',
                (string) $details['http_host'],
                (string) $details['http_port'],
            );
        }

        if (isset($details['workers_configured'])) {
            $lines[] = 'Workers (config) .... ' . (string) $details['workers_configured'];
        }

        if (isset($details['manifest_application'])) {
            $lines[] = 'Kernel .............. ' . (string) $details['manifest_application'];
        }

        if (isset($details['manifest_environment'])) {
            $lines[] = 'Manifest env ........ ' . (string) $details['manifest_environment'];
        }

        $lines[] = '';
        $lines[] = 'Note: live idle/busy worker metrics are not queried in V1 (no --watch).';

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }
}
