# ADR-0006 — Durins Forge package contract & distribution

**Status:** Accepted  
**Date:** 2026-09-27  
**Related:** [Package contract distribution spec](../durins-forge-package-contract-distribution-spec.md), [ADR-0005](ADR-0005-forge-consumer-mode.md), [ADR-0004](ADR-0004-tooling-package-boundaries.md), [public-api.md](../public-api.md), [package-architecture.md](../package-architecture.md)

## Context

Consumer-mode (ADR-0005) made Forge a Composer library with a Forge production namespace and consumer-owned `App\`. That was necessary but not sufficient: the package still lacked an explicit public API whitelist, a documented distribution strategy (Packagist vs nested VCS), and a release gate for `0.1.x`.

Composer does not inherit `repositories` from dependencies. Shipping Durin packages only via nested VCS is not a valid long-term consumer story.

## Decision

1. **Canonical identity** remains `ereborcodeforge/durins-forge` (`type: library`). No rename to `durin-forge` in this initiative.
2. **Public PHP API** is a small whitelist recorded in `docs/public-api.md` (`ApplicationPath`, `HttpApplicationKernel`, `base_path()`, `db()`). Everything else is internal for `0.1.x`.
3. **CLI** (`vendor/bin/durin` and the documented command set) is the primary public DX contract.
4. **Distribution** targets Packagist for the Durin chain. Forge’s own `composer.json` does **not** declare VCS `repositories` for Durin dependencies once they resolve from Packagist.
5. **`durin-architecture` is retained** (decision A) as a deliberate capability dependency even though Forge has no public architecture CLI yet. No production `src/` imports today; removal would be a separate ADR.
6. **Generated apps** require `ereborcodeforge/durins-forge:^0.1` and must not embed nested VCS for `durin-core` / `durin-presets` / `durin-architecture`. A transitional Forge-only VCS entry may remain in preset output until Forge is on Packagist (owned by `durin-presets`).
7. **Tag `v0.1.0`** is an explicit maintainer action after Packagist publish + distribution smoke. This ADR does not create the tag.
8. **`durin-app` / `durin-installer`** remain out of scope.

## Consequences

- Consumers depend on Forge; transitive Durin packages resolve from Packagist without root VCS once Forge itself is published.
- EMPTY DIR DoD (`composer require ereborcodeforge/durins-forge`) is blocked until Packagist lists this package; CI documents the gate rather than faking it with path repositories.
- Preset VCS cleanup lives in `ereborcodeforge/durin-presets` (branch `feat/drop-nested-vcs-repos` → intended `0.1.2`).
- Compatibility source of truth: `docs/public-api.md`.

## Non-goals

- Creating `durin-app` or `durin-installer`
- Making all of `src/` public API
- Automatic release tagging
- Re-extracting Tooling into new packages
