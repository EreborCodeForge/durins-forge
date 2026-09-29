<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Runtime;

use EreborCodeForge\Durin\Core\Manifest\DurinManifest;

/**
 * Concrete runtime decision: execution + optional supervisor.
 *
 * @param list<string> $capabilities
 */
final readonly class RuntimePlan
{
    /**
     * @param list<string> $capabilities
     */
    public function __construct(
        public string $mode,
        public string $executionRuntime,
        public ?string $supervisor,
        public array $capabilities,
    ) {}

    /**
     * Reconstruct a plan from a finalized manifest (Doctor / serve gates).
     */
    public static function fromManifest(DurinManifest $manifest): self
    {
        if ($manifest->runtime !== null) {
            $resolved = $manifest->runtime;
            $mode = $resolved->mode;
            $execution = $resolved->execution;
            $supervisor = $resolved->supervisor;

            $capabilities = $mode === 'job'
                ? ['job-loop', 'messaging']
                : ['persistent-http'];
            if ($supervisor === 'eregion') {
                $capabilities[] = 'process-supervision';
                $capabilities[] = $mode === 'job' ? 'consumer-supervision' : 'http-supervision';
            }

            return new self(
                mode: $mode,
                executionRuntime: $execution,
                supervisor: $supervisor,
                capabilities: array_values(array_unique($capabilities)),
            );
        }

        $mode = $manifest->isJobMode() ? 'job' : 'http';
        $execution = $mode === 'job' ? 'mithril-job' : 'mithril-http';

        return new self(
            mode: $mode,
            executionRuntime: $execution,
            supervisor: null,
            capabilities: $mode === 'job'
                ? ['job-loop', 'messaging']
                : ['persistent-http'],
        );
    }

    /**
     * @return array{mode: string, execution: string, supervisor: ?string}
     */
    public function toCompletePayload(): array
    {
        return [
            'mode' => $this->mode,
            'execution' => $this->executionRuntime,
            'supervisor' => $this->supervisor,
        ];
    }

    public function usesEregion(): bool
    {
        return $this->supervisor === 'eregion';
    }

    public function isJobExecution(): bool
    {
        return $this->executionRuntime === 'mithril-job' || $this->mode === 'job';
    }
}
