# durin-core migration map

**Phase:** 1  
**Package:** `ereborcodeforge/durin-core` `^0.1` (`v0.1.0`)  
**Namespace:** `EreborCodeForge\Durin\Core\`

| current file | current namespace | target class | target namespace | consumers | migration strategy | status |
|---|---|---|---|---|---|---|
| `src/Tooling/Project/Project.php` | `App\Tooling\Project` | `Project` | `...\Core\Project` | Doctor, Graph, Runtime, tests | replace imports; delete Forge class | done |
| `src/Tooling/Project/ProjectDiscovery.php` | `App\Tooling\Project` | `ProjectDiscovery` | `...\Core\Project` | DoctorCommand, GraphDependencies, DevRuntimeSummary, tests | replace imports (call sites already pass cwd) | done |
| `src/Tooling/Project/ProjectPaths.php` | `App\Tooling\Project` | `ProjectPaths` | `...\Core\Project` | tests, Project | replace imports | done |
| `src/Tooling/Project/DurinManifest.php` | `App\Tooling\Project` | `DurinManifest` | `...\Core\Manifest` | Presets, Generators, Runtime, Doctor | replace imports | done |
| `src/Tooling/Project/DurinManifestParser.php` | `App\Tooling\Project` | `DurinManifestParser` | `...\Core\Manifest` | many | replace imports | done |
| `src/Tooling/Project/DurinManifestException.php` | `App\Tooling\Project` | `DurinManifestException` | `...\Core\Manifest` | many | replace imports | done |
| `src/Tooling/Project/DurinManifestModulesEnabler.php` | `App\Tooling\Project` | `DurinManifestModulesEnabler` | `...\Core\Manifest` | MakeModule/Feature | replace imports | done |
| `src/Tooling/Scaffold/ScaffoldPlan.php` | `App\Tooling\Scaffold` | `ScaffoldPlan` | `...\Core\Scaffold` | Presets, Generators | replace imports | done |
| `src/Tooling/Scaffold/ScaffoldAction.php` | `App\Tooling\Scaffold` | `ScaffoldAction` | `...\Core\Scaffold` | via plan | replace imports | done |
| `src/Tooling/Scaffold/ScaffoldActionType.php` | `App\Tooling\Scaffold` | `ScaffoldActionType` | `...\Core\Scaffold` | PresetEngineTest | replace imports | done |
| `src/Tooling/Scaffold/ScaffoldConflict.php` | `App\Tooling\Scaffold` | `ScaffoldConflict` | `...\Core\Scaffold` | NewCommand | replace imports | done |
| `src/Tooling/Scaffold/ScaffoldWriteResult.php` | `App\Tooling\Scaffold` | `ScaffoldWriteResult` | `...\Core\Scaffold` | GeneratorRunner | replace imports | done |
| `src/Tooling/Scaffold/ScaffoldWriter.php` | `App\Tooling\Scaffold` | `ScaffoldWriter` | `...\Core\Mutation` | NewCommand, Generators, tests | replace imports | done |
| `src/Tooling/Output/*` | `App\Tooling\Output` | `ConsoleOutput` et al. | `...\Core\Output` | ScaffoldWriter tests | replace imports | done |
| `src/Tooling/Presets/Preset.php` | `App\Tooling\Presets` | `Preset` | `...\Core\Contract` | preset classes | delete Forge interface; use contract | done |
| `src/Tooling/Presets/ProjectOptions.php` | `App\Tooling\Presets` | `ProjectOptions` | `...\Core\Contract` | NewCommand, presets | delete Forge VO; use contract | done |
| `src/Tooling/Presets/PresetRegistry.php` | `App\Tooling\Presets` | `PresetRegistry` (interface in core) | concrete stays Forge until Phase 2 | DefaultPresetRegistryFactory | implement `Core\Contract\PresetRegistry` | deferred (Phase 2) |
| `src/Tooling/Support/OperationResult.php` | `App\Tooling\Support` | `OperationResult` | `...\Core\Support` | unused in Forge | delete unused duplicate | done |

Concrete presets (`MinimalPreset`, `ServicePreset`, `WorkerPreset`, `PresetEngine`, …) remain in Forge until Phase 2.
