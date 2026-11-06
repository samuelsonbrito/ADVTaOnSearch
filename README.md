# AdvTaOn Search

Consulta, monitoramento e exportação de comunicações processuais (intimações,
citações e editais) publicadas no **DJEN – Diário de Justiça Eletrônico
Nacional**, via Comunica API do PJe (CNJ).

> Em construção. Fases 1, 2 (parcial) e 3 concluídas. A tradução da resposta da
> API do CNJ aguarda uma amostra real; até lá, use `DJEN_GATEWAY=fake` para ver a
> aba Consulta com dados de exemplo.

## Requisitos

- Docker (Laravel Sail) **ou** PHP 8.3+, Composer, Node 22+, PostgreSQL 18 e Redis
- Acesso de rede a partir do **Brasil**: a Comunica API bloqueia IPs estrangeiros

## Rodando do zero (Sail)

```bash
git clone git@github.com:samuelsonbrito/advtaonsearch.git
cd advtaonsearch
cp .env.example .env
docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/var/www/html" -w /var/www/html \
    laravelsail/php84-composer:latest composer install --ignore-platform-reqs
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev
```

App em http://localhost, Mailpit em http://localhost:8025.

## Testes

Os testes de back-end usam PostgreSQL (banco `testing`, criado automaticamente
pelo container `pgsql` do Sail):

```bash
./vendor/bin/sail test   # Pest
npm test                 # Vitest
```

## Capturando uma resposta real da API

A Comunica API só responde a IPs do Brasil. De uma máquina no Brasil:

```bash
php artisan djen:capturar-fixture --oab=12345 --uf=AM
```

O comando grava `tests/Fixtures/djen/comunicacao.json` e `comunicacao.headers.json`.
Anonimize nomes e documentos antes de commitar, mantendo chaves e tipos.

## Configuração

Veja `config/djen.php` e as variáveis `DJEN_*` em `.env.example`.

## Licença

MIT.
