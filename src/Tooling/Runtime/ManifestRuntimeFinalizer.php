<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Runtime;

use EreborCodeForge\Durin\Core\Manifest\DurinManifest;
use EreborCodeForge\Durin\Core\Manifest\DurinManifestException;
use EreborCodeForge\Durin\Core\Manifest\DurinManifestParser;

/**
 * Rewrites durin.yaml runtime fields from a resolved RuntimePlan.
 */
final class ManifestRuntimeFinalizer
{
    public function __construct(
        private readonly DurinManifestParser $parser = new DurinManifestParser(),
    ) {}

    public function finalize(string $applicationRoot, RuntimePlan $plan): DurinManifest
    {
        $path = $applicationRoot . DIRECTORY_SEPARATOR . 'durin.yaml';
        if (!is_file($path)) {
            throw new DurinManifestException("Missing durin.yaml at {$path}");
        }

        $current = $this->parser->parseFile($path);
        $server = $plan->supervisor ?? 'none';
        $updated = new DurinManifest(
            applicationName: $current->applicationName,
            preset: $current->preset,
            runtimeEngine: $current->runtimeEngine,
            runtimeServer: $server,
            features: $current->features,
            architecture: $current->architecture,
            runtimeMode: $plan->mode,
        );

        $yaml = $updated->toYaml();
        if ((string) file_get_contents($path) !== $yaml) {
            if (file_put_contents($path, $yaml) === false) {
                throw new \RuntimeException("Unable to write finalized durin.yaml at {$path}");
            }
        }

        return $updated;
    }
}
