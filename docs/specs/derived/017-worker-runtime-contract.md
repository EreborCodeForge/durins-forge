# SPEC-DX-017 — Worker runtime contract (non-HTTP)

**Status:** Accepted (contract + Mithril runner shipped; Durin preset `worker` implemented)  
**Branch (docs):** `feat/dx-017-worker-runtime-contract`  
**Parent:** Master §21 (preset `worker`), §2.4, ADR-0002  
**Related:** [boundaries.md](../../architecture/boundaries.md), [durins-forge-eregion-spec.md](../../durins-forge-eregion-spec.md), [ADR-0002](../../adr/ADR-0002-cli-runtime-boundaries.md), **[SPEC-MITHRIL-001](../mithril/SPEC-MITHRIL-001-job-worker-runtime.md)** (implementação na lib — **shipped in mithrilphp v2.2.0**)

---

## 1. Problem

O preset `worker` (master §21) exige um **contrato de runtime próprio** antes de implementação. Hoje “worker” no stack Durin/Mithril/Eregion significa **processo PHP HTTP aquecido** atrás do Eregion (`eregion-worker`). Isso **não** cobre:

- consumer de fila / messaging;
- job assíncrono / scheduled;
- app **sem** HTTP público.

Sobrecarregar o Eregion com esse papel sem contrato explícito viola a fronteira do V1 HTTP.

## 2. Goal

Definir o **Job Worker Runtime Contract**: interfaces, ciclo de vida, ownership e impacto no Eregion/Mithril/Durin — suficiente para depois implementar `durin new --preset=worker` sem inventar servidor HTTP nem reusar o protocolo EREGION por engano.

## 3. Vocabulary (obrigatório)

| Termo | Significado | Owner típico |
|-------|-------------|--------------|
| **HTTP worker** | Processo PHP que atende requests via UDS/MessagePack (`eregion-worker`) | Mithril + Eregion |
| **Job worker** | Processo PHP que consome unidades de trabalho (fila, schedule, stream) **sem** porta HTTP pública | Mithril (loop) + Durin (app) |
| **Eregion** | Gateway HTTP / pool / backpressure | Eregion (Go) |
| **Job unit** | Uma unidade atômica de trabalho (mensagem, job id, payload) | App / broker |

Este documento trata **somente Job worker**. HTTP worker permanece [durins-forge-eregion-spec.md](../../durins-forge-eregion-spec.md).

## 4. Decision summary

1. Job workers usam um contrato PHP distinto de `HttpApplication`.
2. **Eregion não é o supervisor canônico de job workers no V1 deste contrato.**
3. Durin orquestra DX (`new --preset=worker`, doctor/status awareness); Mithril deve fornecer o **loop de processo** e discovery do kernel de jobs — ver **[SPEC-MITHRIL-001](../mithril/SPEC-MITHRIL-001-job-worker-runtime.md)**. Durin não reimplementa supervisor de processos.
4. `features.http: false` + `features.messaging: true` (ou modo `runtime.mode: job`) no `durin.yaml` descrevem a intenção; `eregion.yaml` **não** é obrigatório para job-only apps.

## 5. PHP contract (normative)

### 5.1 `JobApplication` (nome canônico proposto)

Espelha o papel de `HttpApplication`, sem Request/Response HTTP:

```php
interface JobApplication
{
    /** Boot idempotente — uma vez por processo. */
    public function boot(): void;

    /**
     * Processa uma unidade de trabalho.
     * Deve capturar exceções de domínio/aplicação e mapear para JobResult
     * (falha de negócio ≠ crash do processo).
     */
    public function handle(JobEnvelope $job): JobResult;

    public function getContainer(): object; // Mithril Container
}
```

### 5.2 `JobEnvelope` (mínimo V1)

```text
id: string           # idempotency / trace
name: string         # job type / routing key
payload: mixed       # dados já decodificados (array/object)
attempt: int         # 1-based
headers: array       # metadata (tenant, correlation-id, …)
```

Sem acoplamento a um broker específico no contrato. Adapters (Redis, SQS, database poll, …) vivem em Infrastructure.

### 5.3 `JobResult`

```text
ack     — sucesso; broker pode remover/commit
retry   — falha transitória; backoff do runner/broker
reject  — falha permanente; dead-letter / log; não retry infinito
```

### 5.4 Discovery

Proposta alinhada ao HTTP:

| Mecanismo | HTTP hoje | Job (proposto) |
|-----------|-----------|----------------|
| composer extra | `extra.mithril.kernel` | `extra.mithril.job_kernel` (ou `job_application`) |
| env | `MITHRIL_KERNEL` | `MITHRIL_JOB_KERNEL` |
| fallback | `App\Kernel` | `App\JobKernel` |

Apps híbridas (HTTP + jobs) **podem** ter dois kernels; não misturar `handle(Request)` e `handle(JobEnvelope)` na mesma interface.

### 5.5 Regras de processo (iguais em espírito ao HTTP)

1. `boot()` idempotente; sem I/O pesado no construtor.
2. Estado de job/request → scoped; pools/logger → singleton.
3. Exceção não tratada no runner → exit non-zero (supervisor externo decide restart).
4. Exceção tratada em `handle` → `JobResult::retry|reject`; processo permanece vivo.
5. Graceful shutdown: runner escuta SIGTERM/SIGINT, termina o job atual (ou respeita timeout), depois exit.

## 6. Ciclo de vida (V1)

```text
OS / supervisor (systemd, container, Mithril CLI)
        │
        ▼
mithril job-worker (ou bin equivalente)   ← Mithril (proposto)
        │  boot(JobApplication)
        │  loop: fetch → handle → ack/retry/reject
        ▼
App\JobKernel + Application/Jobs/*
```

Durin **não** implementa o loop. Durin:

- gera skeleton (`Jobs/`, `JobKernel`, `durin.yaml`);
- documenta como subir;
- estende `doctor`/`status` para reportar “job mode” vs “http mode”.

## 7. `durin.yaml` (worker preset)

```yaml
application:
  name: notifications
  preset: worker

runtime:
  engine: mithril
  server: none          # não usa Eregion como gateway
  mode: job             # novo campo proposto (default: http)

features:
  http: false
  messaging: true

architecture:
  modules: false
```

Notas:

- `runtime.server: eregion` continua válido para presets HTTP.
- `runtime.server: none` + `mode: job` = app job-only.
- Híbrido futuro: `features.http: true` + `messaging: true` com dois entrypoints; fora do primeiro corte do preset.

## 8. Shape do preset `worker` (quando implementar)

```text
src/
  JobKernel.php          # implements JobApplication
  Application/
  Infrastructure/        # adapters de broker (stubs)
  Jobs/                  # handlers por nome de job
routes/                  # AUSENTE ou vazio — não gerar
public/index.php         # AUSENTE ou não usado como entry
```

Não criar controllers/rotas “porque o skeleton HTTP existe”.

## 9. Precisa ajustar o Eregion?

### 9.1 Resposta curta

**Não, para o V1 do job worker.** Eregion permanece o gateway **HTTP**. Job workers **não** devem ser forçados pelo protocolo `eregion/1` (UDS + MessagePack de Request/Response).

### 9.2 O que **não** fazer no Eregion agora

| Tentação | Por que evitar |
|----------|----------------|
| Reusar `eregion-worker` para filas | Contrato é HTTP `Request`→`Response` |
| Inventar “job frames” no mesmo protocolo sem versionar | Mistura backpressure HTTP com ack de mensagem |
| Fazer Eregion virar supervisor genérico de processos | Escopo explode; compete com systemd/k8s |

### 9.3 Quando o Eregion **precisaria** mudar (fase futura, opcional)

Somente se o produto quiser **um único binário supervisor** para HTTP **e** jobs:

1. Novo modo de processo (ex.: `eregion.yaml` → `mode: http | supervise-only`) **sem** listener HTTP; ou
2. Subprotocolo versionado (`eregion/job/1`) separado do HTTP; ou
3. Sidecar documentado — Eregion só HTTP; jobs sob outro unit.

Isso exige ADR + mudança no repositório **Eregion** e no bridge Mithril. **Fora do contrato V1.** Até lá, a resposta oficial é: **zero mudança obrigatória no Eregion**.

### 9.4 Colisão de nomes

Documentar em DX/UX:

- `durin serve --workers=N` → **HTTP workers** (Eregion pool).
- `durin` + preset `worker` → **job worker app** (processo Mithril job loop).

## 10. Impacto por camada

| Camada | V1 deste contrato | Depois (implementação) |
|--------|-------------------|-------------------------|
| **Durin** | Este SPEC; depois preset + JobKernel stub + doctor aware | `durin new --preset=worker`; talvez `durin job:work` thin → Mithril |
| **Mithril** | Gap atual | Implementar [SPEC-MITHRIL-001](../mithril/SPEC-MITHRIL-001-job-worker-runtime.md): `JobApplication`, `JobWorker`, `bin/job-worker` |
| **Eregion** | **Nenhuma mudança obrigatória** | Só se ADR aprovar supervise-only / job protocol |

## 11. Non-goals

- Implementar broker (Redis/SQS/Rabbit) no Durin core.
- Substituir Eregion para HTTP.
- Autoscaling multi-tenant de jobs.
- WebSocket/SSE no job worker.
- Unificar `HttpApplication` e `JobApplication` numa interface só.

## 12. Acceptance (contrato)

- [x] Vocabulário HTTP worker vs Job worker explícito.
- [x] Interface mínima `JobApplication` / envelope / result.
- [x] Ownership: Durin DX, Mithril loop, Eregion fora do path canônico.
- [x] Decisão clara sobre Eregion (sem mudança V1; critérios para fase futura).
- [x] Shape de `durin.yaml` e skeleton do preset.
- [x] Mithril runner shipped (`mithrilphp` v2.2.0 / SPEC-MITHRIL-001).
- [x] Preset `worker` no Durin (`durin new --preset=worker`).
- [ ] ADR-0005 formal (opcional).
- [ ] Brokers reais (app adapters — fora do core).

## 13. Open questions

1. Apps híbridas no mesmo repositório: um ou dois processos no V1 do preset Durin? Recomendação: **dois processos**, um entrypoint cada.
2. Nome do comando Forge opcional (`job:work`) — ver SPEC-MITHRIL-001 §5.7 (não bloqueia DoD da lib).

Nomes de interface / namespaces / entrypoint foram **fechados** em SPEC-MITHRIL-001 §4.

## 14. Definition of Done (desta fatia docs)

Contrato publicado em `docs/specs/derived/`, indexado, com links a partir de boundaries / Eregion spec. Sem código de preset até Mithril (ou stub explícito) fechar o loop.

---

## Appendix A — Fluxo que **não** é este contrato

```text
Client HTTP → Eregion → UDS/MessagePack → eregion-worker → App\Kernel::handle(Request)
```

Isso já está especificado e implementado. Não misturar com jobs.
