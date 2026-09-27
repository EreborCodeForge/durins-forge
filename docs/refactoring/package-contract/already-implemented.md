# Package contract — already implemented (do not redo)

Inventory of work already delivered before / alongside this initiative.
Canonical reference: [durins-forge-package-contract-distribution-spec.md](../../durins-forge-package-contract-distribution-spec.md).

Base for this work: `refactor/consumer-mode-package-hardening`, **already merged to `main`** via PR #28. This initiative continues on `feat/package-contract-distribution` on top of that merge.

---

## Consumer-mode hardening (ADR-0005)

| Item | Path / evidence | Status |
|------|-----------------|--------|
| Composer `type: library` | `composer.json` | Done |
| Production PSR-4 `EreborCodeForge\Durin\Forge\` | `composer.json` autoload | Done |
| No production `App\` autoload | `composer.json`; `tests/Unit/Package/NamespaceBoundaryTest.php` | Done |
| `ApplicationPath` consumer root | `src/Support/ApplicationPath.php` | Done |
| `base_path()` over `ApplicationPath` | `src/Support/helpers.php` | Done |
| `HttpApplicationKernel` | `src/Core/Http/HttpApplicationKernel.php` | Done |
| Application skeleton (not production autoload) | `resources/skeleton/application/` | Done |
| `App\` only in `autoload-dev` for skeleton | `composer.json` | Done |
| Consumer-aware CLI binary | `bin/durin`, `bin/durins-forge` | Done |
| ADR-0005 | `docs/adr/ADR-0005-forge-consumer-mode.md` | Done |
| Consumer-mode spec | `docs/durin-forge-consumer-mode-package-hardening-spec.md` | Done |
| Vendor mutation / consumer root tests | `tests/Integration/ConsumerModeIntegrationTest.php` | Done |
| Generated apps require Forge | `tests/Unit/Package/GeneratedPresetContractTest.php` | Done |
| Namespace boundary tests | `tests/Unit/Package/NamespaceBoundaryTest.php` | Done |
| README consumer / repo modes | `README.md` | Done (aligned further in this initiative) |

**Do not** re-run the `App\` → Forge namespace migration or recreate the skeleton / `ApplicationPath` abstraction.

---

## Composer packages ownership (prior initiative)

| Item | Path / evidence | Status |
|------|-----------------|--------|
| `durin-core` dependency | `composer.json` require | Done |
| `durin-presets` dependency | `composer.json` require `^0.1.1` | Done |
| `durin-architecture` dependency | `composer.json` require `^0.1` | Done |
| Tooling stays in Forge | ADR-0004; `src/Tooling/{Doctor,Generators,Graph,Runtime}` | Done |
| Boundary regression | `tests/Unit/Tooling/ComposerPackageBoundaryTest.php` | Done |
| Ownership maps | `docs/refactoring/composer-packages/` | Done |

**Do not** re-extract Project/Scaffold/Presets trees into Forge `src/Tooling/`.

---

## Explicitly out of scope (still future)

- `ereborcodeforge/durin-app`
- `ereborcodeforge/durin-installer`
- Architecture CLI commands (`adopt` / `evolve` / …)
- Renaming package to `ereborcodeforge/durin-forge`
- Tag / Packagist publish of Forge (`v0.1.0`) — maintainer action

---

## Preset VCS cleanup (owned by `durin-presets`)

Generated `composer.json` historically embedded VCS repositories via:

```text
durin-presets/src/Preset/PresetScaffoldSupport.php
  → composerRepositories() / formerly vcsRepositories()
```

**Patch released as `0.1.2`** on Packagist (merged `feat/drop-nested-vcs-repos`):

- Drop VCS for `durin-core` / `durin-presets` / `durin-architecture`
- Keep transitional Forge-only VCS until consumers no longer need it
- GitHub tag/release: `v0.1.2`

Forge itself is published as **`v0.1.0`** on Packagist. Next presets patch can empty `composerRepositories()` entirely.

See [current-state.md](current-state.md).
