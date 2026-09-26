# SPEC-DX-015 — Dependency graph core

**Status:** Ready for implementation  
**Branch:** `feat/dx-015-dependency-graph-core`  
**Parent sections:** Master §15, §38; Phase 6

## Problem

Teams need dependency direction visibility. AST parsing is expensive and deferred; V1 should use explicit metadata + discovery.

## Goal

Deliver graph domain model + project/module discovery + collectors based on **explicit metadata** (container bindings, routes, module manifests) — not full PHP AST.

## Non-goals

- Mermaid/JSON renderers (016).
- Full architecture rule engine (§44).
- Graph database.

## Current implementation

- Container/route compile artifacts under `var/cache/`.
- No graph model.

## Proposed design

```text
DependencyGraph (nodes/edges)
  ← ProjectModuleDiscovery
  ← ContainerDependencyCollector
  ← RouteDependencyCollector
```

CLI may be stubbed until 016; prefer library + unit tests here.

## Affected files

- Possibly read compiled artifacts from Core

## New files

- `Tooling/Graph/*` model + collectors + tests

## Public API / CLI impact

None required (or hidden command).

## Backward compatibility

N/A.

## Migration

N/A.

## Implementation phases

1. Domain model.
2. Discovery.
3. Collectors from explicit sources.
4. Unit tests with fixtures.

## Tests

- Unit: graph merge, collector fixtures.

## Acceptance criteria

- [ ] Model supports module filter input for later CLI.
- [ ] Collectors use explicit metadata only.
- [ ] No AST dependency.
- [ ] Tests green.

## Risks

- Compiled artifact format changes — version adapters.

## Open questions

None.

## Definition of Done

Core graph library ready for renderers.
