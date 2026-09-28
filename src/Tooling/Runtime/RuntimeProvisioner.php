<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Runtime;

use EreborCodeForge\Durin\Presets\Metadata\RuntimeProfile;

/**
 * Resolves Forge default runner and provisions Eregion when required.
 */
final class RuntimeProvisioner
{
    public const string DEFAULT_RUNNER = 'eregion';

    public function __construct(
        private readonly EregionInstaller $installer = new EregionInstaller(),
        private readonly EregionConfigurator $configurator = new EregionConfigurator(),
        private readonly bool $defaultInstallRunner = true,
    ) {}

    public function resolveRunner(RuntimeProfile $profile): string
    {
        return $profile->runner ?? self::DEFAULT_RUNNER;
    }

    public function shouldInstall(RuntimeProfile $profile): bool
    {
        if ($profile->installRunner !== null) {
            return $profile->installRunner;
        }

        $runner = $this->resolveRunner($profile);

        return $this->defaultInstallRunner && $runner === self::DEFAULT_RUNNER;
    }

    /**
     * @return array{runner: string, installed: bool, configured: bool, install_action: ?string}
     */
    public function provision(
        string $applicationRoot,
        RuntimeProfile $profile,
        bool $skipInstall = false,
    ): array {
        $runner = $this->resolveRunner($profile);
        $installAction = null;
        $installed = false;
        $configured = false;

        if ($runner !== self::DEFAULT_RUNNER) {
            return [
                'runner' => $runner,
                'installed' => false,
                'configured' => false,
                'install_action' => null,
            ];
        }

        if (!$skipInstall && $this->shouldInstall($profile)) {
            $result = $this->installer->install($applicationRoot);
            $installAction = $result['action'];
            $installed = true;
        } else {
            $installed = $this->installer->isInstalled($applicationRoot);
        }

        $this->configurator->configure($applicationRoot);
        $configured = true;

        return [
            'runner' => $runner,
            'installed' => $installed,
            'configured' => $configured,
            'install_action' => $installAction,
        ];
    }
}
