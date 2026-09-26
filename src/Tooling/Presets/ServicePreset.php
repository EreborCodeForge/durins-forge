<?php

declare(strict_types=1);

namespace App\Tooling\Presets;

use App\Tooling\Scaffold\ScaffoldPlan;

/**
 * General-purpose backend service preset (master §18).
 * Layer roots only — no empty Entity/Repository ceremony trees.
 */
final class ServicePreset implements Preset
{
    public function __construct(
        private readonly PresetScaffoldSupport $files = new PresetScaffoldSupport(),
    ) {}

    public function name(): string
    {
        return 'service';
    }

    public function scaffold(ProjectOptions $options): ScaffoldPlan
    {
        $plan = new ScaffoldPlan();
        $app = $options->name;
        $package = $this->files->composerPackageName($app);

        // Align options for service-oriented manifest defaults.
        $serviceOptions = new ProjectOptions(
            name: $options->name,
            preset: 'service',
            targetDirectory: $options->targetDirectory,
            runtimeEngine: $options->runtimeEngine,
            runtimeServer: $options->runtimeServer,
            http: true,
            messaging: false,
            modules: false,
            extra: $options->extra,
        );

        $plan
            ->directory('src/Domain')
            ->directory('src/Application')
            ->directory('src/Infrastructure')
            ->directory('src/Presentation')
            ->directory('routes')
            ->directory('config')
            ->directory('tests')
            ->directory('public')
            ->directory('var/cache')
            ->directory('var/runtime')
            ->file('src/Domain/.gitkeep', '')
            ->file('src/Application/.gitkeep', '')
            ->file('src/Infrastructure/.gitkeep', '')
            ->file('src/Presentation/.gitkeep', '')
            ->file('routes/api.php', $this->files->routesApi())
            ->file('config/app.php', $this->files->configApp($app))
            ->file('public/index.php', $this->files->publicIndex('Service'))
            ->file('tests/ExampleTest.php', $this->files->exampleTest())
            ->file('composer.json', $this->files->composerJson($package))
            ->file('.env.example', $this->files->envExample())
            ->file('README.md', $this->files->readme($app, 'service', [
                'src/Domain',
                'src/Application',
                'src/Infrastructure',
                'src/Presentation',
                'routes',
                'config',
                'tests',
            ]));

        (new ManifestPlanFactory())->appendManifest($plan, $serviceOptions);

        return $plan;
    }
}
