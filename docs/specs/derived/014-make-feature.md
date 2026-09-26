# SPEC-DX-014 — `make:feature`

**Status:** Implemented (pending merge)  
**Branch:** `feat/dx-014-make-feature`  
**Parent sections:** Master §28, §41-004; depends on SPEC-011–013

## Problem

Developers need a higher-level generator that composes use case (+ optional HTTP + tests) after primitives exist.

## Goal

Implement `durin make:feature` per master §28, composing generator primitives (usecase, optional route/handler, tests).

## Non-goals

- All remaining `make:command|query|repository|adapter|migration` commands.
- Preset changes.
- `--repository` / `--migration` flags (rejected with clear error until later slices).

## Current implementation

- `FeatureGenerator` merges `ModuleGenerator` + `UseCaseGenerator` (module layout) into one `ScaffoldPlan`, then optional Http controller + Feature test stubs.
- Single `GeneratorRunner` write; conflict-safe.
- Enables `architecture.modules` via `DurinManifestModulesEnabler` when `durin.yaml` exists.

## Proposed design

Feature generator orchestrates multiple ScaffoldPlan merges then single write. Flags `--http` / `--tests` as in master examples.

## Affected files

- Console Application, README

## New files

- `FeatureGenerator`, `MakeFeatureCommand`, unit/console tests

## Public API / CLI impact

```bash
durin make:feature Billing/CreateInvoice --http --tests
```

## Backward compatibility

Additive.

## Migration

N/A.

## Implementation phases

1. Plan composition.
2. CLI flags.
3. Integration test.
4. Docs.

## Tests

- Temp-root unit tests for composition, flags, conflicts.
- Console tests for happy path and deferred flags.

## Acceptance criteria

- [x] Composes primitives; no duplicate write stacks.
- [x] Conflict-safe.
- [x] Tests green.

## Risks

- Scope creep into full CRUD scaffolds — stick to §28.

## Open questions

None.

## Definition of Done

Feature command shipped with tests.
