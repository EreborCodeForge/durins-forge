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
     * Consumer-first eregion.yaml for supervised mithril-job (Eregion v0.4+ workloads).
     *
     * @return array{path: string, action: string}
     */
    private function ensureJobWorkload(string $applicationRoot): array
    {
        $path = $applicationRoot . DIRECTORY_SEPARATOR . 'eregion.yaml';
        $workload = $this->consumerWorkloadYaml();

        if (!is_file($path)) {
            if (file_put_contents($path, $workload) === false) {
                throw new \RuntimeException("Unable to write {$path}");
            }

            return ['path' => $path, 'action' => 'created'];
        }

        $existing = (string) file_get_contents($path);
        if ($this->hasConsumerJobWorkload($existing)) {
            return ['path' => $path, 'action' => 'exists'];
        }

        // Replace HTTP-only starter from Mithril craft with consumer-first config.
        if (file_put_contents($path, $workload) === false) {
            throw new \RuntimeException("Unable to update {$path}");
        }

        return ['path' => $path, 'action' => 'updated'];
    }

    private function consumerWorkloadYaml(): string
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
      max: 4

YAML;
    }

    private function hasConsumerJobWorkload(string $yaml): bool
    {
        return str_contains($yaml, 'application-worker:')
            && str_contains($yaml, 'vendor/bin/job-worker')
            && str_contains($yaml, 'mode: consumer');
    }
}
