# SPEC-DX-005 — `durin serve`

**Status:** Implemented (pending merge)  
**Branch:** `feat/dx-005-durin-serve`  
**Parent sections:** Master §10, §39-002; depends on SPEC-004

## Problem

`durin serve` exists as a thin alias; it must go through the shared runtime facade with clear production-oriented semantics distinct from `dev`.

## Goal

Align `durin serve` with facade:

- Production-leaning defaults documented.
- Delegates to Mithril/Eregion (no forked server).
- Distinct from `durin dev` (implemented in 006).

## Non-goals

- Implementing `durin dev`.
- Changing Eregion config schema.

## Current implementation

- `src/Console/Commands/ServeCommand.php` (or equivalent) calls `forge serve`.

## Proposed design

Wire ServeCommand → RuntimeFacade::serve. Preserve flags that already pass through to forge where possible. Document semantics in README.

## Affected files

- Serve command, README runtime section

## New files

- Integration test harness if needed

## Public API / CLI impact

```bash
durin serve
```

Behavior remains “start production path via Mithril/Eregion”.

## Backward compatibility

Keep as default production entry; avoid breaking existing scripts.

## Migration

Document any flag renames; prefer none.

## Implementation phases

1. Wire facade.
2. Docs.
3. Integration test (delegate/smoke without full HTTP if possible).

## Tests

- Integration: command resolves and invokes facade (mock adapter acceptable).

## Acceptance criteria

- [x] Uses RuntimeFacade.
- [x] Does not duplicate forge serve logic.
- [x] README states production semantics.

## Risks

- Process `exec` replacement behavior — preserve forge’s exec semantics.

## Open questions

None.

## Definition of Done

Serve via facade, tested, documented.
