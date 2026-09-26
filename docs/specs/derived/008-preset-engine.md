# SPEC-DX-008 — Preset engine

**Status:** Ready for implementation  
**Branch:** `feat/dx-008-preset-engine`  
**Parent sections:** Master §22, §40-001/002; [ADR-0003](../../adr/ADR-0003-architecture-presets.md); depends on SPEC-002

## Problem

Presets need a registry + planning engine before concrete `minimal`/`service` trees are written.

## Goal

Deliver preset engine:

- `Preset` interface → `ScaffoldPlan`
- Registry by name
- Wiring to `durin.yaml` model
- Entry points usable by future `durin new` and preset SPECs

`durin new` may be stubbed or minimal if needed to exercise engine; full UX can land with preset SPECs 009/010.

## Non-goals

- Concrete file trees for all presets.
- `durin add` capabilities marketplace.

## Current implementation

- Foundation ScaffoldPlan/Writer (002).
- No presets.

## Proposed design

```text
PresetRegistry → Preset::scaffold(options) → ScaffoldPlan → (Writer in callers)
```

Validate preset name; refuse unknown presets with clear error.

## Affected files

- Tooling/Presets, possibly Console stub

## New files

- Engine, registry, tests

## Public API / CLI impact

Possibly internal only until 009; if CLI needed for test, keep hidden or `--preset` on experimental `new`.

## Backward compatibility

N/A.

## Migration

N/A.

## Implementation phases

1. Interface + registry.
2. Manifest update helpers.
3. Unit tests with fake preset.

## Tests

- Unit: registry resolve; unknown preset fails.
- Unit: fake preset returns plan actions.

## Acceptance criteria

- [ ] Engine selects preset by name.
- [ ] Produces ScaffoldPlan only (no uncontrolled writes inside preset).
- [ ] Tests pass.

## Risks

- Premature `durin new` scope creep — keep engine-focused.

## Open questions

None.

## Definition of Done

Engine ready for 009/010.
