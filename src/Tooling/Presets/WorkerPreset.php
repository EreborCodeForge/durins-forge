<?php

declare(strict_types=1);

namespace App\Tooling\Presets;

use EreborCodeForge\Durin\Core\Contract\Preset;
use EreborCodeForge\Durin\Core\Contract\ProjectOptions;
use EreborCodeForge\Durin\Core\Scaffold\ScaffoldPlan;

/**
 * Non-HTTP job / queue / scheduled worker preset (master §21, SPEC-DX-017).
 * Requires Mithril ^2.2 JobApplication + bin/job-worker (SPEC-MITHRIL-001).
 */
final class WorkerPreset implements Preset
{
    public function __construct(
        private readonly PresetScaffoldSupport $files = new PresetScaffoldSupport(),
    ) {}

    public function name(): string
    {
        return 'worker';
    }

    public function scaffold(ProjectOptions $options): ScaffoldPlan
    {
        $plan = new ScaffoldPlan();
        $app = $options->name;
        $package = $this->files->composerPackageName($app);

        $workerOptions = new ProjectOptions(
            name: $options->name,
            preset: 'worker',
            targetDirectory: $options->targetDirectory,
            runtimeEngine: $options->runtimeEngine,
            runtimeServer: 'none',
            runtimeMode: 'job',
            http: false,
            messaging: true,
            modules: false,
            extra: $options->extra,
        );

        $plan
            ->directory('src')
            ->directory('src/Application')
            ->directory('src/Infrastructure')
            ->directory('src/Jobs')
            ->directory('config')
            ->directory('tests')
            ->directory('var/cache')
            ->directory('var/runtime')
            ->file('src/Application/.gitkeep', '')
            ->file('src/Infrastructure/.gitkeep', '')
            ->file('src/Jobs/.gitkeep', '')
            ->file('src/JobKernel.php', $this->files->jobKernel($app))
            ->file('config/app.php', $this->files->configApp($app))
            ->file('tests/ExampleTest.php', $this->files->exampleTest())
            ->file('composer.json', $this->files->composerJsonWorker($package))
            ->file('.env.example', $this->files->envExampleWorker())
            ->file('README.md', $this->files->readmeWorker($app, [
                'src/JobKernel.php',
                'src/Application',
                'src/Infrastructure',
                'src/Jobs',
                'config',
                'tests',
            ]));

        (new ManifestPlanFactory())->appendManifest($plan, $workerOptions);

        return $plan;
    }
}
