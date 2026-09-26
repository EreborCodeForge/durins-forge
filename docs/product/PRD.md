# Durin's Forge — Product Requirements Document

**Status:** Accepted (Phase 0 governance)  
**Parent:** [Master DX & Tooling Spec](../specs/master-dx-tooling-spec.md)  
**Audience:** maintainers, implementation agents, reviewers

---

## Problem

Teams building PHP backends need a path from a small API to a modular or distributed system without rewriting the application or adopting a kitchen-sink framework. Today Durin's Forge has a working runtime path (MithrilPHP + Eregion) and basic CLI, but lacks a cohesive developer experience for diagnostics, progressive architecture, and predictable generation.

## Target developer

- Backend engineers building internal services, APIs, and worker-oriented apps.
- Teams that want explicit architecture boundaries without ceremony on day one.
- Maintainers who must keep Durin, MithrilPHP, and Eregion responsibilities clear.

## Jobs to be done

- Create a new app with the smallest useful structure for the chosen profile.
- Run locally with fast feedback (`durin dev`) and serve production via Eregion (`durin serve`).
- Diagnose environment and project health (`durin doctor`, `durin status`).
- Grow structure intentionally (`make:module`, `make:feature`, presets).
- Inspect dependency direction without a graph database (`durin graph:dependencies`).

## Product principles

1. **Simplicity first** — default to minimum useful structure.
2. **Progressive architecture** — evolve without abandoning the framework.
3. **Explicit over magic** — important inferences must be explainable by CLI.
4. **Strict runtime boundaries** — Durin orchestrates; Mithril runs PHP workers; Eregion serves HTTP.
5. **Human-readable before machine-complex** — PHP/YAML/JSON/Mermaid/text over daemons.

## Supported application profiles

| Profile | Intent |
|---------|--------|
| `minimal` | Small API / webhook |
| `service` | Internal backend service |
| `modular` | Modular application (after engine validation) |
| `microservice` | Extracted service boundaries |
| `worker` | Non-HTTP / queue-oriented (when contracts allow) |

V1 public DX prioritizes `minimal` and `service`.

## Non-goals

- Replacing Laravel as a product identity.
- Replacing Eregion, MithrilPHP, Composer, Docker/K8s, or full static analysis.
- Graphical dashboards, SaaS control planes, K8s operators, plugin marketplaces (see master §52).

## Core DX

Command vocabulary target:

```bash
durin new
durin dev
durin serve
durin optimize
durin doctor
durin status
durin graph:dependencies
durin make:module
durin make:usecase
durin make:feature
```

## Runtime model

```text
Durin's Forge (framework + DX)
        │
        ▼
MithrilPHP (PHP runtime + worker contracts + DI)
        │
        ▼
Eregion (Go app server + HTTP + process supervision)
```

## Success criteria

- PHP baseline `^8.5` consistent across Composer, docs, Docker, and CI.
- `durin doctor` useful V1 (PHP, project, Mithril/Eregion, artifacts).
- `durin dev` / `durin serve` / `durin status` with shared runtime facade.
- Preset engine with at least `minimal` and `service`.
- Generator core + `make:module` + aligned `make:usecase`.
- Dependency graph: text + Mermaid (+ JSON).
- Integration tests for scaffolding and doctor without requiring full production server when avoidable.

## Release maturity

| Stage | Meaning |
|-------|---------|
| Phase 0 | Governance docs + derived specs (this delivery) |
| Phases 1–6 | Incremental implementation per derived SPEC |
| Phase 7 | Hardening / runtime integration / public DX readiness |

Public technical communication should wait until master §46 checklist items relevant to the announced surface are true.

## Positioning

> Minimal by default. Structured when needed. Distributed when justified.

Durin's Forge is a lightweight PHP application framework with progressive architectural structure, fast local feedback, and a production runtime path through MithrilPHP + Eregion.
