# Performance — Durin's Forge

Resumo das otimizações aplicadas e recomendações para máxima performance.

## Otimizações implementadas

### 1. Fast path para `/api/health`

O endpoint `GET /api/health` é atendido **antes** de carregar o autoload e o Kernel. Isso elimina:

- Carregamento do Composer autoload
- `Environment::load()` e leitura do `.env`
- Registro de providers e rotas
- Container e pipeline

**Uso:** ideal para health checks de load balancers e Kubernetes (alta taxa de requisições, latência mínima).

### 2. Cache de config

Em produção, evite carregar `config/app.php` e múltiplas chamadas a `Environment::get()` em todo request:

```bash
php bin/durin config:cache
```

Isso gera `var/cache/config.php` com o array de config já resolvido. O Kernel usa esse arquivo quando ele existe.

Para voltar a usar config ao vivo (útil após mudar `.env`):

```bash
php bin/durin config:clear
```

### 3. Container + rotas compilados (`durin optimize`)

```bash
php bin/durin optimize
# → var/cache/container.php
# → var/cache/routes.php
```

O **Erebor\Mithril\Container** expõe `loadCompiled(factories, singletons, preloaded, strict)`:

- **factories**: `abstract => callable(Container): object` — nova instância a cada `get()`
- **singletons**: `abstract => callable(Container): object` — instância cacheada após o primeiro `get()`
- **preloaded**: `abstract => object` — instâncias já resolvidas (ex.: vazio)
- **strict**: se `true`, `get()` de algo não registrado lança exceção (sem fallback para reflection)

Com cache compilado, o Kernel **não** carrega config de providers nem chama `register()` em cada provider; apenas faz `require` do arquivo de cache e `$container->loadCompiled(..., strict: true)`. Assim:

- Não há instanciação dos providers nem chamadas a `register()` em runtime
- Resolução usa apenas os arrays compilados (callables), sem registrar em `runtimeBinds` / `runtimeSingletons`
- Em modo compilado **strict**, `get()` de algo não registrado lança exceção (sem fallback para reflection) — middlewares, controllers e deps precisam estar no `describe()` / compile

Para gerar o cache:

```bash
php bin/durin optimize
# ou só:
php bin/durin container:compile
```

Para limpar (voltar a registrar providers ao vivo no próximo boot):

```bash
php bin/durin container:clear
php bin/durin routes:clear
```

**Quando recompilar:** após adicionar/alterar providers, bindings ou rotas; em deploy após `composer install` (junto com `config:cache`).

### 4. Cache de rotas (usado no boot)

Com `var/cache/routes.php` presente, o Kernel chama `Router::loadCompiledRoutes()` e **não** faz `require` de `web.php`/`api.php`. Gerado por `durin optimize` / `routes:compile`.

### 5. Stack recomendado em produção

```bash
php bin/durin config:cache
php bin/durin optimize
vendor/bin/forge server:check
vendor/bin/forge serve --host=0.0.0.0 --port=8080
```

Dev sem Eregion: `vendor/bin/forge serve:php`.

### Benchmark Docker (live vs compiled)

Ambiente Linux controlado (PHP 8.3, OPcache, sem Xdebug), dois containers:

| Serviço | Porta host | Modo |
|---------|------------|------|
| `app-live` | 8082 | discovery + providers (sem artifacts) |
| `app-compiled` | 8081 | `durin optimize` + `loadCompiled(strict: true)` |

```bash
make up          # sobe os dois
make bench       # compara req/s em /api/health
make down        # encerra
```

Outros: `make bench-live`, `make bench-compiled`, `make compile-status`, `make logs`.
Ajuste carga: `make bench BENCH_REQUESTS=500 BENCH_WARMUP=50`.

### 6. Providers e lazy resolution

- **Database (PDO)** e **Cache** são registrados como singletons; a conexão/instância só é criada no primeiro uso. Rotas que não usam DB nem cache não pagam esse custo.
- **VueViewHandler** e **Session** só são resolvidos quando uma rota web renderiza view; rotas de API não instanciam esses serviços.

### 7. HTTP Response Cache (L1/L2)

Middleware `ResponseCacheMiddleware` + attribute `#[CacheResponse]` cacheiam o **Response** (nunca o Request). Em rotas privadas:

1. **`AuthMiddleware` primeiro** — valida e grava `auth.user_id` no `HttpContext`
2. **`ResponseCacheMiddleware` depois** — chave com `vary: user` (sem identity → BYPASS, sem HIT compartilhado)

Eficiência real: chave correta + L1 in-memory no worker warm (Eregion) + L2 (`CacheInterface`/FileCache) + TTL/tags. Header de diagnóstico: `X-Durin-Cache: HIT|MISS|BYPASS`.

Config: `config/cache.php` → `http_response`. Demo: `GET /api/products` (público) e `GET /api/products/secure` (Auth + private).

Após mudar bindings/rotas: `php bin/durin optimize`.

## Recomendações

### OPcache (PHP)

Garanta que o OPcache está habilitado em produção. Ele mantém o bytecode PHP em memória e reduz I/O e parsing em todo request.

```ini
; php.ini
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0   ; use 1 em desenvolvimento para ver mudanças
```

### Servidor HTTP

O servidor built-in (`php -S`) é **single-thread**: atende um request por vez. Para carga real:

- **PHP-FPM + Nginx (ou Caddy):** vários workers em paralelo.
- **RoadRunner:** mantém a aplicação em memória (menos bootstrap por request).
- **Swoole:** servidor assíncrono (requer extensão).

### Benchmark com wrk

Com `php -S`, use poucas conexões para não enfileirar tudo em um único processo:

```bash
wrk -t2 -c16 -d20s http://localhost:8000/api/health
```

Para testar o fast path com muitas conexões, use um servidor com múltiplos workers (ex.: PHP-FPM com `pm.max_children` alto).

## Resumo

| Medida                         | Efeito                                                                 |
|--------------------------------|------------------------------------------------------------------------|
| Fast path `/api/health`        | Máximo throughput e latência mínima                                   |
| `config:cache` em produção     | Menos I/O e menos chamadas a `Environment`                            |
| `container:compile` em produção | Uso de `Container::loadCompiled()` — sem providers em runtime        |
| Response cache (Auth→Cache)    | Evita reexecução do controller; L1 worker + L2 file; vary por user   |
| OPcache                       | Bytecode em memória, menos parsing                                      |
| PHP-FPM / RoadRunner / Swoole | Concorrência real, mais req/s                                          |
