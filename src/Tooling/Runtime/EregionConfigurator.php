<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Runtime;

use EreborCodeForge\Durin\Core\Contract\ProjectSelection;
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
        ?ProjectSelection $selection = null,
        bool $preserveExisting = false,
    ): array {
        if ($preserveExisting) {
            return [];
        }

        $craft = $this->craft ?? new EregionCraft(new ApplicationResolver($applicationRoot));
        $actions = $craft->craft(force: $force);
        $normalized = $this->normalizeStarter($applicationRoot);
        if ($normalized !== null) {
            $actions[] = $normalized;
        }

        if ($plan !== null && $plan->isJobExecution() && $plan->usesEregion()) {
            $actions[] = $this->ensureJobWorkload($applicationRoot, $selection, $preserveExisting);
        }

        return array_values(array_filter($actions));
    }

    /**
     * @return array{path: string, action: string}|null
     */
    private function normalizeStarter(string $applicationRoot): ?array
    {
        $path = $applicationRoot . DIRECTORY_SEPARATOR . 'eregion.yaml';
        if (!is_file($path)) {
            return null;
        }

        $existing = (string) file_get_contents($path);
        $normalized = $this->stripLegacySingularWorkerKey($existing);
        if (!str_contains($normalized, 'worker_script:')) {
            $normalized = rtrim($normalized) . "\n\nphp:\n  worker_script: vendor/bin/eregion-worker\n";
        }

        if ($normalized === $existing) {
            return ['path' => $path, 'action' => 'exists'];
        }

        if (file_put_contents($path, $normalized) === false) {
            throw new \RuntimeException("Unable to update {$path}");
        }

        return ['path' => $path, 'action' => 'updated'];
    }

    /**
     * Upsert managed consumer workload into eregion.yaml without destroying other keys.
     *
     * @return array{path: string, action: string}
     */
    private function ensureJobWorkload(
        string $applicationRoot,
        ?ProjectSelection $selection,
        bool $preserveExisting,
    ): array {
        $path = $applicationRoot . DIRECTORY_SEPARATOR . 'eregion.yaml';
        $desired = ManagedJobWorkload::desired($selection);

        if ($preserveExisting) {
            return ['path' => $path, 'action' => 'exists'];
        }

        if (!is_file($path)) {
            if (file_put_contents($path, $this->managedWorkloadDocument($selection)) === false) {
                throw new \RuntimeException("Unable to write {$path}");
            }

            return ['path' => $path, 'action' => 'created'];
        }

        $existing = (string) file_get_contents($path);
        $current = ManagedJobWorkload::parseDocument($existing);
        if ($current !== null && $current == $desired) {
            return ['path' => $path, 'action' => 'exists'];
        }
        if ($current !== null) {
            throw new \RuntimeException(
                'Managed workload application-worker does not match the requested policy. Use a future configure/evolve command.'
            );
        }

        $merged = $this->upsertManagedWorkload($existing, ManagedJobWorkload::fragment($desired));
        $merged = $this->stripLegacySingularWorkerKey($merged);
        if (file_put_contents($path, $merged) === false) {
            throw new \RuntimeException("Unable to update {$path}");
        }

        return ['path' => $path, 'action' => 'updated'];
    }

    private function managedWorkloadDocument(?ProjectSelection $selection): string
    {
        return "# Eregion — job supervision (consumer workload)\nworkloads:\n"
            . ManagedJobWorkload::fragment(ManagedJobWorkload::desired($selection));
    }

    /**
     * Insert the Forge-managed workload; preserve all other YAML bytes.
     */
    private function upsertManagedWorkload(string $yaml, string $fragment): string
    {
        $withoutManaged = $this->removeWorkloadBlock($yaml, self::MANAGED_WORKLOAD);

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
