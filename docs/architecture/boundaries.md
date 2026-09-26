# Architecture boundaries

**Status:** Accepted (Phase 0)  
**Parent:** [Master DX & Tooling Spec](../specs/master-dx-tooling-spec.md) §2.4, §51–52  
**Related:** [ADR-0002](../adr/ADR-0002-cli-runtime-boundaries.md)

---

## Ownership matrix

| Concern | Owner |
|---------|-------|
| HTTP server | Eregion |
| PHP worker runtime | MithrilPHP |
| UDS / MessagePack / EREGION protocol | MithrilPHP + Eregion |
| Application framework | Durin |
| CLI application DX | Durin |
| Presets / generators | Durin |
| Project manifest (`durin.yaml`) | Durin |
| Container runtime implementation | MithrilPHP |
| Container compilation orchestration | Durin |
| Runtime metrics | Eregion |
| Runtime status presentation | Durin via Eregion |
| DB abstraction | Mazarbul |
| Architecture dependency visualization | Durin |
| Production monitoring platform | external |

## Durin MUST

- Provide DX commands that explain project and runtime state.
- Orchestrate optimize / thin runtime UX by delegating to Mithril/Eregion.
- Keep generated code inspectable and conflict-safe.

## Durin MUST NOT

- Reimplement UDS transport, MessagePack framing, EREGION protocol, worker supervision, HTTP concurrency, or Eregion backpressure.
- Introduce graph databases, DX daemons, or remote control planes for basic inspection.
- Silently change the PHP or runtime compatibility baseline (requires ADR).

## CLI boundary

```text
durin
 ├── project / application DX
 ├── generators
 ├── diagnostics
 ├── inspection
 ├── optimize
 └── thin runtime orchestration  →  Mithril  →  Eregion
```

Commands such as `serve`, `status`, and `doctor` may call Mithril/Eregion APIs or binaries. They must not fork those implementations.

## Application layering (guidance)

Preferred dependency direction when modules exist:

```text
Domain → (no Infrastructure / HTTP)
Application → Domain
Infrastructure → Application / Domain contracts
Http → Application
```

Full `inspect:architecture` enforcement is a future companion (master §44), not Phase 0–6 V1 scope.
