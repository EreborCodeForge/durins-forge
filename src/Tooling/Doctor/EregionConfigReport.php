<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Doctor;

/**
 * Parsed `eregion check` output. Workloads come from the CLI, not from a text search of the YAML.
 */
final readonly class EregionConfigReport
{
    /**
     * @param list<array{name: string, mode: string, min: int, max: int}> $workloads
     */
    public function __construct(
        public bool $validated,
        public string $detail,
        public array $workloads,
        public string $metricsAccess,
    ) {}

    public static function fromOutput(int $exitCode, string $stdout, string $stderr): self
    {
        $workloads = [];
        $access = 'unknown';
        foreach (preg_split('/\R/', $stdout) ?: [] as $line) {
            $line = trim($line);
            if (preg_match('/^- (\S+) mode=(\S+) workers=(\d+)\.\.(\d+)$/', $line, $match) === 1) {
                $workloads[] = [
                    'name' => $match[1],
                    'mode' => $match[2],
                    'min' => (int) $match[3],
                    'max' => (int) $match[4],
                ];
            }
            if (str_starts_with($line, 'metrics_access=')) {
                $value = substr($line, strlen('metrics_access='));
                $access = in_array($value, ['loopback', 'public', 'unset'], true) ? $value : 'unknown';
            }
        }

        $validated = $exitCode === 0 && str_contains($stdout, 'OK:');
        if ($validated) {
            $detail = 'config validated; process not probed';
        } elseif (trim($stderr) !== '') {
            $detail = trim($stderr);
        } else {
            $detail = 'eregion check failed';
        }
        $detail = preg_replace('/\s+/', ' ', $detail) ?? $detail;
        if (strlen($detail) > 240) {
            $detail = substr($detail, 0, 240);
        }

        return new self($validated, $detail, $workloads, $access);
    }

    public function hasMode(string $mode): bool
    {
        foreach ($this->workloads as $workload) {
            if ($workload['mode'] === $mode) {
                return true;
            }
        }

        return false;
    }
}
