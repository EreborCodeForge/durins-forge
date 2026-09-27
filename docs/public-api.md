# Durin’s Forge — Public API

**Source of truth for compatibility in `0.1.x`.**  
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
| Canonical binary | `vendor/bin/durin` |

---

## Public PHP API (whitelist)

### Supported classes

| Class | Role |
|-------|------|
| `EreborCodeForge\Durin\Forge\Support\ApplicationPath` | Canonical application root / path resolution for consumers and CLI |
| `EreborCodeForge\Durin\Forge\Core\Http\HttpApplicationKernel` | HTTP kernel composed by consumer `App\Kernel` |

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

## Public CLI API (`0.1.x`)

Preserve command names, important args/options, and meaningful exit-code semantics.

```text
durin doctor
durin status
durin dev
durin serve
durin optimize
durin graph:dependencies

durin make:module
durin make:usecase
durin make:feature

durin migrate
durin migrate:rollback
durin migrate:fresh

durin routes:compile
durin routes:clear
durin routes:postman

durin config:cache
durin config:clear
durin container:compile
durin container:clear

durin new
```

Notes:

- `durin new` is supported in Forge; creation UX may later be wrapped by `durin-installer`.
- Database `migrate*` is **Mazarbul DDL**, not architecture migration (future architecture commands will use distinct names).
- `bin/durins-forge` remains a compatibility entry in `0.1.x`; `vendor/bin/durin` is canonical.

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
