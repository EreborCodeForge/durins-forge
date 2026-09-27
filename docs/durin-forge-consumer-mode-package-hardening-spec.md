# Durin Forge — Consumer Mode, Namespace & Package Hardening Specification

**Status:** Implementation specification  
**Repository:** `EreborCodeForge/durins-forge`  
**Date:** 2026-09-27  
**Purpose:** prepare Durin Forge to be consumed as a real Composer dependency by a future `durin-app`, while preserving the Forge-owned tooling already validated in the repository.

---

# Mission

Transform `durins-forge` from a repository that still behaves partly as the root application into a Composer-installable framework package.

The target dependency direction is:

```text
future application
    │
    └── ereborcodeforge/durins-forge
            │
            ├── durin-core
            ├── durin-presets
            ├── durin-architecture
            ├── mithrilphp
            └── other framework dependencies
```

The application owns:

```text
App\
App\Kernel
durin.yaml
config/
routes/
public/
.env
application src/
```

Durin Forge owns:

```text
EreborCodeForge\Durin\Forge\
Console
Tooling
Framework/Core
framework Infrastructure
CLI orchestration
Doctor
Generators
Graph
Runtime integration
```

The framework MUST NOT claim the application's `App\` namespace.

---

# Validated Current State

The repository was inspected before this specification.

Current package integration is already complete:

```text
ereborcodeforge/durin-core          ^0.1
ereborcodeforge/durin-presets       ^0.1
ereborcodeforge/durin-architecture  ^0.1
```

Current Forge-owned tooling remaining in `src/Tooling/` is:

```text
Tooling/
├── Doctor/
├── Generators/
├── Graph/
└── Runtime/
```

This is correct.

These directories MUST NOT be removed simply because project/preset/architecture concerns were extracted.

They represent Forge-level developer experience and orchestration.

---

# Decision — Should `Tooling` Still Exist?

Yes.

The remaining `Tooling` code is intentional.

Ownership:

```text
Doctor
  -> Durin Forge

Generators
  -> Durin Forge
     using durin-core ScaffoldPlan/ScaffoldWriter

Graph
  -> Durin Forge
     using durin-core project metadata

Runtime
  -> Durin Forge
     adapting Mithril/Eregion
```

What must no longer exist inside Forge Tooling:

```text
Project model
Manifest implementation
generic Scaffold primitives
Preset implementations
Preset registry implementation
Architecture planners/detectors duplicated from packages
```

Those concerns are already owned by:

```text
durin-core
durin-presets
durin-architecture
```

Therefore the desired remaining tree is valid:

```text
src/
└── Tooling/
    ├── Doctor/
    ├── Generators/
    ├── Graph/
    └── Runtime/
```

The problem is the namespace and package/runtime ownership, not the existence of this directory.

---

# Primary Problems To Solve

The current repository still has root-application assumptions that prevent clean consumption through Composer.

The implementation must address all of the following.

## P0 — Framework uses the `App\` namespace

Current Composer autoload conceptually contains:

```json
{
  "autoload": {
    "psr-4": {
      "App\\": "src/"
    }
  }
}
```

Framework classes currently use namespaces such as:

```text
App\Console
App\Core
App\Infrastructure
App\Tooling
App\Kernel
```

This creates a namespace collision when Durin Forge is installed into an application that also owns:

```text
App\
```

Target framework namespace:

```text
EreborCodeForge\Durin\Forge\
```

---

## P0 — Framework determines base path from its own source location

Current helper behavior derives project root using the physical Forge source directory.

That works when Forge is the root repository.

It fails when installed as:

```text
my-app/
└── vendor/
    └── ereborcodeforge/
        └── durins-forge/
```

The application root must be resolved independently from package installation path.

---

## P0 — `bin/durin` assumes Forge is the root package

The CLI currently loads an autoloader relative to the Forge repository.

When installed under `vendor/`, the command must run against the consuming application's autoloader and application root.

The Composer `bin` mechanism must become the canonical distribution mechanism.

---

## P0 — Generated projects do not currently consume Durin Forge

Current generated fixture Composer files require Mithril directly but not Durin Forge.

At the same time generated README instructions refer to commands such as:

```bash
php bin/durin doctor
php bin/durin dev
```

while generated projects do not contain that binary.

This contract must be aligned before creating `durin-app`.

---

# Non-Goals

Do NOT in this task:

- create `durin-app`;
- create `durin-installer`;
- introduce new presets;
- introduce architecture `adopt/evolve` CLI;
- redesign `durin-core`;
- redesign `durin-presets` **APIs** (classes, registry, preset identifiers);
- redesign `durin-architecture`;
- move Doctor into a new package;
- move Generators into a new package;
- move Graph into a new package;
- move Runtime into a new package;
- introduce Jev or an LLM;
- redesign Mithril;
- redesign Eregion;
- change generated architecture semantics;
- change preset identifiers;
- introduce additional Composer packages.

**Allowed Exception — preset template contract:** coordinated updates to `durin-presets` embedded scaffold templates (`composer.json` / README / minimal HTTP bootstrap strings in `PresetScaffoldSupport`) are required so generated apps consume `ereborcodeforge/durins-forge` and document `vendor/bin/durin`. That is template alignment, not an API redesign.

This is package hardening and ownership alignment only.

---

# Target Composer Identity

Change Durin Forge from a root application-oriented package into a reusable Composer library.

Target concept:

```json
{
  "name": "ereborcodeforge/durins-forge",
  "type": "library",
  "autoload": {
    "psr-4": {
      "EreborCodeForge\\Durin\\Forge\\": "src/"
    },
    "files": [
      "src/Support/helpers.php"
    ]
  },
  "bin": [
    "bin/durin"
  ]
}
```

Use the actual dependencies and versions already present.

Do not replace the complete real `composer.json` with this snippet.

---

# Target Namespace

All framework-owned PHP production code MUST use:

```text
EreborCodeForge\Durin\Forge\
```

Examples:

```text
EreborCodeForge\Durin\Forge\Console\Application
EreborCodeForge\Durin\Forge\Console\Commands\DoctorCommand
EreborCodeForge\Durin\Forge\Tooling\Doctor\DoctorRunner
EreborCodeForge\Durin\Forge\Tooling\Generators\FeatureGenerator
EreborCodeForge\Durin\Forge\Tooling\Graph\DependencyGraphAssembler
EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimeFacade
```

Do NOT keep production framework code under:

```text
App\
```

---

# Physical Folder Policy

Changing the namespace does NOT require unnecessary folder churn.

The following physical layout is acceptable:

```text
src/
├── Console/
├── Core/
├── Infrastructure/
├── Tooling/
│   ├── Doctor/
│   ├── Generators/
│   ├── Graph/
│   └── Runtime/
└── Support/
```

with namespace:

```text
EreborCodeForge\Durin\Forge\...
```

Do not rename `Tooling` merely for aesthetics.

---

# Ownership Classification Before Refactor

Before changing namespaces, inspect every production file under:

```text
src/
config/
routes/
public/
resources/
bin/
```

Classify each as exactly one of:

```text
FRAMEWORK
APPLICATION
TEMPLATE/SKELETON
TEST/DEMO
```

Record:

```text
docs/refactoring/package-hardening/ownership-map.md
```

Format:

```text
path
current namespace
classification
target location
target namespace
action
```

Example:

```text
src/Tooling/Doctor/DoctorRunner.php
FRAMEWORK
keep src/Tooling/Doctor
EreborCodeForge\Durin\Forge\Tooling\Doctor
rename namespace/imports

src/Kernel.php
APPLICATION / TEMPLATE
must not remain as Forge-owned App\Kernel
move to skeleton/template strategy
```

Do not guess classifications.

Inspect consumers first.

---

# Application-Owned Code

Code representing the user's application MUST NOT remain part of the framework namespace.

Current examples requiring explicit classification include:

```text
src/Kernel.php
config/app.php
routes/*
public/index.php
.env.example
application providers
application-specific bootstrap
```

These files may be:

1. moved into a framework-owned skeleton/template resource temporarily; or
2. retained only in a dedicated fixture/example application; or
3. later moved into `durin-app`.

For this task, prefer a framework-local skeleton resource where required by existing `durin new`.

Recommended temporary ownership:

```text
resources/
└── skeleton/
    └── application/
```

Do NOT autoload skeleton `App\` code as framework classes.

---

# Framework-Owned `Core`

The existing physical `src/Core/` directory is not the same responsibility as Composer package `durin-core`.

This naming overlap is allowed temporarily but must be documented.

Distinction:

```text
ereborcodeforge/durin-core
  -> project/tooling primitives

durins-forge/src/Core
  -> framework HTTP/config/provider/etc. implementation
```

The agent MUST NOT move Forge `src/Core` into the `durin-core` package merely because both are named "Core".

Rename the folder only if there is a concrete clarity benefit and the refactor remains small.

Namespace should become:

```text
EreborCodeForge\Durin\Forge\Core\...
```

---

# Framework-Owned `Infrastructure`

Inspect every class in:

```text
src/Infrastructure/
```

Keep in Forge when it is reusable framework infrastructure, for example:

```text
cache integration
database bridge
session implementation
security framework adapter
view infrastructure
Mithril/Eregion bridges
```

Move to skeleton/application ownership if it contains business/application-specific assumptions.

Do not extract another package in this task.

Target namespace for retained classes:

```text
EreborCodeForge\Durin\Forge\Infrastructure\...
```

---

# Phase 0 — Baseline

Before modifications:

```bash
composer validate --strict --no-check-publish
composer install
composer test
```

Record:

```text
current test count
assertion count
known deprecations
CI status
generated preset fixture hashes/tree
CLI behavior
```

Current documented post-package-integration baseline includes 111 green tests.

Verify the real current number before implementation.

Create:

```text
docs/refactoring/package-hardening/baseline.md
```

---

# Phase 1 — Namespace Migration

Migrate framework-owned code from:

```text
App\...
```

to:

```text
EreborCodeForge\Durin\Forge\...
```

Recommended sequence:

```text
1. Tooling
2. Console
3. Core framework code
4. Infrastructure
5. tests
```

Run tests between groups.

Do NOT combine application/skeleton extraction into the same commit as every namespace change.

---

# Phase 1A — Tooling

The image-validated current tree remains:

```text
Tooling/
├── Doctor/
├── Generators/
├── Graph/
└── Runtime/
```

Change namespaces only.

Examples:

```text
App\Tooling\Doctor
→
EreborCodeForge\Durin\Forge\Tooling\Doctor
```

```text
App\Tooling\Generators
→
EreborCodeForge\Durin\Forge\Tooling\Generators
```

```text
App\Tooling\Graph
→
EreborCodeForge\Durin\Forge\Tooling\Graph
```

```text
App\Tooling\Runtime
→
EreborCodeForge\Durin\Forge\Tooling\Runtime
```

Preserve imports from:

```text
EreborCodeForge\Durin\Core\
EreborCodeForge\Durin\Presets\
EreborCodeForge\Durin\Architecture\
```

Do not reimplement those dependencies.

---

# Phase 1B — Console

Migrate:

```text
App\Console\
→
EreborCodeForge\Durin\Forge\Console\
```

All CLI commands remain owned by Forge.

Examples:

```text
NewCommand
DoctorCommand
StatusCommand
DevCommand
ServeCommand
OptimizeCommand
GraphDependenciesCommand
MakeModuleCommand
MakeUseCaseCommand
MakeFeatureCommand
DB migration commands
```

Do not change command names or arguments.

---

# Phase 1C — Framework Core

Migrate reusable framework classes currently under:

```text
App\Core\
```

to:

```text
EreborCodeForge\Durin\Forge\Core\
```

Preserve behavior.

Review:

```text
Attributes
Cache
Container
Contracts
HTTP
Providers
Routing
Exceptions
Discovery
Descriptors
ServiceProvider
```

Application-specific discovery assumptions must be made configurable rather than hardcoded to Forge's old `App\` source directory.

---

# Phase 1D — Infrastructure

Migrate retained reusable infrastructure from:

```text
App\Infrastructure\
```

to:

```text
EreborCodeForge\Durin\Forge\Infrastructure\
```

Update helpers/providers/tests accordingly.

---

# Phase 2 — Application Root Contract

Introduce one explicit application-root abstraction.

Do NOT let framework classes derive the application root from:

```php
__DIR__
dirname(__DIR__, ...)
```

inside vendor code.

Recommended minimal abstraction:

```php
final class ApplicationPath
{
    public static function setRoot(string $root): void;
    public static function root(): string;
    public static function path(string $path = ''): string;
}
```

or an equivalent small service.

Do not build a service locator.

---

# Application Root Resolution

Preferred precedence:

```text
1. explicitly provided application root
2. project discovery from current working directory
3. fail clearly
```

Use `durin-core` project discovery where appropriate.

Do NOT silently fall back to the package installation directory.

---

# `base_path()` Compatibility

If `base_path()` remains public DX, keep it as a thin compatibility helper.

Target:

```php
function base_path(string $path = ''): string
{
    return ApplicationPath::path($path);
}
```

The helper must not determine root itself.

This preserves existing application DX while removing vendor-path coupling.

---

# Phase 3 — Composer Binary Consumer Mode

Make Composer's `bin` feature the canonical mechanism.

Expected consumer:

```text
my-app/
└── vendor/
    └── bin/
        └── durin
```

Do not require copying `bin/durin` into every app.

---

# `bin/durin` Requirements

The binary must:

```text
load the consuming Composer project
resolve application root
bootstrap Forge Console Application
run against the consumer project
```

It must work when physically located under:

```text
vendor/ereborcodeforge/durins-forge/bin/durin
```

Do not assume:

```text
../vendor/autoload.php
```

means the consuming application's autoloader.

Implement Composer-vendor-safe bootstrap.

---

# Remove Root-Only Bin Linking

The current root package workaround:

```text
scripts/link-vendor-bins.php
```

exists because root package binaries are not linked to its own `vendor/bin`.

After Forge becomes a normal dependency, this should no longer be part of the consumer contract.

Determine whether it is still useful only for Forge repository development.

Preferred final state:

```text
consumer applications
  -> Composer automatically exposes vendor/bin/durin

Forge repository development
  -> php bin/durin
```

Do not mutate consumer `vendor/bin` manually from Composer scripts.

---

# Phase 4 — Separate App Kernel From Framework Package

Current:

```text
src/Kernel.php
namespace App;
```

This cannot remain as a framework-owned autoloaded class.

Classify it as application/skeleton code.

Preferred target:

```text
resources/skeleton/application/src/Kernel.php
```

or another explicit skeleton location.

The final consumer application will own:

```text
App\Kernel
```

---

# Framework Reuse From App Kernel

Avoid duplicating large framework internals into the skeleton.

Where practical, extract reusable behavior into framework classes.

Example direction:

```text
App\Kernel
    ↓
uses / extends / composes
EreborCodeForge\Durin\Forge\...
```

Do not force inheritance if composition/current contracts are simpler.

The application kernel should remain small.

---

# Phase 5 — Separate Application Bootstrap Assets

Review current root:

```text
config/
routes/
public/
resources/views/
.env.example
```

Anything that exists because Forge currently acts as an application root must be classified as application skeleton/example.

Framework package resources may remain when they are genuine framework resources.

Target distinction:

```text
src/
  framework code

resources/framework/
  framework-owned resources if any

resources/skeleton/application/
  future app bootstrap files
```

Avoid a hidden mixture.

---

# Phase 6 — Align Generated Presets

Generated applications currently contain a Composer project but do not require Durin Forge.

**Canonical change location:** `ereborcodeforge/durin-presets` (`PresetScaffoldSupport` and related templates). Forge then bumps the presets constraint and regenerates fixtures. Do not post-process scaffold plans inside Forge `NewCommand`.

Change generated application Composer plans so application projects consume:

```text
ereborcodeforge/durins-forge
```

directly.

Avoid requiring internal packages directly unless the generated app itself imports them.

Target dependency:

```text
generated app
    ↓
durins-forge
    ↓
durin-core / presets / architecture / mithril
```

---

# Generated Composer Contract

Conceptual:

```json
{
  "type": "project",
  "require": {
    "php": "^8.5",
    "ereborcodeforge/durins-forge": "^<compatible-version>"
  },
  "autoload": {
    "psr-4": {
      "App\\": "src/"
    }
  }
}
```

Do not hardcode an imaginary version.

Use the actual supported Forge version/release strategy.

---

# Generated CLI Contract

Generated README must use:

```bash
vendor/bin/durin doctor
vendor/bin/durin dev
```

or:

```bash
./vendor/bin/durin doctor
```

Do NOT document:

```bash
php bin/durin ...
```

unless the generated application actually owns `bin/durin`.

Preferred model:

```text
Durin binary comes from Composer dependency.
```

---

# Phase 7 — Generated HTTP Bootstrap

Current minimal generated `public/index.php` is a 503 placeholder.

Before `durin-app`, define one canonical minimal application bootstrap that is actually runnable through the supported runtime.

Do not create a second HTTP server.

Target remains:

```text
application bootstrap
    ↓
Mithril worker
    ↓
Eregion
```

The generated bootstrap should reuse Forge/Mithril contracts.

Preserve the runtime boundary.

---

# Worker Preset

Worker applications should also consume Forge as a dependency if they use Forge CLI/DX.

Do not force Eregion into worker mode.

Keep:

```text
worker
  -> Mithril job runtime
  -> no public HTTP requirement
```

---

# Phase 8 — Test Consumer Installation

Create a temporary consumer fixture outside the Forge source tree.

Structure:

```text
/tmp/durin-consumer/
├── composer.json
├── durin.yaml
├── src/
├── config/
├── routes/
└── public/
```

Install Forge as a Composer dependency.

The test must prove the framework does not depend on being repository root.

---

# Mandatory Consumer Tests

From the consumer project, validate:

```bash
composer install
vendor/bin/durin
vendor/bin/durin doctor
vendor/bin/durin status
vendor/bin/durin optimize
vendor/bin/durin graph:dependencies
```

Where runtime is available:

```bash
vendor/bin/durin dev
```

Generators:

```bash
vendor/bin/durin make:module Billing
vendor/bin/durin make:usecase CreateInvoice --module=Billing
```

Validate all writes occur in the consumer project, never inside:

```text
vendor/ereborcodeforge/durins-forge/
```

This is a mandatory safety test.

---

# Vendor Mutation Safety

Add a regression test that fails if a Forge command writes into its own package directory.

Invariant:

```text
all user project mutations:
  target path starts with application root

never:
  vendor/ereborcodeforge/durins-forge
```

---

# Phase 9 — Package Tests

Existing Forge tests must remain green.

Add tests specifically for:

```text
framework namespace contains no production App\ classes
consumer root resolution
vendor bin execution
base_path() resolves consumer root
generated app requires Forge
generated README commands exist
no write into vendor package
preset fixture equivalence
```

---

# Namespace Boundary Test

Add an automated check:

Production Forge PHP files under `src/` must not declare:

```text
namespace App
```

Exception:

```text
none
```

Skeleton/template files stored as plain resources may contain `App\`.

They must not be autoloaded as Forge source.

---

# Application Namespace Boundary

The future consumer application exclusively owns:

```text
App\
```

Framework package exclusively owns:

```text
EreborCodeForge\Durin\Forge\
```

Package libraries retain:

```text
EreborCodeForge\Durin\Core\
EreborCodeForge\Durin\Presets\
EreborCodeForge\Durin\Architecture\
```

No overlap.

---

# Target Source Tree

Recommended end state:

```text
durins-forge/
├── src/
│   ├── Console/
│   │   ├── Application.php
│   │   └── Commands/
│   │
│   ├── Core/
│   │   ├── Http/
│   │   ├── Routing/
│   │   ├── Providers/
│   │   └── ...
│   │
│   ├── Infrastructure/
│   │   └── ...
│   │
│   ├── Tooling/
│   │   ├── Doctor/
│   │   ├── Generators/
│   │   ├── Graph/
│   │   └── Runtime/
│   │
│   └── Support/
│       ├── ApplicationPath.php
│       └── helpers.php
│
├── bin/
│   └── durin
│
├── resources/
│   └── skeleton/
│       └── application/
│
├── tests/
└── composer.json
```

All PHP source under `src/`:

```text
EreborCodeForge\Durin\Forge\...
```

---

# Keep Tooling — Explicit Rule

Do NOT remove:

```text
src/Tooling/Doctor
src/Tooling/Generators
src/Tooling/Graph
src/Tooling/Runtime
```

unless a separate future package extraction is explicitly approved.

These are currently correct Forge responsibilities.

A future extraction may be considered only if:

```text
another product needs the subsystem independently
the subsystem has a stable API
it has an independent release reason
it is useful without Forge
```

Do not extract for folder cleanliness.

---

# README Alignment

Update README after implementation.

Remove stale statement that says tooling has no Composer packages.

Document current ownership:

```text
durin-core
durin-presets
durin-architecture
durins-forge Tooling
```

Normalize Mithril version documentation with actual Composer constraint.

Document consumer-mode binary:

```bash
vendor/bin/durin
```

Do not advertise `durin-app` yet.

---

# ADR

Create:

```text
docs/adr/ADR-0005-forge-consumer-mode.md
```

Required decisions:

```text
Forge is a Composer library
Forge owns no App\ namespace
consumer owns application root
Tooling remains Forge-owned
application skeleton is separate from framework source
Composer bin is canonical
base_path uses explicit application context
durin-app remains next step, not part of this ADR
```

---

# Migration Sequence

Execute in this order:

```text
0. baseline
1. ownership map
2. Tooling namespace migration
3. Console namespace migration
4. Core/Infrastructure namespace migration
5. application root abstraction
6. Composer binary consumer mode
7. separate App\Kernel/application assets
8. align generated Composer projects
9. align generated CLI docs
10. consumer-project integration test
11. README/ADR cleanup
12. full regression
```

Do not reorder steps in a way that creates a long-lived broken branch.

---

# Commit Strategy

Suggested:

```text
docs: record package-hardening baseline and ownership map

refactor: move tooling to Forge namespace
refactor: move console to Forge namespace
refactor: move framework core to Forge namespace
refactor: move infrastructure to Forge namespace

refactor: introduce application root abstraction
refactor: make durin binary consumer-aware

refactor: separate application kernel from framework source
refactor: separate app bootstrap assets

refactor: make generated apps depend on durins-forge
docs: align generated CLI instructions with vendor bin

test: add composer consumer-mode integration coverage
docs: document Forge consumer ownership
```

Keep commits reviewable.

---

# Compatibility

Preserve:

```text
durin command names
command options
preset ids
durin.yaml schema
generated architecture semantics
Doctor result semantics
Graph formats
Mithril/Eregion runtime contracts
```

Internal PHP namespace changes are expected.

If the old `App\Tooling` namespace was documented as public API, add a short migration note.

Do not add long-lived class aliases unless there is confirmed external usage.

---

# Blocking Conditions

Stop and report before proceeding if:

- a framework class fundamentally requires `App\` namespace;
- runtime bootstrap cannot receive an explicit application root;
- Composer binary cannot resolve consumer autoload safely;
- generated app requires copying framework implementation;
- separating `App\Kernel` changes public runtime behavior unexpectedly;
- consumer commands write to vendor paths;
- package tests and consumer tests disagree about root resolution;
- changing package type breaks current Composer distribution unexpectedly.

Do not solve blockers by duplicating framework code into the generated app.

---

# Acceptance Criteria

The task is complete when:

- [ ] `composer.json` represents Forge as a reusable library/package;
- [ ] production `src/` no longer uses `App\` namespace;
- [ ] Forge production namespace is `EreborCodeForge\Durin\Forge\`;
- [ ] `Tooling/Doctor` remains and works;
- [ ] `Tooling/Generators` remains and works;
- [ ] `Tooling/Graph` remains and works;
- [ ] `Tooling/Runtime` remains and works;
- [ ] application root is independent from package install path;
- [ ] `base_path()` resolves consumer application root;
- [ ] Composer-installed `vendor/bin/durin` works;
- [ ] consumer commands do not mutate Forge's vendor directory;
- [ ] `App\Kernel` is application/skeleton-owned, not framework autoload;
- [ ] generated apps require Durin Forge when they depend on Forge DX;
- [ ] generated README instructions invoke a binary that actually exists;
- [ ] generated preset fixtures remain semantically equivalent;
- [ ] all existing tests pass;
- [ ] consumer-mode integration tests pass;
- [ ] CI remains green on PHP 8.5;
- [ ] README no longer documents stale package ownership;
- [ ] ADR records the consumer-mode decision.

---

# Definition of Done

The repository is ready for the next package (`durin-app`) only when this command model is possible:

```text
consumer application
    ↓
composer require ereborcodeforge/durins-forge
    ↓
vendor/bin/durin doctor
vendor/bin/durin dev
vendor/bin/durin make:feature ...
```

without:

```text
git clone durins-forge
copying Forge source into App\
manual vendor/bin linking
framework code using App\ namespace
framework code resolving vendor directory as application root
```

At that point the next specification may create:

```text
ereborcodeforge/durin-app
```

as a minimal application skeleton.

`durin-installer` remains after `durin-app`.
