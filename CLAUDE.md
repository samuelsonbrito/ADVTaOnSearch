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
./vendor/bin/sail artisan schedule:test --name=djen:monitorar   # dispara o monitoramento agendado
./vendor/bin/sail artisan djen:monitorar [--alvo=ID] [--sync]   # monitora agora (fila ou síncrono)

# Testes
./vendor/bin/sail test           # Pest no PostgreSQL (banco `testing`)
DB_HOST=127.0.0.1 php artisan test   # Pest fora do Sail, com o pgsql do compose exposto
npm test                         # Vitest (vp test run)

# DJEN
php artisan djen:diagnostico [--sem-api]               # banco, cache, fila, e-mail e acesso à API deste servidor
php artisan migrate --seed                            # usuária demo@advtaon.test / password (não roda em produção)
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

## Aba Consulta

- Rota pública `GET /consulta` (`consulta.index`, `throttle:djen-consulta`); `/` redireciona para ela.
- Query string do app: `oab, uf, parte, advogado, processo, tribunal, inicio, fim, pagina`.
  `ConsultaRequest` normaliza (só dígitos em OAB/processo, UF/tribunal em maiúsculas) e,
  em caso de erro, **não redireciona** (evita loop em link compartilhado): a página
  recebe `errors` e não consulta.
- `ConsultaController` → `ComunicacaoGateway::buscar(FiltroConsulta)` → `ResultadoConsulta`
  → `ResultadoConsultaResource`. Falhas viram a prop `erro {tipo, mensagem}`.
- Gateways (`DJEN_GATEWAY`): `api` (`DjenApiGateway`, **pendente**: lança
  `DjenMappingPendingException` até existir a fixture real) e `fake`
  (`FakeComunicacaoGateway`, 45 comunicações fictícias determinísticas).
- DTOs do app em `app/Data/Djen/` (contrato da interface, não da API).
- Inteiro teor: HTML sanitizado no servidor (`App\Support\HtmlSanitizer`, allowlist,
  sem atributos) antes do `v-html`. `linkDocumento` só aceita http(s).
- Front: `pages/Consulta/Index.vue` (layout `PublicLayout`), componentes `FiltroBusca`,
  `CardComunicacao`, `ModalInteiroTeor`, `Paginacao`, `EstadoVazio`,
  `consulta/SkeletonResultados`. UF e tribunal usam `<select>` nativo.
- `FiltroBusca`: debounce próprio (700 ms) nos campos de texto; selects/datas buscam na
  hora. Toda busca imediata **cancela** o debounce pendente (senão ele atropelava a
  paginação), e o debounce não dispara se os filtros já são os da URL.

## Aba Monitoramento

- Rotas `auth`+`verified` em `/monitoramento` (`monitoramento.*`); `dashboard` e o
  `fortify.home` redirecionam para lá.
- Tabelas: `monitoring_targets` (tipo `oab|parte|processo` — enum `TipoAlvo`; único por
  usuário+tipo+valor+uf), `communications` (único em `hash`; jsonb para destinatários e
  advogados), `communication_target` (= model pivot `Alerta`; único por
  comunicação+alvo; `lida_em`, `notificada_em`) e `users.whatsapp` (opcional).
- Fluxo: Scheduler (`routes/console.php`, `dailyAt(djen.monitoring.time)` no fuso
  configurado) → `djen:monitorar` → um `MonitorarAlvoJob` por alvo ativo
  (`RateLimited('djen')` + `WithoutOverlapping`) → `MonitorarAlvo::executar()` busca os
  últimos N dias (paginando até `max_paginas`) e cria comunicações/alertas com
  `createOrFirst` → notifica os alertas com `notificada_em` nulo e os marca.
  Deduplicação garantida pelos índices únicos; um aviso que falhou é reenviado na próxima
  execução, um já enviado nunca se repete.
- Erros no job: 429 → `release(Retry-After)`; mapeamento pendente/403/4xx → `fail()`;
  indisponível → exceção (retry com `backoff` 60/300/900 s, 5 tentativas).
- Notificação `NovasComunicacoes`: `mail` sempre; `WhatsAppChannel` se o usuário tiver
  número. O canal delega a `WhatsAppDriver` (só `log` implementado; registrar novos
  drivers em `AppServiceProvider::registerWhatsApp()`).
- LGPD: excluir alvo, "excluir todos os meus dados" (com senha) e excluir a conta apagam
  alvos/alertas; comunicações órfãs são apagadas na hora (`Communication::apagarOrfas()`)
  e diariamente por `model:prune` (`Communication::prunable()`).
- Traduções dos e-mails do Laravel em `lang/pt_BR.json`.
- Testes usam `Tests\Support\GatewayEmMemoria` para simular comunicações novas entre execuções.
- **Datas de disponibilização são datas de calendário**: compare `toDateString()`, nunca
  instantes (o fuso de Manaus excluía o 1º dia da janela).

## Aba Exportação

- Rotas `auth`+`verified` em `/exportacoes` (`exportacoes.index|store|download|destroy`);
  `store` com `throttle:10,1`. Tabela `exports` = model `Exportacao` (`origem`
  consulta|monitoramento, `formato` csv|xlsx|pdf, `status`
  pendente|processando|concluida|falhou, `parametros` jsonb, `arquivo` no disco privado).
- Seleção no front: `useSelecao` (chave → página; sobrevive à paginação com
  preserveState; limpa quando os filtros mudam), `BarraExportacao` (visitante vê convite
  para entrar), `CardComunicacao` com `selecionavel` + `v-model:selecionada`,
  `useExportar` (POST + toast de erro).
- `parametros`: Consulta → `filtros` (query da Consulta) + `selecao` `[{pagina, hashes}]`
  ou `todos`; Monitoramento → `filtros {alvo, status}` + `selecao` (ids de alertas) ou
  `todos`. `ColetorExportacao` rebusca as páginas da Consulta (cache do DjenClient) e
  revalida a posse dos alertas ao gerar. Limite `djen.exportacao.max_itens`.
- Até `limite_sincrono` itens selecionados: gera na hora e devolve
  `Inertia::location(download)` (o "409" no console é o mecanismo do Inertia). "Todos" ou
  seleção maior: `GerarExportacaoJob` (RateLimited só p/ Consulta) + e-mail
  `ExportacaoConcluida`; a página usa `usePoll` enquanto `emAndamento`.
- Arquivos: `PlanilhaComunicacoes` (maatwebsite/excel v4; CSV com `;` e BOM; células que
  começam com `= + - @` ganham `'` contra injeção de fórmula; texto truncado em 32 mil
  caracteres) e PDF via dompdf com a view `resources/views/exports/comunicacoes.blade.php`
  (não é tela; tudo escapado; `enable_font_subsetting` — sem ele o PDF passa de 1 MB).
- LGPD: arquivo apagado junto com o registro (evento `deleting`), `model:prune` diário após
  `dias_retencao`, e `User::deleting` apaga as exportações por Eloquent (a FK em cascata não
  dispara eventos e deixaria arquivos órfãos).
- `ComunicacaoData::textoPuro()` descarta o conteúdo de `<script>`/`<style>` (o
  `strip_tags` sozinho mantinha o código como texto).

## Interface, idioma e erros

- Idioma padrão `pt_BR` (`config/app.php`). Traduções do servidor em `lang/pt_BR/*.php`
  (validação, auth, senhas, paginação) e `lang/pt_BR.json` (e-mails, Fortify, toasts).
  As telas do kit (auth, settings, 2FA, passkeys) e os textos de leitor de tela do
  `components/ui` foram traduzidos no próprio Vue. Novos textos: escreva em português.
- Identidade: `AppLogoIcon` (documento + lupa, só traço, `fill="none"` nos paths para
  ignorar o `fill-current` dos layouts), cor da marca em `--primary`/`--sidebar-primary`
  (`resources/css/app.css`, claro e escuro) e `public/favicon.{svg,ico}` + `apple-touch-icon.png`.
- Erros: `bootstrap/app.php` renderiza a página Inertia `Erro` (layout público) para
  403/404/429/500/503; 419 volta com toast; JSON continua JSON; 500 com `APP_DEBUG=true`
  mostra o erro detalhado. O 429 da Consulta mostra o `Retry-After`.
- `djen:monitorar --sync` isola falhas por alvo (um SMTP fora do ar não interrompe os
  demais; o aviso fica pendente e sai na próxima execução) e retorna falha no fim.

## Deploy

- Servidor **com IP no Brasil**. Arquivos em `deploy/`: `deploy.sh` (manutenção, pull,
  `composer --no-dev`, build, migrate, `optimize`, `queue:restart`, diagnóstico),
  `nginx/advtaon.conf` (validado com `nginx -t`), `supervisor/advtaon-worker.conf`,
  `cron/advtaon` e `env.production.example`.
- O app funciona com `php artisan optimize` (sem `env()` fora de `config/`). Health
  check em `/up`.

## Configuração

`config/djen.php` (variáveis `DJEN_*` no `.env.example`): base URL, timeout,
retry/backoff, itens por página, TTL do cache (600 s), rate limits (jobs e por
IP), horário/fuso/janela/paginação/limite de alvos do monitoramento, gateway
(`DJEN_GATEWAY=api|fake`), exportação (disco, limite síncrono, máximo de itens, dias de
retenção) e driver do WhatsApp (`log`).

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
- O daemon do Docker não sobe sozinho: `dockerd &` e depois `docker compose up -d pgsql`
  (a imagem do app do Sail não builda aqui por hosts bloqueados; testes rodam com
  `DB_HOST=127.0.0.1 php artisan test`).
- Prints com Playwright: use `--lang=pt-BR` no Chromium (o `<input type=date>` segue o
  idioma do navegador).
