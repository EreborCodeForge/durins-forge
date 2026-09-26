# Architecture overview

**Status:** Accepted (Phase 0)  
**Parent:** [Master DX & Tooling Spec](../specs/master-dx-tooling-spec.md) §2, §51  
**Related:** [boundaries.md](boundaries.md), [ADR-0002](../adr/ADR-0002-cli-runtime-boundaries.md)

---

## Purpose

Durin's Forge is the application framework and developer experience layer. It does not own HTTP serving or PHP worker process supervision.

## Stack

```text
Developer / CI
      │
      ▼
Durin's Forge CLI + Application Kernel
      │  optimize, generate, diagnose, thin runtime UX
      ▼
MithrilPHP
      │  DI, worker contracts, forge tooling, UDS/MessagePack client side
      ▼
Eregion
      │  HTTP, concurrency, worker supervision, backpressure
      ▼
Application code (src/)
```

## Progressive application shape

```text
minimal API → service → modular application → modular monolith → microservice
```

Structure grows when needed. Generators and presets must not create empty ceremony directories by default.

## Tooling layout (target)

Internal namespaces under the application package (see [ADR-0004](../adr/ADR-0004-tooling-package-boundaries.md)):

```text
Tooling/Doctor
Tooling/Status
Tooling/Graph
Tooling/Presets
Tooling/Generators
Tooling/Project
Tooling/Runtime
```

Extraction into Composer packages is deferred until reuse and versioning criteria are met.

## Operational artifacts

| Artifact | Owner |
|----------|-------|
| `durin.yaml` | Durin — project shape / capabilities |
| `config/*` | Application config |
| `eregion.yaml` | Eregion server config |
| `.env` | Secrets / environment values |
| `var/cache/*` | Compiled Durin artifacts |
| `var/runtime/eregion.json` | Mithril/Eregion runtime manifest |

## Documentation map

| Doc | Role |
|-----|------|
| [PRD](../product/PRD.md) | Product intent |
| [Master DX spec](../specs/master-dx-tooling-spec.md) | Parent program spec |
| [Derived specs](../specs/derived/) | Implementable slices |
| [ADRs](../adr/) | Normative decisions |
| [Eregion integration](../durins-forge-eregion-spec.md) | Runtime integration detail |
| [PERFORMANCE](../PERFORMANCE.md) | Optimize / bench notes |
