<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Runtime;

use EreborCodeForge\Durin\Core\Manifest\DurinManifestException;
use EreborCodeForge\Durin\Core\Manifest\DurinManifestParser;

/**
 * Mithril-backed facade: one startup path for serve/dev; status via provider.
 *
 * Dev default uses Eregion (`forge serve`) with local bind defaults because this
 * application's public/index.php is an UDS worker (php -S / serve:php → 503).
 * Escape hatch: RuntimeOptions::$preferPhpServer / CLI `--php`.
 *
 * Job execution is never routed to HTTP forge serve.
 */
final class MithrilRuntimeFacade implements RuntimeFacade
{
    public function __construct(
        private readonly RuntimeProcessRunner $processRunner = new PhpForgeProcessRunner(),
        private readonly RuntimeStatusProvider $statusProvider = new MithrilRuntimeStatusProvider(),
        private readonly ?string $forgeBinary = null,
        private readonly DurinManifestParser $manifestParser = new DurinManifestParser(),
    ) {}

    public function serve(RuntimeOptions $options): int
    {
        $this->assertHttpExecution($options->workingDirectory);

        return $this->processRunner->run(
            $this->resolveForgeBinary($options->workingDirectory),
            array_merge(['serve'], $options->forgeServeArgs()),
            $options->workingDirectory,
        );
    }

    public function dev(RuntimeOptions $options): int
    {
        $this->assertHttpExecution($options->workingDirectory);

        if ($options->preferPhpServer) {
            return $this->processRunner->run(
                $this->resolveForgeBinary($options->workingDirectory),
                array_merge(
                    ['serve:php', '--host=' . $options->host, '--port=' . (string) $options->port],
                    $options->passthroughArgs,
                ),
                $options->workingDirectory,
            );
        }

        // Same orchestration stack as serve — only bind/env defaults differ.
        return $this->serve($options);
    }

    public function status(RuntimeOptions $options): RuntimeStatus
    {
        return $this->statusProvider->read($options);
    }

    private function assertHttpExecution(string $workingDirectory): void
    {
        $path = $workingDirectory . DIRECTORY_SEPARATOR . 'durin.yaml';
        if (!is_file($path)) {
            return;
        }

        try {
            $plan = RuntimePlan::fromManifest($this->manifestParser->parseFile($path));
        } catch (DurinManifestException) {
            return;
        }

        if (!$plan->isJobExecution()) {
            return;
        }

        if ($plan->usesEregion()) {
            throw new RuntimeOrchestrationException(
                'Job applications with Eregion supervision are not started via serve/dev. '
                . 'Use: durin run'
            );
        }

        throw new RuntimeOrchestrationException(
            'Job applications are not started via serve/dev. Use: durin run'
        );
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
