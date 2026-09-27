# durin-architecture migration map

**Phase:** 3  
**Package:** `ereborcodeforge/durin-architecture` `^0.1` (`v0.1.0`)  
**Namespace:** `EreborCodeForge\Durin\Architecture\`

## Forge inventory

| Concern | Forge code before Phase 3 | Action |
|---------|---------------------------|--------|
| ArchitectureDetector | none | install package contracts only |
| DriftDetector / DriftReport | none | install package contracts only |
| AdoptionPlanner | none | install package contracts only |
| EvolutionPlanner | none | install package contracts only |
| MigrationPlanner (architecture) | none (`migrate` CLI is DB) | install package contracts only |
| ArchitectureState / Target | none | consume DTOs from package |
| CLI adopt / evolve / architecture migrate | not implemented | **do not invent** in this phase |

## Package surface (authoritative)

| Package type | Kind | Notes |
|---|---|---|
| `Detection\ArchitectureDetector` | interface | no Forge implementation yet |
| `Drift\DriftDetector` | interface | no Forge implementation yet |
| `Adoption\AdoptionPlanner` | interface | no Forge implementation yet |
| `Evolution\EvolutionPlanner` | interface | no Forge implementation yet |
| `Migration\MigrationPlanner` | interface | architecture planning only |
| Model / Drift / Migration DTOs | concrete | available for future CLI |

## Migration status

- Composer dependency installed; core + presets resolve without duplicate versions.
- No internal Forge architecture code to delete.
- No new public CLI commands added (non-goal / no prior surface).
- Smoke test proves package autoload + preset dependency from vendor.
