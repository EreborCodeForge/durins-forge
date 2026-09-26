# SPEC-DX-010 — Preset `service`

**Status:** Ready for implementation  
**Branch:** `feat/dx-010-preset-service`  
**Parent sections:** Master §18, §40-004; depends on SPEC-008/009 patterns

## Problem

Internal backend services need slightly more structure than `minimal` without full modular monolith scaffolding.

## Goal

Implement `service` preset per master §18: application layering suited to a single deployable service, `durin.yaml` `preset: service`, still avoiding empty ceremony folders.

## Non-goals

- `modular` / `microservice` / `worker`.
- Changing `minimal`.

## Current implementation

- Engine + minimal preset patterns.

## Proposed design

`ServicePreset` builds ScaffoldPlan. Reuse templates/helpers from minimal where shared (composer baseline, PHP 8.5, Mithril pins).

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

- [ ] Service preset registered and selectable.
- [ ] Distinct from minimal in documented ways.
- [ ] Tests green.

## Risks

- Overbuilding toward modular — reject extra dirs not in §18.

## Open questions

None.

## Definition of Done

Service preset merged with tests.
