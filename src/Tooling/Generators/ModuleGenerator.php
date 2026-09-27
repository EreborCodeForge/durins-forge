<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Generators;

use EreborCodeForge\Durin\Core\Scaffold\ScaffoldPlan;

/**
 * Creates src/Modules/{Name} with a minimal marker (master §26).
 * Does not create full Domain/Application/Http trees inside the module.
 * durin.yaml modules flag is updated by DurinManifestModulesEnabler after a successful write
 * (ScaffoldWriter refuses overwrites by default).
 */
final class ModuleGenerator implements CodeGenerator
{
    public function __construct(
        private readonly NameInflector $names = new NameInflector(),
        private readonly StubTemplate $templates = new StubTemplate(),
    ) {}

    public function plan(GeneratorRequest $request): ScaffoldPlan
    {
        if (count($this->names->segments($request->name)) !== 1) {
            throw new \InvalidArgumentException(
                'make:module expects a single module name (e.g. Billing), not a nested path.'
            );
        }

        $module = $this->names->className($request->name);
        $base = 'src/Modules/' . $module;
        $marker = $this->templates->render(
            <<<'STUB'
<?php

declare(strict_types=1);

/**
 * Module marker for {{module}}.
 * Keep this file small — layer folders are created by later generators as needed.
 */
return [
    'name' => '{{module}}',
];

STUB,
            ['module' => $module],
        );

        return (new ScaffoldPlan())
            ->directory('src/Modules')
            ->directory($base)
            ->file($base . '/module.php', $marker);
    }
}
