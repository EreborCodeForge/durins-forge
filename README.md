# Durin's Forge

Framework e forjador de apps sobre **MithrilPHP**, com Clean Architecture. Em produção, **Eregion** guarda o HTTP; o Worker Mithril mantém o Kernel aquecido.

> **Durin forja a app → Mithril aquece o Worker → Eregion guarda os portões.**

---

## Requisitos

- PHP **8.5+** (no Windows use **WSL** se o host ainda estiver em 8.3)
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

`composer.json` já pinna:

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
# equivalente: php bin/durin optimize
vendor/bin/forge server:check
php bin/durin serve --host=0.0.0.0 --port=8080
```

### `durin serve` (produção)

`durin serve` é a entrada **orientada a produção**: orquestra via `RuntimeFacade` → `vendor/bin/forge serve` → Eregion. Durin não implementa o servidor HTTP.

Defaults: `--host=0.0.0.0`, `--port=8080`. Flags extras são repassadas ao Forge.

```bash
php bin/durin serve --host=0.0.0.0 --port=8080 --workers=4
```

Equivalente de baixo nível: `vendor/bin/forge serve ...`.

### Dev local

`durin dev` é a entrada **de desenvolvimento**: defaults locais (`127.0.0.1:8080`, `APP_ENV=development`) e o **mesmo** `RuntimeFacade` de produção (Eregion). Não é um segundo stack de startup.

```bash
php bin/durin doctor
php bin/durin dev
```

Escape hatch (limitado): `--php` chama `forge serve:php`. Neste skeleton o `public/index.php` é worker UDS — `serve:php` / `php -S` tipicamente devolvem **503**. Preferir Eregion também em local.

Hot-reload completo depende do que Mithril/Eregion expõem; Durin não finge hot-reload se o runtime não tiver.

---

## Fronteira de responsabilidade

| Camada | Papel |
|--------|--------|
| **Durin’s Forge** | Skeleton, `App\Kernel` (`HttpApplication`), rotas, `durin optimize`, migrate/make |
| **MithrilPHP** | Worker, DI, HTTP, Forge CLI, bridge Eregion (`eregion-worker`) |
| **Eregion (Go)** | HTTP público, pool, UDS, recycle |

Durin **não** reimplementa UDS/MessagePack nem servidor HTTP de produção.

---

## Kernel e artifacts

- [`src/Kernel.php`](src/Kernel.php) — `implements HttpApplication`, boot **idempotente**
- Preferência: `var/cache/container.php` + `var/cache/routes.php`
- Fallback (dev): providers via discovery + `require` de `routes/*.php`
- Request-bound (ex.: `SessionManager`) → `scoped()`; Router/config → singleton
- Exceções em `handle()` → Response 500 (worker permanece vivo)

```bash
php bin/durin optimize           # container + routes
php bin/durin container:compile  # só container
php bin/durin routes:compile     # só rotas
php bin/durin config:cache
```

---

## CLI Durin

```bash
php bin/durin
# ou
php bin/durins-forge
```

| Comando | Descrição |
|---------|-----------|
| `new` | Cria projeto a partir de preset (`--preset=minimal|service`) |
| `optimize` | Compila container + rotas → `var/cache/` |
| `doctor` | Diagnóstico de PHP, projeto, Mithril/Eregion e artefatos (`--json`, `--strict`) |
| `dev` | Desenvolvimento local via facade → Eregion (`--php` = serve:php) |
| `serve` | Runtime de produção via facade → Mithril/Eregion (`forge serve`) |
| `status` | O que está configurado/disponível agora (`--json`; sem `--watch` nesta fatia) |
| `migrate` / `migrate:fresh` / `migrate:rollback` | Migrações |
| `make:module` | Cria `src/Modules/{Name}` com marcador mínimo |
| `make:usecase` | Gera DTO + Use Case (`Domain/Name` ou `--module=Billing`) |
| `config:cache` / `config:clear` | Cache de config em `var/cache/` |
| `container:compile` / `container:clear` | Container |
| `routes:compile` / `routes:clear` | Rotas |
| `routes:postman` | Export Postman |

`vendor/bin/forge` permanece o CLI do **Mithril** (serve, eregion:craft, server:*).

`doctor` = a app **pode** rodar; `status` = o que está **configurado/disponível agora** (binário, `eregion.yaml`, manifesto). Métricas live de workers ficam fora desta fatia (sem `--watch`).

### Presets

```bash
php bin/durin new webhook-api --preset=minimal
php bin/durin new billing --preset=service
```

| Preset | Quando usar | Estrutura |
|--------|-------------|-----------|
| `minimal` | API pequena / webhook | `src/Http`, `src/Application` (sem Domain) |
| `service` | Backend service geral | `Domain`, `Application`, `Infrastructure`, `Presentation` (só roots; sem Entity/Repository vazios) |

Ambos gravam `durin.yaml` e compartilham baseline PHP 8.5 + Mithril.

---

## Frontend (opcional)

```bash
npm install
npm run dev      # desenvolvimento
npm run build    # produção → public/build/
```

---

## Docker

Imagem com PHP 8.5 + sockets + msgpack. O entrypoint roda `durin optimize` (modo compiled) e preferencialmente `forge serve`; se o check do Eregion falhar, cai em `forge serve:php`.

```bash
make up      # live :8082 | compiled :8081
make bench
make down
```

Detalhes: [docs/PERFORMANCE.md](docs/PERFORMANCE.md). Spec Eregion: [docs/durins-forge-eregion-spec.md](docs/durins-forge-eregion-spec.md).

DX / tooling (programa): [docs/specs/master-dx-tooling-spec.md](docs/specs/master-dx-tooling-spec.md) · [PRD](docs/product/PRD.md) · [ADRs](docs/adr/) · [specs derivadas](docs/specs/derived/).

---

## Estrutura (resumo)

```
src/Kernel.php              # HttpApplication
src/Core/                   # Discovery, compile, HTTP pipeline
src/Application|Domain|…    # Clean Architecture
routes/                     # web.php + api.php
var/cache/                  # artifacts (gitignore)
var/runtime/eregion.json    # manifesto (gitignore via /var/)
public/index.php            # Worker + EregionBridge (spawned by Eregion)
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

## Licença

MIT — *Build with Mithril. Shape with Durin. Run in Eregion.*
