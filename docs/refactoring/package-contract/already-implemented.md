# Package contract — already implemented (do not redo)

Canonical reference: [durins-forge-package-contract-distribution-spec.md](../../durins-forge-package-contract-distribution-spec.md).

Base: consumer-mode on `main` (PR #28) + package-contract (PR #29) + `v0.1.0` Packagist release.

---

## Consumer-mode hardening (ADR-0005)

| Item | Status |
|------|--------|
| `type: library`, NS `EreborCodeForge\Durin\Forge\` | Done |
| `ApplicationPath`, skeleton, consumer CLI | Done |
| Namespace / consumer / preset contract tests | Done |

**Do not** re-run the `App\` → Forge migration.

---

## Package contract + distribution

| Item | Status |
|------|--------|
| `docs/public-api.md`, `docs/package-architecture.md`, ADR-0006 | Done |
| Forge Packagist + tag `v0.1.0` @ merge `a480edd` | Done |
| Presets `0.1.2` drop nested Durin VCS (Forge VCS transitional) | Done |
| Presets `0.1.3` drop **all** generated `repositories` | Done (this branch) |
| Real `distribution-smoke` EMPTY DIR CI | Done (this branch) |

---

## Explicitly out of scope (next)

- `ereborcodeforge/durin-app`
- `ereborcodeforge/durin-installer`
- Architecture CLI commands
- Renaming package to `ereborcodeforge/durin-forge`
