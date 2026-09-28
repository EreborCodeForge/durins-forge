<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Runtime;

use EreborCodeForge\Durin\Presets\Metadata\RuntimeProfile;

/**
 * Orchestrates install/configure side effects from a resolved RuntimePlan.
 */
final class RuntimeProvisioner
{
    public const string EREGION = 'eregion';

    /** @deprecated Use capability resolution; kept for transitional string compares */
    public const string DEFAULT_RUNNER = self::EREGION;

    public function __construct(
        private readonly EregionInstaller $installer = new EregionInstaller(),
        private readonly EregionConfigurator $configurator = new EregionConfigurator(),
        private readonly bool $defaultInstallRunner = true,
        private readonly RuntimeResolver $resolver = new RuntimeResolver(),
    ) {}

    public function resolve(RuntimeProfile $profile): RuntimePlan
    {
        return $this->resolver->resolve($profile);
    }

    /**
     * @deprecated Prefer resolve() → RuntimePlan
     */
    public function resolveRunner(RuntimeProfile $profile): string
    {
        $plan = $this->resolve($profile);

        return $plan->supervisor ?? $plan->executionRuntime;
    }

    public function shouldInstall(RuntimeProfile $profile, ?RuntimePlan $plan = null): bool
    {
        $plan ??= $this->resolve($profile);

        if (!$plan->usesEregion()) {
            return false;
        }

        if ($profile->installRunner !== null) {
            return $profile->installRunner;
        }

        return $this->defaultInstallRunner;
    }

    /**
     * @return array{
     *     plan: RuntimePlan,
     *     runner: string,
     *     installed: bool,
     *     configured: bool,
     *     install_action: ?string
     * }
     */
    public function provision(
        string $applicationRoot,
        RuntimeProfile $profile,
        bool $skipInstall = false,
        ?RuntimePlan $plan = null,
    ): array {
        $plan ??= $this->resolve($profile);

        if (!$plan->usesEregion()) {
            return [
                'plan' => $plan,
                'runner' => $plan->executionRuntime,
                'installed' => false,
                'configured' => false,
                'install_action' => null,
            ];
        }

        $installAction = null;
        $installed = false;

        if (!$skipInstall && $this->shouldInstall($profile, $plan)) {
            $result = $this->installer->install($applicationRoot);
            $installAction = $result['action'];
            $installed = true;
        } else {
            $installed = $this->installer->isInstalled($applicationRoot);
        }

        $this->configurator->configure($applicationRoot, plan: $plan);

        return [
            'plan' => $plan,
            'runner' => self::EREGION,
            'installed' => $installed,
            'configured' => true,
            'install_action' => $installAction,
        ];
    }
}
