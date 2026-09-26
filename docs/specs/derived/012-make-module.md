# SPEC-DX-012 — `make:module`

**Status:** Ready for implementation  
**Branch:** `feat/dx-012-make-module`  
**Parent sections:** Master §26, §41-002; depends on SPEC-011

## Problem

Growing apps need a command to introduce a module boundary without hand-creating trees.

## Goal

Implement `durin make:module <Name>` generating the module skeleton defined in master §26 (only required dirs/files; no empty ceremony).

## Non-goals

- Full modular preset.
- `make:feature` (014).

## Current implementation

- Generator core.
- No `make:module`.

## Proposed design

Console command → ModuleGenerator → ScaffoldPlan → Writer. Update `durin.yaml` modules flag/list if present.

## Affected files

- Console Application, README

## New files

- Command, generator, integration tests (temp project)

## Public API / CLI impact

```bash
durin make:module Billing
```

## Backward compatibility

Additive.

## Migration

N/A.

## Implementation phases

1. Generator plan.
2. CLI.
3. Integration test.
4. Docs.

## Tests

- Integration in temp dir with minimal project fixture.

## Acceptance criteria

- [ ] Creates module structure per §26.
- [ ] Refuses overwrite by default.
- [ ] Tests green.

## Risks

- Defining module layout that conflicts with current Clean Architecture folders — follow master §26 over inventing new trees.

## Open questions

None.

## Definition of Done

Command shipped with tests and docs.
