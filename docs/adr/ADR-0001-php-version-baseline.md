# ADR-0001 — PHP version baseline

**Status:** Accepted  
**Date:** 2026-09-25  
**Parent:** [Master DX Spec](../specs/master-dx-tooling-spec.md) §5, §36

## Context

`composer.json` already requires `"php": "^8.5"`. Several docs and Docker assets still mentioned PHP 8.3, creating a false supported matrix.

## Decision

The sole supported baseline is:

```text
PHP ^8.5
```

All production examples, Docker images used for application runtime/bench, CI matrices, and installation docs must use PHP 8.5 unless this ADR is superseded.

## Consequences

- Normalize README, `docs/**`, `Dockerfile`, `docker-compose.yml`, and related examples away from PHP 8.3.
- CI minimum matrix: PHP 8.5 only (optional future: next stable PHP).
- Any future minimum-version change requires an ADR update before code/docs diverge.

## Non-goals

- Expanding support to older PHP lines.
- Documenting dual baselines.
