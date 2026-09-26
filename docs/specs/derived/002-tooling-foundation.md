# SPEC-DX-002 — Tooling foundation

**Status:** Ready for implementation  
**Branch:** `feat/dx-002-tooling-foundation`  
**Parent sections:** Master §29–31, §43, Phase 1; [ADR-0004](../../adr/ADR-0004-tooling-package-boundaries.md)

## Problem

Doctor, presets, and generators need shared models (results, project discovery, scaffold plan/writer, console output). Building features without this foundation duplicates I/O and conflict logic.

## Goal

Deliver in-tree tooling primitives:

- Tooling result / diagnostic models
- Console output abstraction
- Project discovery
- `durin.yaml` model (parse + validate minimal schema)
- `ScaffoldPlan` + `ScaffoldWriter` (plan then write; conflict-safe)

No large user-facing feature set yet.

## Non-goals

- `durin new`, doctor CLI, presets content, graph CLI.
- Extracting Composer packages.
- Full YAML feature parity with `.env` / `eregion.yaml`.

## Current implementation

- CLI: `src/Console/Application.php` + commands.
- Optimize/discovery in `src/Core/`.
- No `durin.yaml`, no `ScaffoldPlan`.

## Proposed design

Namespace under application code (e.g. `App\Tooling\...` or `DurinsForge\Tooling\...` matching existing PSR-4):

```text
Tooling/Project   — Project, paths, DurinManifest
Tooling/Scaffold  — ScaffoldPlan, ScaffoldAction, ScaffoldWriter
Tooling/Output    — ConsoleWriter / formatter seam
Tooling/Support   — Result value objects as needed
```

Writer rules (§43): refuse overwrite by default; deterministic paths; create parents; report conflicts; idempotent where reasonable.

## Affected files

- `composer.json` autoload if new namespace roots needed
- Possibly thin wiring from Console later (not required to register new commands in this SPEC)

## New files

- Tooling project/scaffold/output classes + unit tests under `tests/`

## Public API / CLI impact

None required (library layer). Optional: no new commands.

## Backward compatibility

No change to existing commands.

## Migration

N/A.

## Implementation phases

1. Manifest model + parser for minimal `durin.yaml`.
2. Project discovery from application root.
3. ScaffoldPlan + Writer with conflict detection.
4. Output abstraction used by tests (and ready for doctor).

## Tests

- Unit: parse valid/invalid manifest.
- Unit: plan composition.
- Unit: writer creates files; refuses overwrite; reports conflict.

## Acceptance criteria

- [ ] Manifest round-trip for minimal schema (application name/preset, runtime engine/server flags as in master §22).
- [ ] Writer never overwrites without explicit force API (force may be stubbed/unimplemented until Phase 7).
- [ ] Unit tests pass via `composer test`.

## Risks

- Over-abstracting output — keep the seam small.

## Open questions

- Exact PSR-4 root (`App\Tooling` vs existing layout) — choose to match current `App\` namespace.

## Definition of Done

Foundation merged with tests; no user CLI obligation beyond what’s needed to compile.
