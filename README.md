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
vendor/bin/durin doctor
vendor/bin/durin dev
vendor/bin/durin make:feature ...
```

Apps geradas por `durin new` já declaram `ereborcodeforge/durins-forge` e documentam `vendor/bin/durin`.

### Desenvolvimento deste repositório

```bash
composer install
# ignore-platform-req=ext-msgpack no Windows se a extensão ainda não estiver instalada

cp .env.example .env
php bin/durin migrate
```

`composer.json` pinna (entre outros):

- `ereborcodeforge/mithrilphp:^2.2`
- `ereborcodeforge/mazarbul:^1.0`
- `ereborcodeforge/durin-core:^0.1`
- `ereborcodeforge/durin-presets:^0.1.1`
- `ereborcodeforge/durin-architecture:^0.1`

DX de banco: `db()` / `DB::database('name')` (Mazarbul). Migrations DDL ainda usam `DB::pdo()` quando precisam de atributos PDO.

---

## Como rodar (runtime)

```bash
vendor/bin/forge server:install          # baixa Eregion → .mithril/bin
vendor/bin/forge eregion:craft           # eregion.yaml + var/runtime/eregion.json
vendor/bin/durin optimize
vendor/bin/forge server:check
vendor/bin/durin serve --host=0.0.0.0 --port=8080
```

Neste repo (DX local), `php bin/durin` é equivalente.

### `durin serve` / `durin dev`

Entrada orientada a produção via `RuntimeFacade` → Mithril/Eregion. Defaults de `dev` usam `127.0.0.1:8080` e `APP_ENV=development`.

```bash
vendor/bin/durin doctor
vendor/bin/durin status
vendor/bin/durin dev
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

Ver [ADR-0004](docs/adr/ADR-0004-tooling-package-boundaries.md) e [ADR-0005](docs/adr/ADR-0005-forge-consumer-mode.md).

Skeleton de aplicação (não autoloadado como framework): [`resources/skeleton/application/`](resources/skeleton/application/).

---

## Kernel e artifacts

- Consumidor: `App\Kernel` compõe `EreborCodeForge\Durin\Forge\Core\Http\HttpApplicationKernel`
- Preferência: `var/cache/container.php` + `var/cache/routes.php` (ignorados com `APP_ENV=testing`)
- Fallback (dev): providers via discovery + `require` de `routes/*.php`
- `ApplicationPath` / `base_path()` resolvem a **raiz da aplicação**, não o path do package em `vendor/`

```bash
vendor/bin/durin optimize
vendor/bin/durin container:compile
vendor/bin/durin routes:compile
vendor/bin/durin config:cache
```

---

## CLI Durin

```bash
vendor/bin/durin
# neste repo:
php bin/durin
php bin/durins-forge
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

## Próximo passo

O pacote de aplicação mínima **`durin-app`** (e depois `durin-installer`) ainda não faz parte deste repositório. Este package está pronto para ser consumido via Composer conforme [ADR-0005](docs/adr/ADR-0005-forge-consumer-mode.md).
