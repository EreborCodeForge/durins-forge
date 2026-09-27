# ADR-0004 — Tooling package boundaries

**Status:** Accepted (amended)  
**Date:** 2026-09-25  
**Amended:** 2026-09-27  
**Parent:** [Master DX Spec](../specs/master-dx-tooling-spec.md) §29, §32–33  
**Related:** [Composer packages integration spec](../durin-forge-composer-packages-integration-spec.md), [final ownership](../refactoring/composer-packages/final-ownership.md)

## Context

The master DX program initially kept tooling in-tree to avoid premature extraction. The project/manifest/scaffold/preset responsibilities were later extracted into Composer packages and Durin Forge now consumes them as the source of truth.

Earlier candidate names (`durin-project`, `durin-inspector`) were superseded by the published packages:

- `ereborcodeforge/durin-core`
- `ereborcodeforge/durin-presets`
- `ereborcodeforge/durin-architecture`

## Decision

1. **Authoritative packages:** Forge must depend on the three packages above for their respective concerns (see ownership matrix in `docs/refactoring/composer-packages/final-ownership.md`). Duplicate in-tree implementations of those concerns are forbidden.
2. **Forge retains:** CLI orchestration, terminal UX, framework bootstrap, Doctor/Status/Dev/Serve/Optimize, generators, dependency graph, Mithril/Eregion runtime integration.
3. **Dependency direction:** CLI → Forge tooling adapters → package libraries → (runtime) Mithril/Eregion; packages must not depend on Forge.
4. **Further extraction** (e.g. `durin-app`, installer, architecture CLI wiring) requires a separate ADR/spec — do not mix into package-consumption work.

## Consequences

- Composer `repositories` VCS entries (until Packagist publishing) and `^0.1` constraints are the integration path.
- Derived DX specs that assumed in-tree Project/Presets should treat package classes as the implementation.
- Architecture planning APIs exist in `durin-architecture`; public Forge commands for adopt/evolve remain future work.

## Non-goals

- Redesigning package APIs during consumption.
- Creating `durin-app` / `durin-installer` in this ADR.
- A custom package manager.
