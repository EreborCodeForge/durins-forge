# Derived specs index

Parent: [master-dx-tooling-spec.md](../master-dx-tooling-spec.md) §47–48.

Implementation order (one branch / merge each):

| SPEC | Title | Branch |
|------|-------|--------|
| [001](001-version-normalization.md) | Version normalization | `feat/dx-001-version-normalization` |
| [002](002-tooling-foundation.md) | Tooling foundation | `feat/dx-002-tooling-foundation` |
| [003](003-doctor-core.md) | Doctor core | `feat/dx-003-doctor-core` |
| [004](004-runtime-facade.md) | Runtime facade | `feat/dx-004-runtime-facade` |
| [005](005-durin-serve.md) | `durin serve` | `feat/dx-005-durin-serve` |
| [006](006-durin-dev.md) | `durin dev` | `feat/dx-006-durin-dev` |
| [007](007-durin-status.md) | `durin status` | `feat/dx-007-durin-status` |
| [008](008-preset-engine.md) | Preset engine | `feat/dx-008-preset-engine` |
| [009](009-preset-minimal.md) | Preset `minimal` | `feat/dx-009-preset-minimal` |
| [010](010-preset-service.md) | Preset `service` | `feat/dx-010-preset-service` |
| [011](011-generator-core.md) | Generator core | `feat/dx-011-generator-core` |
| [012](012-make-module.md) | `make:module` | `feat/dx-012-make-module` |
| [013](013-make-usecase-normalization.md) | Normalize `make:usecase` | `feat/dx-013-make-usecase-normalization` |
| [014](014-make-feature.md) | `make:feature` | `feat/dx-014-make-feature` |
| [015](015-dependency-graph-core.md) | Dependency graph core | `feat/dx-015-dependency-graph-core` |
| [016](016-dependency-graph-renderers.md) | Graph renderers | `feat/dx-016-dependency-graph-renderers` |
| [017](017-worker-runtime-contract.md) | Worker runtime contract (job / non-HTTP) | `feat/dx-017-worker-runtime-contract` (docs) |

Protocol: implement on branch → commit → push → wait for merge to `main` and maintainer signal → pull `main` → next branch.

Post-V1: SPEC-017 is the prerequisite contract for preset `worker` (master §21). It does **not** require Eregion changes for the first job-worker cut.

Mithril library work: [../mithril/SPEC-MITHRIL-001-job-worker-runtime.md](../mithril/SPEC-MITHRIL-001-job-worker-runtime.md) (implement in `mithrilphp`, not Durin).
