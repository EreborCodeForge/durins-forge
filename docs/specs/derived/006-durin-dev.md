# SPEC-DX-006 — `durin dev`

**Status:** Implemented (pending merge)  
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

### Decision (open question resolved)

Default runtime for `durin dev` is **Eregion via `forge serve`** with local bind defaults (`127.0.0.1:8080`), because this application's `public/index.php` is an UDS worker and `forge serve:php` / `php -S` typically return 503.

Escape hatch: `--php` → `forge serve:php` (documented as limited).

Same `RuntimeFacade` / process runner as `serve` — no second startup stack.

## Affected files

- Console registration, README

## New files

- `DevCommand`, facade dev path, tests

## Public API / CLI impact

```bash
durin dev
durin dev --php
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

- [x] Command exists and uses shared facade.
- [x] Semantics documented as distinct from `serve`.
- [x] Tests cover invocation path.

## Risks

- Ambiguity whether default dev uses Eregion or PHP built-in — **resolved**: Eregion default; `--php` escape.

## Open questions

- ~~Default runtime for dev~~ → Eregion; `--php` for serve:php.

## Definition of Done

`durin dev` usable, tested, documented.
