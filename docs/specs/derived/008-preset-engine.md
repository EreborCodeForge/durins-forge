# SPEC-DX-008 — Preset engine

**Status:** Implemented (pending merge)  
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
- Public `durin new` CLI in this slice (engine-only).

## Current implementation

- Foundation ScaffoldPlan/Writer (002).
- No presets.

## Proposed design

```text
PresetRegistry → Preset::scaffold(options) → ScaffoldPlan → (Writer in callers)
```

Validate preset name; refuse unknown presets with clear error. Engine appends `durin.yaml` via `ManifestPlanFactory` when the preset did not already plan it.

## Affected files

- Tooling/Presets

## New files

- Engine, registry, tests

## Public API / CLI impact

Internal only until 009/010.

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

- [x] Engine selects preset by name.
- [x] Produces ScaffoldPlan only (no uncontrolled writes inside preset).
- [x] Tests pass.

## Risks

- Premature `durin new` scope creep — kept engine-focused.

## Open questions

None.

## Definition of Done

Engine ready for 009/010.
