# Package hardening — ownership map

**Date:** 2026-09-27  
**Format:** path | current namespace | classification | target location | target namespace | action

## Summary

| Classification | Meaning |
|----------------|---------|
| FRAMEWORK | Stays in Forge package under `EreborCodeForge\Durin\Forge\` |
| APPLICATION / TEMPLATE | Moves to `resources/skeleton/application/` (not autoloaded by Forge) |
| TEST/DEMO | Tests / fixtures |

---

## bin/

| path | current namespace | classification | target location | target namespace | action |
|------|-------------------|----------------|-----------------|------------------|--------|
| bin/durin | n/a | FRAMEWORK | bin/durin | n/a | rewrite consumer-aware bootstrap |
| bin/durins-forge | n/a | FRAMEWORK | bin/durins-forge | n/a | keep as alias to durin for local DX |

## src/Kernel.php

| path | current namespace | classification | target location | target namespace | action |
|------|-------------------|----------------|-----------------|------------------|--------|
| src/Kernel.php | App | APPLICATION / TEMPLATE | resources/skeleton/application/src/Kernel.php | App | extract reusable boot into Forge; skeleton owns App\Kernel |

## src/Console/ (FRAMEWORK)

All `App\Console\*` → keep path → `EreborCodeForge\Durin\Forge\Console\*` — rename namespace/imports.

## src/Core/ (FRAMEWORK)

| path | notes |
|------|-------|
| src/Core/** (except helpers.php) | FRAMEWORK → `EreborCodeForge\Durin\Forge\Core\*` |
| src/Core/helpers.php | FRAMEWORK → move to `src/Support/helpers.php`; thin `base_path()` |
| src/Core/Http/Controllers/HealthCheckController.php | FRAMEWORK (framework health endpoint) |
| src/Core/DiscoveryServiceProvider.php | FRAMEWORK; make application namespace prefix configurable (default `App\`) |

## src/Infrastructure/ (FRAMEWORK)

All reusable bridges/cache/db/session/security/view → `EreborCodeForge\Durin\Forge\Infrastructure\*` — rename namespace/imports. No business domain found; keep in Forge.

## src/Tooling/ (FRAMEWORK)

| path | classification | action |
|------|----------------|--------|
| Tooling/Doctor/** | FRAMEWORK | rename to Forge namespace; keep consumer `App\` string checks |
| Tooling/Generators/** | FRAMEWORK | rename; keep emitted `App\` FQCNs for consumer code |
| Tooling/Graph/** | FRAMEWORK | rename namespace/imports |
| Tooling/Runtime/** | FRAMEWORK | rename namespace/imports |

## config/ routes/ public/ .env.example

| path | classification | target | action |
|------|----------------|--------|--------|
| config/* | APPLICATION / TEMPLATE | resources/skeleton/application/config/ | move; keep root copies only if Docker/bench requires TEMPLATE mirror |
| routes/* | APPLICATION / TEMPLATE | resources/skeleton/application/routes/ | move |
| public/index.php | APPLICATION / TEMPLATE | resources/skeleton/application/public/index.php | move |
| public/assets, public/build, public/resources | APPLICATION / TEMPLATE (demo UI) | skeleton or remain as local demo assets | classify as TEMPLATE; not framework API |
| .env.example | APPLICATION / TEMPLATE | resources/skeleton/application/.env.example | move |
| eregion.yaml | APPLICATION / TEMPLATE | skeleton or root TEMPLATE for local runtime | keep root if local forge serve needs it; document |

## resources/

| path | classification | action |
|------|----------------|--------|
| resources/css, js, views | APPLICATION / TEMPLATE (demo SPA) | move under skeleton or leave as non-autoloaded demo; not Forge PHP |
| resources/skeleton/application/ | TEMPLATE | create; hold App bootstrap |

## tests/

| path | classification | action |
|------|----------------|--------|
| tests/** (non-fixture) | TEST | namespace → `EreborCodeForge\Durin\Forge\Tests\` |
| tests/Fixtures/generated-* | TEST/DEMO | update after presets alignment |

## scripts/

| path | classification | action |
|------|----------------|--------|
| scripts/link-vendor-bins.php | FRAMEWORK (Forge-repo DX only) | keep post-install for this repo; not consumer contract |
