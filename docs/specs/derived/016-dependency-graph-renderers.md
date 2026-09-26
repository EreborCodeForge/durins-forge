# SPEC-DX-016 — Dependency graph renderers

**Status:** Ready for implementation  
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

- Graph core (015).

## Proposed design

```text
GraphCommand → collectors → render(format)
```

Formats: `text` (default), `mermaid`, `json`.

## Affected files

- Console Application, README

## New files

- Renderers, command, tests

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

N/A.

## Implementation phases

1. Text renderer.
2. Mermaid + JSON.
3. CLI + module filter.
4. Docs.

## Tests

- Unit: renderers golden strings/fixtures.
- Integration: CLI smoke with fixture project.

## Acceptance criteria

- [ ] Three formats work.
- [ ] Module filter applied.
- [ ] Tests green.
- [ ] README documents command.

## Risks

- Huge graphs in text mode — allow module filter as primary mitigation for V1.

## Open questions

None.

## Definition of Done

CLI complete for V1 graph surface; ends initial DX program slices 001–016.
