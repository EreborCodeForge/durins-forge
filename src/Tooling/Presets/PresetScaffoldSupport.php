<?php

declare(strict_types=1);

namespace App\Tooling\Presets;

/**
 * Shared scaffold file contents for HTTP-oriented presets.
 */
final class PresetScaffoldSupport
{
    public function composerPackageName(string $app): string
    {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $app) ?? $app);
        $slug = trim($slug, '-') ?: 'app';

        return 'app/' . $slug;
    }

    public function routesApi(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

/** @var \Erebor\Mithril\Router $router */

$router->get('/api/health', static fn () => ['status' => 'ok']);

PHP;
    }

    public function configApp(string $app): string
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

    public function publicIndex(string $label): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

// Worker / HTTP entry placeholder for a {$label} Durin app.
// Replace with Mithril HttpApplication bootstrap when wiring runtime.

require dirname(__DIR__) . '/vendor/autoload.php';

http_response_code(503);
header('Content-Type: text/plain; charset=utf-8');
echo "{$label} Durin app: configure Kernel + Eregion worker entry before serving.\\n";

PHP;
    }

    public function exampleTest(): string
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

    public function composerJson(string $package): string
    {
        $json = [
            'name' => $package,
            'type' => 'project',
            'require' => [
                'php' => '^8.5',
                'ext-msgpack' => '*',
                'ext-sockets' => '*',
                'ereborcodeforge/mithrilphp' => '^2.2',
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

    public function composerJsonWorker(string $package): string
    {
        $json = [
            'name' => $package,
            'type' => 'project',
            'require' => [
                'php' => '^8.5',
                'ext-msgpack' => '*',
                'ext-sockets' => '*',
                'ereborcodeforge/mithrilphp' => '^2.2',
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
                    'job_kernel' => 'App\\JobKernel',
                ],
            ],
            'scripts' => [
                'test' => 'phpunit',
                'job:work' => 'job-worker',
            ],
            'config' => [
                'sort-packages' => true,
            ],
        ];

        return json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }

    public function jobKernel(string $app): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App;

use Erebor\Mithril\Container;
use Erebor\Mithril\Contracts\JobApplication;
use Erebor\Mithril\Jobs\InMemoryJobTransport;
use Erebor\Mithril\Jobs\JobEnvelope;
use Erebor\Mithril\Jobs\JobResult;
use Erebor\Mithril\Jobs\JobTransport;

/**
 * Job application kernel for {$app}.
 * Bind a real JobTransport adapter for production brokers.
 */
final class JobKernel implements JobApplication
{
    private Container \$container;
    private bool \$booted = false;

    public function __construct()
    {
        \$this->container = new Container();
    }

    public function boot(): void
    {
        if (\$this->booted) {
            return;
        }

        // Demo transport — replace with Infrastructure adapter (Redis/SQS/…).
        \$this->container->singleton(JobTransport::class, new InMemoryJobTransport([]));

        \$this->booted = true;
    }

    public function handle(JobEnvelope \$job): JobResult
    {
        // Dispatch by \$job->name into src/Jobs handlers.
        return JobResult::ack();
    }

    public function getContainer(): Container
    {
        return \$this->container;
    }
}

PHP;
    }

    public function envExample(): string
    {
        return <<<'ENV'
APP_ENV=development
APP_DEBUG=true
APP_URL=http://127.0.0.1:8080
APP_PORT=8080

ENV;
    }

    public function envExampleWorker(): string
    {
        return <<<'ENV'
APP_ENV=development
APP_DEBUG=true
# Optional: MITHRIL_JOB_KERNEL=App\JobKernel

ENV;
    }

    /**
     * @param list<string> $structureLines
     */
    public function readmeWorker(string $app, array $structureLines): string
    {
        $structure = implode("\n", array_map(
            static fn (string $line): string => '- `' . $line . '`',
            $structureLines,
        ));

        return <<<MD
# {$app}

Created with `durin new` preset **worker** (job / non-HTTP).

## Next steps

```bash
composer install
cp .env.example .env
# bind a real JobTransport in JobKernel::boot()
php vendor/bin/job-worker
# or: composer job:work
```

This app does **not** use Eregion. See Mithril job-worker docs and Durin SPEC-DX-017.

Structure:

{$structure}

MD;
    }

    /**
     * @param list<string> $structureLines
     */
    public function readme(string $app, string $preset, array $structureLines): string
    {
        $structure = implode("\n", array_map(
            static fn (string $line): string => '- `' . $line . '`',
            $structureLines,
        ));

        return <<<MD
# {$app}

Created with `durin new` preset **{$preset}**.

## Next steps

```bash
composer install
cp .env.example .env
php bin/durin doctor
php bin/durin dev
```

Structure:

{$structure}

MD;
    }
}
