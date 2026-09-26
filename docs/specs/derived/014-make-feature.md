# SPEC-DX-014 — `make:feature`

**Status:** Ready for implementation  
**Branch:** `feat/dx-014-make-feature`  
**Parent sections:** Master §28, §41-004; depends on SPEC-011–013

## Problem

Developers need a higher-level generator that composes use case (+ optional HTTP + tests) after primitives exist.

## Goal

Implement `durin make:feature` per master §28, composing generator primitives (usecase, optional route/handler, tests).

## Non-goals

- All remaining `make:command|query|repository|adapter|migration` commands.
- Preset changes.

## Current implementation

- Module + usecase generators.

## Proposed design

Feature generator orchestrates multiple ScaffoldPlan merges then single write. Flags like `--http` / `--tests` as in master examples.

## Affected files

- Console, README

## New files

- Feature generator + integration tests

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
4. Docs (journey §45).

## Tests

- Integration temp project with module fixture.

## Acceptance criteria

- [ ] Composes primitives; no duplicate write stacks.
- [ ] Conflict-safe.
- [ ] Tests green.

## Risks

- Scope creep into full CRUD scaffolds — stick to §28.

## Open questions

None.

## Definition of Done

Feature command shipped with tests.
