# Package contract — current state (Phase 0 audit)

Audit date: 2026-09-27  
Branch: `feat/package-contract-distribution` (on top of `main` after PR #28 / consumer-mode)  
Spec: [durins-forge-package-contract-distribution-spec.md](../../durins-forge-package-contract-distribution-spec.md)

Consumer-mode is **already on `main`** — do not treat it as pending merge.

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
| `ereborcodeforge/durin-presets` | `^0.1.1` | yes (`0.1.1`) | `durin new` / preset engine |
| `ereborcodeforge/durin-architecture` | `^0.1` | yes (`0.1.0`) | **Retained** (decision A) — capability dependency; no production `src/` imports yet |
| `ereborcodeforge/mithrilphp` | `^2.2` | yes | Runtime / console kernel |
| `ereborcodeforge/mazarbul` | `^1.0` | yes | Data layer / `db()` |
| `ereborcodeforge/durins-forge` | — | **no (404)** | This package — release gate |

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

- `repositories` (removed in this initiative once Packagist resolves Durin deps)
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
| durin-presets | published | `0.1.0`, `0.1.1` |
| durin-architecture | published | `0.1.0` |
| mithrilphp / mazarbul | published | yes |
| **durins-forge** | **not published** | no `v0.1.0` tag yet |

---

## Blockers (release / DoD)

1. **`ereborcodeforge/durins-forge` not on Packagist** — EMPTY DIR `composer require` DoD cannot pass without path/VCS workaround (forbidden as a fake green gate).
2. **Preset generated apps** — nested Durin VCS removed on branch `feat/drop-nested-vcs-repos` of `durin-presets` (pending merge + Packagist `0.1.2`). Transitional Forge-only VCS remains until (1).
3. **Tag `v0.1.0`** — explicit maintainer action after distribution smoke is green on Packagist.

---

## Acceptance checklist (spec)

- [x] Canonical package identity documented
- [x] Role / ownership documented (`docs/package-architecture.md`, ADR-0006)
- [x] Public API whitelist exists (`docs/public-api.md`)
- [x] Internal APIs explicitly non-contractual
- [x] CLI contract documented
- [x] Consumer root contract documented
- [x] Vendor mutation invariant tested (consumer-mode integration)
- [x] Composer binary works in dependency-style bootstrap (unit/integration; Packagist install pending)
- [x] Stable Durin deps resolve without Forge `repositories` VCS (after Phase 3)
- [x] Generated app requires Forge (preset contract tests)
- [ ] Generated standard app needs no Durin VCS — **blocked** on presets patch + Forge Packagist
- [x] Package contract tests present
- [ ] Distribution smoke on Packagist — **blocked** (job prepared, gated)
- [x] README aligned
- [x] ADR-0006 exists
- [ ] Release ready for maintainer tag `v0.1.0` — pending Packagist publish + smoke
