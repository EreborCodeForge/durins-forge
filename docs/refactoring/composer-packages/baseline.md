# Composer packages integration — baseline

**Date:** 2026-09-27  
**Branch:** `feat/composer-baseline`  
**Base commit:** `e149ab4` (`main` after framework-core-cleanup)

## Purpose

Record pre-migration behavior so package integration regressions are not blamed on Composer packages.

## Environment

| Item | Value |
|------|-------|
| PHP | 8.5.10 |
| Platform note | `ext-msgpack` missing on this agent host; `composer install --ignore-platform-req=ext-msgpack` used |
| PHPUnit | 12.5.33 |

## Commands run

| Command | Result |
|---------|--------|
| `composer validate` | `./composer.json is valid` |
| `composer install --ignore-platform-req=ext-msgpack` | OK (lock already satisfied) |
| `composer test` / `phpunit` | **107 tests, 493 assertions, OK** (3 deprecations) |
| `php bin/durin` (list via unknown `--help`) | Lists available commands; prints `Command "--help" not found` (pre-existing CLI quirk) |
| `php bin/durin new` (no args) | Exit 2, usage message |
| `php bin/durin doctor` | Exit 4, **unhealthy** — `msgpack` FAIL (environment); other checks OK/WARN |
| `php bin/durin status` | Exit 0 — tooling available; runtime manifest missing (WARN) |
| `php bin/durin optimize` | Exit 0 — compiled container + routes |
| `php bin/durin graph:dependencies` | Exit 0 — graph rendered |
| `php bin/durin adopt` | **Not found** (expected; architecture CLI not in Forge yet) |
| `php bin/durin evolve` | **Not found** (expected) |
| `php bin/durin migrate` | Exit 0 — DB migrations (not architecture migrate) |

## Known pre-existing failures / quirks

Do **not** attribute these to package migration:

1. Host missing `ext-msgpack` → Composer platform fail without `--ignore-platform-req`; `durin doctor` reports msgpack FAIL / exit 4.
2. `durin --help` / `command --help` do not show dedicated help text; Mithril treats `--help` as a command name or runs the command.
3. `adopt` / `evolve` architecture commands are absent (DB `migrate` exists and is unrelated).
4. PHPUnit reports 3 deprecations (suite still green).

## Current Composer dependencies (Forge)

```text
php ^8.5
ereborcodeforge/mazarbul ^1.0
ereborcodeforge/mithrilphp ^2.2
```

No `durin-core` / `durin-presets` / `durin-architecture` yet.

## Internal ownership (pre-migration)

| Concern | Location |
|---------|----------|
| Project / manifest | `src/Tooling/Project/` |
| Scaffold primitives | `src/Tooling/Scaffold/` |
| Console output helpers | `src/Tooling/Output/` |
| Presets / registry | `src/Tooling/Presets/` |
| Architecture detect/drift/adopt/evolve | **none in Forge** |

## Regression fixtures

Generated with `php bin/durin new …` (exit 0 for each):

| Fixture | Preset | Tree file |
|---------|--------|-----------|
| `tests/Fixtures/generated-minimal/` | minimal | `generated-minimal.tree.txt` |
| `tests/Fixtures/generated-service/` | service | `generated-service.tree.txt` |
| `tests/Fixtures/generated-worker/` | worker | `generated-worker.tree.txt` |

Presets **not** present in V1 (no fixtures): modular, microservice.

Captured per fixture: full file tree, `durin.yaml`, `composer.json`, important generated contents.

## Gate

Baseline recorded. Next phase: integrate `ereborcodeforge/durin-core` only.
