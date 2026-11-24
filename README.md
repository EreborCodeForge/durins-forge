# Durin's Forge

Framework e forjador de apps sobre **MithrilPHP**. Este repositório é o pacote Composer **`ereborcodeforge/durins-forge`** (`type: library`) — não um domínio de negócio pré-montado. Apps consumidoras nascem com `durin new` + generators e dependem deste package.

Namespace de produção: **`EreborCodeForge\Durin\Forge\`**. O namespace **`App\`** pertence exclusivamente à aplicação consumidora.

Em produção, **Eregion** guarda o HTTP; o Worker Mithril mantém o Kernel aquecido.

> **Durin forja a app → Mithril aquece o Worker → Eregion guarda os portões.**

---

## Requisitos

- PHP **8.5+** ([ADR-0001](docs/adr/ADR-0001-php-version-baseline.md))
- Extensões: `json`, `pdo`, **`msgpack`**, **`sockets`**
- Composer 2
- Node.js (opcional, frontend Vue/Vite)

---

## Consumo (caminho canônico)

Numa aplicação:

```bash
composer require ereborcodeforge/durins-forge
durins-forge doctor
durins-forge dev
durins-forge make:feature ...
# CI / container, without the global launcher:
vendor/bin/durins-forge doctor
```

Apps geradas por `durin new` já declaram `ereborcodeforge/durins-forge`. O comando canônico é `durins-forge` quando o Installer global está no `PATH`. Em CI e contêineres use `vendor/bin/durins-forge`. `vendor/bin/durin` continua como alias de compatibilidade e não é o nome recomendado.

Se `vendor/bin` vier antes do binário global do Composer no `PATH`, o alias legado `durin` pode sombrear o `durin` do Installer. O contrato assume o `PATH` recomendado (binário global do Composer antes de `vendor/bin`). Esta ferramenta não altera o `PATH`.

### Matriz de distribuição

Smoke de pull request valida o código da branch (`composer validate --strict`, `check-platform-reqs`, testes de init). Smoke de release usa só artefatos do Packagist (`durins-forge:^0.4` e `durin-app:^0.4` + `durin init`). Pacote ou versão ausente falha o job. `DURIN_SMOKE_REQUIRED=1` faz o script `scripts/workloads-integration-smoke.sh` tratar `SKIP` como `FAIL`. App gerada não declara `repositories` nem `minimum-stability: dev`. Publicação no Packagist não faz parte deste fluxo.

### Desenvolvimento deste repositório

```bash
composer install
# ignore-platform-req=ext-msgpack no Windows se a extensão ainda não estiver instalada

cp .env.example .env
php bin/durins-forge migrate
```

`composer.json` pinna (entre outros):

- `ereborcodeforge/mithrilphp:^3.1`
- `ereborcodeforge/mazarbul:^1.0`
- `ereborcodeforge/durin-core:^0.1`
- `ereborcodeforge/durin-presets:^0.1.3`
- `ereborcodeforge/durin-architecture:^0.1`

DX de banco: `db()` / `DB::database('name')` (Mazarbul). Migrations DDL ainda usam `DB::pdo()` quando precisam de atributos PDO.

---

## Como rodar (runtime)

```bash
vendor/bin/forge server:install          # baixa Eregion → .mithril/bin
vendor/bin/forge eregion:craft           # eregion.yaml + var/runtime/eregion.json
durins-forge optimize
vendor/bin/forge server:check
durins-forge serve --host=0.0.0.0 --port=8080
# CI / container: vendor/bin/durins-forge optimize
```

Neste checkout, `php bin/durins-forge` é o entrypoint. `php bin/durin` permanece como alias.

### `durins-forge serve` / `durins-forge dev`

Entrada orientada a produção via `RuntimeFacade` → Mithril/Eregion. Defaults de `dev` usam `127.0.0.1:8080` e `APP_ENV=development`.

```bash
durins-forge doctor
durins-forge status
durins-forge dev
```

`doctor` = a app **pode** rodar · `status` = o que está **configurado/disponível agora**. Sem `--watch` no V1.

---

## Ownership

| Pacote / área | Responsabilidade |
|---------------|------------------|
| **`durin-core`** | Project/manifest/scaffold primitives |
| **`durin-presets`** | Presets e templates de `durin new` |
| **`durin-architecture`** | Planners/detectors de arquitetura |
| **`durins-forge` Tooling** | Doctor, Generators, Graph, Runtime + CLI |
| **App consumidora** | `App\Kernel`, `config/`, `routes/`, `public/`, `.env` |

Ver [ADR-0004](docs/adr/ADR-0004-tooling-package-boundaries.md), [ADR-0005](docs/adr/ADR-0005-forge-consumer-mode.md) e [ADR-0006](docs/adr/ADR-0006-durins-forge-package-contract.md).

Contrato público: [`docs/public-api.md`](docs/public-api.md) · arquitetura do pacote: [`docs/package-architecture.md`](docs/package-architecture.md).

Skeleton de aplicação (não autoloadado como framework): [`resources/skeleton/application/`](resources/skeleton/application/).

> **Distribuição:** `ereborcodeforge/durins-forge` **`v0.1.0`** está no Packagist (tag apontando para o merge do package-contract). Apps geradas (`durin-presets` ≥ `0.1.3`) dependem só de Forge — sem `repositories` VCS. Pacotes futuros `durin-app` / `durin-installer` ainda não existem.

---

## Kernel e artifacts

- Consumidor: `App\Kernel` compõe `EreborCodeForge\Durin\Forge\Core\Http\HttpApplicationKernel`
- Preferência: `var/cache/container.php` + `var/cache/routes.php` (ignorados com `APP_ENV=testing`)
- Fallback (dev): providers via discovery + `require` de `routes/*.php`
- `ApplicationPath` / `base_path()` resolvem a **raiz da aplicação**, não o path do package em `vendor/`

```bash
durins-forge optimize
durins-forge container:compile
durins-forge routes:compile
durins-forge config:cache
```

---

## CLI Durin

```bash
durins-forge
# CI / container:
vendor/bin/durins-forge
# neste checkout:
php bin/durins-forge
# alias de compatibilidade, não recomendado:
php bin/durin
```

| Comando | Descrição |
|---------|-----------|
| `new` | Cria projeto a partir de preset (`--preset=minimal\|service\|worker`) |
| `doctor` | Diagnóstico PHP / projeto / Mithril-Eregion / artefatos (`--json`, `--strict`) |
| `status` | Status do runtime / tooling |
| `dev` / `serve` | Sobe runtime via Mithril/Eregion |
| `optimize` | Compila container + routes |
| `graph:dependencies` | Grafo de dependências |
| `make:module` / `make:usecase` / `make:feature` | Generators |
| `migrate` / `migrate:rollback` / `migrate:fresh` | Migrations |

---

## Testes

```bash
composer test
```

Inclui fronteira de namespace (`App\` proibido em `src/`), contrato dos presets gerados e integração consumer-mode (writes só na app).

---

## WorkerGuard

`durin worker:audit` roda dois requests artificiais no mesmo worker, em memória, sem subir o Eregion e sem escrever em `vendor/`. Um watchpoint explícito não é isolamento geral: o Guard só vê o que a app registrou.

Copie [`resources/stubs/config/worker_guard.php`](resources/stubs/config/worker_guard.php) para `config/worker_guard.php` na app. O default é `off`.

```bash
php bin/durin worker:audit --scenario=tests/WorkerGuard/CleanScenario.php
php bin/durin worker:audit --scenario=tests/WorkerGuard/CleanScenario.php --format=json
php bin/durin worker:audit --scenario=tests/WorkerGuard/ContaminatedScenario.php --strict
```

Exit `0` limpo, `1` finding relevante (`--strict` inclui warning), `2` cenário, config ou bootstrap inválido. O relatório fica em `var/worker-guard/latest.json`.

---

## Próximo passo

O pacote de aplicação mínima **`durin-app`** (e depois `durin-installer`) ainda não faz parte deste repositório. Este package está pronto para ser consumido via Composer conforme [ADR-0005](docs/adr/ADR-0005-forge-consumer-mode.md).
