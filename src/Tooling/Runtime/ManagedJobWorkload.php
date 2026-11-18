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
    public static function desired(?ProjectSelection $selection): array
    {
        $spec = [
            'mode' => 'consumer',
            'command' => ['php', 'vendor/bin/job-worker'],
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
            $spec['scaling'] = [
                'strategy' => $selection->scalingStrategy,
                'scale_up_cooldown' => '1s',
                'scale_down_idle_for' => '30s',
            ];
        }

        return $spec;
    }

    /**
     * @param array<string, mixed> $spec
     */
    public static function fragment(array $spec): string
    {
        $workers = $spec['workers'];
        $lines = [
            '  application-worker:',
            '    mode: consumer',
            '    command:',
            '      - php',
            '      - vendor/bin/job-worker',
            '    workers:',
            '      min: ' . $workers['min'],
            '      max: ' . $workers['max'],
        ];

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
            $lines[] = '      scale_up_cooldown: ' . $spec['scaling']['scale_up_cooldown'];
            $lines[] = '      scale_down_idle_for: ' . $spec['scaling']['scale_down_idle_for'];
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function parseDocument(string $yaml): ?array
    {
        $lines = preg_split("/\r\n|\n|\r/", $yaml);
        if ($lines === false) {
            throw new \RuntimeException('Unable to read eregion.yaml lines.');
        }

        $starts = [];
        foreach ($lines as $index => $line) {
            if ($line === '  application-worker:' || str_starts_with($line, '  application-worker: ')) {
                $starts[] = $index;
            }
        }

        if ($starts === []) {
            return null;
        }

        if (count($starts) > 1) {
            throw new \RuntimeException(
                'eregion.yaml has an ambiguous application-worker block. Refusing to edit it.'
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

        return self::parseBlock(array_slice($lines, $start, $end - $start));
    }

    /**
     * @param list<string> $lines
     * @return array<string, mixed>
     */
    private static function parseBlock(array $lines): array
    {
        $mode = null;
        $command = [];
        $workers = [];
        $resources = [];
        $scaling = [];
        $section = null;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#') || $trimmed === 'application-worker:') {
                continue;
            }

            if (str_starts_with($trimmed, '- ')) {
                if ($section !== 'command') {
                    throw new \RuntimeException('eregion.yaml application-worker uses syntax this editor cannot merge.');
                }
                $command[] = trim(substr($trimmed, 2));
                continue;
            }

            if (preg_match('/^([A-Za-z0-9_]+):\s*(.*)$/', $trimmed, $matches) !== 1) {
                throw new \RuntimeException('eregion.yaml application-worker uses syntax this editor cannot merge.');
            }

            $key = $matches[1];
            $value = $matches[2];
            if ($value === '') {
                $section = $key;
                continue;
            }

            if ($key === 'mode') {
                $mode = $value;
                $section = null;
                continue;
            }

            if ($section === 'workers') {
                if (preg_match('/^-?\d+$/', $value) !== 1) {
                    throw new \RuntimeException('eregion.yaml application-worker uses syntax this editor cannot merge.');
                }
                $workers[$key] = (int) $value;
                continue;
            }

            if ($section === 'resources') {
                $resources[$key] = $key === 'memory_mb' ? (int) $value : $value;
                continue;
            }

            if ($section === 'scaling') {
                $scaling[$key] = $value;
                continue;
            }

            throw new \RuntimeException('eregion.yaml application-worker uses syntax this editor cannot merge.');
        }

        if ($mode === null || $command === [] || !isset($workers['min'], $workers['max'])) {
            throw new \RuntimeException('eregion.yaml application-worker is missing mode, command, or workers.');
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
