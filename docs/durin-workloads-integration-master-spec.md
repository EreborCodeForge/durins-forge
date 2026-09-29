# Durin Ecosystem — Workloads Integration Master Spec

**Status:** E2E integrated (Phase 1 + Phase 2 + Phase 5 validated; Phases 3–4 baseline Eregion v0.4.0)

# Mission

Coordenar a evolução de workloads entre:

```text
eregion
mithrilphp
durin-core
durin-presets
durins-forge
durin-app
durin-installer
```

`durin-architecture` não precisa de mudança obrigatória neste escopo inicial.

# Responsibility Map

```text
durin-presets
→ declara intenção/capabilities

durins-forge
→ resolve execução + supervisor

durin-core
→ modela manifest/runtime de forma neutra

durin-app
→ bootstrap neutro

durin-installer
→ orquestra criação

mithrilphp
→ executa HTTP/jobs persistentemente

eregion
→ supervisiona e escala workloads

broker
→ mantém backlog e semântica de entrega
```

# Target Flow — HTTP

```text
minimal/service
  ↓
durin-presets
requires persistent-http
  ↓
Forge
execution=mithril-http
supervisor=eregion
  ↓
Eregion HTTP workload
  ↓
Mithril persistent HTTP Worker
```

# Target Flow — Worker Standalone

```text
worker
  ↓
durin-presets
requires job-loop + messaging
  ↓
Forge
execution=mithril-job
supervisor=null
  ↓
php vendor/bin/job-worker
  ↓
JobTransport
  ↓
broker
```

# Target Flow — Worker Supervisionado

```text
worker
  ↓
Forge
execution=mithril-job
supervisor=eregion
  ↓
Eregion consumer workload
  ↓
N x php vendor/bin/job-worker
  ↓
JobTransport
  ↓
broker
```

# Subjobs

Correto:

```text
Worker A
  ↓
JobDispatcher
  ↓
broker
  ↓
Workload B
  ↓
N workers
```

Incorreto:

```text
Worker A
  ↓
spawn Worker B
```

A escala pertence ao Eregion/pool, não ao job handler.

# Implementation Order

## Phase 1 — Runtime neutrality — DONE

1. `durin-core`
2. `durin-presets`
3. `durins-forge`
4. `durin-app`
5. `durin-installer`

Resultado esperado:

```text
worker → Mithril JobWorker sem Eregion obrigatório
minimal/service → HTTP com Eregion
```

## Phase 2 — Mithril job hardening — DONE (`mithrilphp` v3.0.0)

Implementado e publicado:

```text
idle != stop
graceful drain
max_jobs recycle
metrics observer
JobDispatcher
```

Consumidores alinhados: `durins-forge` ^0.4 / `mithrilphp` ^3.0, `durin-app`, `jobs`, scaffolds de `durin-presets` (demo transport com `idleWhenEmpty: true`).

## Phase 3 — Eregion workloads — BASELINE (`eregion` v0.4.0)

Presente no Eregion (sem reimplementação nesta rodada):

```text
WorkloadSpec
WorkloadTemplate
ResolvedWorkloadSpec
WorkloadRegistry
Reconciler
multiple WorkerPool
http mode
consumer mode
```

## Phase 4 — Dynamic scaling — BASELINE (`eregion` v0.4.0)

Presente no Eregion (sem reimplementação nesta rodada):

```text
min/max
min=0
backlog strategy
resource clamp
scale-up cooldown
scale-down idle delay
graceful drain
```

## Phase 5 — Forge + Eregion consumer integration — DONE (`durins-forge` v0.4.0)

Forge gera/configura workload Eregion consumer-first para RuntimePlan job+supervisor; doctor alinhado; pin default Eregion `v0.4.0`; `durin run` escolhe launcher por `execution`/`supervisor`.

Smoke E2E: `scripts/workloads-integration-smoke.sh` (standalone, supervised, scale, crash/restart, drain, idle, HTTP, launcher matrix).

# Versioning

Não reescrever releases 0.1.x existentes.

Cada repo deve fazer release compatível com SemVer conforme o tamanho da quebra pública.

Mudanças de manifest/contratos públicos devem ser tratadas como migration explícita e possuir leitura de formato legado quando viável.

# Cross-Repository Rules

- presets não instalam runtime;
- Core não conhece IDs de preset;
- installer não escolhe runtime;
- Forge não implementa scheduler;
- Eregion não implementa domínio/broker obrigatório;
- Mithril não exige Eregion;
- Job handlers não criam processos;
- broker continua sendo fonte de backlog;
- Eregion controla capacidade;
- Mithril controla unidade de execução.

# End-to-End Example — MQTT

```text
durin new telemetry --preset=worker
  ↓
worker preset
  ↓
mithril-job
  ↓
opcional Eregion supervisor
  ↓
workload telemetry
workers min=1 max=8
  ↓
MqttJobTransport
  ↓
Mosquitto shared subscription
```

# End-to-End Example — Fan-out

```text
video.uploaded
  ↓
orchestrator worker
  ↓
JobDispatcher
  ├── transcode.360
  ├── transcode.720
  ├── transcode.1080
  └── thumbnail
         ↓
       broker
         ↓
Eregion escala pools especializados
```

# Ecosystem Acceptance Criteria

- `minimal` usa HTTP persistente supervisionado por Eregion.
- `service` usa HTTP persistente supervisionado por Eregion.
- `worker` roda standalone apenas com Mithril.
- `worker` pode opcionalmente ser supervisionado por Eregion.
- Eregion suporta múltiplos workloads simultaneamente.
- Consumer workload pode escalar entre min/max.
- `min=0` não mantém processo desnecessário.
- subjobs escalam via broker.
- nenhum broker é implementado obrigatoriamente em Eregion.
- Core não conhece `minimal`, `service` ou `worker`.
- Installer não possui fallback `eregion`.

# Definition of Done

Durin consegue ir de uma aplicação HTTP simples a uma frota de consumers persistentes e escaláveis sem misturar responsabilidades entre framework, runtime, supervisor e broker.

**Validated (2026-09-28):** Phases 1, 2 e 5 fechadas ponta a ponta com `scripts/workloads-integration-smoke.sh` (smokes 1–8). Phases 3–4 tratadas como baseline `eregion` v0.4.0.
