# Durin Forge — Capability-Based Runtime Resolution

**Status:** implementation-ready  
**Repository:** `EreborCodeForge/durins-forge`

# Mission

Transformar Forge na camada responsável por converter requirements de preset em runtime concreto.

Fluxo:

```text
Preset RuntimeProfile
  ↓
RuntimeResolver
  ↓
RuntimePlan
  ↓
RuntimeProvisioner
  ↓
manifest/configuração final
```

# Current Problem

O comportamento atual equivalente a:

```php
$profile->runner ?? 'eregion'
```

faz `runner=null` significar Eregion.

Isso é incorreto para o preset `worker`.

# Runtime Registry

Criar abstrações equivalentes a:

```php
interface RuntimeDefinition
{
    public function id(): string;
    public function capabilities(): array;
    public function supports(RuntimeProfile $profile): bool;
}
```

e:

```text
RuntimeRegistry
RuntimeResolver
RuntimePlan
RuntimeProvisioner
```

# Built-in Definitions

## Mithril HTTP runtime

```text
id=mithril-http
capabilities:
  - persistent-http
```

## Mithril Job runtime

```text
id=mithril-job
capabilities:
  - job-loop
  - messaging
```

Executa:

```bash
php vendor/bin/job-worker
```

## Eregion Supervisor

```text
id=eregion
capabilities:
  - process-supervision
  - http-supervision
  - consumer-supervision   # após suporte no Eregion
```

# RuntimePlan

Não sobrecarregar um único campo `runner`.

Direção:

```php
final readonly class RuntimePlan
{
    public function __construct(
        public string $mode,
        public string $executionRuntime,
        public ?string $supervisor,
        public array $capabilities,
    ) {}
}
```

Exemplos:

```text
minimal/service:
execution=mithril-http
supervisor=eregion

worker standalone:
execution=mithril-job
supervisor=null

worker supervisionado:
execution=mithril-job
supervisor=eregion
```

# Resolution Rules

1. Todos os `requiredCapabilities` precisam ser atendidos.
2. Se houver `preferredRunner`, validar compatibilidade.
3. Aplicar preferências/defaults de Forge.
4. Resultado deve ser determinístico.
5. Sem runtime compatível → erro explícito.

Nunca fallback silencioso para Eregion.

# Provisioning

Separar seleção de side effects:

```text
RuntimeResolver      = puro
RuntimeProvisioner   = orchestration
EregionInstaller     = instala binário
EregionConfigurator  = config/workloads
```

Adicionar configurador de Mithril job apenas se houver side effect real necessário.

# Worker Resolution

Primeira etapa:

```text
worker
  ↓
mithril-job
  ↓
sem Eregion obrigatório
```

Etapa posterior:

```text
worker + supervision policy
  ↓
mithril-job
  +
eregion supervisor
```

Preset não muda.

# Eregion Workload Config

Forge traduz `RuntimePlan` para Eregion.

Exemplo:

```yaml
workloads:
  application-worker:
    mode: consumer
    command:
      - php
      - vendor/bin/job-worker
    workers:
      min: 1
      max: 4
```

Presets não escrevem essa config.

# Init Flow

```text
preset.resolve
scaffold.plan
scaffold.apply
runtime.resolve
runtime.provision
runtime.configure
manifest.finalize
validate
complete
```

Manter `--progress=jsonl`.

# Complete Payload

Evoluir payload para algo como:

```json
{
  "type": "complete",
  "preset": "worker",
  "runtime": {
    "mode": "job",
    "execution": "mithril-job",
    "supervisor": null
  }
}
```

# Serve / Dev

Não encaminhar job mode para HTTP `forge serve`.

Direção:

```text
HTTP → Eregion HTTP
job standalone → job-worker
job supervised → Eregion consumer workload
```

Se necessário introduzir comando futuro mais neutro (`run`), mas não duplicar lógica.

# Doctor

Doctor valida por RuntimePlan, não por preset name.

```text
HTTP/Eregion:
  binary
  config
  protocol
  worker entry

job standalone:
  JobKernel
  JobTransport binding/config

job + Eregion:
  checks dos dois
```

# Must Do

- remover fallback automático para Eregion;
- RuntimeRegistry;
- RuntimeResolver;
- RuntimePlan;
- Mithril job como runtime explícito;
- composite execution+supervisor;
- atualizar Doctor;
- atualizar JSONL complete;
- manifest finalizado após resolução;
- init idempotente.

# Must Not

- hardcode preset IDs;
- delegar resolução ao installer;
- mover scheduler do Eregion para Forge;
- obrigar Eregion em job apps.

# Tests

```text
minimal → mithril-http + eregion
service → mithril-http + eregion
worker → mithril-job sem supervisor
unsupported capability
preferred compatible/incompatible
job supervisionado
init idempotente
Doctor por runtime plan
JSONL complete
```

# Acceptance Criteria

- Worker não instala Eregion automaticamente.
- HTTP continua usando Eregion.
- Forge pode adicionar supervisão a jobs sem alterar preset.
- Installer recebe decisão pronta.

# Definition of Done

Forge é a única camada que resolve **requirements → execução + supervisor**.
