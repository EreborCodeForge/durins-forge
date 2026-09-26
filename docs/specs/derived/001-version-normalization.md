# SPEC-DX-001 — Version normalization

**Status:** Implemented (pending merge)  
**Branch:** `feat/dx-001-version-normalization`  
**Parent sections:** Master §5, §36; [ADR-0001](../../adr/ADR-0001-php-version-baseline.md)

## Problem

Docs and runtime assets can drift from Composer’s `php: ^8.5` baseline, implying unsupported PHP lines.

## Goal

Make PHP **8.5** the only documented and exercised baseline across remaining code, CI, Docker, examples, and install docs. Close gaps left after Phase 0 document moves.

## Non-goals

- Supporting PHP &lt; 8.5.
- Changing Mithril/Eregion protocol versions (separate change).
- Full CI productization beyond a minimal PHP 8.5 job if none exists.

## Current implementation

- `composer.json` already has `"php": "^8.5"`.
- Phase 0 normalizes primary docs/Docker references; this SPEC audits leftovers and adds CI matrix if missing.

## Proposed design

1. Grep-audit repository for stale `8.3` / `^8.3` in first-party docs and Docker/CI (ignore unrelated npm lock versions).
2. Ensure `Dockerfile` / compose app images use `php:8.5-cli-*` (or equivalent published tag).
3. Add or adjust GitHub Actions (or existing CI) to run `composer test` on PHP 8.5.
4. Document baseline in README requirements (already 8.5+) without contradicting Docker.

## Affected files

- `Dockerfile`, `docker-compose.yml`, CI workflows under `.github/` (create if absent)
- `README.md`, `docs/**` leftovers
- Possibly `docker/**` helper images

## New files

- `.github/workflows/ci.yml` (if no CI exists)

## Public API / CLI impact

None.

## Backward compatibility

Environments still on PHP 8.3 are explicitly unsupported (already true via Composer).

## Migration

Operators must use PHP 8.5+ locally and in images. No app code migration.

## Implementation phases

1. Audit + fix remaining references.
2. CI job PHP 8.5 + `composer test`.
3. Verify Docker build tag availability; if 8.5 image tag missing on registry, stop and report blocker per §50 (do not silently pin 8.3).

## Tests

- CI green on PHP 8.5.
- Optional: script/check in docs DoD that no first-party `PHP 8.3` remains (exclude master historical mentions inside ADR narrative if quoted).

## Acceptance criteria

- [x] No first-party install/runtime doc recommends PHP 8.3.
- [x] Docker app/bench images align with 8.5 (`php:8.5-cli-bookworm` verified on Docker Hub).
- [x] CI runs tests on PHP 8.5 (`.github/workflows/ci.yml`).
- [x] ADR-0001 remains accurate.

## Risks

- Host registry may lag on `php:8.5` tags — treat as blocker, do not downgrade.

## Open questions

None for V1 (baseline fixed by ADR-0001).

## Definition of Done

Audit clean, Docker/CI aligned, pushed on branch, ready to merge.
