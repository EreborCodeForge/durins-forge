# Package contract — current state

Audit date: 2026-09-27 (closed after presets `0.1.3` + real distribution smoke)  
Branch: `feat/distribution-smoke-presets-0.1.3` → `main`  
Spec: [durins-forge-package-contract-distribution-spec.md](../../durins-forge-package-contract-distribution-spec.md)

---

## Status

| Gate | State |
|------|-------|
| `durin-core` Packagist | done |
| `durin-presets` Packagist | done (`0.1.3` — **no** generated VCS) |
| `durin-architecture` Packagist | done |
| `durins-forge` Packagist | done |
| `durins-forge` tag `v0.1.0` @ `a480edd` | done |
| Distribution smoke (EMPTY DIR DoD) | enabled in CI |

Next initiative: **`durin-app`**.

---

## Composer metadata

| Field | Value |
|-------|-------|
| name | `ereborcodeforge/durins-forge` |
| type | `library` |
| PHP | `^8.5` |
| bin | `bin/durin`, `bin/durins-forge` |
| production PSR-4 | `EreborCodeForge\Durin\Forge\` → `src/` |
| files autoload | `src/Support/helpers.php` |
| `App\` production autoload | **no** |

---

## Direct dependencies

| Package | Constraint | Packagist |
|---------|------------|-----------|
| `ereborcodeforge/durin-core` | `^0.1` | `0.1.0` |
| `ereborcodeforge/durin-presets` | `^0.1.3` | `0.1.3` |
| `ereborcodeforge/durin-architecture` | `^0.1` | `0.1.0` |
| `ereborcodeforge/mithrilphp` | `^2.2` | yes |
| `ereborcodeforge/mazarbul` | `^1.0` | yes |

`durin-architecture` retained (ADR-0006 decision A).

---

## Generated app contract (presets ≥ 0.1.3)

```json
{
  "require": {
    "php": "^8.5",
    "ereborcodeforge/durins-forge": "^0.1"
  }
}
```

No `repositories` key. No knowledge of Forge GitHub / core / presets / architecture VCS.

---

## Acceptance checklist

- [x] Canonical package identity documented
- [x] Role / ownership documented
- [x] Public API whitelist (`docs/public-api.md`)
- [x] Internal APIs non-contractual
- [x] CLI + consumer root contracts documented
- [x] Vendor mutation invariant tested
- [x] Composer binary works from Packagist install
- [x] Stable deps resolve without Forge `repositories`
- [x] Generated app requires Forge only
- [x] Generated app has **no** Durin VCS entries (presets `0.1.3`)
- [x] Package contract tests present
- [x] Distribution smoke job enabled (real EMPTY DIR flow)
- [x] README aligned
- [x] ADR-0006 exists
- [x] Tagged `v0.1.0` + Packagist sync
