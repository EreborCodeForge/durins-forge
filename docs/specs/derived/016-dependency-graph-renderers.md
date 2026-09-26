# SPEC-DX-016 — Dependency graph renderers

**Status:** Implemented (pending merge)  
**Branch:** `feat/dx-016-dependency-graph-renderers`  
**Parent sections:** Master §15, §38-005/006; depends on SPEC-015

## Problem

Graph data must be human- and machine-consumable without external graph tools.

## Goal

Implement `durin graph:dependencies` with:

- Text renderer
- Mermaid renderer
- JSON renderer
- `--module=` filter

## Non-goals

- Interactive UI.
- AST enrichment.
- `inspect:architecture` rules.

## Current implementation

- `TextGraphRenderer`, `MermaidGraphRenderer`, `JsonGraphRenderer` via `GraphRendererRegistry`.
- `GraphDependenciesCommand` → `ProjectDiscovery` → `DependencyGraphAssembler` → renderer.
- Formats: `text` (default), `mermaid`, `json`.

## Proposed design

```text
GraphCommand → assemblers → render(format)
```

## Affected files

- Console Application, README

## New files

- Renderers, registry, command, tests

## Public API / CLI impact

```bash
durin graph:dependencies
durin graph:dependencies --format=mermaid
durin graph:dependencies --format=json
durin graph:dependencies --module=Billing
```

## Backward compatibility

Additive.

## Migration

N/A. Container edges require optional `var/cache/container.descriptor.php` (DescriptorProvider shape).

## Implementation phases

1. Text renderer.
2. Mermaid + JSON.
3. CLI + module filter.
4. Docs.

## Tests

- Unit: renderer golden assertions.
- Console smoke with fixture project + module filter.

## Acceptance criteria

- [x] Three formats work.
- [x] Module filter applied.
- [x] Tests green.
- [x] README documents command.

## Risks

- Huge graphs in text mode — module filter is the V1 mitigation.

## Open questions

None.

## Definition of Done

CLI complete for V1 graph surface; ends initial DX program slices 001–016.
