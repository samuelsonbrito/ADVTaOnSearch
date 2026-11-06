# CLAUDE.md — AdvTaOn Search

Aplicação Laravel que consulta, monitora e exporta comunicações processuais
(intimações, citações, editais) do **DJEN**, usando a Comunica API do PJe/CNJ.

## Stack

- PHP 8.3+ · Laravel 13 · Fortify (auth do Vue Starter Kit oficial)
- Vue 3 (`<script setup>`) + Inertia 3 + TypeScript + Tailwind 4 + shadcn-vue
- Vite+ (`vp`): build, Vitest (`vp test`), lint/format (Oxlint/Oxfmt via `vp check`)
- Rotas no front: **Ziggy** (`route('nome')`) nas nossas telas; o kit usa Wayfinder nas telas de auth/settings
- PostgreSQL + Redis (cache, sessão, filas) + Mailpit via **Laravel Sail**
- `maatwebsite/excel` (CSV/XLSX), `barryvdh/laravel-dompdf` (PDF)
- Pest 4 (back-end) · Vitest + Vue Test Utils + jsdom (front-end)

## Comandos

```bash
# Ambiente (Sail)
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm run dev

# Filas e scheduler (em terminais separados)
./vendor/bin/sail artisan queue:work redis --tries=3
./vendor/bin/sail artisan schedule:work      # roda o scheduler continuamente
./vendor/bin/sail artisan schedule:test      # dispara um job agendado manualmente

# Testes
./vendor/bin/sail test           # Pest no PostgreSQL (banco `testing`)
DB_HOST=127.0.0.1 php artisan test   # Pest fora do Sail, com o pgsql do compose exposto
npm test                         # Vitest (vp test run)

# DJEN
php artisan djen:capturar-fixture --oab=12345 --uf=AM   # salva resposta REAL (precisa de IP no Brasil)
php artisan djen:capturar-fixture --endpoint=comunicacao/tribunal --saida=tests/Fixtures/djen/tribunais.json

# Qualidade
vendor/bin/pint                  # formatação PHP
npm run check                    # lint + format (vp check)
npm run check:fix
npm run types:check              # vue-tsc
npm run build
composer ci:check                # tudo acima, como no CI
```

Ao final de cada fase: Pest, Vitest, Pint, `vp check`, `vue-tsc`, `npm run build`, commit.

## Convenções

- Código e nomes de domínio em português quando refletem o DJEN (`Comunicacao`,
  `Destinatario`, `numeroOab`); infraestrutura Laravel em inglês (tabelas
  `monitoring_targets`, `communications`, ...).
- **Nunca inventar campos da API.** Os nomes vêm da resposta real salva em
  `tests/Fixtures/djen/` (ainda pendente: a API bloqueia IPs fora do Brasil).
- Toda chamada ao CNJ passa por `App\Services\Djen\DjenClient`. Nada de `Http::`
  espalhado. Respostas viram DTOs tipados (sem arrays soltos).
- Controllers devolvem `Inertia::render` com props via API Resources; os tipos
  em `resources/js/types/` espelham esses Resources.
- Front: `resources/js/pages/{Consulta,Monitoramento,Exportacao}` e componentes
  reutilizáveis em `resources/js/components/`.
- Formulários com `useForm`; busca com `router.get(..., { preserveState, preserveScroll })`
  para manter os filtros na query string.
- Testes de front: `resources/js/**/*.test.ts`, importando de `vite-plus/test`.
- Testes de back: `Http::fake()` com a fixture real; `assertInertia` nas telas.

## DjenClient (`app/Services/Djen/`)

- `get($path, $query)` / `getCached($path, $query)` → `DjenResponse` (status,
  json decodificado, headers, `fromCache`). Ainda **sem DTOs de domínio**: eles
  dependem da fixture real.
- Query normalizada: remove `null`/`''`, apara strings; a chave de cache
  (`djen:` + sha1) ignora a ordem dos parâmetros. Só sucessos vão para o cache.
- Retry (`retry.times` extras) em falha de conexão, 429 e 5xx, com backoff
  exponencial `sleep_ms × 2^(n-1)`, limitado por `max_sleep_ms`; em 429 usa o
  `Retry-After` (também limitado). 4xx não são retentados.
- Exceções (todas herdam de `DjenException`): `DjenUnavailableException`
  (timeout/conexão/5xx), `DjenRateLimitedException` (429, `retryAfterSeconds`),
  `DjenBlockedException` (403 — geobloqueio), `DjenBadRequestException` (outros
  4xx), `DjenUnexpectedResponseException` (2xx que não é JSON).
- Rate limiters em `AppServiceProvider`: `djen` (Jobs, `RateLimited('djen')`)
  e `djen-consulta` (por IP, `throttle:djen-consulta`).
- Testes: `Http::preventStrayRequests()` + `Sleep::fake()`; corpos genéricos
  até a fixture real chegar.

## Configuração

`config/djen.php` (variáveis `DJEN_*` no `.env.example`): base URL, timeout,
retry/backoff, itens por página, TTL do cache (600 s), rate limits (jobs e por
IP), horário/fuso/janela do monitoramento e driver do WhatsApp (`log`).

## Decisões de arquitetura

- **Vue Starter Kit** (Laravel 13), não Breeze — Breeze foi descontinuado no Laravel 12.
- **Ziggy + Wayfinder convivem**: Wayfinder é exigido pelas telas do kit;
  Ziggy foi pedido para as telas do domínio.
- **Larastan removido**: dependia de download de dist pelo GitHub, bloqueado no
  ambiente de desenvolvimento em nuvem. Pode voltar depois.
- **Fonte local** (`@fontsource-variable/plus-jakarta-sans`) em vez de Bunny Fonts:
  build sem dependência externa.
- **PostgreSQL em todo lugar**: dev (Sail), testes (banco `testing`, criado pelo
  Sail) e CI (service `postgres:18-alpine`). Padrão do `config/database.php` é
  `pgsql`. Pode usar recursos do Postgres (jsonb, índices parciais, `ILIKE`).
- `withoutVite()` no `TestCase`: testes de back não dependem do build.
- **Geobloqueio do CNJ**: a Comunica API responde 403 (CloudFront) para IPs
  fora do Brasil. Produção e workers de monitoramento precisam de IP brasileiro.

## Ambiente de desenvolvimento em nuvem (Claude Code)

- Composer: a API do GitHub (zipballs) é bloqueada; instale com
  `composer config -g use-github-api false` e `--prefer-source`.
- `fonts.bunny.net` e a Comunica API não são acessíveis daqui.
