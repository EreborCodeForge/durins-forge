<?php

declare(strict_types=1);

namespace App\Tooling\Runtime;

/**
 * Mithril-backed facade: one startup path for serve/dev; status via provider.
 *
 * Dev default uses Eregion (`forge serve`) with local bind defaults because this
 * application's public/index.php is an UDS worker (php -S / serve:php → 503).
 * Escape hatch: RuntimeOptions::$preferPhpServer / CLI `--php`.
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
