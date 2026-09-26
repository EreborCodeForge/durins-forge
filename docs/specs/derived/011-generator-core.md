# SPEC-DX-011 — Generator core

**Status:** Ready for implementation  
**Branch:** `feat/dx-011-generator-core`  
**Parent sections:** Master §41-001, §43; depends on SPEC-002

## Problem

`make:*` commands and presets need a reusable generator layer (templates, path resolution, conflict policy) separate from individual command UX.

## Goal

Deliver generator core on top of ScaffoldPlan/Writer:

- Template rendering seam (simple PHP/stub templates acceptable)
- Name/path helpers (Studly, path segments)
- Shared options (`--force` deferred to Phase 7 unless trivial; default refuse overwrite)
- API used by module/usecase/feature commands

## Non-goals

- Implementing all `make:*` commands here.
- AST refactor tools.

## Current implementation

- `make:usecase` exists with its own generation logic.
- ScaffoldWriter from 002.

## Proposed design

```text
Generator → builds ScaffoldPlan → ScaffoldWriter
```

Migrate shared bits from MakeUsecase only as needed without breaking behavior (full alignment is SPEC-013).

## Affected files

- Possibly extract helpers from existing make command

## New files

- `Tooling/Generators/*` + unit tests

## Public API / CLI impact

None required.

## Backward compatibility

Do not break `make:usecase` in this slice.

## Migration

Internal only.

## Implementation phases

1. Core API + templates.
2. Conflict tests.
3. Optional thin adapter used by one existing path without behavior change.

## Tests

- Unit: path helpers, conflict detection, idempotent re-run when files identical (if supported).

## Acceptance criteria

- [ ] Generators produce plans, not ad-hoc writes.
- [ ] Default no overwrite.
- [ ] Unit tests pass.

## Risks

- Big-bang rewrite of make:usecase — avoid; wait for 013.

## Open questions

None.

## Definition of Done

Generator core ready for 012–014.
