# Durin's Forge

Framework e forjador de apps sobre **MithrilPHP**, com Clean Architecture. Em produção, **Eregion** guarda o HTTP; o Worker Mithril mantém o Kernel aquecido.

> **Durin forja a app → Mithril aquece o Worker → Eregion guarda os portões.**

Programa DX V1 (SPECs 001–016) entregue: foundation, doctor, runtime, presets, generators e grafo de dependências.

---

## Requisitos

- PHP **8.5+** ([ADR-0001](docs/adr/ADR-0001-php-version-baseline.md))
- Extensões: `json`, `pdo`, **`msgpack`**, **`sockets`**
- Composer 2
- Node.js (opcional, frontend Vue/Vite)

---

## Instalação

```bash
composer install
# ignore-platform-req=ext-msgpack no Windows se a extensão ainda não estiver instalada

cp .env.example .env
php bin/durin migrate
```

`composer.json` pinna:

- `ereborcodeforge/mithrilphp:^2.1`
- `ereborcodeforge/mazarbul:^1.0` — persistência oficial (lazy connections, stream, bulk)
- `extra.mithril.kernel = App\\Kernel`
- `extra.mithril.eregion = v0.3.0`

DX de banco: `db()` / `DB::database('name')` (Mazarbul). Migrations DDL ainda usam `DB::pdo()` quando precisam de atributos PDO.

---

## Como rodar (caminho canônico)

```bash
vendor/bin/forge server:install          # baixa Eregion → .mithril/bin
vendor/bin/forge eregion:craft           # eregion.yaml + var/runtime/eregion.json
vendor/bin/durin optimize                # após composer install (link-bins)
vendor/bin/forge server:check
php bin/durin serve --host=0.0.0.0 --port=8080
```

### `durin serve` (produção)

Entrada **orientada a produção**: `RuntimeFacade` → `vendor/bin/forge serve` → Eregion. Durin não implementa o servidor HTTP.

Defaults: `--host=0.0.0.0`, `--port=8080`. Flags extras são repassadas ao Forge.

```bash
php bin/durin serve --host=0.0.0.0 --port=8080 --workers=4
```

### `durin dev` (local)

Mesmo `RuntimeFacade` de produção, com defaults locais (`127.0.0.1:8080`, `APP_ENV=development`).

```bash
php bin/durin doctor
php bin/durin status
php bin/durin dev
```

Escape hatch: `--php` chama `forge serve:php`. Neste skeleton o `public/index.php` é worker UDS — `serve:php` / `php -S` tipicamente devolvem **503**. Preferir Eregion também em local.

`doctor` = a app **pode** rodar · `status` = o que está **configurado/disponível agora** (binário, `eregion.yaml`, manifesto). Sem `--watch` no V1.

---

## Fronteira de responsabilidade

| Camada | Papel |
|--------|--------|
| **Durin’s Forge** | Skeleton, `App\Kernel`, presets, generators, doctor/status/graph, `durin optimize`, migrate |
| **MithrilPHP** | Worker, DI, HTTP, Forge CLI, bridge Eregion (`eregion-worker`) |
| **Eregion (Go)** | HTTP público, pool, UDS, recycle |

Durin **não** reimplementa UDS/MessagePack nem servidor HTTP de produção ([ADR-0002](docs/adr/ADR-0002-cli-runtime-boundaries.md)).

Tooling interno vive em `App\Tooling\` (Project, Scaffold, Doctor, Runtime, Presets, Generators, Graph) — sem pacotes Composer separados no V1 ([ADR-0004](docs/adr/ADR-0004-tooling-package-boundaries.md)).

---

## Kernel e artifacts

- [`src/Kernel.php`](src/Kernel.php) — `implements HttpApplication`, boot **idempotente**
- Preferência: `var/cache/container.php` + `var/cache/routes.php`
- Fallback (dev): providers via discovery + `require` de `routes/*.php`
- Request-bound (ex.: `SessionManager`) → `scoped()`; Router/config → singleton

```bash
php bin/durin optimize           # container + routes
php bin/durin container:compile
php bin/durin routes:compile
php bin/durin config:cache
```

---

## CLI Durin

```bash
php bin/durin
# alias
php bin/durins-forge
```

| Comando | Descrição |
|---------|-----------|
| `new` | Cria projeto a partir de preset (`--preset=minimal\|service`) |
| `doctor` | Diagnóstico PHP / projeto / Mithril-Eregion / artefatos (`--json`, `--strict`) |
| `status` | Runtime configurado agora (`--json`) |
| `dev` | Dev local via facade → Eregion (`--php` = serve:php) |
| `serve` | Produção via facade → Mithril/Eregion |
| `optimize` | Compila container + rotas → `var/cache/` |
| `graph:dependencies` | Grafo de deps (`--format=text\|mermaid\|json`, `--module=`) |
| `make:module` | `src/Modules/{Name}/module.php` (+ `architecture.modules` no `durin.yaml`) |
| `make:usecase` | DTO + Use Case (`Domain/Name` ou `--module=Billing`) |
| `make:feature` | Módulo + use case (`Module/Name`; opcional `--http` `--tests`) |
| `migrate` / `migrate:fresh` / `migrate:rollback` | Migrações |
| `seed` | Seeds |
| `config:cache` / `config:clear` | Cache de config |
| `container:compile` / `container:clear` | Container |
| `routes:compile` / `routes:clear` | Rotas |
| `routes:postman` | Export Postman |

`vendor/bin/forge` permanece o CLI do **Mithril** (`serve`, `eregion:craft`, `server:*`).

---

## Presets

```bash
php bin/durin new webhook-api --preset=minimal
php bin/durin new billing --preset=service
```

| Preset | Quando usar | Estrutura |
|--------|-------------|-----------|
| `minimal` | API pequena / webhook | `src/Http`, `src/Application` (sem Domain) |
| `service` | Backend service geral | roots `Domain`, `Application`, `Infrastructure`, `Presentation` |

Ambos gravam `durin.yaml` e compartilham baseline PHP 8.5 + Mithril ([ADR-0003](docs/adr/ADR-0003-architecture-presets.md)). Presets `modular` / `microservice` / `worker` ficam fora do V1.

---

## Generators

Escrita via `ScaffoldPlan` → `ScaffoldWriter` (conflict-safe; overwrite idêntico é idempotente).

```bash
# Módulo (marcador mínimo — sem árvores Domain/Http vazias)
php bin/durin make:module Billing

# Use case — layout legado Application
php bin/durin make:usecase User/CreateUser

# Use case — layout modular (master §27)
php bin/durin make:usecase CreateInvoice --module=Billing
# → src/Modules/Billing/Application/CreateInvoice/{CreateInvoice,CreateInvoiceInput}.php

# Feature = módulo + use case (+ opcional Http/tests) em um único write
php bin/durin make:feature Billing/CreateInvoice --http --tests
```

`--repository` / `--migration` em `make:feature` ainda não são suportados (erro explícito).

---

## Grafo de dependências

Fontes V1 (sem AST): marcadores `src/Modules/*/module.php`, `var/cache/routes.php`, e opcionalmente `var/cache/container.descriptor.php` (formato `DescriptorProvider`).

```bash
php bin/durin graph:dependencies
php bin/durin graph:dependencies --format=mermaid
php bin/durin graph:dependencies --format=json
php bin/durin graph:dependencies --module=Billing
```

---

## Frontend (opcional)

```bash
npm install
npm run dev      # desenvolvimento
npm run build    # produção → public/build/
```

---

## Docker

Imagem com PHP 8.5 + sockets + msgpack. O entrypoint roda `durin optimize` e preferencialmente `forge serve`; se o check do Eregion falhar, cai em `forge serve:php`.

```bash
make up      # live :8082 | compiled :8081
make bench
make down
```

---

## Estrutura (resumo)

```
src/Kernel.php                 # HttpApplication
src/Core/                      # Discovery, compile, HTTP pipeline
src/Application|Domain|…       # Clean Architecture (preset service)
src/Modules/{Name}/            # Módulos (make:module / make:feature)
src/Tooling/                   # DX: Project, Scaffold, Doctor, Runtime,
                               #     Presets, Generators, Graph
src/Console/Commands/          # CLI durin
routes/                        # web.php + api.php
var/cache/                     # container.php, routes.php, artifacts
var/runtime/eregion.json       # manifesto Eregion
public/index.php               # Worker + EregionBridge
docs/
  product/PRD.md
  architecture/
  adr/
  specs/master-dx-tooling-spec.md
  specs/derived/               # SPECs 001–016
```

---

## Testes

```bash
composer test
# ou
./vendor/bin/phpunit
```

CI (GitHub Actions) executa a suíte em **PHP 8.5** com `msgpack` e `sockets` ([ADR-0001](docs/adr/ADR-0001-php-version-baseline.md)).

---

## Documentação

| Doc | Conteúdo |
|------|----------|
| [PRD](docs/product/PRD.md) | Produto DX |
| [Architecture](docs/architecture/overview.md) | Visão e [fronteiras](docs/architecture/boundaries.md) |
| [ADRs](docs/adr/) | Decisões (PHP 8.5, CLI/runtime, presets, tooling) |
| [Master DX spec](docs/specs/master-dx-tooling-spec.md) | Spec canônica |
| [SPECs derivadas](docs/specs/derived/) | Fatias 001–016 (implementadas) |
| [Eregion](docs/durins-forge-eregion-spec.md) | Runtime HTTP |
| [Performance](docs/PERFORMANCE.md) | Docker / bench |

---

## Licença

MIT — *Build with Mithril. Shape with Durin. Run in Eregion.*
