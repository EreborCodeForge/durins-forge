# SPEC-DX-003 — Doctor core

**Status:** Ready for implementation  
**Branch:** `feat/dx-003-doctor-core`  
**Parent sections:** Master §12, §37, Phase 2; [ADR-0002](../../adr/ADR-0002-cli-runtime-boundaries.md)

## Problem

Operators lack a single Durin command explaining PHP, project, Mithril/Eregion, and artifact health. Closest today is `forge server:check`, which is not Durin project-aware DX.

## Goal

Ship useful V1 `durin doctor`:

- PHP version / required extensions
- Project basics (composer, kernel paths, `durin.yaml` if present)
- Mithril / Eregion compatibility signals (delegate, don’t reimplement protocol)
- Compiled artifacts presence/sanity when optimize expected
- Human output + JSON mode + exit codes per master §12

## Non-goals

- Full architecture boundary analysis (§44).
- Resources/soak checks beyond cheap local signals.
- Blocking on advanced graph rules.

## Current implementation

- Mithril: `server:check`, `server:version`.
- Durin: optimize/compile commands; no `doctor`.

## Proposed design

```text
Check interface → CheckResult → DoctorReport → Renderer (text|json)
```

Register `durin doctor` in Console Application. Reuse foundation Context/Project from SPEC-002. Compose Mithril checks where appropriate.

Exit codes: success / warnings / failures as specified in master §12 (implement the documented mapping).

## Affected files

- `src/Console/Application.php`
- New `DoctorCommand`
- README command list when user-visible

## New files

- `Tooling/Doctor/*` checks, report, renderers
- Tests

## Public API / CLI impact

```bash
durin doctor
durin doctor --json
durin doctor --strict
```

(Flags per master; implement V1 subset if needed but document.)

## Backward compatibility

Additive command.

## Migration

N/A.

## Implementation phases

1. Diagnostic core + result model.
2. PHP/environment checks.
3. Project checks.
4. Mithril/Eregion checks (thin).
5. Human + JSON output.

## Tests

- Unit per check (pass/fail fixtures).
- Integration: invoke CLI in project root (no full Eregion HTTP required).

## Acceptance criteria

- [ ] `durin doctor` runs and reports actionable items.
- [ ] JSON mode machine-readable.
- [ ] Does not fork Eregion protocol implementation.
- [ ] Tests cover at least PHP + project check paths.

## Risks

- Over-coupling to `forge` CLI output format — prefer stable APIs/files when available.

## Open questions

- Exact `--strict` semantics if master leaves edge cases — default: warnings escalate to non-zero.

## Definition of Done

Command usable, tested, docs updated, branch pushed.
