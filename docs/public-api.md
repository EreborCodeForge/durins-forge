# Durin’s Forge — Public API

**Source of truth for compatibility in `0.2.x`.**  
Anything not listed here is **unstable / internal** and may change without a major version bump, provided the public CLI and bootstrap contracts below remain intact.

Related: [package-architecture.md](package-architecture.md), [ADR-0006](adr/ADR-0006-durins-forge-package-contract.md).

---

## Package identity

| | |
|--|--|
| Composer name | `ereborcodeforge/durins-forge` |
| Type | `library` |
| PHP | `^8.5` |
| Production namespace | `EreborCodeForge\Durin\Forge\` |
| Application namespace | `App\` — **consumer-owned only** |
| Canonical binary | `durins-forge` (`vendor/bin/durins-forge` without the global launcher; `php bin/durins-forge` in this checkout) |

---

## Public PHP API (whitelist)

### Supported classes

| Class | Role |
|-------|------|
| `EreborCodeForge\Durin\Forge\Support\ApplicationPath` | Canonical application root / path resolution for consumers and CLI |
| `EreborCodeForge\Durin\Forge\Core\Http\HttpApplicationKernel` | HTTP kernel composed by consumer `App\Kernel` |
| `EreborCodeForge\Durin\Forge\Core\DiscoveryServiceProvider` | Application `config/app.php` provider entry for Mithril service discovery |

Any Forge class imported by **generated application code** (skeleton / presets) **must** be added to this whitelist.

### Public helpers (`src/Support/helpers.php`)

| Helper | Role |
|--------|------|
| `base_path(?string $path = null): string` | Application DX over `ApplicationPath::path()` |
| `db(...)` | Convenience DX while Mazarbul is the supported data layer |

Do not add global helpers casually; new helpers require an update to this document.

---

## Consumer root contract

- Canonical context: `ApplicationPath`
- Invariant: **application root ≠ Forge package root** (especially under `vendor/ereborcodeforge/durins-forge`)
- Mutations target `ApplicationPath::root()` (or paths owned by `durin-core` project primitives)
- Never derive the consumer root from Forge `__DIR__` alone

### Vendor mutation invariant

No user-facing command may write into `vendor/ereborcodeforge/durins-forge/`. Covered by consumer-mode integration tests.

---

## Public CLI API (`0.2.x`)

Preserve command names, important args/options, and meaningful exit-code semantics.

```text
durins-forge doctor
durins-forge status
durins-forge dev
durins-forge serve
durins-forge optimize
durins-forge graph:dependencies

durins-forge presets:list
durins-forge presets:list --format=json
durins-forge init
durins-forge init --preset=<id>
durins-forge init --preset=<id> --progress=jsonl
durins-forge init --skip-runtime-install

durins-forge make:module
durins-forge make:usecase
durins-forge make:feature

durins-forge migrate
durins-forge migrate:rollback
durins-forge migrate:fresh

durins-forge routes:compile
durins-forge routes:clear
durins-forge routes:postman

durins-forge config:cache
durins-forge config:clear
durins-forge container:compile
durins-forge container:clear

durins-forge new
durins-forge new --preset=<id>
```

Notes:

- Preset catalog and default come from `ereborcodeforge/durin-presets` — Forge does not hardcode preset ID lists.
- `durins-forge init` initializes a neutral application root (created by `durin-app` / installer). It is not architecture migration.
- Default runtime runner is Eregion; `init` installs/configures it unless `--skip-runtime-install`.
- `--progress=jsonl` emits stable stage IDs for installer UX.
- `durin new` from the installer remains the creation UX. The local helper is `durins-forge new`.
- Database `migrate*` is **Mazarbul DDL**, not architecture migration.
- `durins-forge` is the canonical CLI. `bin/durin` and `vendor/bin/durin` remain compatibility aliases until a future major version.

Human-readable wording may improve if it is not part of a tested/documented machine contract (e.g. JSON schemas where exposed).

---

## Internal API (default)

Not contractual in `0.1.x`:

- Console command classes
- `src/Tooling/**` (Doctor, Generators, Graph, Runtime)
- Internal renderers, graph models, generator internals
- Provider discovery internals
- Infrastructure adapters not referenced by the application skeleton

Mark with `@internal` where useful. The authoritative public list is this file.

---

## Application skeleton

`resources/skeleton/application/` is **application-owned content staged in Forge**, not framework runtime source.

- May declare `App\Kernel` composing `HttpApplicationKernel`
- Must **not** be on production PSR-4
- `autoload-dev` mapping of `App\` → skeleton is for this repository’s tests only

---

## Compatibility policy (`0.1.x`)

- SemVer; internal PHP may evolve more freely than after `1.0.0`
- Must preserve: public PHP whitelist, CLI names/args/exit semantics, consumer root + vendor mutation invariants, Composer binary bootstrap behavior
- Before `1.0.0`: freeze broader PHP API, CLI policy, `durin.yaml` contracts, and extension points
