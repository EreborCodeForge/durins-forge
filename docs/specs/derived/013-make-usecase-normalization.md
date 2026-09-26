# SPEC-DX-013 — Normalize `make:usecase`

**Status:** Ready for implementation  
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

- `MakeUsecaseCommand` (or equivalent) generates DTO + interface + use case under current layout.

## Proposed design

- Route generation through Generator core.
- Detect module argument/option if present (`Billing/CreateInvoice` or `--module=`).
- Preserve output for current invocations as much as practical; document intentional path changes.

## Affected files

- Existing make:usecase command + tests
- README

## New files

- Possibly usecase templates under Tooling/Generators

## Public API / CLI impact

Same command name; options may grow (`--module`).

## Backward compatibility

Prefer preserving default paths for existing apps. If change required, document migration in SPEC commit.

## Migration

Note in README if paths change.

## Implementation phases

1. Wrap existing behavior in generator plan.
2. Add module-aware mode.
3. Update tests.

## Tests

- Unit/feature covering current golden paths.
- Module path case.

## Acceptance criteria

- [ ] Uses generator core.
- [ ] Existing happy path still works or documented migration applied.
- [ ] Tests updated and green.

## Risks

- Silent path changes breaking user scripts — require explicit changelog note.

## Open questions

- Exact module path convention — follow master §27.

## Definition of Done

Normalized command merged with tests.
