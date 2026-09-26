# SPEC-DX-009 — Preset `minimal`

**Status:** Ready for implementation  
**Branch:** `feat/dx-009-preset-minimal`  
**Parent sections:** Master §17, §40-003; depends on SPEC-008

## Problem

Small APIs need a preset that generates the minimum useful structure without Clean Architecture ceremony.

## Goal

Implement `minimal` preset producing a ScaffoldPlan matching master §17 (small `src/` surface, HTTP entry, tests stub, `durin.yaml` with `preset: minimal`).

## Non-goals

- Modules, messaging, microservice layout.
- `service` preset (010).

## Current implementation

- Preset engine (008).
- Existing skeleton is richer than minimal — preset targets **new** projects / temp dirs, not necessarily rewriting this repo.

## Proposed design

`MinimalPreset` lists files/dirs actions. Prefer generating into target directory for `durin new <name> --preset=minimal` if `new` is available; otherwise test via engine API writing to temp dir.

## Affected files

- Presets definitions, README presets section, maybe templates

## New files

- Minimal preset + templates + integration tests

## Public API / CLI impact

```bash
durin new <app> --preset=minimal
```

If `durin new` not yet public, expose via engine test + document follow-up — prefer shipping thin `durin new` here if missing, scoped to minimal only.

## Backward compatibility

Additive.

## Migration

N/A.

## Implementation phases

1. Plan contents per §17.
2. Writer integration.
3. Temp-dir integration test.
4. Docs.

## Tests

- Integration: scaffold to temp dir; assert key paths; no Domain/Entity empty tree.

## Acceptance criteria

- [ ] Minimal tree matches simplicity-first rules.
- [ ] Writes `durin.yaml` with preset minimal.
- [ ] Conflict-safe via ScaffoldWriter.
- [ ] Integration test green.

## Risks

- Diverging from existing repo skeleton — acceptable; preset is for new apps.

## Open questions

None.

## Definition of Done

Minimal preset shippable and tested.
