# SPEC-DX-015 — Dependency graph core

**Status:** Implemented (pending merge)  
**Branch:** `feat/dx-015-dependency-graph-core`  
**Parent sections:** Master §15, §38; Phase 6

## Problem

Teams need dependency direction visibility. AST parsing is expensive and deferred; V1 should use explicit metadata + discovery.

## Goal

Deliver graph domain model + project/module discovery + collectors based on **explicit metadata** (container bindings, routes, module manifests) — not full PHP AST.

## Non-goals

- Mermaid/JSON/text renderers and CLI (016).
- Full architecture rule engine (§44).
- Graph database.
- Parsing compiled container closures (use `var/cache/container.descriptor.php` DescriptorProvider shape instead).

## Current implementation

- `Tooling/Graph`: `DependencyGraph`, nodes/edges, `ProjectModuleDiscovery`, `ContainerDependencyCollector`, `RouteDependencyCollector`, loaders, `DependencyGraphAssembler`.
- Module markers: `src/Modules/{Name}/module.php`.
- Container metadata: optional `var/cache/container.descriptor.php` (same shape as `DescriptorProvider`).
- Routes metadata: optional `var/cache/routes.php` (compiled export).
- Supports `module` filter input for later CLI.

## Proposed design

```text
DependencyGraph (nodes/edges)
  ← ProjectModuleDiscovery
  ← ContainerDependencyCollector
  ← RouteDependencyCollector
  ← DependencyGraphAssembler
```

CLI deferred to 016.

## Affected files

- None outside Tooling/Graph (+ tests/SPEC).

## New files

- `src/Tooling/Graph/*` + unit tests

## Public API / CLI impact

None (library only).

## Backward compatibility

N/A.

## Migration

N/A. Projects that want container edges in the graph may emit `var/cache/container.descriptor.php` (016/docs can mention).

## Implementation phases

1. Domain model.
2. Discovery.
3. Collectors from explicit sources.
4. Unit tests with fixtures.

## Tests

- Unit: merge/filter, module discovery, container/route collectors, assembler with fixtures.

## Acceptance criteria

- [x] Model supports module filter input for later CLI.
- [x] Collectors use explicit metadata only.
- [x] No AST dependency.
- [x] Tests green.

## Risks

- Compiled artifact format changes — version adapters.

## Open questions

None.

## Definition of Done

Core graph library ready for renderers.
