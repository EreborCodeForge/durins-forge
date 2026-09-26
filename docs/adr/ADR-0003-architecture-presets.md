# ADR-0003 — Architecture presets

**Status:** Accepted  
**Date:** 2026-09-25  
**Parent:** [Master DX Spec](../specs/master-dx-tooling-spec.md) §16–22, §40

## Context

Applications need progressive structure without forcing a full modular tree on day one. A preset system must encode profiles while staying inspectable via `durin.yaml`.

## Decision

Durin supports named presets that produce a `ScaffoldPlan` (not ad-hoc file writes):

| Preset | V1 priority |
|--------|-------------|
| `minimal` | First |
| `service` | First |
| `modular` | After engine validation |
| `microservice` | After engine validation |
| `worker` | After contracts are explicit |

Project shape is declared in a small `durin.yaml` that must not mirror all of `.env` or `eregion.yaml`.

## Consequences

- Implement preset engine + `minimal` + `service` before other presets.
- Presets and `make:*` share scaffold plan/writer primitives.
- Empty ceremony directories are forbidden by default (simplicity first).

## Non-goals

- Implementing all presets before validating the engine.
- A plugin marketplace for third-party presets in this program.
