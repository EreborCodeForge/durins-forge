<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Runtime;

use Erebor\Mithril\Runtime\Eregion\ApplicationResolver;
use Erebor\Mithril\Runtime\Eregion\EregionCraft;

/**
 * Writes Eregion config/manifest for the application root (idempotent).
 * Translates RuntimePlan job+supervisor into consumer workloads when needed.
 */
class EregionConfigurator
{
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
     * @return array{path: string, action: string}
     */
    private function ensureJobWorkload(string $applicationRoot): array
    {
        $path = $applicationRoot . DIRECTORY_SEPARATOR . 'eregion.yaml';
        $workload = <<<'YAML'

workloads:
  application-worker:
    mode: consumer
    command:
      - php
      - vendor/bin/job-worker
    workers:
      min: 1
      max: 4
YAML;

        if (!is_file($path)) {
            if (file_put_contents($path, "# Eregion — job supervision\n" . ltrim($workload) . "\n") === false) {
                throw new \RuntimeException("Unable to write {$path}");
            }

            return ['path' => $path, 'action' => 'created'];
        }

        $existing = (string) file_get_contents($path);
        if (str_contains($existing, 'application-worker:') || str_contains($existing, 'vendor/bin/job-worker')) {
            return ['path' => $path, 'action' => 'exists'];
        }

        if (file_put_contents($path, rtrim($existing) . "\n" . $workload . "\n") === false) {
            throw new \RuntimeException("Unable to update {$path}");
        }

        return ['path' => $path, 'action' => 'updated'];
    }
}
