# Durin Forge — Composer Packages Integration & Internal Code Removal Specification

**Status:** Implementation specification  
**Repository:** `EreborCodeForge/durins-forge`  
**Target packages:**
- `EreborCodeForge/durin-core`
- `EreborCodeForge/durin-presets`
- `EreborCodeForge/durin-architecture`

**Primary goal:** refactor Durin Forge to consume the three extracted Composer packages as the authoritative implementation, migrate consumers incrementally, validate behavior after each package, and remove the duplicated implementation from the framework.

**Important:** this is an integration/refactoring task, not a redesign.

---

# Mission

Replace the internal implementations already extracted from Durin Forge with the corresponding Composer packages:

```text
durin-core
durin-presets
durin-architecture
```

The migration MUST happen incrementally and in this order:

```text
1. durin-core
2. durin-presets
3. durin-architecture
```

For each package:

```text
install
  ↓
inspect current internal implementation
  ↓
map old classes → package classes
  ↓
migrate consumers
  ↓
run tests
  ↓
verify behavior
  ↓
remove duplicated internal implementation
  ↓
run tests again
```

Durin Forge must remain functional after every phase.

---

# Context

The architecture/preset functionality was previously implemented directly inside Durin Forge.

Those responsibilities have now been extracted into separate repositories:

```json
{
  "type": "vcs",
  "url": "https://github.com/EreborCodeForge/durin-core"
},
{
  "type": "vcs",
  "url": "https://github.com/EreborCodeForge/durin-presets"
},
{
  "type": "vcs",
  "url": "https://github.com/EreborCodeForge/durin-architecture"
}
```

The next step is NOT to duplicate new functionality.

The next step is to make these packages the source of truth used by Durin Forge.

---

# Desired End State

Target dependency model:

```text
durin-forge
    │
    ├── durin-core
    │
    ├── durin-presets
    │      └── durin-core
    │
    └── durin-architecture
           ├── durin-core
           └── durin-presets
```

Durin Forge remains responsible for:

```text
CLI
command orchestration
framework bootstrap
terminal output
runtime integration
Mithril integration
Eregion integration
doctor
status
dev
serve
optimize
dependency graph UI/output
```

The packages become responsible for:

```text
durin-core
  project model
  manifest
  scaffold primitives
  generic safe mutations

durin-presets
  preset definitions
  preset registry
  preset resolution
  preset-specific scaffold plans

durin-architecture
  architecture detection
  drift
  adopt
  evolve
  migrate
  architecture transition planning
```

---

# Core Principle

There MUST be only one authoritative implementation of each concern after migration.

Final state must NOT contain:

```text
Durin Forge internal ServicePreset
+
durin-presets ServicePreset
```

or:

```text
Durin Forge internal MigrationPlanner
+
durin-architecture MigrationPlanner
```

Temporary duplication is allowed only during an active migration phase.

It must be removed before moving to the next package.

---

# Non-Goals

Do NOT:

- create `durin-app` yet;
- create `durin-installer` yet;
- change CLI syntax;
- rename public presets;
- redesign the preset catalog;
- introduce new architecture types;
- change `durin.yaml` schema;
- change generated application structure;
- change runtime ownership;
- refactor Mithril/Eregion;
- add Jev;
- add LLM integration;
- publish additional packages;
- redesign package APIs unless integration is impossible otherwise;
- migrate all three packages in a single change;
- remove internal implementations before package consumers are migrated.

---

# Implementation Rule: Inspect Before Editing

The agent MUST inspect the current Durin Forge repository and the three package repositories before making integration changes.

Do not assume class names or paths from this specification.

This specification defines responsibilities and migration order.

The current repositories define the actual implementation.

Before each package integration, produce a map:

```text
OLD CLASS / FILE
TARGET PACKAGE CLASS
CURRENT CONSUMERS
ACTION
```

Example:

```text
OLD
src/Tooling/Project/ProjectDiscovery.php

NEW
EreborCodeForge\Durin\Core\Project\ProjectDiscovery

CONSUMERS
NewCommand
DoctorProjectCheck
PresetContextFactory

ACTION
replace imports and DI binding
```

---

# Phase 0 — Baseline Before Refactoring

Before modifying Composer dependencies or imports, establish the current baseline.

Run all existing tests.

At minimum:

```bash
composer validate
composer install
composer test
```

Use the real project commands if different.

Also validate currently implemented CLI flows.

Recommended:

```bash
durin --help
durin new --help
durin doctor --help
durin status --help
durin optimize --help
durin graph:dependencies --help
```

Where implemented, also test:

```bash
durin adopt --help
durin evolve --help
durin migrate --help
```

Record failures that already exist before migration.

Do not attribute pre-existing failures to the package migration.

---

# Baseline Fixture Applications

Create or reuse temporary fixtures that represent current behavior.

Recommended fixtures:

```text
tests/Fixtures/
├── generated-minimal/
├── generated-service/
├── generated-modular/
├── generated-microservice/
├── generated-worker/
├── existing-project/
├── modified-by-user/
├── architecture-drift/
└── migration-project/
```

Only include presets/features that actually exist in the current implementation.

Capture:

```text
generated file tree
durin.yaml
composer.json mutations
important generated file contents
command exit codes
important CLI output
```

These fixtures become regression evidence after package integration.

---

# Composer Preparation

Ensure the VCS repositories are configured in Durin Forge.

Example:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/EreborCodeForge/durin-core"
    },
    {
      "type": "vcs",
      "url": "https://github.com/EreborCodeForge/durin-presets"
    },
    {
      "type": "vcs",
      "url": "https://github.com/EreborCodeForge/durin-architecture"
    }
  ]
}
```

During development, branch versions may be used:

```json
"ereborcodeforge/durin-core": "dev-main"
```

Once packages have tags, prefer tagged constraints such as:

```json
"ereborcodeforge/durin-core": "^0.1"
```

Do not tag or change versions merely to satisfy this spec.

Use the versions actually available.

---

# Phase 1 — Integrate `durin-core`

## Goal

Make `durin-core` the authoritative implementation for project, manifest and generic scaffold primitives.

Do not touch preset or architecture integration yet except where required to compile against the new core contracts.

## Phase 1.1 — Inspect `durin-core`

Inspect:

```text
composer.json
autoload namespace
public classes
interfaces
DTOs/value objects
tests
dependencies
```

Verify:

```text
package name
PHP requirement
namespace root
branch/tag availability
```

Check package installation independently if possible.

## Phase 1.2 — Inventory Internal Core Responsibilities

Find the current Durin Forge implementations related to:

```text
Project
ProjectDiscovery
ProjectPaths
DurinManifest
manifest reader
manifest writer
manifest validator
capabilities
ScaffoldPlan
ScaffoldOperation
ScaffoldConflict
ScaffoldResult
ScaffoldWriter
generic filesystem mutation
Preset contract, if owned by core package
```

Create:

```text
docs/refactoring/composer-packages/durin-core-map.md
```

Required columns:

```text
current file
current namespace
target class
target namespace
consumers
migration strategy
status
```

## Phase 1.3 — Install `durin-core`

Install only core first.

Example:

```bash
composer require ereborcodeforge/durin-core:dev-main
```

Use the actual package name/constraint.

Verify:

```bash
composer show ereborcodeforge/durin-core
composer dump-autoload
```

Do not install the other two packages manually in this phase unless Composer brings them as actual dependencies.

## Phase 1.4 — Migrate Core Consumers Incrementally

Recommended migration order:

```text
1. value objects / DTOs
2. manifest
3. project discovery/model
4. scaffold plan
5. safe mutation / scaffold writer
6. contracts
```

For each group:

```text
replace import
replace DI/container binding if required
adapt constructor signatures minimally
run focused tests
run static analysis
```

Do not perform unrelated refactors.

## Phase 1.5 — Dependency Injection / Composition Root

If Forge registers internal implementations in a container, update bindings.

Example conceptual change:

```text
BEFORE

ProjectDiscoveryInterface
    -> ForgeProjectDiscovery

AFTER

ProjectDiscoveryInterface
    -> Durin Core ProjectDiscovery
```

Prefer package classes directly when no Forge adapter is necessary.

Do not create wrappers merely to preserve an internal namespace.

Use adapters only where Forge-specific behavior genuinely exists.

## Phase 1.6 — Compatibility Bridges

A temporary bridge is allowed only when direct migration is unsafe.

Rules:

```text
bridge contains no business logic
bridge is marked temporary
new code cannot depend on bridge
bridge removal is part of this phase
```

## Phase 1.7 — Validate Core Integration

Run:

```text
core unit tests
Forge unit tests
Forge integration tests
generation regression tests
manifest tests
conflict tests
idempotency tests
```

Validate especially:

```text
durin.yaml parsing unchanged
durin.yaml writing unchanged
project discovery unchanged
scaffold plans unchanged
existing files are not overwritten
conflicts remain explicit
dry-run behavior remains unchanged if implemented
```

## Phase 1.8 — Remove Internal Core Implementation

Only after all consumers use the package.

Delete duplicated Forge classes for migrated responsibilities.

Then search the repository for old namespaces.

Do not leave dead duplicated classes.

## Phase 1.9 — Core Gate

Do NOT begin presets until all are true:

- [ ] `durin-core` installed through Composer;
- [ ] Forge production code uses it;
- [ ] Forge tests use it;
- [ ] generated applications remain equivalent;
- [ ] internal duplicate core code removed;
- [ ] no compatibility bridge remains unless explicitly documented;
- [ ] full test suite green or only known baseline failures remain.

---

# Phase 2 — Integrate `durin-presets`

## Goal

Make `durin-presets` the authoritative source for preset definitions, preset registry and deterministic preset resolution.

The Forge CLI must orchestrate presets but not implement them.

## Phase 2.1 — Inspect Package

Inspect the real package.

Determine:

```text
available presets
canonical identifiers
registry class
resolver class
template/resource locations
dependencies
namespace
```

Do not assume every preset discussed in design conversations exists.

Use the implementation as source of truth.

## Phase 2.2 — Inventory Existing Forge Presets

Find all internal code related to:

```text
PresetRegistry
MinimalPreset
ServicePreset
ModularPreset
MicroservicePreset
WorkerPreset
other implemented presets
type → preset mapping
preset aliases
preset templates
preset-specific scaffold logic
```

Create:

```text
docs/refactoring/composer-packages/durin-presets-map.md
```

Include:

```text
preset id
current implementation
target package class
templates/resources
consumers
public behavior
migration status
```

## Phase 2.3 — Install Package

Example:

```bash
composer require ereborcodeforge/durin-presets:dev-main
```

Verify:

```bash
composer show ereborcodeforge/durin-presets
```

Confirm Composer resolves its `durin-core` dependency correctly.

Do not add duplicate versions of core.

## Phase 2.4 — Centralize Preset Registry

Durin Forge MUST stop constructing specific presets throughout the framework.

Avoid:

```php
new MinimalPreset();
new ServicePreset();
```

spread across commands/services.

Target:

```text
PresetRegistry
      ↓
get(name)
      ↓
Preset
```

There must be one authoritative registry.

## Phase 2.5 — Type → Preset Resolution

If the current implementation supports application types such as:

```text
api
webhook
worker
consumer
cli
microservice
```

move/consume that resolution from `durin-presets`.

Forge must not maintain another map.

Conceptual flow:

```text
type
 ↓
ApplicationPresetResolver
 ↓
preset name
 ↓
PresetRegistry
 ↓
Preset
```

Explicit preset selection must override default resolution if that is current behavior:

```text
--preset
   >
type default
```

Do not introduce new type mappings in this integration.

## Phase 2.6 — Templates and Resources

Ensure preset-specific templates are loaded from the package.

Do not leave a second copy in Forge.

Verify package resource paths work when installed under:

```text
vendor/ereborcodeforge/durin-presets/
```

Do not rely on repository-relative paths that only work during local development.

This test is mandatory.

## Phase 2.7 — Validate `durin new`

Run all existing supported presets.

Use the actual CLI syntax.

Compare with baseline fixtures.

Validate:

```text
same paths
same files
same manifest
same Composer changes
same important contents
same exit behavior
```

Minor nondeterministic values such as timestamps must be normalized in snapshot comparison.

## Phase 2.8 — Remove Internal Preset Implementation

Delete migrated internal:

```text
preset classes
registry
resolver
templates
preset maps
aliases duplicated in Forge
```

Search for direct construction of preset classes.

Final Forge code should depend primarily on contracts/registry.

## Phase 2.9 — Presets Gate

Do NOT begin architecture until:

- [ ] `durin-presets` installed;
- [ ] all current presets resolve through package;
- [ ] Forge has one registry;
- [ ] preset resources load from vendor installation;
- [ ] `durin new` regression fixtures match baseline;
- [ ] internal preset implementations removed;
- [ ] tests green.

---

# Phase 3 — Integrate `durin-architecture`

## Goal

Make `durin-architecture` authoritative for architecture understanding and transition planning.

Forge keeps commands and output.

## Phase 3.1 — Inspect Package

Inspect actual implementations for:

```text
ArchitectureState
ArchitectureDetector
DetectionResult
DriftDetector
DriftReport
AdoptionPlanner
EvolutionPlanner
MigrationPlanner
MigrationPlan
MigrationOperation
transition rules
```

Only migrate what really exists.

Do not implement missing features while integrating.

## Phase 3.2 — Inventory Forge Architecture Code

Create:

```text
docs/refactoring/composer-packages/durin-architecture-map.md
```

Map all existing:

```text
detectors
planners
transition rules
DTOs
result models
CLI dependencies
filesystem dependencies
rendering dependencies
```

Flag classes that mix multiple responsibilities:

```text
planner + terminal output
planner + filesystem writes
planner + Composer execution
```

## Phase 3.3 — Install Package

Example:

```bash
composer require ereborcodeforge/durin-architecture:dev-main
```

Verify:

```bash
composer show ereborcodeforge/durin-architecture
composer show ereborcodeforge/durin-core
composer show ereborcodeforge/durin-presets
```

Confirm there is one resolved version of each package.

## Phase 3.4 — Migrate Structured Models First

Migrate consumers of:

```text
ArchitectureState
ArchitectureTarget
DetectionResult
DriftReport
MigrationPlan
MigrationOperation
EvolutionPlan
AdoptionPlan
```

Prefer package models as the cross-boundary types.

Do not keep Forge duplicates.

## Phase 3.5 — Migrate Detection / Drift

Replace internal architecture detection with package services.

Forge may render the result.

Target:

```text
ArchitectureDetector
        ↓
DetectionResult
        ↓
Forge renderer
```

And:

```text
DriftDetector
        ↓
DriftReport
        ↓
Forge doctor/CLI renderer
```

Library code must not depend on terminal output.

## Phase 3.6 — Migrate Adopt

If `adopt` exists:

```text
Forge AdoptCommand
        ↓
ProjectDiscovery (core)
        ↓
ArchitectureDetector
        ↓
AdoptionPlanner
        ↓
plan
        ↓
Forge applies allowed project metadata changes
```

Do not add code rewriting if current behavior does not do so.

## Phase 3.7 — Migrate Evolve

If implemented:

```text
Forge EvolveCommand
        ↓
EvolutionPlanner
        ↓
EvolutionPlan
        ↓
preview
        ↓
apply using generic mutation primitives
```

Preserve existing safety behavior.

## Phase 3.8 — Migrate Migrate

If implemented:

```text
Forge MigrateCommand
        ↓
MigrationPlanner
        ↓
MigrationPlan
        ↓
Forge renderer
        ↓
explicit apply
```

Migration plan should remain structured.

Conceptual model:

```text
KEEP
CREATE
MOVE
MODIFY
REMOVE
CONFLICT
WARNING
```

No silent destructive operation.

## Phase 3.9 — Separate Rendering If Needed

If package integration exposes old classes that print directly to terminal, remove this coupling.

Preferred:

```text
durin-architecture
    -> structured result

durin-forge
    -> human output / JSON output
```

This is the only type of internal boundary refactor explicitly encouraged during this integration.

Do not redesign output text unnecessarily.

## Phase 3.10 — Validate Architecture Flows

Use baseline fixtures.

Test:

```text
architecture detection
drift detection
adopt
evolve
migrate
invalid transitions
conflicts
manual user modifications
non-destructive behavior
```

Where a feature is not currently implemented, do not create it just to satisfy the list.

## Phase 3.11 — Remove Internal Architecture Implementation

Remove duplicated:

```text
detectors
planners
result models
transition maps
drift rules
migration models
```

Search for old namespaces and direct implementations.

## Phase 3.12 — Architecture Gate

Package integration is complete when:

- [ ] Forge uses `durin-architecture`;
- [ ] no duplicated architecture source of truth remains;
- [ ] commands stay in Forge;
- [ ] output stays in Forge;
- [ ] architecture library remains deterministic;
- [ ] migration safety remains intact;
- [ ] all relevant regression tests pass.

---

# Final Composer State

The final Durin Forge `composer.json` should conceptually contain:

```json
{
  "require": {
    "php": "^8.5",
    "ereborcodeforge/durin-core": "^0.1",
    "ereborcodeforge/durin-presets": "^0.1",
    "ereborcodeforge/durin-architecture": "^0.1"
  }
}
```

Use real available versions.

Do not remove existing framework/runtime dependencies.

If transitive dependency constraints make a direct dependency unnecessary, keep direct requirements for packages that Durin Forge directly imports.

---

# Dependency Boundary Rules

The integration must preserve:

```text
durin-core
  cannot depend on Forge
  cannot depend on presets
  cannot depend on architecture

durin-presets
  can depend on core
  cannot depend on architecture
  cannot depend on Forge

durin-architecture
  can depend on core
  can depend on presets
  cannot depend on Forge
```

Durin Forge may depend on all three.

---

# Add Boundary Tests

Add a lightweight test/script preventing regressions.

Examples of forbidden namespace references:

```text
inside durin-core:
  DurinForge\
  Durin\Presets\
  Durin\Architecture\

inside durin-presets:
  DurinForge\
  Durin\Architecture\

inside durin-architecture:
  DurinForge\
```

Use actual namespace roots.

Do not install a heavy architecture analysis platform solely for this.

---

# Regression Test Matrix

At the end, validate at least the behaviors that exist.

| Area | Before packages | After packages |
|---|---|---|
| CLI help | baseline | equivalent |
| project discovery | baseline | equivalent |
| `durin.yaml` | baseline | equivalent |
| preset resolution | baseline | equivalent |
| generated project | baseline | equivalent |
| file conflicts | baseline | equivalent |
| idempotency | baseline | equivalent |
| doctor | baseline | equivalent |
| status | baseline | equivalent |
| optimize | baseline | equivalent |
| dependency graph | baseline | equivalent |
| adopt | baseline | equivalent if implemented |
| evolve | baseline | equivalent if implemented |
| migrate | baseline | equivalent if implemented |

---

# Important Manual Validation

Create a clean temporary project through the normal current Forge flow.

Then manually modify user-owned code.

Example:

```text
change generated PHP file
add custom configuration
modify composer.json
add extra source directories
```

Then run supported Durin commands.

The package migration must not introduce assumptions that generated files remain untouched.

---

# Vendor Installation Validation

This is mandatory.

The packages must work when installed in `vendor/`.

Do not validate only with repositories checked out side-by-side.

Perform a clean test:

```text
fresh temporary project
        ↓
Composer resolves packages through VCS/tag
        ↓
no local path repositories
        ↓
run Durin tests/commands
```

Verify especially:

```text
template/resource lookup
filesystem paths
Composer metadata
autoload
```

---

# Composer Validation

Run:

```bash
composer validate --strict
composer install
composer dump-autoload -o
```

Also inspect:

```bash
composer show ereborcodeforge/durin-core
composer show ereborcodeforge/durin-presets
composer show ereborcodeforge/durin-architecture
```

Check dependency reasons:

```bash
composer why ereborcodeforge/durin-core
composer why ereborcodeforge/durin-presets
composer why ereborcodeforge/durin-architecture
```

---

# Versioning

During integration, `dev-main` is acceptable if packages are not tagged.

Before public distribution, prefer releases.

Suggested first development releases:

```text
durin-core          0.1.0
durin-presets       0.1.0
durin-architecture  0.1.0
```

Do not tag automatically as part of this implementation unless release/version changes are explicitly authorized.

---

# Commit Strategy

Prefer small commits.

Recommended:

```text
chore: add durin-core composer dependency
refactor: migrate project model to durin-core
refactor: migrate manifest handling to durin-core
refactor: migrate scaffold primitives to durin-core
refactor: remove internal core implementation

chore: add durin-presets composer dependency
refactor: migrate preset registry
refactor: migrate preset implementations
refactor: remove internal presets

chore: add durin-architecture composer dependency
refactor: migrate architecture models
refactor: migrate architecture detection
refactor: migrate evolution and migration planning
refactor: remove internal architecture implementation
```

Do not combine package migrations in the same commit unless the current repository architecture makes separation impossible.

---

# Rollback Strategy

Each package migration must be independently revertible.

If package integration fails:

```text
revert Forge consumer changes
keep package repository unchanged
fix package
retry integration
```

Do not copy/fork the package implementation back into Forge as a workaround.

---

# Must Do

- inspect current code before changing imports;
- preserve behavior;
- integrate packages one by one;
- use Composer packages as authoritative code;
- migrate tests with consumers;
- remove duplicated code before moving to next package;
- validate vendor-installed resource paths;
- preserve current manifest schema;
- preserve preset identifiers;
- preserve command contracts;
- preserve mutation safety;
- document actual ownership after migration.

---

# Must Not

- big-bang migrate all packages;
- redesign while extracting;
- change public CLI without separate spec;
- silently alter generated projects;
- leave duplicate registries;
- leave duplicate transition rules;
- leave dead old classes;
- introduce circular dependencies;
- add Jev/AI dependencies;
- move runtime responsibilities into packages;
- implement `durin-app` in this task;
- implement global installer in this task.

---

# Blocking Conditions

Stop the affected slice and document a blocker when:

- package API cannot represent current Forge behavior;
- current Forge and package behavior differ materially;
- two authoritative internal implementations already exist;
- a required package introduces a circular dependency;
- migration requires changing `durin.yaml`;
- migration requires breaking public CLI behavior;
- preset resource loading only works from repository paths;
- architecture package depends on Forge;
- core package depends on preset/architecture;
- tests indicate that extraction changed generated application behavior.

Do not conceal blockers with compatibility hacks that become permanent.

---

# Documentation Deliverables

Update/create:

```text
docs/refactoring/composer-packages/
├── baseline.md
├── durin-core-map.md
├── durin-presets-map.md
├── durin-architecture-map.md
└── final-ownership.md
```

Also update the existing ADR for tooling/package boundaries.

The final ownership doc should show:

```text
Concern → Package/Forge owner
```

---

# Final Ownership Matrix

| Concern | Owner |
|---|---|
| project model | `durin-core` |
| project discovery | `durin-core` |
| `durin.yaml` | `durin-core` |
| scaffold model | `durin-core` |
| generic safe mutation | `durin-core` |
| preset contract | `durin-core` |
| concrete presets | `durin-presets` |
| preset registry | `durin-presets` |
| type → preset resolution | `durin-presets` |
| preset resources/templates | `durin-presets` |
| architecture detection | `durin-architecture` |
| drift | `durin-architecture` |
| adopt planning | `durin-architecture` |
| evolve planning | `durin-architecture` |
| migrate planning | `durin-architecture` |
| CLI commands | `durin-forge` |
| terminal rendering | `durin-forge` |
| framework bootstrap | `durin-forge` |
| doctor runtime checks | `durin-forge` |
| status | `durin-forge` |
| dev/serve | `durin-forge` |
| Mithril integration | Forge/Mithril boundary |
| Eregion integration | Forge/Eregion boundary |

---

# Definition of Done

The task is complete when:

- [ ] Durin Forge installs all required packages through Composer;
- [ ] `durin-core` is authoritative for its responsibilities;
- [ ] `durin-presets` is authoritative for its responsibilities;
- [ ] `durin-architecture` is authoritative for its responsibilities;
- [ ] no duplicate internal implementations remain;
- [ ] no circular package dependencies exist;
- [ ] CLI remains owned by Forge;
- [ ] runtime remains owned by Forge/Mithril/Eregion;
- [ ] current `durin.yaml` schema remains compatible;
- [ ] current preset identifiers remain compatible;
- [ ] generated project regression fixtures match baseline;
- [ ] conflicts/non-destructive mutation behavior remains compatible;
- [ ] vendor-installed templates/resources work;
- [ ] package/Forge test suites pass;
- [ ] Composer validation passes;
- [ ] old namespaces are no longer used by production code;
- [ ] documentation reflects final ownership;
- [ ] no `durin-app` or installer work was mixed into this task.

---

# Agent Execution Sequence

Execute this specification in this exact sequence:

```text
STEP 0
Establish baseline and fixtures.

STEP 1
Integrate durin-core.
Do not continue until internal core duplication is removed.

STEP 2
Integrate durin-presets.
Do not continue until internal preset duplication is removed.

STEP 3
Integrate durin-architecture.
Do not continue until internal architecture duplication is removed.

STEP 4
Run complete regression and clean-install validation.

STEP 5
Update ownership documentation and ADR.

STOP.
```

Do not create `durin-app`.

Do not create `durin-installer`.

Those are separate future specifications.

---

# Expected Final Architecture

```text
                         Durin Forge
                framework / CLI / orchestration
                           │
          ┌────────────────┼──────────────────┐
          │                │                  │
          ▼                ▼                  ▼
    durin-core       durin-presets     durin-architecture
 project/manifest      presets          detect/evolve/migrate
 scaffold primitives   registry         drift/adopt/plans
          ▲                │                  │
          └────────────────┴──────────────────┘

Runtime remains:

Durin Forge
    ↓
MithrilPHP
    ↓
Eregion
```

---

# Next Work After This Spec

Only after this specification is complete and green should the next distribution layer be implemented:

```text
durin-app
    ↓
composer create-project
```

After `durin-app` is proven through Composer distribution:

```text
durin-installer
    ↓
composer global require ereborcodeforge/durin-installer
    ↓
durin new my-app
```

That work is explicitly outside this specification.
