# Durin’s Forge — Package Architecture

Framework composition and developer-experience package for Durin applications.

Related: [public-api.md](public-api.md), [ADR-0006](adr/ADR-0006-durins-forge-package-contract.md), [ADR-0005](adr/ADR-0005-forge-consumer-mode.md), [ADR-0004](adr/ADR-0004-tooling-package-boundaries.md).

---

## Role

Durin Forge connects:

```text
application bootstrap
CLI
framework services
tooling
runtime orchestration
generators
diagnostics
project graph
Durin packages
Mithril runtime
Mazarbul data layer
```

It is **not**: the application skeleton package (`durin-app`), the global installer (`durin-installer`), project-state primitives (`durin-core`), the preset catalog (`durin-presets`), architecture planning (`durin-architecture`), Mithril itself, Eregion, or a domain business app.

---

## Ecosystem graph

```text
durin-app                     (future)
    │
    ▼
durins-forge                  THIS PACKAGE
    │
    ├── durin-core
    ├── durin-presets
    ├── durin-architecture    (retained capability; no public Forge CLI yet)
    ├── mithrilphp
    └── mazarbul
```

Later:

```text
durin-installer → creates durin-app → durins-forge
```

Target consumer relationship:

```text
application
    ↓
ereborcodeforge/durins-forge
    ↓
durin-core / durin-presets / durin-architecture
mithrilphp / mazarbul
```

The application must not need a Git clone of Durin Forge.

---

## Ownership matrix

| Area | Owner |
|------|--------|
| `Console/` — command registration & orchestration | Forge |
| `Core/` — HTTP/kernel, routing, providers, cache/container contracts | Forge |
| `Infrastructure/` — DB/cache/session/security/view adapters | Forge |
| `Tooling/Doctor` | Forge |
| `Tooling/Generators` | Forge |
| `Tooling/Graph` | Forge |
| `Tooling/Runtime` (`dev` / `serve` / `status`) | Forge |
| `Support/` — `ApplicationPath`, helpers | Forge |
| `bin/` | Forge |
| `resources/skeleton/` — bootstrap assets for generation | Forge (staged app content) |
| Discovery, durin manifest, scaffold plans, generic mutations | `durin-core` |
| Presets, registry, application-type, scaffold intent | `durin-presets` |
| Detection, state, drift, adoption/evolution plans | `durin-architecture` |
| Worker runtime, HTTP/runtime contracts, console kernel, Eregion protocol | `mithrilphp` |
| Data layer | `mazarbul` |
| `App\`, domain, `durin.yaml`, `.env`, `config/`, `routes/`, `public/`, app tests | Consumer |

---

## Namespace boundary

| Scope | Namespace |
|-------|-----------|
| Forge production | `EreborCodeForge\Durin\Forge\` |
| Core / Presets / Architecture | `EreborCodeForge\Durin\{Core,Presets,Architecture}\` |
| Application | `App\` |

Automated: `tests/Unit/Package/NamespaceBoundaryTest.php`.

---

## Distribution strategy

Canonical: publish the Durin chain on **Packagist** so consumers run:

```bash
composer require ereborcodeforge/durins-forge
```

without nested VCS repositories.

Composer does **not** inherit `repositories` from dependencies. Nested VCS is not a long-term strategy.

Root-only fields (`repositories`, `minimum-stability`, `prefer-stable`, `config`, `scripts`) may exist for Forge repository DX; package runtime must never require consumers to honor them.

### Release dependency order

1. durin-core  
2. durin-presets  
3. durin-architecture (retained)  
4. mithrilphp / mazarbul  
5. Forge clean install resolves all  
6. Maintainer tags Forge (`v0.1.0`)  

No `dev-main` on the stable path.

### Package install side effects

Install must **not**: write app files, modify config, copy skeleton, migrate, start services, or download Eregion (except via an explicit command). Only autoload + binaries.

---

## Package modes

1. **Repository development:** `git clone` → `composer install` → `php bin/durin`  
2. **Consumer mode:** `composer require` → `vendor/bin/durin`  

Same implementation; no separate root-app package in this repository.

---

## Generated application contract

Desired generated `composer.json` (post Packagist):

```json
{
  "require": {
    "php": "^8.5",
    "ereborcodeforge/durins-forge": "^0.1"
  }
}
```

Generated apps require **Forge**, not direct `durin-core` / `durin-presets` / `durin-architecture` / `mithrilphp`, unless the app’s public contract imports them directly.

Preset template ownership lives in `durin-presets` (see package-contract blockers for residual VCS entries).
