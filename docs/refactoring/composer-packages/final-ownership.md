# Final ownership — Composer packages integration

**Date:** 2026-09-27  
**Status:** post-integration (Phases 0–3 complete in stacked PRs)

## Dependency model

```text
durin-forge
    │
    ├── ereborcodeforge/durin-core ^0.1
    │
    ├── ereborcodeforge/durin-presets ^0.1
    │      └── durin-core
    │
    └── ereborcodeforge/durin-architecture ^0.1
           ├── durin-core
           └── durin-presets
```

No circular package dependencies. Forge does not re-implement package concerns.

## Ownership matrix

| Concern | Owner |
|---|---|
| project model | `durin-core` |
| project discovery | `durin-core` |
| `durin.yaml` | `durin-core` |
| scaffold model | `durin-core` |
| generic safe mutation | `durin-core` |
| preset contract / `ProjectOptions` | `durin-core` |
| concrete presets | `durin-presets` |
| preset registry | `durin-presets` |
| type → preset resolution | `durin-presets` (V1: explicit `--preset` only) |
| preset resources/templates | `durin-presets` (vendor install) |
| architecture detection / drift / adopt / evolve / migrate planning | `durin-architecture` (contracts; Forge CLI not wired yet) |
| CLI commands | `durin-forge` |
| terminal rendering | `durin-forge` / Mithril console |
| framework bootstrap | `durin-forge` |
| doctor / status / dev / serve / optimize / graph | `durin-forge` |
| generators (`make:*`) | `durin-forge` (uses core scaffold writer) |
| Mithril integration | Forge / Mithril boundary |
| Eregion integration | Forge / Eregion boundary |

## Removed from Forge

- `src/Tooling/Project/`
- `src/Tooling/Scaffold/`
- `src/Tooling/Output/`
- `src/Tooling/Presets/`
- unused `src/Tooling/Support/OperationResult.php`

## Remaining in Forge (`src/Tooling/`)

- `Doctor/`
- `Generators/`
- `Graph/`
- `Runtime/`

## Regression evidence

- Baseline: `docs/refactoring/composer-packages/baseline.md` + `tests/Fixtures/generated-{minimal,service,worker}/`
- After presets: trees and normalized `durin.yaml` matched fixtures
- PHPUnit: 111 tests green (includes architecture vendor smoke)

## Explicitly out of scope (still)

- `durin-app` / `composer create-project`
- `durin-installer`
- New `adopt` / `evolve` / architecture-migrate CLI surface
- Jev / LLM
