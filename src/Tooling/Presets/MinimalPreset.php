<?php

declare(strict_types=1);

namespace App\Tooling\Presets;

use EreborCodeForge\Durin\Core\Contract\Preset;
use EreborCodeForge\Durin\Core\Contract\ProjectOptions;
use EreborCodeForge\Durin\Core\Scaffold\ScaffoldPlan;

/**
 * Small HTTP API / webhook preset (master §17). No Domain ceremony.
 */
final class MinimalPreset implements Preset
{
    public function __construct(
        private readonly PresetScaffoldSupport $files = new PresetScaffoldSupport(),
    ) {}

    public function name(): string
    {
        return 'minimal';
    }

    public function scaffold(ProjectOptions $options): ScaffoldPlan
    {
        $plan = new ScaffoldPlan();
        $app = $options->name;
        $package = $this->files->composerPackageName($app);

        $plan
            ->directory('src/Http')
            ->directory('src/Application')
            ->directory('routes')
            ->directory('config')
            ->directory('tests')
            ->directory('public')
            ->directory('var/cache')
            ->directory('var/runtime')
            ->file('src/Http/.gitkeep', '')
            ->file('src/Application/.gitkeep', '')
            ->file('routes/api.php', $this->files->routesApi())
            ->file('config/app.php', $this->files->configApp($app))
            ->file('public/index.php', $this->files->publicIndex('Minimal'))
            ->file('tests/ExampleTest.php', $this->files->exampleTest())
            ->file('composer.json', $this->files->composerJson($package))
            ->file('.env.example', $this->files->envExample())
            ->file('README.md', $this->files->readme($app, 'minimal', [
                'src/Http',
                'src/Application',
                'routes',
                'config',
                'tests',
            ]));

        (new ManifestPlanFactory())->appendManifest($plan, $options);

        return $plan;
    }
}
