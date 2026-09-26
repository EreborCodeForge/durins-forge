# ADR-0002 — CLI and runtime boundaries

**Status:** Accepted  
**Date:** 2026-09-25  
**Parent:** [Master DX Spec](../specs/master-dx-tooling-spec.md) §6, §8  
**Related:** [boundaries.md](../architecture/boundaries.md)

## Context

Durin already exposes thin CLI entrypoints (`bin/durin`) and aliases such as `serve` that delegate to Mithril `forge`. There is a risk of duplicating Eregion/Mithril behavior inside Durin as DX grows (`dev`, `status`, `doctor`).

## Decision

The Durin CLI is the developer-facing UX for application DX, diagnostics, generators, optimize, and **thin** runtime orchestration. Runtime implementation remains in MithrilPHP and Eregion.

Canonical flow:

```text
durin → Mithril → Eregion
```

Shared runtime UX (`dev`, `serve`, `status`) must share one orchestration facade; Durin must not maintain two startup stacks.

## Consequences

- New runtime commands prefer delegation and presentation over reimplementation.
- `doctor` may compose Mithril checks (e.g. `server:check`) but owns Durin-specific project/artifact checks.
- Blocker (§50): if required behavior belongs only to Mithril/Eregion, stop and report rather than forking.

## Non-goals

- Replacing `forge` / Eregion CLIs entirely.
- Embedding HTTP server logic in Durin.
