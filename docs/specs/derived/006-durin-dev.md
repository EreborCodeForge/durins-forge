# SPEC-DX-006 — `durin dev`

**Status:** Ready for implementation  
**Branch:** `feat/dx-006-durin-dev`  
**Parent sections:** Master §9, §39-003; depends on SPEC-004

## Problem

Developers need a first-class local command with readable defaults, distinct from production `serve`.

## Goal

Implement `durin dev`:

- Validate environment lightly (may call doctor subset or shared checks).
- Start selected local runtime via facade (prefer Mithril dev path / `serve:php` or documented local strategy).
- Print runtime summary (app, env, PHP, container/routes mode when known).
- Developer-friendly logs.

## Non-goals

- Full hot-reload if Mithril/Eregion lack it — document limitation.
- Production hardening.

## Current implementation

- README documents `forge serve:php` for dev without Eregion.
- No `durin dev`.

## Proposed design

`DevCommand` → `RuntimeFacade::dev` with development defaults (debug-friendly, fewer workers assumptions). Reuse output abstraction from foundation.

## Affected files

- Console registration, README

## New files

- `DevCommand`, facade dev path, tests

## Public API / CLI impact

```bash
durin dev
```

## Backward compatibility

Additive.

## Migration

Encourage `durin dev` in docs; keep `forge serve:php` as lower-level escape hatch.

## Implementation phases

1. Facade `dev` implementation.
2. CLI + summary output.
3. Tests + docs.

## Tests

- Integration with fake facade.
- Optional smoke if local PHP server available in CI.

## Acceptance criteria

- [ ] Command exists and uses shared facade.
- [ ] Semantics documented as distinct from `serve`.
- [ ] Tests cover invocation path.

## Risks

- Ambiguity whether default dev uses Eregion or PHP built-in — choose one default, document the other flag/escape.

## Open questions

- Default runtime for dev: prefer Mithril `serve:php` unless project already has Eregion installed and `--eregion` passed — decide in implementation and record in SPEC update if needed.

## Definition of Done

`durin dev` usable, tested, documented.
