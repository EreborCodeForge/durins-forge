# SPEC-DX-010 — Preset `service`

**Status:** Implemented (pending merge)  
**Branch:** `feat/dx-010-preset-service`  
**Parent sections:** Master §18, §40-004; depends on SPEC-008/009 patterns

## Problem

Internal backend services need slightly more structure than `minimal` without full modular monolith scaffolding.

## Goal

Implement `service` preset per master §18: application layering suited to a single deployable service, `durin.yaml` `preset: service`, still avoiding empty ceremony folders.

## Non-goals

- `modular` / `microservice` / `worker`.
- Changing `minimal` behavior (shared helpers extracted only).

## Current implementation

- Engine + minimal preset patterns.

## Proposed design

`ServicePreset` builds ScaffoldPlan with layer roots. Shared file templates live in `PresetScaffoldSupport`.

## Affected files

- Presets, docs

## New files

- Service preset + tests

## Public API / CLI impact

```bash
durin new <app> --preset=service
```

## Backward compatibility

Additive.

## Migration

N/A.

## Implementation phases

1. Plan contents §18.
2. Integration test temp dir.
3. Docs compare minimal vs service.

## Tests

- Integration scaffold assertions for service-specific paths only.

## Acceptance criteria

- [x] Service preset registered and selectable.
- [x] Distinct from minimal in documented ways.
- [x] Tests green.

## Risks

- Overbuilding toward modular — rejected Entity/Repository empty trees.

## Open questions

None.

## Definition of Done

Service preset merged with tests.
