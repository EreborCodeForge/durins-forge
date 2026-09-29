# Durin’s Forge — Composer Package Contract, Distribution & Release Specification

**Status:** Implementation specification  
**Canonical repository:** `EreborCodeForge/durins-forge`  
**Canonical Composer package:** `ereborcodeforge/durins-forge`  
**Current target line:** `0.1.x`  
**PHP baseline:** `^8.5`  
**Purpose:** formally define Durin’s Forge itself as the public Composer framework package that composes the Durin ecosystem and is consumed by applications.

---

# Mission

Formalize `ereborcodeforge/durins-forge` as a real Composer framework package with a stable ownership boundary, explicit public API, reproducible dependency chain, consumer-safe CLI and release process.

The package already exists in implementation.

This specification does NOT recreate it.

It aligns the existing implementation into an explicit package contract so the next layer, `durin-app`, can safely depend on it.

The target user relationship is:

```text
application
    ↓
ereborcodeforge/durins-forge
    ↓
durin-core
durin-presets
durin-architecture
mithrilphp
mazarbul
```

The application must not need a Git clone of Durin Forge.

---

# Current Verified State

At the start of this specification the repository already has:

```text
composer package:
  ereborcodeforge/durins-forge

type:
  library

production namespace:
  EreborCodeForge\Durin\Forge\

Composer binaries:
  bin/durin
  bin/durins-forge

application root abstraction:
  ApplicationPath

application namespace:
  App\ belongs to consumer

application skeleton:
  resources/skeleton/application/

internal packages:
  ereborcodeforge/durin-core ^0.1
  ereborcodeforge/durin-presets ^0.1.1
  ereborcodeforge/durin-architecture ^0.1

runtime:
  ereborcodeforge/mithrilphp ^3.0

database:
  ereborcodeforge/mazarbul ^1.0
```

The latest consumer-mode hardening has already moved framework production code away from `App\`.

Do not redo that migration.

---

# Why This Specification Exists

Previous work established implementation boundaries but did not formally model Durin Forge itself as a public package.

Missing package-level decisions include:

```text
what exactly durins-forge owns
what is public PHP API
what is internal implementation
what CLI behavior is public
how applications depend on it
how transitive Durin packages are resolved
how releases are versioned
what must be published before Forge
what guarantees 0.1.x provides
what the future durin-app may assume
```

This specification closes those gaps.

---

# Package Identity

Canonical Composer name:

```text
ereborcodeforge/durins-forge
```

Canonical Git repository:

```text
https://github.com/EreborCodeForge/durins-forge
```

Do NOT rename the Composer package to:

```text
ereborcodeforge/durin-forge
```

in this initiative.

The repository and package already use `durins-forge`; renaming would introduce needless compatibility work.

A naming change requires a separate ADR.

---

# Package Role

Durin Forge is:

> The framework composition and developer-experience package for Durin applications.

It is responsible for connecting:

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

It is NOT:

```text
the application skeleton package
the global installer
the project-state primitive package
the preset catalog package
the architecture planning package
the Mithril runtime itself
the Eregion server
a domain/business application
```

---

# Ecosystem Ownership

Target package graph:

```text
durin-app                     future
    │
    ▼
durins-forge                  THIS PACKAGE
    │
    ├── durin-core
    ├── durin-presets
    ├── durin-architecture
    ├── mithrilphp
    └── mazarbul
```

Later:

```text
durin-installer
    ↓
creates durin-app
    ↓
durins-forge
```

---

# Package Responsibilities

`durins-forge` owns:

```text
Console/
  command registration
  command orchestration

Core/
  reusable framework HTTP/kernel behavior
  routing integration
  provider/discovery integration
  framework cache/container contracts

Infrastructure/
  framework-owned DB/cache/session/security/view adapters

Tooling/Doctor/
  environment/project diagnostics

Tooling/Generators/
  module/usecase/feature generators

Tooling/Graph/
  dependency graph DX

Tooling/Runtime/
  dev/serve/status orchestration

Support/
  consumer application context
  ApplicationPath
  framework helpers

bin/
  durin CLI entry
  compatibility binary if retained

resources/skeleton/
  application bootstrap resources used by generation
```

---

# Responsibilities Owned Elsewhere

Do NOT duplicate these in Forge.

## `durin-core`

Owns:

```text
project discovery primitives
durin manifest model
scaffold plans
safe generic project mutations
shared tooling contracts
```

## `durin-presets`

Owns:

```text
preset definitions
preset registry
application-type resolution
preset templates/scaffold intent
```

## `durin-architecture`

Owns:

```text
architecture detection
architecture state
drift
adoption plans
evolution plans
migration plans
transition rules
```

## `mithrilphp`

Owns:

```text
worker runtime
HTTP/runtime contracts
console kernel foundation
Eregion runtime protocol integration
```

## Consumer application

Owns:

```text
App\
App\Kernel
business/domain code
durin.yaml
.env
config/
routes/
public/
application tests
```

---

# Public Surface Strategy

Not every class inside the package is public API.

For `0.1.x`, use an explicit small public surface.

Everything not explicitly listed is considered unstable/internal.

This prevents accidental compatibility commitments.

---

# Public API — Composer Contract

The following are public package-level contracts:

```text
package name:
ereborcodeforge/durins-forge

PHP:
^8.5

type:
library

binary:
vendor/bin/durin

application namespace:
App\ belongs to consumer

framework namespace:
EreborCodeForge\Durin\Forge\
```

These are compatibility-sensitive.

---

# Public API — CLI

The CLI is the primary public DX API.

Current public commands include the commands already documented and tested, such as:

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
```

`durin new` is also a supported Forge command, but creation UX will later be wrapped by `durin-installer`.

Do not reinterpret DB `migrate` as architecture migration.

Architecture migration requires a separately named future command.

---

# CLI Compatibility

For `0.1.x`, preserve where already documented/tested:

```text
command names
argument names
option names
important exit-code semantics
JSON output schemas where exposed
non-destructive generator behavior
```

Human-readable wording may improve unless tests/documentation define it as a contract.

---

# Public PHP API — Initial Whitelist

Only classes required by consumer bootstrap should be treated as supported PHP API initially.

At minimum:

```text
EreborCodeForge\Durin\Forge\Support\ApplicationPath

EreborCodeForge\Durin\Forge\Core\Http\HttpApplicationKernel
```

If generated application code directly imports any additional Forge class, that class becomes part of the supported surface and MUST be added to:

```text
docs/public-api.md
```

Do not silently expose all classes under `src/`.

---

# Helper API

Current helpers such as:

```php
base_path()
db()
```

must be classified explicitly.

Recommended:

```text
base_path()
  public application DX

db()
  public convenience DX while Mazarbul integration remains supported
```

Document helper behavior and return types.

Do not add new global helpers casually.

---

# Internal API

Treat these implementation areas as internal by default:

```text
Console command PHP classes
Tooling implementation classes
renderers
internal graph models
runtime orchestration implementation
generator internals
provider discovery internals
infrastructure adapters not referenced by app skeleton
```

They may change inside `0.1.x` provided public CLI behavior remains compatible.

Where useful, add:

```php
/** @internal */
```

Do not mechanically annotate every class if it adds noise.

The authoritative public list is `docs/public-api.md`.

---

# Application Skeleton Contract

`resources/skeleton/application/` is not framework runtime source.

It is application-owned content staged inside Forge for generation/testing.

It may contain:

```text
App\Kernel
public/index.php
config defaults
route bootstrap
.env.example
```

Skeleton PHP using `App\` MUST NOT be included in production PSR-4 autoload.

Current `autoload-dev` use is acceptable for Forge test support.

---

# App Kernel Contract

The generated application kernel should remain small.

Target relationship:

```text
App\Kernel
    ↓ composition
HttpApplicationKernel
```

Avoid copying framework implementation into the application.

Consumer-owned kernel may customize application behavior through explicit extension/composition points.

---

# Consumer Root Contract

Framework code MUST resolve filesystem operations against the consumer application root.

Canonical context:

```text
ApplicationPath
```

Invariant:

```text
application root != Forge package root
```

All commands that mutate project files MUST target:

```text
ApplicationPath::root()
```

or a project path derived from `durin-core`.

Never derive consumer root from Forge's `__DIR__`.

---

# Vendor Mutation Invariant

No user-facing command may write into:

```text
vendor/ereborcodeforge/durins-forge/
```

except normal Composer installation itself.

Maintain regression coverage for this.

This invariant is release-blocking.

---

# Composer Binary Contract

Canonical consumer command:

```bash
vendor/bin/durin
```

The binary must work when the physical source is:

```text
vendor/ereborcodeforge/durins-forge/bin/durin
```

It must:

```text
locate consumer Composer autoload
resolve consumer root
bootstrap ApplicationPath
load app environment
run Forge Console\Application
```

The consumer must not need to copy a CLI file into `bin/`.

---

# Compatibility Binary

Current package also exposes:

```text
bin/durins-forge
```

Decide before `1.0` whether this remains public or becomes a compatibility alias.

For `0.1.x`:

```text
vendor/bin/durin
```

is canonical.

Document `durins-forge` as compatibility/legacy if it remains.

Do not create two independent CLI implementations.

---

# Composer Dependency Contract

Current direct dependency model:

```text
durins-forge
├── durin-core
├── durin-presets
├── durin-architecture
├── mithrilphp
└── mazarbul
```

Only declare a dependency directly when Forge:

```text
imports it directly
uses it as a deliberate capability dependency
or exposes behavior requiring it
```

Avoid convenience duplication.

---

# Architecture Package Dependency Review

At the time this specification was written, `durin-architecture` is installed but public Forge architecture commands are still future work.

The agent MUST verify whether current production code imports the package.

If there are no production imports, choose one and record the decision:

```text
A. retain it deliberately as a framework capability dependency
   because Forge guarantees architecture APIs are available to future commands

or

B. remove it from direct Forge require until Forge integrates it
```

Do not change this silently.

Record the decision in ADR/package documentation.

---

# Distribution Problem — VCS Repositories Are Root-Only

Composer custom repositories are root-package configuration.

A dependency's:

```json
"repositories": [...]
```

is not recursively loaded by Composer consumers.

Therefore this is NOT a valid long-term distribution strategy:

```text
consumer
  requires durins-forge from one repository

durins-forge/composer.json
  points to durin-core VCS
  points to durin-presets VCS
  points to durin-architecture VCS
```

The consumer will not inherit those repository declarations.

---

# Canonical Public Distribution Strategy

For public/open-source distribution, publish the complete required package chain to Packagist:

```text
ereborcodeforge/durin-core
ereborcodeforge/durin-presets
ereborcodeforge/durin-architecture
ereborcodeforge/durins-forge
```

Then a consumer requires only:

```bash
composer require ereborcodeforge/durins-forge
```

No manual VCS repository setup.

---

# Alternative Distribution

If packages are intentionally not published to public Packagist, configure one explicit Composer repository at the consumer/root level:

```text
Private Packagist
Satis
another Composer repository
```

Do not rely on nested VCS repositories.

For the current public GitHub project, public Packagist is the preferred path.

---

# Forge Repositories Field

Once all direct Durin dependencies are resolvable from the canonical Composer repository:

remove Forge's VCS `repositories` entries unless they are intentionally needed for repository-local development.

Preferred final package metadata:

```text
requires package names/version constraints
does not depend on root-only repository declarations
```

Local development overrides belong in developer-only workflow, not the public package contract.

---

# Root-Only Composer Fields

Review current:

```text
repositories
minimum-stability
prefer-stable
config
scripts
```

Some Composer fields only affect the package when it is the root project.

That is acceptable for repository development, but package runtime must never depend on those fields being honored by a consumer.

Document this distinction.

---

# Composer Package Metadata

Target metadata should include:

```text
name
description
type=library
license
authors
homepage
support
keywords
require
require-dev
autoload
autoload-dev
bin
```

Keep metadata minimal and accurate.

Do not advertise Durin Forge as the installer once `durin-installer` becomes the actual installer package.

Suggested description direction:

> Application framework and developer tooling for Durin, built on MithrilPHP.

Avoid implying every app must use Clean Architecture because presets intentionally support simpler designs.

---

# Composer Autoload

Production:

```json
{
  "psr-4": {
    "EreborCodeForge\\Durin\\Forge\\": "src/"
  },
  "files": [
    "src/Support/helpers.php"
  ]
}
```

Consumer `App\` MUST NOT appear in production autoload.

Skeleton `App\` may appear in `autoload-dev` only for package tests.

---

# Package Namespace Boundary

Production:

```text
EreborCodeForge\Durin\Forge\
```

Internal Durin packages:

```text
EreborCodeForge\Durin\Core\
EreborCodeForge\Durin\Presets\
EreborCodeForge\Durin\Architecture\
```

Application:

```text
App\
```

Add/retain an automated namespace boundary test.

---

# Package Installation Modes

The package must support two modes.

## Repository development

```bash
git clone ...
composer install
php bin/durin
```

## Consumer mode

```bash
composer require ereborcodeforge/durins-forge
vendor/bin/durin
```

Both must exercise the same framework implementation.

No separate root-app implementation.

---

# Release Versioning

Use SemVer.

During `0.x`:

```text
0.1.x
```

may evolve internal PHP APIs more freely.

Still preserve explicit public CLI and application bootstrap contracts where practical.

Before `1.0.0`, define:

```text
stable PHP public API
stable CLI compatibility policy
stable durin.yaml compatibility policy
stable extension points
```

---

# Initial Forge Release

Current internal package releases are already in the `0.1.x` line.

The first formally distributed Forge package should use:

```text
v0.1.0
```

only after the distribution gate passes.

Do not tag automatically from this spec unless the release action is explicitly authorized.

---

# Release Dependency Order

Before releasing Forge:

```text
1. durin-core compatible release available
2. durin-presets compatible release available
3. durin-architecture compatible release available if retained
4. mithrilphp compatible release available
5. mazarbul compatible release available
6. Forge clean install resolves all dependencies
7. tag Forge
```

No Forge release should reference unresolved `dev-main` dependencies for the normal stable path.

---

# Distribution Gate

Before tagging Forge, test from outside the repository.

Create a fresh temporary project.

The test MUST NOT use:

```text
path repositories
sibling checkouts
Forge composer.lock
Forge vendor/
```

---

# Consumer Install Smoke Test

Target test:

```bash
mkdir durin-consumer-smoke
cd durin-consumer-smoke

composer init   --name=app/durin-consumer-smoke   --type=project   --no-interaction

composer require ereborcodeforge/durins-forge:^0.1
```

After package publication this command must work with no custom VCS repository declarations.

---

# CLI Smoke Test

From consumer root:

```bash
vendor/bin/durin
vendor/bin/durin doctor
vendor/bin/durin status
```

Where runtime prerequisites permit:

```bash
vendor/bin/durin dev
```

Validate exit codes and root resolution.

---

# Generator Smoke Test

From consumer root:

```bash
vendor/bin/durin make:module Billing
vendor/bin/durin make:usecase CreateInvoice --module=Billing
```

Verify:

```text
files created under consumer src/
no files created under vendor/
```

---

# `durin new` Smoke Test

Using a clean installed Forge:

```bash
vendor/bin/durin new generated-app --preset=minimal
```

Then:

```bash
cd generated-app
composer install
vendor/bin/durin doctor
```

This proves:

```text
Forge package
  can generate
  another consumer app
  which can install Forge again
```

This is a high-value end-to-end distribution test.

---

# Preset Distribution Contract

Generated `composer.json` from `durin-presets` must reference:

```text
ereborcodeforge/durins-forge
```

with a compatible released constraint.

It must NOT require users to know all internal package repositories.

Once public packages are available from Packagist, generated applications SHOULD NOT contain VCS repository entries for:

```text
durin-core
durin-presets
durin-architecture
durins-forge
```

Those are temporary bootstrap details, not desired application DX.

---

# Generated Project Dependency Principle

Generated app:

```text
require durins-forge
```

Do NOT directly require:

```text
durin-core
durin-presets
durin-architecture
mithrilphp
```

unless generated application source directly imports those packages as part of its public app contract.

Prefer transitive ownership through Forge.

---

# Public API Documentation

Create:

```text
docs/public-api.md
```

Initial sections:

```text
Composer package
CLI commands
Supported PHP classes
Global helpers
Application bootstrap contract
Application root contract
Versioning policy
Internal APIs
```

This file becomes the source of truth for compatibility decisions.

---

# Package Architecture Documentation

Create:

```text
docs/package-architecture.md
```

Include:

```text
durins-forge role
dependency graph
ownership matrix
application vs framework boundary
Tooling ownership
runtime boundary
skeleton boundary
```

Do not duplicate every ADR.

Link to ADRs.

---

# Package Health Checks

CI should validate:

```text
composer validate --strict
composer install --prefer-dist
unit tests
integration tests
namespace boundary
consumer root safety
generated preset contract
consumer-mode CLI
```

Add a separate distribution job when package repository publication is ready.

---

# CI — Distribution Job

Recommended job:

```text
distribution-smoke
```

It should create a clean temp Composer project and install Forge independently.

Pre-release, this may point to the repository explicitly in the root test project.

Post-release, it MUST install through the canonical Composer repository without custom VCS config.

---

# Dependency Resolution Test

Add:

```bash
composer show -t ereborcodeforge/durins-forge
```

or equivalent Composer inspection in CI/release validation.

Verify exactly one compatible version of:

```text
durin-core
durin-presets
durin-architecture
mithrilphp
mazarbul
```

No duplicate/fork resolution.

---

# No Root-Repository Dependency Test

After public package publication, add a test that fails if a generated app requires custom VCS repository entries for the standard Durin packages.

Desired consumer composer:

```json
{
  "require": {
    "php": "^8.5",
    "ereborcodeforge/durins-forge": "^0.1"
  }
}
```

plus application-specific extensions/dependencies.

---

# Package Install Side Effects

Installing Forge as a dependency must NOT:

```text
write application files automatically
modify consumer config automatically
copy skeleton files automatically
run migrations automatically
start services
download Eregion automatically unless explicitly requested by a command
```

Composer installation should register autoload/binaries only.

Explicit CLI commands perform application changes.

---

# Composer Scripts

Repository-local Composer scripts may support Forge development.

Do not rely on dependency package scripts executing in consumer projects.

Consumer behavior must work through:

```text
autoload
bin
explicit CLI commands
```

---

# Security / Supply Chain

Before stable public release:

```text
use tagged dependencies
avoid dev-main in stable requires
prefer dist installs
publish from canonical GitHub repositories
protect tags/releases
enable dependency/security scanning where practical
```

Do not add heavy release tooling solely for this spec.

---

# Package Tests Required

Retain/add tests for:

```text
production src has no App\ namespace
ApplicationPath explicit root
ApplicationPath discovery
vendor path rejection
base_path consumer resolution
Composer binary bootstrap
generator writes only to app
skeleton is not production autoload
App\Kernel composes Forge kernel
generated Composer requires Forge
generated README uses vendor/bin/durin
```

---

# Package Contract Test

Add a test reading `composer.json` that asserts at minimum:

```text
name = ereborcodeforge/durins-forge
type = library
php = ^8.5
Forge PSR-4 exists
App\ production PSR-4 does not exist
bin/durin is declared
durin-core compatible require exists
durin-presets compatible require exists
mithrilphp compatible require exists
```

If `durin-architecture` remains a direct package contract, assert it too.

---

# Public Surface Test

Add a lightweight test that ensures the initial public PHP entry points remain autoloadable:

```php
class_exists(ApplicationPath::class)
class_exists(HttpApplicationKernel::class)
```

Do not attempt to freeze every class in `src/`.

---

# README Alignment

README should explain three distinct modes clearly.

## Install framework into existing app

```bash
composer require ereborcodeforge/durins-forge
```

## Create app

Future:

```bash
composer create-project ereborcodeforge/durin-app my-app
```

Do not advertise until `durin-app` exists.

## Global DX

Future:

```bash
composer global require ereborcodeforge/durin-installer
durin new my-app
```

Do not advertise until installer exists.

---

# Package Description Alignment

Current wording that describes Forge as both "framework and project forger" is acceptable for `0.1`.

However, avoid implying that this package is the final global installer.

Long-term terminology:

```text
durins-forge
  framework + project tooling

durin-app
  application skeleton

durin-installer
  global creation UX
```

---

# ADR

Create:

```text
docs/adr/ADR-0006-durins-forge-package-contract.md
```

Record:

```text
canonical Composer name
package role
public API strategy
internal API strategy
CLI as primary DX API
distribution repository decision
versioning
relationship to durin-app
relationship to installer
```

---

# Implementation Phases

## Phase 0 — Audit

Inspect current repository against this specification.

Produce:

```text
docs/refactoring/package-contract/current-state.md
```

Record:

```text
composer metadata
direct dependencies
production imports
public bootstrap imports
binary behavior
root-only Composer fields
package publication status
```

---

## Phase 1 — Public API Definition

Create:

```text
docs/public-api.md
docs/package-architecture.md
```

Classify current consumer-visible PHP types.

Do not refactor implementation unless needed for a clean boundary.

---

## Phase 2 — Composer Contract Cleanup

Align:

```text
description
keywords
direct dependency rationale
repositories strategy
bin contract
autoload
root-only fields
```

Do not change package name.

---

## Phase 3 — Distribution Chain

Ensure all stable direct Durin dependencies are available through the chosen canonical Composer repository.

Preferred:

```text
Packagist
```

Validate package names and tags.

---

## Phase 4 — Preset Output Cleanup

Once Composer package resolution works without root VCS declarations:

update `durin-presets` so generated apps no longer include unnecessary VCS repository entries.

Release a compatible preset patch version if needed.

Then bump Forge constraint if necessary.

---

## Phase 5 — Distribution Smoke

Test:

```text
fresh project
composer require durins-forge
vendor/bin/durin
doctor
status
generators
durin new
generated app composer install
generated app vendor/bin/durin doctor
```

No local sibling repositories.

---

## Phase 6 — Release Readiness

Confirm:

```text
all tests green
distribution smoke green
dependencies tagged
docs current
public API recorded
CI green
```

Then the maintainer may tag:

```text
v0.1.0
```

Release/tag creation requires explicit maintainer action.

---

# Must Do

- treat `durins-forge` as a package, not an app repository;
- keep `App\` consumer-owned;
- keep Tooling Forge-owned;
- document a small public PHP API;
- preserve CLI behavior;
- make Composer dependency resolution reproducible;
- remove long-term dependence on nested VCS repositories;
- validate real consumer installation;
- ensure generated apps depend on Forge;
- keep installer work separate.

---

# Must Not

- recreate extracted core/preset/architecture implementations;
- move Tooling into new packages;
- rename Composer package;
- create `durin-app` in this task;
- create `durin-installer` in this task;
- make all `src/` classes public API;
- rely on dependency `repositories` declarations;
- require Git clone for normal consumers;
- run app mutations during Composer install;
- silently change preset structure.

---

# Blocking Conditions

Stop and document a blocker if:

- a stable Forge dependency cannot be resolved without a custom root repository;
- generated app requires a package version that does not exist;
- `vendor/bin/durin` fails in a true dependency install;
- app generation writes into vendor;
- skeleton imports an internal class not intended to become supported;
- a direct dependency cycle appears;
- `durin-presets` and Forge constraints cannot be made compatible;
- runtime requires Forge to be repository root.

Do not hide these blockers with local-only workarounds.

---

# Acceptance Criteria

The Durin Forge package is considered formally modeled and aligned when:

- [ ] canonical package identity is documented;
- [ ] role and ownership are documented;
- [ ] public API whitelist exists;
- [ ] internal APIs are explicitly non-contractual;
- [ ] CLI contract is documented;
- [ ] consumer root contract is documented;
- [ ] vendor mutation invariant is tested;
- [ ] Composer binary works from dependency install;
- [ ] stable dependency chain resolves without inherited VCS repositories;
- [ ] generated app requires Forge, not internal packages unnecessarily;
- [ ] generated standard app no longer needs Durin VCS entries once packages are published;
- [ ] package contract tests pass;
- [ ] distribution smoke passes;
- [ ] README is aligned;
- [ ] ADR-0006 exists;
- [ ] release is ready for `v0.1.0`.

---

# Definition of Done

The key proof is this flow:

```text
EMPTY DIRECTORY
      ↓
composer require ereborcodeforge/durins-forge:^0.1
      ↓
vendor/bin/durin
      ↓
framework runs against consumer root
      ↓
vendor/bin/durin new sample --preset=minimal
      ↓
cd sample
composer install
      ↓
vendor/bin/durin doctor
      ↓
application is operational
```

No Git clone.

No sibling repositories.

No manual copying of Forge source.

No `App\` namespace owned by the framework.

No writes into Forge's vendor directory.

Once this is true, the package boundary is complete.

The next initiative is then:

```text
ereborcodeforge/durin-app
```

followed by:

```text
ereborcodeforge/durin-installer
```

---

# Agent Execution Instruction

Use this specification to align the existing package, not rewrite it.

Execution order:

```text
1. audit current package
2. document public/internal API
3. normalize Composer package contract
4. resolve distribution chain
5. coordinate any durin-presets distribution cleanup
6. add real clean-install smoke tests
7. update docs/ADR
8. stop before durin-app
```

When a change is required in `durin-presets`, implement it in that canonical package and release a compatible patch.

Do not patch generated Composer behavior locally in Forge if `durin-presets` owns that behavior.
