# 09 · Desenvolvimento

## Subindo o ambiente

O passo a passo completo (Sail, sem Docker, conta de demonstração) está no
[README da raiz](../README.md#rodando-do-zero-sail). Resumo:

```bash
cp .env.example .env
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm install && ./vendor/bin/sail npm run dev
./vendor/bin/sail artisan queue:work redis     # outro terminal
./vendor/bin/sail artisan schedule:work        # outro terminal
```

| Serviço | Endereço                                                              |
| ------- | --------------------------------------------------------------------- |
| App     | http://localhost                                                      |
| Mailpit | http://localhost:8025                                                 |
| Login   | `demo@faroljuris.test` / `password` (do seeder; não roda em produção) |

> [!TIP]
> **Fora do Brasil**, use `DJEN_GATEWAY=fake` no `.env`. A API real responde `403` e
> nada funciona. O gateway fake tem 45 comunicações fictícias que respeitam os filtros.

## Testes

| Lado      | Ferramenta                           | Onde                          | Comando                  |
| --------- | ------------------------------------ | ----------------------------- | ------------------------ |
| Back-end  | Pest 4 (PostgreSQL, banco `testing`) | `tests/Feature`, `tests/Unit` | `./vendor/bin/sail test` |
| Front-end | Vitest + Vue Test Utils + jsdom      | `resources/js/**/*.test.ts`   | `npm test`               |

Fora do Sail, com o PostgreSQL do compose exposto:

```bash
DB_HOST=127.0.0.1 php artisan test
```

### Como os testes de back são escritos

- **Nada de rede**: `Http::preventStrayRequests()` + `Http::fake()` com a fixture real
  (`tests/Fixtures/djen/comunicacao.json`). `Sleep::fake()` para o backoff não esperar.
- **Telas**: `assertInertia(fn ($page) => $page->component('Consulta/Index')->where(...))`.
- **Monitoramento**: `Tests\Support\GatewayEmMemoria` simula comunicações que aparecem
  entre uma execução e outra.
- **jsonb**: o PostgreSQL reordena chaves; use `toEqual` em vez de `toBe`.
- **Vite**: `TestCase` chama `withoutVite()`, então os testes de back não precisam do build.

Organização:

```
tests/Feature/
├── Auth/ · Settings/     telas do kit
├── Consulta/             busca pública
├── Djen/                 DjenClient, gateway, mapper, comandos
├── Monitoramento/        alvos, job, notificação, LGPD
├── Exportacao/           síncrona, fila, arquivos, posse
├── DiagnosticoDjenTest.php · PaginasErroTest.php · SeederTest.php
tests/Unit/
├── Config/ · Data/ · Services/ · Support/
```

## Qualidade

```bash
vendor/bin/pint          # formata PHP
npm run check            # lint + format do front (vp check: Oxlint + Oxfmt)
npm run check:fix        # corrige o que der
npm run types:check      # vue-tsc
npm run build            # build de produção
composer ci:check        # tudo acima + Vitest + Pest, igual ao CI
```

## Fluxo de Git

| Regra          | Como                                                                                                     |
| -------------- | -------------------------------------------------------------------------------------------------------- |
| Branch padrão  | `main` (protegida pelo CI; nada é commitado direto nela)                                                 |
| Nome da branch | `tipo/nome-em-kebab-case`: `feature/area-administrativa`, `fix/paginacao-consulta`, `docs/guia-de-marca` |
| Mensagem       | [Conventional Commits](https://www.conventionalcommits.org/pt-br/v1.0.0/): `tipo(escopo): descrição`     |
| Tipos          | `feat`, `fix`, `docs`, `refactor`, `test`, `chore`, `style`, `perf`, `ci`, `build`                       |
| Integração     | PR para a `main`, CI verde, merge                                                                        |

Exemplos:

```text
feat(monitoramento): permite pausar todos os alvos de uma vez
fix(exportacao): mantém a ordem das páginas selecionadas
docs: explica a retenção do rastreio
```

## CI

`.github/workflows/tests.yml` roda em todo PR e em push na `main`:

1. Serviço `postgres:18-alpine` (banco `testing`).
2. PHP 8.3 e Node 22.
3. `composer setup` (dependências, `.env`, chave, migrations, `npm install`, build).
4. `composer ci:check`.

Cache, sessão e fila usam drivers em memória (`array`, `sync`) no CI.

## Convenções

- **Nomes**: domínio em português quando reflete o DJEN (`Comunicacao`, `Destinatario`,
  `numeroOab`, `MonitorarAlvo`); infraestrutura Laravel em inglês (tabelas
  `monitoring_targets`, `communications`...).
- **Nunca invente campos da API.** Os nomes saem da resposta real na fixture.
- **HTTP para o CNJ só pelo `DjenClient`.** Respostas viram DTOs.
- **Controllers** devolvem `Inertia::render` com props de API Resources.
- **Tipos TS** em `resources/js/types/` espelham os Resources.
- **Datas de disponibilização** são datas de calendário: `toDateString()`.
- **Textos** novos em português (`lang/pt_BR/*.php`, `lang/pt_BR.json`).
- Ao fim de cada mudança: Pest, Vitest, Pint, `vp check`, `vue-tsc`, build.

## Receitas

### Adicionar um filtro na Consulta

1. `ConsultaRequest`: normalização em `prepareForValidation()`, regra em `rules()`,
   mensagem em `messages()` e o campo em `filtro()`.
2. `FiltroConsulta`: propriedade, `temCriterio()` (se for critério), `fromQuery()` e
   `toQuery()`.
3. `DjenApiGateway::parametros()`: o nome do parâmetro **na API** (confirme com
   `djen:verificar-filtros`; adicione um `checar(...)` lá).
4. `FakeComunicacaoGateway`: aplique o filtro nos dados fictícios.
5. `StoreExportacaoRequest`: a regra em `filtros.*`.
6. Front: `FiltrosConsulta` em `types/djen.ts`, campo em `FiltroBusca.vue`
   (`normalizado()` e `prontoParaBuscaAutomatica`), props em `ConsultaController`.
7. Testes nos dois lados.

### Novo tipo de alvo no Monitoramento

1. Caso novo em `App\Enums\TipoAlvo` (`rotulo()` e `filtro()`).
2. Normalização e validação em `StoreMonitoringTargetRequest`.
3. `MonitoringTarget::descricao()` e o `FormAlvo.vue`.
4. Testes de criação e do job.

### Plugar um provedor de WhatsApp

1. Classe que implementa `App\Notifications\WhatsApp\WhatsAppDriver`.
2. Caso novo em `AppServiceProvider::registerWhatsApp()`.
3. `DJEN_WHATSAPP_DRIVER=<nome>` e as credenciais em `config/services.php`.

### Novo formato de exportação

1. Caso em `FormatoExportacao` (`rotulo()` e `mime()`).
2. Ramo em `GeradorExportacao::conteudo()`.
3. Testes em `tests/Feature/Exportacao`.

## Ambiente de desenvolvimento em nuvem (Claude Code)

Particularidades do container usado pelo agente:

- A API de zipballs do GitHub é bloqueada: `composer config -g use-github-api false` e
  `composer install --prefer-source`.
- `fonts.bunny.net` e a Comunica API não são acessíveis.
- O Docker não sobe sozinho: `dockerd &` e `docker compose up -d pgsql`. Os testes rodam
  com `DB_HOST=127.0.0.1 php artisan test`.
