<?php

declare(strict_types=1);

namespace App\Tooling\Runtime;

/**
 * Mithril-backed facade: one startup path for serve/dev; status via provider.
 */
final class MithrilRuntimeFacade implements RuntimeFacade
{
    public function __construct(
        private readonly RuntimeProcessRunner $processRunner = new PhpForgeProcessRunner(),
        private readonly RuntimeStatusProvider $statusProvider = new MithrilRuntimeStatusProvider(),
        private readonly ?string $forgeBinary = null,
    ) {}

    public function serve(RuntimeOptions $options): int
    {
        return $this->processRunner->run(
            $this->resolveForgeBinary($options->workingDirectory),
            array_merge(['serve'], $options->forgeServeArgs()),
            $options->workingDirectory,
        );
    }

    public function dev(RuntimeOptions $options): int
    {
        // Local PHP path (SPEC-006 will refine UX). Shared runner — no second stack.
        return $this->processRunner->run(
            $this->resolveForgeBinary($options->workingDirectory),
            array_merge(
                ['serve:php', '--host=' . $options->host, '--port=' . (string) $options->port],
                $options->passthroughArgs,
            ),
            $options->workingDirectory,
        );
    }

    public function status(RuntimeOptions $options): RuntimeStatus
    {
        return $this->statusProvider->read($options);
    }

    private function resolveForgeBinary(string $workingDirectory): string
    {
        if ($this->forgeBinary !== null) {
            return $this->forgeBinary;
        }

        $candidates = [
            $workingDirectory . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'forge',
            $workingDirectory . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'forge.bat',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeOrchestrationException(
            'vendor/bin/forge not found. Run composer install.'
        );
    }
}
