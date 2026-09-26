<?php

declare(strict_types=1);

namespace App\Tooling\Presets;

use App\Tooling\Scaffold\ScaffoldPlan;

/**
 * Small HTTP API / webhook preset (master §17). No Domain ceremony.
 */
final class MinimalPreset implements Preset
{
    public function name(): string
    {
        return 'minimal';
    }

    public function scaffold(ProjectOptions $options): ScaffoldPlan
    {
        $plan = new ScaffoldPlan();
        $app = $options->name;
        $package = $this->composerPackageName($app);

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
            ->file('routes/api.php', $this->routesApi())
            ->file('config/app.php', $this->configApp($app))
            ->file('public/index.php', $this->publicIndex())
            ->file('tests/ExampleTest.php', $this->exampleTest())
            ->file('composer.json', $this->composerJson($package))
            ->file('.env.example', $this->envExample())
            ->file('README.md', $this->readme($app));

        (new ManifestPlanFactory())->appendManifest($plan, $options);

        return $plan;
    }

    private function composerPackageName(string $app): string
    {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $app) ?? $app);
        $slug = trim($slug, '-') ?: 'app';

        return 'app/' . $slug;
    }

    private function routesApi(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

/** @var \Erebor\Mithril\Router $router */

$router->get('/api/health', static fn () => ['status' => 'ok']);

PHP;
    }

    private function configApp(string $app): string
    {
        $name = var_export($app, true);

        return <<<PHP
<?php

declare(strict_types=1);

return [
    'name' => {$name},
    'env' => getenv('APP_ENV') ?: 'development',
];

PHP;
    }

    private function publicIndex(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

// Worker / HTTP entry placeholder for a minimal Durin app.
// Replace with Mithril HttpApplication bootstrap when wiring runtime.

require dirname(__DIR__) . '/vendor/autoload.php';

http_response_code(503);
header('Content-Type: text/plain; charset=utf-8');
echo "Minimal Durin app: configure Kernel + Eregion worker entry before serving.\n";

PHP;
    }

    private function exampleTest(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ExampleTest extends TestCase
{
    public function test_truth(): void
    {
        $this->assertTrue(true);
    }
}

PHP;
    }

    private function composerJson(string $package): string
    {
        $json = [
            'name' => $package,
            'type' => 'project',
            'require' => [
                'php' => '^8.5',
                'ext-msgpack' => '*',
                'ext-sockets' => '*',
                'ereborcodeforge/mithrilphp' => '^2.1',
            ],
            'require-dev' => [
                'phpunit/phpunit' => '^12.5',
            ],
            'autoload' => [
                'psr-4' => [
                    'App\\' => 'src/',
                ],
            ],
            'autoload-dev' => [
                'psr-4' => [
                    'App\\Tests\\' => 'tests/',
                ],
            ],
            'extra' => [
                'mithril' => [
                    'kernel' => 'App\\Kernel',
                    'eregion' => 'v0.3.0',
                    'eregion_repo' => 'EreborCodeForge/eregion',
                ],
            ],
            'scripts' => [
                'test' => 'phpunit',
            ],
            'config' => [
                'sort-packages' => true,
            ],
        ];

        return json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }

    private function envExample(): string
    {
        return <<<'ENV'
APP_ENV=development
APP_DEBUG=true
APP_URL=http://127.0.0.1:8080
APP_PORT=8080

ENV;
    }

    private function readme(string $app): string
    {
        return <<<MD
# {$app}

Created with `durin new` preset **minimal**.

## Next steps

```bash
composer install
cp .env.example .env
php bin/durin doctor
php bin/durin dev
```

Structure stays small by design: `src/Http`, `src/Application`, `routes`, `config`, `tests`.

MD;
    }
}
