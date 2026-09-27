# Package hardening — baseline

**Date:** 2026-09-27  
**Purpose:** Record pre-hardening behavior before consumer-mode / namespace migration.

## Environment

| Item | Value |
|------|-------|
| PHP | 8.5.10 |
| PHPUnit | 12.5.33 |
| Platform note | Host may lack `ext-msgpack`; use `--ignore-platform-req=ext-msgpack` when needed |

## Commands

| Command | Result |
|---------|--------|
| `composer validate --strict --no-check-publish` | `./composer.json is valid` |
| `composer test` | **113 tests, 510 assertions, OK** (3 deprecations) |

## Package identity (pre-hardening)

| Field | Value |
|-------|-------|
| `name` | `ereborcodeforge/durins-forge` |
| `type` | `project` |
| PSR-4 | `App\` → `src/` |
| helpers | `src/Core/helpers.php` |
| `extra.mithril.kernel` | `App\Kernel` |
| bins | `bin/durin`, `bin/durins-forge` |

## Source inventory

| Metric | Count |
|--------|-------|
| PHP files under `src/` | 133 |
| Declaring `namespace App` / `App\` | 132 |
| Declaring `EreborCodeForge\Durin\Forge\` | 0 |
| `helpers.php` (no namespace) | 1 |

## Known quirks (do not attribute to hardening)

1. Host missing `ext-msgpack` → doctor exit 4 / Composer platform fail without ignore flag.
2. `adopt` / `evolve` architecture commands absent (expected).
3. PHPUnit 3 deprecations (suite still green).
4. `base_path()` derives root from Forge package `__DIR__` (fails under vendor install).
5. Generated presets require `mithrilphp` directly and document `php bin/durin` (misaligned with consumer bin).

## CLI smoke (pre-change)

Forge-as-root still works for local DX: `php bin/durin`, doctor/status/optimize/graph against this repository.

## Gate

Proceed only with this baseline recorded. Target after hardening: same or higher green test count plus consumer-mode tests.
