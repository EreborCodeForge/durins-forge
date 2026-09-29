<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Console\Commands;

use EreborCodeForge\Durin\Core\Manifest\DurinManifestException;
use EreborCodeForge\Durin\Core\Project\ProjectDiscovery;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimeLaunchException;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimeLauncherRegistry;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimeOrchestrationException;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimePlan;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RunOptions;
use Erebor\Mithril\Console\Command;

/**
 * Neutral execution entry: ProjectDiscovery → RuntimePlan → matching RuntimeLauncher.
 * No preset IDs; decisions use only mode / executionRuntime / supervisor.
 */
final class RunCommand extends Command
{
    public function __construct(
        private readonly ?ProjectDiscovery $discovery = null,
        private readonly ?RuntimeLauncherRegistry $launchers = null,
    ) {}

    public static function getSignature(): string
    {
        return 'run';
    }

    public static function getDescription(): string
    {
        return 'Executa a aplicação conforme o RuntimePlan (HTTP ou job)';
    }

    public function execute(): int
    {
        $cwd = getcwd() ?: base_path();
        $discovery = $this->discovery ?? new ProjectDiscovery();
        $registry = $this->launchers ?? RuntimeLauncherRegistry::builtIn();

        try {
            $project = $discovery->discover($cwd);
        } catch (DurinManifestException $e) {
            $this->error($e->getMessage());

            return 2;
        }

        if ($project->manifest === null) {
            $this->error('Missing durin.yaml — run vendor/bin/durin init first.');

            return 2;
        }

        $plan = RuntimePlan::fromManifest($project->manifest);
        $options = RunOptions::fromArgv($this->args, $project->root());

        try {
            $launcher = $registry->resolve($plan);

            return $launcher->launch($plan, $options);
        } catch (RuntimeLaunchException|RuntimeOrchestrationException $e) {
            $this->error($e->getMessage());

            return 1;
        }
    }
}
