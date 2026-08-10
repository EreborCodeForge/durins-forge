# Spec: Durin’s Forge × MithrilPHP × Eregion

**Status:** Implementation guide for Durin’s Forge  
**Versão:** 1.0  
**Data:** 2026-08-04  
**Stack:**

| Camada | Pacote / binário | Papel |
|--------|------------------|-------|
| Engine | `ereborcodeforge/mithrilphp` ^2.1 | Worker, DI, HTTP, Forge CLI, bridge Eregion |
| Server | Eregion Go `v0.3.0+` (protocol `eregion/1`) | HTTP público, pool, UDS, recycle |
| Framework | **Durin’s Forge** (este documento) | App skeleton, Kernel, rotas, compile, DX |

Contratos irmãos:

- [runtime-worker.md](runtime-worker.md)
- [eregion-binary-distribution.md](eregion-binary-distribution.md)
- Bridge PHP: implementação em `src/Runtime/Eregion/`

---

## 1. Fronteira de responsabilidade

### 1.1 MithrilPHP já fornece (não reimplementar)

- `HttpApplication`, `Worker`, `beginScope` / `endScope`
- `EregionBridge` + `bin/eregion-worker`
- Forge CLI: `eregion:craft`, `serve`, `serve:php`, `server:install`, `server:check`, `server:version`
- Manifesto `var/runtime/eregion.json`
- Request/Response (body binário, headers multi-valor)
- Container compilado + Router compilado (APIs)

### 1.2 Eregion (Go) já fornece

- Servidor HTTP / pool / fila / timeouts
- Spawn do worker PHP com flags CLI
- Protocolo UDS + MessagePack
- Defaults de `eregion.yaml` omitidos
- Releases com assets + `checksums.txt`

### 1.3 Durin’s Forge **deve** implementar

| Área | Obrigatório |
|------|-------------|
| Skeleton de app | `composer create-project` / `durin new` |
| `App\Kernel` | `implements HttpApplication` |
| Boot | Carregar `.env`, config, container, rotas |
| Compile | Gerar `var/cache/container.php` e `var/cache/routes.php` |
| Escopos | Request-bound como **scoped**; singletons warm |
| Exceções HTTP | Tratar no Kernel → Response (500 válido ≠ crash) |
| DX | Comandos de framework (migrate, make:*, etc.) **sem** substituir `forge serve` |
| Docs da app | Como instalar e subir com Eregion |

### 1.4 Durin’s Forge **não deve**

- Implementar servidor HTTP próprio para produção
- Reimplementar UDS / MessagePack / handshake
- Usar `resetWorker()` como recycle de processo no v1
- Esconder o contrato `HttpApplication` atrás de um Kernel incompatível
- Exigir worker PHP custom por app (o entrypoint é `eregion-worker`)

---

## 2. Contrato do Kernel (obrigatório)

```php
<?php

declare(strict_types=1);

namespace App;

use Erebor\Mithril\Container;
use Erebor\Mithril\Contracts\HttpApplication;
use Erebor\Mithril\Http\Request;
use Erebor\Mithril\Http\Response;

final class Kernel implements HttpApplication
{
    private Container $container;
    private bool $booted = false;

    public function boot(): void
    {
        if ($this->booted) {
            return; // obrigatório: idempotente
        }

        // 1. env / config
        // 2. prefer loadCompiled(container) + loadCompiledRoutes
        // 3. fallback: bind runtime se artifacts ausentes (dev)

        $this->booted = true;
    }

    public function handle(Request $request): Response
    {
        // Router + pipeline + domínio
        // Exceptions → Response (ex.: 500 JSON/HTML)
    }

    public function getContainer(): Container
    {
        return $this->container;
    }
}
```

Regras:

1. **Boot uma vez por processo** — Worker chama `boot()` no start; nunca assumir reboot por request.
2. **Sem estado de request em singleton/global** — tickets, auth, UoW → `scoped()`.
3. **Router / config / logger / pools** → singleton ou preloaded no artifact.
4. **Constructor sem side effects pesados** — trabalho de I/O no `boot()`.
5. Classe descoberta por:
   - `composer.json` → `extra.mithril.kernel`
   - ou env `MITHRIL_KERNEL`
   - fallback `App\Kernel`

```json
{
  "extra": {
    "mithril": {
      "kernel": "App\\Kernel",
      "eregion": "v0.3.0"
    }
  }
}
```

---

## 3. Layout mínimo da aplicação Durin

```text
my-app/
├── app/
│   └── Kernel.php                 # HttpApplication
├── config/                        # config da app (Durin)
├── public/
│   └── index.php                  # FPM / serve:php (FpmOnceBridge + Worker)
├── routes/
│   └── web.php                    # ou equivalente Durin
├── var/
│   ├── cache/
│   │   ├── container.php          # forge/durin optimize
│   │   └── routes.php
│   └── runtime/
│       └── eregion.json           # forge eregion:craft / serve
├── eregion.yaml                   # starter (Go consome)
├── .env
├── composer.json
└── vendor/
```

`public/index.php` (dev / FPM):

```php
<?php

declare(strict_types=1);

use App\Kernel;
use Erebor\Mithril\Runtime\FpmOnceBridge;
use Erebor\Mithril\Runtime\Worker;

require __DIR__ . '/../vendor/autoload.php';

(new Worker(new Kernel(), new FpmOnceBridge()))->run();
```

Produção **não** usa `index.php` como loop multi-request: usa Eregion → `eregion-worker` → mesmo `Kernel`.

---

## 4. Compile / optimize (Durin)

Durin’s Forge deve expor algo equivalente a:

```bash
vendor/bin/durin optimize
# ou
vendor/bin/forge optimize   # se Durin registrar no Forge
```

Artefatos esperados pelo manifesto Mithril:

| Path | Conteúdo |
|------|----------|
| `var/cache/container.php` | factories / singletons / preloaded |
| `var/cache/routes.php` | rotas compiladas |

Sem artifacts: `server:check` emite **warning**; app pode bootar em modo runtime (mais lento). Em produção, optimize é **obrigatório** no checklist Durin.

---

## 5. Fluxo ponta a ponta (app Durin)

```text
composer create-project ereborcodeforge/durins-forge my-app
cd my-app

composer require ereborcodeforge/mithrilphp:^2.1
# (ou já vem no skeleton)

# PHP 8.3+, ext-msgpack, ext-sockets

vendor/bin/forge server:install          # baixa Eregion pinado → .mithril/bin
vendor/bin/forge eregion:craft           # eregion.yaml + var/runtime/eregion.json
vendor/bin/durin optimize                # container + routes
vendor/bin/forge server:check
vendor/bin/forge serve --host=0.0.0.0 --port=8080
```

O que `forge serve` faz:

1. valida PHP / extensões / Kernel / worker / binário  
2. regenera manifesto  
3. `exec` → `eregion serve --config=eregion.yaml --manifest=...`  
4. Eregion sobe N workers com `php vendor/bin/eregion-worker --socket=... --manifest=...`  
5. PHP escuta UDS, handshake, loop warm  

Dev sem Eregion:

```bash
vendor/bin/forge serve:php --port=8000
```

---

## 6. Instalação — checklist Durin (documentar no README do framework)

### Requisitos de máquina

- PHP **8.3+**
- Extensões: `json`, **`msgpack`**, **`sockets`**
- Composer 2
- OS: Linux/macOS preferencial para UDS em produção; Windows ok para craft/install/dev (AF_UNIX conforme suporte local)

### Dependências Composer (skeleton)

```json
{
  "require": {
    "php": "^8.3",
    "ereborcodeforge/mithrilphp": "^2.1",
    "ext-msgpack": "*",
    "ext-sockets": "*"
  },
  "extra": {
    "mithril": {
      "kernel": "App\\Kernel",
      "eregion": "v0.3.0",
      "eregion_repo": "EreborCodeForge/eregion"
    }
  }
}
```

### Binário Eregion

```bash
vendor/bin/forge server:install
# overrides: EREGION_VERSION, EREGION_BINARY, --version=v0.3.0
vendor/bin/forge server:version
# esperado:
#   eregion 0.3.0
#   protocol eregion/1
```

Não versionar o binário Go no repo da app — usar `.mithril/bin/` (gitignore).

---

## 7. Como rodar

### 7.1 Produção / staging (caminho canônico)

```bash
vendor/bin/durin optimize
vendor/bin/forge server:check
vendor/bin/forge serve --host=0.0.0.0 --port=8080 --workers=4
```

Health / ops: responsabilidade do Eregion (ex. endpoints internos quando existirem). Durin não inventa HTTP server paralelo.

### 7.2 Desenvolvimento rápido

```bash
vendor/bin/forge serve:php --host=127.0.0.1 --port=8000
```

Mesmo Kernel + Worker; um request por processo (`FpmOnceBridge`).

### 7.3 Docker (recomendação Durin)

Imagem com:

- PHP 8.3-cli + msgpack + sockets  
- `composer install --no-dev`  
- `forge server:install` no build ou entrypoint  
- `durin optimize` no build  
- `CMD ["vendor/bin/forge", "serve", "--host=0.0.0.0", "--port=8080"]`

Volume **não** precisa expor `public/` para o Go se Eregion fala só com workers PHP; o entry HTTP é o processo Eregion.

---

## 8. Recycle e lifetimes (regras que Durin deve ensinar)

| Evento | Comportamento |
|--------|----------------|
| HTTP 500 da app | Worker **permanece** vivo |
| `max_requests` (Go) | Eregion conta requests sozinho e recicla o processo |
| memory / `meta.recycle` (PHP) | Continuar enviando `memory_usage` e `meta.recycle` quando fizer sentido; exit `10` no recycle planejado |
| `requests_handled` (PHP) | Diagnóstico apenas — não é a autoridade do recycle |
| Falha de protocolo / frame | exit `21` → crash/replace |
| Falha `endScope` | exit `22` → não processa próximo request |
| EOF / shutdown | encerramento limpo |

Durin **não** chama `resetWorker()` no lugar de recycle de processo no v1. Fila / capacity / logging / prefix são 100% Go + `eregion.yaml`.

---

## 9. O que Durin pode adicionar (opcional, recomendado)

| Feature | Notas |
|---------|--------|
| `durin new` / skeleton | Já com Kernel, routes, `public/index.php`, `.gitignore` (`/var/`, `/.mithril/`, `/eregion.yaml` opcional commitado) |
| `durin optimize` | Compile container + routes |
| `durin serve` | Alias fino para `forge serve` (não fork do protocolo) |
| Middleware pipeline | Em cima do Router Mithril |
| Exception renderer | HTML/JSON por `Accept` / env |
| Config tree | Arrays / arquivos — **não** misturar com `eregion.yaml` |
| Test helpers | Boot Kernel + `InMemoryBridge` |

`eregion.yaml` = config do **servidor Go**.  
Config da app Durin = `.env` + `config/*`. Não fundir.

---

## 10. Critérios de aceite (Durin’s Forge pronto)

A app gerada / framework está alinhado quando:

- [ ] `App\Kernel` implementa `HttpApplication` com boot idempotente
- [ ] `extra.mithril.kernel` aponta para o Kernel
- [ ] `public/index.php` usa `Worker` + `FpmOnceBridge`
- [ ] `durin optimize` gera artifacts em `var/cache/`
- [ ] `forge eregion:craft` + `server:install` + `server:check` passam
- [ ] `forge serve` sobe Eregion e responde HTTP via worker warm
- [ ] Scoped services isolam request; singletons persistem no processo
- [ ] Exception no handle vira 500 sem matar o worker
- [ ] README da app documenta os fluxos §5–§7
- [ ] `.mithril/` e caches locais no `.gitignore`

---

## 11. Fora de escopo deste ciclo

- Autoscaling multi-app no mesmo worker  
- WebSocket / SSE no PHP worker  
- Download do Eregion fora do `forge server:install`  
- Substituir MessagePack por JSON  
- Windows named pipes  

---

## 12. Resumo em uma frase

> **Durin’s Forge forja a aplicação e o Kernel; MithrilPHP mantém o Worker aquecido; Eregion guarda os portões HTTP.**

```text
durin optimize → forge server:install → forge eregion:craft → forge serve
```

**Build with Mithril. Shape with Durin. Run in Eregion.**
