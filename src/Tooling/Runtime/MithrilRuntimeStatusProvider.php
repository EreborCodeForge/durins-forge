<?php

declare(strict_types=1);

namespace App\Tooling\Runtime;

use Erebor\Mithril\Runtime\Eregion\ApplicationResolver;
use Erebor\Mithril\Runtime\Eregion\EregionBinaryResolver;

/**
 * Reads local runtime metadata via Mithril resolvers (no protocol reimplementation).
 */
final class MithrilRuntimeStatusProvider implements RuntimeStatusProvider
{
    public function __construct(
        private readonly EregionBinaryResolver $binaryResolver = new EregionBinaryResolver(),
    ) {}

    public function read(RuntimeOptions $options): RuntimeStatus
    {
        $root = $options->workingDirectory;
        $resolver = new ApplicationResolver($root);
        $binary = $this->binaryResolver->resolve($root);
        $config = $resolver->eregionConfigPath();
        $manifest = $root . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'eregion.json';

        $details = [
            'eregion_config_present' => is_file($config),
            'manifest_present' => is_file($manifest),
            'worker_present' => is_file($resolver->eregionWorkerPath()),
        ];

        if ($binary === null) {
            return new RuntimeStatus(
                available: false,
                summary: 'Eregion binary not found (run forge server:install)',
                binaryPath: null,
                manifestPath: is_file($manifest) ? $manifest : null,
                configPath: is_file($config) ? $config : null,
                details: $details,
            );
        }

        return new RuntimeStatus(
            available: true,
            summary: 'Runtime tooling available via Mithril/Eregion',
            binaryPath: $binary,
            manifestPath: is_file($manifest) ? $manifest : null,
            configPath: is_file($config) ? $config : null,
            details: $details,
        );
    }
}
