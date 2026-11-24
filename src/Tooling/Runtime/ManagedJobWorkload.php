<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Runtime;

use EreborCodeForge\Durin\Core\Contract\ProjectSelection;

/**
 * Renders and compares only the Forge-managed application-worker workload.
 */
final class ManagedJobWorkload
{
    /**
     * @return array<string, mixed>
     */
    public static function desired(?ProjectSelection $selection, string $kind = 'consumer'): array
    {
        $http = $kind === 'http';
        $spec = [
            'mode' => $http ? 'http' : 'consumer',
            'command' => $http ? ['php', 'vendor/bin/eregion-worker'] : ['php', 'vendor/bin/job-worker'],
            'workers' => [
                'min' => $selection?->workersMin ?? 1,
                'max' => $selection?->workersMax ?? 1,
            ],
        ];

        $resources = [];
        if ($selection?->resourceClass !== null) {
            $resources['class'] = $selection->resourceClass;
        }
        if ($selection?->memoryMb !== null) {
            $resources['memory_mb'] = $selection->memoryMb;
        }
        if ($resources !== []) {
            $spec['resources'] = $resources;
        }

        if ($selection?->scalingStrategy !== null) {
            $advanced = in_array($selection->scalingStrategy, ['cpu_utilization', 'latency', 'throughput', 'hybrid', 'scheduled', 'adaptive'], true);
            $scaling = ['strategy' => $selection->scalingStrategy];
            if (!$advanced) {
                $scaling['scale_up_cooldown'] = '1s';
                $scaling['scale_down_idle_for'] = '30s';
            }
            $raw = $selection->scalingFragment['raw'] ?? null;
            if (is_string($raw) && $raw !== '') {
                $kept = [];
                foreach (preg_split("/\r\n|\n|\r/", $raw) ?: [] as $rawLine) {
                    $trimmed = trim($rawLine);
                    if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                        continue;
                    }
                    $kept[] = rtrim($rawLine);
                }
                if ($kept !== []) {
                    $scaling['raw'] = implode("\n", $kept) . "\n";
                }
            }
            $spec['scaling'] = $scaling;
        }

        return $spec;
    }

    /**
     * @param array<string, mixed> $spec
     */
    public static function fragment(array $spec): string
    {
        $workers = $spec['workers'];
        $name = ($spec['mode'] ?? '') === 'http' ? 'http' : 'application-worker';
        $lines = [
            '  ' . $name . ':',
            '    mode: ' . $spec['mode'],
            '    command:',
        ];
        foreach ($spec['command'] as $part) {
            $lines[] = '      - ' . $part;
        }
        $lines[] = '    workers:';
        $lines[] = '      min: ' . $workers['min'];
        $lines[] = '      max: ' . $workers['max'];

        if (isset($spec['resources']) && is_array($spec['resources'])) {
            $lines[] = '    resources:';
            if (isset($spec['resources']['class'])) {
                $lines[] = '      class: ' . $spec['resources']['class'];
            }
            if (isset($spec['resources']['memory_mb'])) {
                $lines[] = '      memory_mb: ' . $spec['resources']['memory_mb'];
            }
        }

        if (isset($spec['scaling']) && is_array($spec['scaling'])) {
            if (($spec['scaling']['strategy'] ?? '') === 'fixed') {
                $lines[] = '    # fixed targets workers.max, subject to resource clamps';
            }
            $lines[] = '    scaling:';
            $lines[] = '      strategy: ' . $spec['scaling']['strategy'];
            if (isset($spec['scaling']['scale_up_cooldown'])) {
                $lines[] = '      scale_up_cooldown: ' . $spec['scaling']['scale_up_cooldown'];
            }
            if (isset($spec['scaling']['scale_down_idle_for'])) {
                $lines[] = '      scale_down_idle_for: ' . $spec['scaling']['scale_down_idle_for'];
            }
            if (isset($spec['scaling']['raw']) && is_string($spec['scaling']['raw'])) {
                foreach (preg_split("/\r\n|\n|\r/", rtrim($spec['scaling']['raw'])) ?: [] as $rawLine) {
                    $lines[] = '      ' . $rawLine;
                }
            }
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function parseDocument(string $yaml, string $name = 'application-worker'): ?array
    {
        $lines = preg_split("/\r\n|\n|\r/", $yaml);
        if ($lines === false) {
            throw new \RuntimeException('Unable to read eregion.yaml lines.');
        }

        $starts = [];
        foreach ($lines as $index => $line) {
            if ($line === '  ' . $name . ':' || str_starts_with($line, '  ' . $name . ': ')) {
                $starts[] = $index;
            }
        }

        if ($starts === []) {
            return null;
        }

        if (count($starts) > 1) {
            throw new \RuntimeException(
                'eregion.yaml has an ambiguous ' . $name . ' block. Refusing to edit it.'
            );
        }

        $start = $starts[0];
        $end = count($lines);
        for ($i = $start + 1; $i < count($lines); $i++) {
            $line = $lines[$i];
            if ($line === '') {
                continue;
            }
            if (preg_match('/^ {0,2}\S/', $line) === 1) {
                $end = $i;
                break;
            }
        }

        return self::parseBlock(array_slice($lines, $start, $end - $start), $name);
    }

    /**
     * @param list<string> $lines
     * @return array<string, mixed>
     */
    private static function parseBlock(array $lines, string $name): array
    {
        $mode = null;
        $command = [];
        $workers = [];
        $resources = [];
        $scaling = [];
        $raw = [];
        $section = null;
        $scalingIndent = null;

        foreach ($lines as $line) {
            $indent = strspn($line, ' ');
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#') || $trimmed === $name . ':') {
                continue;
            }

            if ($section === 'scaling' && $scalingIndent !== null && $indent > $scalingIndent) {
                $direct = $indent === $scalingIndent + 2
                    && preg_match('/^(strategy|scale_up_cooldown|scale_down_idle_for):\s*(\S.*)$/', $trimmed, $scalar) === 1;
                if ($direct) {
                    $scaling[$scalar[1]] = $scalar[2];
                    continue;
                }
                $raw[] = substr($line, $scalingIndent + 2);
                continue;
            }

            if (str_starts_with($trimmed, '- ')) {
                if ($section !== 'command') {
                    throw new \RuntimeException('eregion.yaml ' . $name . ' uses syntax this editor cannot merge.');
                }
                $command[] = trim(substr($trimmed, 2));
                continue;
            }

            if (preg_match('/^([A-Za-z0-9_]+):\s*(.*)$/', $trimmed, $matches) !== 1) {
                throw new \RuntimeException('eregion.yaml ' . $name . ' uses syntax this editor cannot merge.');
            }

            $key = $matches[1];
            $value = $matches[2];
            if ($value === '') {
                $section = $key;
                if ($key === 'scaling') {
                    $scalingIndent = $indent;
                }
                continue;
            }

            if ($key === 'mode') {
                $mode = $value;
                $section = null;
                continue;
            }

            if ($section === 'workers') {
                if (preg_match('/^-?\d+$/', $value) !== 1) {
                    throw new \RuntimeException('eregion.yaml ' . $name . ' uses syntax this editor cannot merge.');
                }
                $workers[$key] = (int) $value;
                continue;
            }

            if ($section === 'resources') {
                $resources[$key] = $key === 'memory_mb' ? (int) $value : $value;
                continue;
            }

            throw new \RuntimeException('eregion.yaml ' . $name . ' uses syntax this editor cannot merge.');
        }

        if ($raw !== []) {
            $scaling['raw'] = implode("\n", $raw) . "\n";
        }

        if ($mode === null || $command === [] || !isset($workers['min'], $workers['max'])) {
            throw new \RuntimeException('eregion.yaml ' . $name . ' is missing mode, command, or workers.');
        }

        $spec = [
            'mode' => $mode,
            'command' => $command,
            'workers' => [
                'min' => $workers['min'],
                'max' => $workers['max'],
            ],
        ];
        if ($resources !== []) {
            $spec['resources'] = $resources;
        }
        if ($scaling !== []) {
            $spec['scaling'] = $scaling;
        }

        return $spec;
    }
}
