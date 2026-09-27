<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Generators;

use EreborCodeForge\Durin\Core\Scaffold\ScaffoldPlan;

/**
 * Tiny generator used to prove the core API (not a public make:* command).
 */
final class PlaceholderClassGenerator implements CodeGenerator
{
    public function __construct(
        private readonly NameInflector $names = new NameInflector(),
        private readonly StubTemplate $templates = new StubTemplate(),
    ) {}

    public function plan(GeneratorRequest $request): ScaffoldPlan
    {
        $class = $this->names->className($request->name);
        $relativeDir = $this->names->relativePath($request->name);
        $namespaceSuffix = $this->names->namespaceSuffix($request->name);

        $dir = 'src/Generated' . ($relativeDir !== '' ? '/' . $relativeDir : '');
        $namespace = 'App\\Generated' . ($namespaceSuffix !== '' ? '\\' . $namespaceSuffix : '');
        $path = $dir . '/' . $class . '.php';

        $contents = $this->templates->render(
            <<<'STUB'
<?php

declare(strict_types=1);

namespace {{namespace}};

final class {{class}}
{
}

STUB,
            [
                'namespace' => $namespace,
                'class' => $class,
            ],
        );

        return (new ScaffoldPlan())
            ->directory($dir)
            ->file($path, $contents);
    }
}
