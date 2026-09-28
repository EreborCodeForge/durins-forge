<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Runtime;

use Erebor\Mithril\Runtime\Eregion\EregionBinaryResolver;
use Erebor\Mithril\Runtime\Eregion\EregionInstaller as MithrilEregionInstaller;

/**
 * Idempotent Eregion binary install for the application root.
 */
class EregionInstaller
{
    public function __construct(
        private readonly ?MithrilEregionInstaller $installer = null,
        private readonly EregionBinaryResolver $binaryResolver = new EregionBinaryResolver(),
    ) {}

    /**
     * @return array{path: string, version: string, asset: string, action: string}
     */
    public function install(string $applicationRoot, bool $force = false): array
    {
        $installer = $this->installer ?? new MithrilEregionInstaller($this->binaryResolver);

        return $installer->install(
            workingDirectory: $applicationRoot,
            force: $force,
        );
    }

    public function isInstalled(string $applicationRoot): bool
    {
        $path = $this->binaryResolver->localInstallPath($applicationRoot);

        return $this->binaryResolver->isExecutable($path);
    }
}
