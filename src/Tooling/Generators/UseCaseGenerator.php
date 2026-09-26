<?php

declare(strict_types=1);

namespace App\Tooling\Generators;

use App\Tooling\Scaffold\ScaffoldPlan;

/**
 * Plans DTO + use case artifacts (master §27).
 *
 * Default (no module): preserves legacy layout under src/Application
 * (DTOs + UseCases + Interface).
 *
 * With option module=Billing: module layout under
 * src/Modules/{Module}/Application/{Name}/ ({Name}.php + {Name}Input.php).
 */
final class UseCaseGenerator implements CodeGenerator
{
    public function __construct(
        private readonly NameInflector $names = new NameInflector(),
        private readonly StubTemplate $templates = new StubTemplate(),
    ) {}

    public function plan(GeneratorRequest $request): ScaffoldPlan
    {
        $module = $request->option('module');
        if (is_string($module) && $module !== '') {
            return $this->planModule($request, $this->names->studly($module));
        }

        return $this->planDefault($request);
    }

    private function planDefault(GeneratorRequest $request): ScaffoldPlan
    {
        $segments = $this->names->segments($request->name);
        if ($segments === []) {
            throw new \InvalidArgumentException(
                'make:usecase expects a name (e.g. User/CreateUser).'
            );
        }

        $class = $this->names->className($request->name);
        $relative = $this->names->relativePath($request->name);
        $nsSuffix = $this->names->namespaceSuffix($request->name);

        $dtoNs = 'App\\Application\\DTOs' . ($nsSuffix !== '' ? '\\' . $nsSuffix : '');
        $ucNs = 'App\\Application\\UseCases' . ($nsSuffix !== '' ? '\\' . $nsSuffix : '');

        $dtoDir = 'src/Application/DTOs' . ($relative !== '' ? '/' . $relative : '');
        $ucDir = 'src/Application/UseCases' . ($relative !== '' ? '/' . $relative : '');

        $dto = $this->templates->render(
            <<<'STUB'
<?php

declare(strict_types=1);

namespace {{namespace}};

readonly class {{class}}DTO
{
    public function __construct(
        // TODO: Add your properties here
    ) {}
}

STUB,
            ['namespace' => $dtoNs, 'class' => $class],
        );

        $interface = $this->templates->render(
            <<<'STUB'
<?php

declare(strict_types=1);

namespace {{namespace}};

use {{dtoNamespace}}\{{class}}DTO;

interface {{class}}UseCaseInterface
{
    public function execute({{class}}DTO $dto): mixed;
}

STUB,
            [
                'namespace' => $ucNs,
                'dtoNamespace' => $dtoNs,
                'class' => $class,
            ],
        );

        $useCase = $this->templates->render(
            <<<'STUB'
<?php

declare(strict_types=1);

namespace {{namespace}};

use {{dtoNamespace}}\{{class}}DTO;

final class {{class}}UseCase implements {{class}}UseCaseInterface
{
    public function __construct(
        // private SomeRepositoryInterface $repository
    ) {}

    public function execute({{class}}DTO $dto): mixed
    {
        // TODO: Implement business logic
        return null;
    }
}

STUB,
            [
                'namespace' => $ucNs,
                'dtoNamespace' => $dtoNs,
                'class' => $class,
            ],
        );

        $plan = new ScaffoldPlan();
        $plan->directory('src/Application');
        $plan->directory('src/Application/DTOs');
        $plan->directory('src/Application/UseCases');
        if ($relative !== '') {
            $plan->directory($dtoDir);
            $plan->directory($ucDir);
        }

        return $plan
            ->file($dtoDir . '/' . $class . 'DTO.php', $dto)
            ->file($ucDir . '/' . $class . 'UseCaseInterface.php', $interface)
            ->file($ucDir . '/' . $class . 'UseCase.php', $useCase);
    }

    private function planModule(GeneratorRequest $request, string $module): ScaffoldPlan
    {
        $segments = $this->names->segments($request->name);
        if ($segments === []) {
            throw new \InvalidArgumentException(
                'make:usecase expects a name (e.g. CreateInvoice).'
            );
        }

        $class = $this->names->className($request->name);
        $relative = $this->names->relativePath($request->name);
        $folder = $relative !== '' ? $relative . '/' . $class : $class;

        $base = 'src/Modules/' . $module . '/Application/' . $folder;
        $ns = 'App\\Modules\\' . $module . '\\Application\\' . str_replace('/', '\\', $folder);

        $input = $this->templates->render(
            <<<'STUB'
<?php

declare(strict_types=1);

namespace {{namespace}};

readonly class {{class}}Input
{
    public function __construct(
        // TODO: Add your properties here
    ) {}
}

STUB,
            ['namespace' => $ns, 'class' => $class],
        );

        $useCase = $this->templates->render(
            <<<'STUB'
<?php

declare(strict_types=1);

namespace {{namespace}};

final class {{class}}
{
    public function __construct(
        // private SomeRepositoryInterface $repository
    ) {}

    public function execute({{class}}Input $input): mixed
    {
        // TODO: Implement business logic
        return null;
    }
}

STUB,
            ['namespace' => $ns, 'class' => $class],
        );

        return (new ScaffoldPlan())
            ->directory('src/Modules')
            ->directory('src/Modules/' . $module)
            ->directory('src/Modules/' . $module . '/Application')
            ->directory($base)
            ->file($base . '/' . $class . 'Input.php', $input)
            ->file($base . '/' . $class . '.php', $useCase);
    }
}
