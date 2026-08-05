COMPOSE ?= docker compose
BENCH_REQUESTS ?= 300
BENCH_WARMUP ?= 30

.PHONY: help build up down restart logs ps \
	up-live up-compiled \
	bench bench-live bench-compiled \
	bench-secure bench-ratelimit bench-api \
	shell-live shell-compiled compile-status

help: ## Lista comandos
	@grep -E '^[a-zA-Z_-]+:.*?##' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-18s\033[0m %s\n", $$1, $$2}'

build: ## Build da imagem PHP (Linux, OPcache, sem Xdebug)
	$(COMPOSE) build

up: ## Sobe live (:8082) e compiled (:8081)
	$(COMPOSE) up -d --build app-live app-compiled
	@echo ""
	@echo "live:      http://localhost:8082/api/health"
	@echo "compiled:  http://localhost:8081/api/health"
	@echo "rode:      make bench"

up-live: ## Sobe só o cenário não compilado (:8082)
	$(COMPOSE) up -d --build app-live

up-compiled: ## Sobe só o cenário compilado (:8081)
	$(COMPOSE) up -d --build app-compiled

down: ## Para e remove containers
	$(COMPOSE) down

restart: down up ## Reinicia os dois cenários

logs: ## Logs dos dois apps
	$(COMPOSE) logs -f app-live app-compiled

ps: ## Status dos containers
	$(COMPOSE) ps

bench: ## Compara live vs compiled (precisa dos dois no ar)
	@echo "Aguardando health dos apps..."
	@$(COMPOSE) up -d --build app-live app-compiled
	@$(COMPOSE) --profile bench run --rm \
		-e BENCH_REQUESTS=$(BENCH_REQUESTS) \
		-e BENCH_WARMUP=$(BENCH_WARMUP) \
		bench both

bench-live: ## Benchmark só live
	@$(COMPOSE) up -d --build app-live
	@$(COMPOSE) --profile bench run --rm --no-deps \
		-e BENCH_REQUESTS=$(BENCH_REQUESTS) \
		-e BENCH_WARMUP=$(BENCH_WARMUP) \
		-e LIVE_URL=http://app-live:8080/api/health \
		bench live

bench-compiled: ## Benchmark só compiled
	@$(COMPOSE) up -d --build app-compiled
	@$(COMPOSE) --profile bench run --rm --no-deps \
		-e BENCH_REQUESTS=$(BENCH_REQUESTS) \
		-e BENCH_WARMUP=$(BENCH_WARMUP) \
		-e COMPILED_URL=http://app-compiled:8080/api/health \
		bench compiled

shell-live: ## Shell no container live
	$(COMPOSE) exec app-live sh

shell-compiled: ## Shell no container compiled
	$(COMPOSE) exec app-compiled sh

compile-status: ## Mostra se cada container tem cache optimize
	@echo "=== live ===" && $(COMPOSE) exec app-live sh -c 'ls -la var/cache/container.php var/cache/routes.php 2>/dev/null || echo "(sem artifacts)"'
	@echo "=== compiled ===" && $(COMPOSE) exec app-compiled sh -c 'ls -la var/cache/container.php var/cache/routes.php 2>/dev/null || echo "(sem artifacts)"'

# --- Stress local (Eregion + wrk no WSL/host) ---
BASE_URL ?= http://127.0.0.1:8080
THREADS ?= 2
CONNECTIONS ?= 20
DURATION ?= 10s

bench-secure: ## wrk no /api/products/secure (com e sem Bearer)
	@command -v wrk >/dev/null || (echo "instale wrk (WSL: apt install wrk)" && exit 1)
	@echo "== secure + token =="
	wrk -t$(THREADS) -c$(CONNECTIONS) -d$(DURATION) --latency \
		-s bench/wrk/secure.lua $(BASE_URL)/api/products/secure
	@echo ""
	@echo "== secure anon (401) =="
	wrk -t$(THREADS) -c$(CONNECTIONS) -d$(DURATION) --latency \
		-s bench/wrk/secure_anon.lua $(BASE_URL)/api/products/secure

bench-ratelimit: ## wrk + curl no /api/products/limited (5/min → 429)
	@command -v wrk >/dev/null || (echo "instale wrk (WSL: apt install wrk)" && exit 1)
	@echo "== sequential (expect 200 x5 then 429) =="
	@for i in 1 2 3 4 5 6 7 8; do \
		c=$$(curl -s -o /dev/null -w '%{http_code}' $(BASE_URL)/api/products/limited); \
		echo "  #$$i → HTTP $$c"; \
	done
	@echo ""
	@echo "== wrk burst =="
	wrk -t$(THREADS) -c$(CONNECTIONS) -d$(DURATION) --latency \
		-s bench/wrk/ratelimit.lua $(BASE_URL)/api/products/limited

bench-api: ## Suite completa: public + secure + ratelimit
	@sed -i 's/\r$$//' bench/stress.sh 2>/dev/null || true
	@chmod +x bench/stress.sh
	BASE_URL=$(BASE_URL) THREADS=$(THREADS) CONNECTIONS=$(CONNECTIONS) DURATION=$(DURATION) \
		./bench/stress.sh

test-secure-wrk: ## wrk no /api/products/secure (Bearer + anon)
	@sed -i 's/\r$$//' bench/test-secure-wrk.sh 2>/dev/null || true
	@chmod +x bench/test-secure-wrk.sh
	BASE_URL=$(BASE_URL) TOKEN=$(TOKEN) THREADS=$(THREADS) CONNECTIONS=$(CONNECTIONS) DURATION=$(DURATION) \
		./bench/test-secure-wrk.sh
