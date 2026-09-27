<?php

declare(strict_types=1);

namespace App\Tooling\Generators;

use EreborCodeForge\Durin\Core\Scaffold\ScaffoldPlan;

/**
 * Higher-level generator: Module + UseCase (+ optional Http/tests) in one plan (master §28).
 *
 * Name form: Module/UseCaseName (e.g. Billing/CreateInvoice).
 * Options: http (bool), tests (bool).
 */
final class FeatureGenerator implements CodeGenerator
{
    public function __construct(
        private readonly NameInflector $names = new NameInflector(),
        private readonly StubTemplate $templates = new StubTemplate(),
        private readonly ModuleGenerator $modules = new ModuleGenerator(),
        private readonly UseCaseGenerator $useCases = new UseCaseGenerator(),
    ) {}

    public function plan(GeneratorRequest $request): ScaffoldPlan
    {
        $segments = $this->names->segments($request->name);
        if (count($segments) < 2) {
            throw new \InvalidArgumentException(
                'make:feature expects Module/Name (e.g. Billing/CreateInvoice).'
            );
        }

        $module = $segments[0];
        $useCaseName = implode('/', array_slice($segments, 1));
        $class = $this->names->className($useCaseName);
        $relative = $this->names->relativePath($useCaseName);
        $folder = $relative !== '' ? $relative . '/' . $class : $class;

        $plan = new ScaffoldPlan();
        $plan->merge($this->modules->plan(new GeneratorRequest($module, $request->projectRoot)));
        $plan->merge($this->useCases->plan(new GeneratorRequest(
            $useCaseName,
            $request->projectRoot,
            ['module' => $module],
        )));

        if ($request->option('http') === true) {
            $plan->merge($this->planHttp($module, $class, $folder));
        }

        if ($request->option('tests') === true) {
            $plan->merge($this->planTests($module, $class, $folder));
        }

        return $plan;
    }

    private function planHttp(string $module, string $class, string $folder): ScaffoldPlan
    {
        $dir = 'src/Modules/' . $module . '/Http';
        $useCaseNs = 'App\\Modules\\' . $module . '\\Application\\' . str_replace('/', '\\', $folder);
        $ns = 'App\\Modules\\' . $module . '\\Http';

        $contents = $this->templates->render(
            <<<'STUB'
<?php

declare(strict_types=1);

namespace {{namespace}};

use {{useCaseNamespace}}\{{class}};
use {{useCaseNamespace}}\{{class}}Input;

/**
 * HTTP entry for {{class}}. Wire this into routes separately.
 */
final class {{class}}Controller
{
    public function __construct(
        private {{class}} $useCase,
    ) {}

    public function __invoke(): mixed
    {
        // TODO: map request → {{class}}Input
        return $this->useCase->execute(new {{class}}Input());
    }
}

STUB,
            [
                'namespace' => $ns,
                'useCaseNamespace' => $useCaseNs,
                'class' => $class,
            ],
        );

        return (new ScaffoldPlan())
            ->directory($dir)
            ->file($dir . '/' . $class . 'Controller.php', $contents);
    }

    private function planTests(string $module, string $class, string $folder): ScaffoldPlan
    {
        $dir = 'tests/Feature/Modules/' . $module;
        $useCaseNs = 'App\\Modules\\' . $module . '\\Application\\' . str_replace('/', '\\', $folder);

        $contents = $this->templates->render(
            <<<'STUB'
<?php

declare(strict_types=1);

namespace App\Tests\Feature\Modules\{{module}};

use {{useCaseNamespace}}\{{class}};
use {{useCaseNamespace}}\{{class}}Input;
use PHPUnit\Framework\TestCase;

final class {{class}}Test extends TestCase
{
    public function test_execute_returns_null_until_implemented(): void
    {
        $useCase = new {{class}}();
        $this->assertNull($useCase->execute(new {{class}}Input()));
    }
}

STUB,
            [
                'module' => $module,
                'useCaseNamespace' => $useCaseNs,
                'class' => $class,
            ],
        );

        return (new ScaffoldPlan())
            ->directory('tests')
            ->directory('tests/Feature')
            ->directory('tests/Feature/Modules')
            ->directory($dir)
            ->file($dir . '/' . $class . 'Test.php', $contents);
    }
}
