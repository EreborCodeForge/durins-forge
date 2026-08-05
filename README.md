# Durin's Forge

Framework e forjador de apps sobre **MithrilPHP**, com Clean Architecture. Em produção, **Eregion** guarda o HTTP; o Worker Mithril mantém o Kernel aquecido.

> **Durin forja a app → Mithril aquece o Worker → Eregion guarda os portões.**

---

## Requisitos

- PHP **8.3+**
- Extensões: `json`, **`msgpack`**, **`sockets`**
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
- `extra.mithril.kernel = App\\Kernel`
- `extra.mithril.eregion = v0.1.0`

---

## Como rodar (caminho canônico)

```bash
vendor/bin/forge server:install          # baixa Eregion → .mithril/bin
vendor/bin/forge eregion:craft           # eregion.yaml + var/runtime/eregion.json
vendor/bin/durin optimize                # após composer install (link-bins)
# equivalente: php bin/durin optimize
vendor/bin/forge server:check
vendor/bin/forge serve --host=0.0.0.0 --port=8080
```

### Dev sem Eregion

O `public/index.php` é o **worker UDS** (`EregionBridge`), não front FPM. Para HTTP local use o caminho canônico:

```bash
vendor/bin/forge serve --host=127.0.0.1 --port=8080
```

(`forge serve:php` / `php -S -t public` devolve 503 — o entrypoint não fala HTTP.)

Alias opcional:

```bash
php bin/durin serve --host=0.0.0.0 --port=8080   # → vendor/bin/forge serve
```

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
| `optimize` | Compila container + rotas → `var/cache/` |
| `serve` | Alias fino para `forge serve` |
| `migrate` / `migrate:fresh` / `migrate:rollback` | Migrações |
| `make:usecase` | Gera DTO + Use Case |
| `config:cache` / `config:clear` | Cache de config em `var/cache/` |
| `container:compile` / `container:clear` | Container |
| `routes:compile` / `routes:clear` | Rotas |
| `routes:postman` | Export Postman |

`vendor/bin/forge` permanece o CLI do **Mithril** (serve, eregion:craft, server:*).

---

## Frontend (opcional)

```bash
npm install
npm run dev      # desenvolvimento
npm run build    # produção → public/build/
```

---

## Docker

Imagem com PHP 8.3 + sockets + msgpack. O entrypoint roda `durin optimize` (modo compiled) e preferencialmente `forge serve`; se o check do Eregion falhar, cai em `forge serve:php`.

```bash
make up      # live :8082 | compiled :8081
make bench
make down
```

Detalhes: [docs/PERFORMANCE.md](docs/PERFORMANCE.md). Spec completa: [docs/durins-forge-eregion-spec.md](docs/durins-forge-eregion-spec.md).

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

---

## Licença

MIT — *Build with Mithril. Shape with Durin. Run in Eregion.*
