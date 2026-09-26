# SPEC-DX-004 — Runtime facade contract

**Status:** Implemented (pending merge)  
**Branch:** `feat/dx-004-runtime-facade`  
**Parent sections:** Master §8, §39; [ADR-0002](../../adr/ADR-0002-cli-runtime-boundaries.md)

## Problem

`serve`, `dev`, and `status` must share orchestration primitives. Without a facade, each command risks a separate startup stack.

## Goal

Define and implement an internal runtime facade contract:

- Resolve runtime options (env, host, port, workers, mode).
- Delegate start/status operations to Mithril/Eregion adapters.
- Provide test seams (interfaces / doubles).

No requirement to finish polished UX of `dev`/`status` in this SPEC (those are 006/007); facade must be enough for 005 to adopt.

## Non-goals

- Full `durin dev` feature set.
- `--watch` for status.
- Reimplementing Eregion.

## Current implementation

- `ServeCommand` thin-aliases `vendor/bin/forge serve`.

## Proposed design

```php
interface RuntimeFacade {
    public function serve(RuntimeOptions $options): int;
    public function dev(RuntimeOptions $options): int;
    public function status(RuntimeOptions $options): RuntimeStatus;
}
```

Adapter calls existing Mithril binaries/APIs. Keep in `Tooling/Runtime`.

## Affected files

- Existing `ServeCommand` may start depending on facade (or wait for SPEC-005).

## New files

- Runtime options/status DTOs, facade interface, Mithril adapter, unit tests with doubles.

## Public API / CLI impact

None required in this slice (internal).

## Backward compatibility

Preserve current `durin serve` behavior when wired in 005.

## Migration

N/A.

## Implementation phases

1. DTOs + interface.
2. Mithril adapter skeleton.
3. Unit tests with fake adapter.

## Tests

- Unit: options defaults; facade delegates to adapter (mock).

## Acceptance criteria

- [x] Single facade interface used as the extension point for serve/dev/status.
- [x] No second startup stack introduced.
- [x] Tests with doubles pass.

## Risks

- Discovering Mithril APIs are process-only — adapter may wrap process execution; keep that isolated.

## Open questions

None blocking.

## Definition of Done

Facade + adapter seam landed with tests.
