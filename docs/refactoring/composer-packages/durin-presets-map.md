# durin-presets migration map

**Phase:** 2  
**Package:** `ereborcodeforge/durin-presets` `^0.1` (`v0.1.0`)  
**Namespace:** `EreborCodeForge\Durin\Presets\`

| preset id | current implementation | target package class | templates/resources | consumers | public behavior | migration status |
|---|---|---|---|---|---|---|
| `minimal` | `App\Tooling\Presets\MinimalPreset` | `...\Presets\Preset\MinimalPreset` | in-class via `PresetScaffoldSupport` | NewCommand, registry | unchanged | done |
| `service` | `App\Tooling\Presets\ServicePreset` | `...\Presets\Preset\ServicePreset` | same | NewCommand, registry | unchanged | done |
| `worker` | `App\Tooling\Presets\WorkerPreset` | `...\Presets\Preset\WorkerPreset` | same | NewCommand, registry | unchanged | done |
| registry | `App\Tooling\Presets\PresetRegistry` | `...\Presets\Registry\PresetRegistry` | n/a | PresetEngine | single authoritative registry | done |
| factory | `DefaultPresetRegistryFactory` | `...\Registry\DefaultPresetRegistryFactory` | n/a | NewCommand | registers minimal/service/worker | done |
| engine | `PresetEngine` | `...\Registry\PresetEngine` | n/a | NewCommand | plan-only | done |
| support | `PresetScaffoldSupport` | `...\Preset\PresetScaffoldSupport` | embedded PHP strings | presets | vendor path only | done |
| manifest helper | `ManifestPlanFactory` | `...\Preset\ManifestPlanFactory` | n/a | engine | unchanged | done |
| exception | `UnknownPresetException` | `...\Preset\UnknownPresetException` | n/a | registry | unchanged | done |

Type → preset resolution maps beyond explicit `--preset` are **not** present in V1; no second map left in Forge.

Modular / microservice presets: not implemented (no migration).
