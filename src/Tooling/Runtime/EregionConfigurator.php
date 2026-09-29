<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Runtime;

use Erebor\Mithril\Runtime\Eregion\ApplicationResolver;
use Erebor\Mithril\Runtime\Eregion\EregionCraft;

/**
 * Writes Eregion config/manifest for the application root (idempotent).
 * Translates RuntimePlan job+supervisor into a managed consumer workload.
 *
 * Forge owns only the managed workload key; existing YAML is merged, never replaced.
 */
class EregionConfigurator
{
    public const string MANAGED_WORKLOAD = 'application-worker';

    public function __construct(
        private readonly ?EregionCraft $craft = null,
    ) {}

    /**
     * @return list<array{path: string, action: string}>
     */
    public function configure(
        string $applicationRoot,
        bool $force = false,
        ?RuntimePlan $plan = null,
    ): array {
        $craft = $this->craft ?? new EregionCraft(new ApplicationResolver($applicationRoot));
        $actions = $craft->craft(force: $force);

        if ($plan !== null && $plan->isJobExecution() && $plan->usesEregion()) {
            $actions[] = $this->ensureJobWorkload($applicationRoot);
        }

        return array_values(array_filter($actions));
    }

    /**
     * Upsert managed consumer workload into eregion.yaml without destroying other keys.
     *
     * @return array{path: string, action: string}
     */
    private function ensureJobWorkload(string $applicationRoot): array
    {
        $path = $applicationRoot . DIRECTORY_SEPARATOR . 'eregion.yaml';
        $managed = $this->managedWorkloadDocument();

        if (!is_file($path)) {
            if (file_put_contents($path, $managed) === false) {
                throw new \RuntimeException("Unable to write {$path}");
            }

            return ['path' => $path, 'action' => 'created'];
        }

        $existing = (string) file_get_contents($path);
        if ($this->hasManagedConsumerWorkload($existing)) {
            return ['path' => $path, 'action' => 'exists'];
        }

        $merged = $this->upsertManagedWorkload($existing);
        $merged = $this->stripLegacySingularWorkerKey($merged);
        if (file_put_contents($path, $merged) === false) {
            throw new \RuntimeException("Unable to update {$path}");
        }

        return ['path' => $path, 'action' => 'updated'];
    }

    private function managedWorkloadDocument(): string
    {
        return <<<'YAML'
# Eregion — job supervision (consumer workload)
workloads:
  application-worker:
    mode: consumer
    command:
      - php
      - vendor/bin/job-worker
    workers:
      min: 1
      max: 1

YAML;
    }

    /**
     * YAML fragment indented under a top-level `workloads:` key.
     */
    private function managedWorkloadFragment(): string
    {
        return <<<'YAML'
  application-worker:
    mode: consumer
    command:
      - php
      - vendor/bin/job-worker
    workers:
      min: 1
      max: 1

YAML;
    }

    private function hasManagedConsumerWorkload(string $yaml): bool
    {
        return str_contains($yaml, self::MANAGED_WORKLOAD . ':')
            && str_contains($yaml, 'vendor/bin/job-worker')
            && str_contains($yaml, 'mode: consumer');
    }

    /**
     * Insert or replace only the Forge-managed workload; preserve all other YAML.
     */
    private function upsertManagedWorkload(string $yaml): string
    {
        $withoutManaged = $this->removeWorkloadBlock($yaml, self::MANAGED_WORKLOAD);
        $fragment = $this->managedWorkloadFragment();

        if (preg_match('/^workloads:\s*(?:\r?\n|$)/m', $withoutManaged) === 1) {
            return (string) preg_replace(
                '/^(workloads:\s*\r?\n)/m',
                '$1' . $fragment,
                $withoutManaged,
                1,
            );
        }

        $trimmed = rtrim($withoutManaged);

        return $trimmed . ($trimmed === '' ? '' : "\n\n") . "workloads:\n" . $fragment;
    }

    /**
     * Mithril's HTTP starter still emits top-level `worker:` which Eregion v0.4+ rejects.
     * Remove only that obsolete key; preserve `workers:`, server, and user settings.
     */
    private function stripLegacySingularWorkerKey(string $yaml): string
    {
        $lines = preg_split("/\r\n|\n|\r/", $yaml);
        if ($lines === false) {
            return $yaml;
        }

        $start = null;
        foreach ($lines as $i => $line) {
            if ($line === 'worker:' || str_starts_with($line, 'worker: ')) {
                $start = $i;
                break;
            }
        }

        if ($start === null) {
            return $yaml;
        }

        $end = count($lines);
        for ($i = $start + 1; $i < count($lines); $i++) {
            $line = $lines[$i];
            if ($line === '') {
                continue;
            }
            if (preg_match('/^\S/', $line) === 1) {
                $end = $i;
                break;
            }
        }

        array_splice($lines, $start, $end - $start);

        return implode("\n", $lines);
    }

    /**
     * Remove a single workload entry (2-space indent) if present.
     */
    private function removeWorkloadBlock(string $yaml, string $name): string
    {
        $lines = preg_split("/\r\n|\n|\r/", $yaml);
        if ($lines === false) {
            return $yaml;
        }

        $start = null;
        $needle = '  ' . $name . ':';
        foreach ($lines as $i => $line) {
            if ($line === $needle || str_starts_with($line, $needle . ' ')) {
                $start = $i;
                break;
            }
        }

        if ($start === null) {
            return $yaml;
        }

        $end = count($lines);
        for ($i = $start + 1; $i < count($lines); $i++) {
            $line = $lines[$i];
            if ($line === '') {
                continue;
            }
            // Next key at indent ≤ 2 (sibling workload or top-level) ends the block.
            if (preg_match('/^ {0,2}\S/', $line) === 1) {
                $end = $i;
                break;
            }
        }

        array_splice($lines, $start, $end - $start);

        return implode("\n", $lines);
    }
}
