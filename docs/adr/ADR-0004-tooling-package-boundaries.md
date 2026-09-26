# ADR-0004 — Tooling package boundaries

**Status:** Accepted  
**Date:** 2026-09-25  
**Parent:** [Master DX Spec](../specs/master-dx-tooling-spec.md) §29, §32–33

## Context

The master spec describes possible future packages (`ereborcodeforge/durin-project`, `durin-inspector`, `durin-presets`). Premature extraction increases versioning and DX friction before APIs stabilize.

## Decision

For the current program (Phases 0–7 V1):

1. Implement tooling inside the Durin's Forge application tree (e.g. `src/Tooling/...` or equivalent existing console/core seams).
2. Do **not** extract separate Composer packages until all of the following hold:
   - stable public contracts used by more than one consumer;
   - independent release cadence justified;
   - ADR update naming the package and ownership.
3. Dependency direction remains: CLI → Tooling use-cases → Project/Runtime adapters → Mithril/Eregion; never the reverse.

## Consequences

- Derived specs target in-tree modules first.
- Extraction criteria from master §32 become a future ADR amendment, not silent refactor.
- Avoid publishing empty package stubs.

## Non-goals

- Monorepo package split in Phase 0–2.
- A custom package manager.
