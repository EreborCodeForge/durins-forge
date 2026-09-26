# SPEC-DX-013 — Normalize `make:usecase`

**Status:** Implemented (pending merge)  
**Branch:** `feat/dx-013-make-usecase-normalization`  
**Parent sections:** Master §27, §41-003; depends on SPEC-011 (and 012 if module-aware)

## Problem

Existing `make:usecase` predates project/module model and generator core; it must align without surprising breakage.

## Goal

Refactor `make:usecase` to use generator core; support module-aware paths when modules exist; keep default paths sensible for current non-modular apps.

## Non-goals

- Removing the command.
- Implementing `make:feature`.

## Current implementation

- `MakeUseCaseCommand` → `UseCaseGenerator` → `GeneratorRunner` / `ScaffoldWriter`.
- **Default:** `src/Application/DTOs/{Domain}/{Name}DTO.php` + `UseCases/{Domain}/{Name}UseCase(.php|Interface.php)` (legacy-compatible).
- **Module:** `durin make:usecase CreateInvoice --module=Billing` → `src/Modules/Billing/Application/CreateInvoice/{CreateInvoice,CreateInvoiceInput}.php` (master §27 naming; no interface).

## Proposed design

- Route generation through Generator core.
- Detect `--module=` via ArgParser.
- Preserve default Application layout; document module layout as additive.

## Affected files

- Existing make:usecase command + tests
- README

## New files

- `UseCaseGenerator`, unit/console tests

## Public API / CLI impact

```bash
durin make:usecase User/CreateUser
durin make:usecase CreateInvoice --module=Billing
```

## Backward compatibility

Default paths unchanged vs pre-normalization command. Conflict handling now refuses overwrite (ScaffoldWriter) instead of silently skipping — intentional alignment with generator core.

## Migration

If scripts relied on silent skip of existing files, they must delete targets first or handle exit code 1 conflicts.

## Implementation phases

1. Wrap existing behavior in generator plan.
2. Add module-aware mode.
3. Update tests.

## Tests

- Unit covering default + module golden paths + conflict.
- Console command tests for both modes.

## Acceptance criteria

- [x] Uses generator core.
- [x] Existing happy path still works or documented migration applied.
- [x] Tests updated and green.

## Risks

- Silent path changes breaking user scripts — require explicit changelog note.

## Open questions

None — module convention settled as master §27 under `src/Modules/{Module}/Application/{Name}/`.

## Definition of Done

Normalized command merged with tests.
