<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Runtime;

use EreborCodeForge\Durin\Core\Manifest\DurinManifest;
use EreborCodeForge\Durin\Core\Manifest\DurinManifestException;
use EreborCodeForge\Durin\Core\Manifest\DurinManifestParser;
use EreborCodeForge\Durin\Core\Runtime\ResolvedRuntime;

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
        $updated = new DurinManifest(
            applicationName: $current->applicationName,
            preset: $current->preset,
            features: $current->features,
            architecture: $current->architecture,
            runtime: new ResolvedRuntime(
                mode: $plan->mode,
                engine: 'mithril',
                execution: $plan->executionRuntime,
                supervisor: $plan->supervisor,
            ),
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
