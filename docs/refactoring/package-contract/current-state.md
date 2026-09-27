# Package contract — current state (Phase 0 audit)

Audit date: 2026-09-27 (updated after `v0.1.0` / presets `v0.1.2` tags)  
Branch: `main` (package-contract via PR #29; consumer-mode via PR #28)  
Spec: [durins-forge-package-contract-distribution-spec.md](../../durins-forge-package-contract-distribution-spec.md)

Consumer-mode is **already on `main`**. First stable Forge tag **`v0.1.0`** is published and resolved on Packagist.

---

## Composer metadata

| Field | Value |
|-------|-------|
| name | `ereborcodeforge/durins-forge` |
| type | `library` |
| PHP | `^8.5` |
| license | MIT |
| bin | `bin/durin`, `bin/durins-forge` |
| production PSR-4 | `EreborCodeForge\Durin\Forge\` → `src/` |
| files autoload | `src/Support/helpers.php` |
| `App\` in production autoload | **no** |
| `App\` in autoload-dev | skeleton only (`resources/skeleton/application/src/`) |

---

## Direct dependencies

| Package | Constraint | Packagist | Rationale |
|---------|------------|-----------|-----------|
| `ereborcodeforge/durin-core` | `^0.1` | yes (`0.1.0`) | Project/scaffold/manifest primitives |
| `ereborcodeforge/durin-presets` | `^0.1.1` | yes (`0.1.2`) | `durin new` / preset engine |
| `ereborcodeforge/durin-architecture` | `^0.1` | yes (`0.1.0`) | **Retained** (decision A) — capability dependency; no production `src/` imports yet |
| `ereborcodeforge/mithrilphp` | `^2.2` | yes | Runtime / console kernel |
| `ereborcodeforge/mazarbul` | `^1.0` | yes | Data layer / `db()` |
| `ereborcodeforge/durins-forge` | `^0.1` | yes (`v0.1.0`) | This package |

---

## Production imports / public bootstrap

Public PHP surface used by skeleton / consumers:

- `EreborCodeForge\Durin\Forge\Support\ApplicationPath`
- `EreborCodeForge\Durin\Forge\Core\Http\HttpApplicationKernel`
- Helpers: `base_path()`, `db()`

Binary bootstrap (`bin/durin`):

1. Locate consumer `vendor/autoload.php`
2. `ApplicationPath::setRoot(application root)` — never Forge package root under vendor
3. Load `.env` if present
4. Run `Console\Application`

---

## Root-only Composer fields

These apply when developing **this** repository; they are **not** inherited by consumers of the package:

- `repositories` (removed — Packagist resolves Durin deps)
- `minimum-stability` / `prefer-stable`
- `config` (`sort-packages`, `optimize-autoloader`, `github-protocols`, `preferred-install`)
- `scripts` (`test`, `link-bins`, `post-install-cmd`, `post-update-cmd`)

`link-bins` remains Forge-repo DX only. Composer install of Forge as a dependency must not mutate the consumer application tree.

---

## Binary behavior

| Mode | Entry | Root |
|------|-------|------|
| Repository development | `php bin/durin` | Forge checkout (also consumer of itself for DX) |
| Consumer mode | `vendor/bin/durin` | Consumer project root |

Canonical consumer DX: `vendor/bin/durin`.

---

## Package publication status

| Package | Packagist | Stable tags observed |
|---------|-----------|----------------------|
| durin-core | published | `0.1.0` |
| durin-presets | published | `0.1.0`, `0.1.1`, **`0.1.2`** |
| durin-architecture | published | `0.1.0` |
| mithrilphp / mazarbul | published | yes |
| **durins-forge** | **published** | **`v0.1.0`** (GitHub release + Packagist) |

Verified: empty-dir `composer require ereborcodeforge/durins-forge:^0.1` installs `v0.1.0` and exposes `vendor/bin/durin`.

---

## Remaining follow-ups

1. Generated apps still emit a **transitional Forge-only VCS** entry (presets `0.1.2`). Remove that list entirely in a later presets patch once consumers reliably resolve Forge from Packagist alone.
2. Run / watch CI `distribution-smoke` job (enabled after Packagist sync).
3. Optional: drop Forge VCS from fixtures once presets stop emitting it.

---

## Acceptance checklist (spec)

- [x] Canonical package identity documented
- [x] Role / ownership documented (`docs/package-architecture.md`, ADR-0006)
- [x] Public API whitelist exists (`docs/public-api.md`)
- [x] Internal APIs explicitly non-contractual
- [x] CLI contract documented
- [x] Consumer root contract documented
- [x] Vendor mutation invariant tested (consumer-mode integration)
- [x] Composer binary works from dependency install (`vendor/bin/durin` after Packagist require)
- [x] Stable Durin deps resolve without Forge `repositories` VCS
- [x] Generated app requires Forge (preset contract tests)
- [ ] Generated standard app needs no Durin VCS — transitional Forge VCS remains in presets `0.1.2`
- [x] Package contract tests present
- [x] Distribution smoke job present (CI); Packagist require verified locally
- [x] README aligned
- [x] ADR-0006 exists
- [x] Tagged **`v0.1.0`** and synced to Packagist
