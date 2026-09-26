# SPEC-DX-007 — `durin status`

**Status:** Implemented (pending merge)  
**Branch:** `feat/dx-007-durin-status`  
**Parent sections:** Master §13, §39-004; depends on SPEC-004  
**Explicitly out of scope:** `status --watch` (future SPEC)

## Problem

Operators need a Durin-facing view of runtime state without learning raw Eregion tooling first.

## Goal

Implement `durin status` that reads runtime status via facade/provider and prints a clear summary (workers, endpoints, health signals available from Mithril/Eregion).

## Non-goals

- `--watch` / TUI.
- Remote monitoring SaaS.
- Inventing metrics Eregion does not expose.

## Current implementation

- No Durin status command.
- Eregion/Mithril may expose version/check/manifest under `var/runtime/`.

## Proposed design

`RuntimeStatusProvider` (master §30) ← adapter reading manifest/check outputs. CLI renderer text (JSON optional if cheap).

V1 reports tooling + config/manifest (HTTP bind, configured workers, protocol). Live idle/busy metrics wait for an Eregion ops API / `--watch` SPEC.

## Affected files

- Console, README

## New files

- Status command, DTOs if not in 004, tests

## Public API / CLI impact

```bash
durin status
durin status --json
```

## Backward compatibility

Additive.

## Migration

N/A.

## Implementation phases

1. Status provider reading known files/APIs.
2. CLI renderer.
3. Tests with fixture manifests.

## Tests

- Unit: parse fixture `eregion` manifest / status payload.
- Integration: CLI with fixtures.

## Acceptance criteria

- [x] Reports useful status when runtime metadata present.
- [x] Degrades gracefully when runtime not running (non-cryptic message).
- [x] No `--watch` in this slice.
- [x] Uses shared facade/provider seam.

## Risks

- Unstable upstream status format — version the adapter.

## Open questions

None blocking V1.

## Definition of Done

Status command merged with tests and docs.
