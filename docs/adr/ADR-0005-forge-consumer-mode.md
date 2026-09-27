# ADR-0005 — Forge consumer mode

**Status:** Accepted  
**Date:** 2026-09-27  
**Related:** [Consumer mode package hardening spec](../durin-forge-consumer-mode-package-hardening-spec.md), [ADR-0004](ADR-0004-tooling-package-boundaries.md)

## Context

Durin Forge still behaved as a root application (`type: project`, `App\` autoload, `base_path()` from package `__DIR__`, CLI autoload relative to the Forge repo). That blocks clean Composer consumption by a future `durin-app`.

## Decision

1. **Forge is a Composer library** (`type: library`) named `ereborcodeforge/durins-forge`.
2. **Forge owns no `App\` namespace.** Production PSR-4 is `EreborCodeForge\Durin\Forge\` → `src/`.
3. **The consumer owns the application root.** `ApplicationPath` is set explicitly or discovered from CWD; it never falls back to the package install path under `vendor/`.
4. **`base_path()`** is a thin helper over `ApplicationPath::path()`.
5. **Composer `bin` is canonical.** Consumers run `vendor/bin/durin`. `scripts/link-vendor-bins.php` remains Forge-repo DX only.
6. **Tooling remains Forge-owned:** `src/Tooling/{Doctor,Generators,Graph,Runtime}`.
7. **Application skeleton is separate from framework source:** `resources/skeleton/application/` holds `App\Kernel` and bootstrap assets; not autoloaded as framework classes (`App\` is only in `autoload-dev` for this repository).
8. **Generated apps depend on Forge** via `durin-presets` templates (`ereborcodeforge/durins-forge`), not on Mithril directly.
9. **`durin-app` remains the next step**, not part of this ADR.

## Consequences

- Internal PHP namespaces changed; CLI command names and preset IDs are unchanged.
- Consumer integration tests must prove writes never land inside `vendor/ereborcodeforge/durins-forge`.
- Preset template alignment lives in `durin-presets` (API redesign remains out of scope).

## Non-goals

- Creating `durin-app` / `durin-installer`.
- Extracting Doctor / Generators / Graph / Runtime into new packages.
- Long-lived class aliases for old `App\Tooling\*` namespaces.
