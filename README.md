# AdvTaOn Search

Consulta, monitoramento e exportação de comunicações processuais (intimações,
citações e editais) publicadas no **DJEN – Diário de Justiça Eletrônico
Nacional**, via Comunica API do PJe (CNJ).

> Em construção. Fase 1 (setup) concluída; as próximas fases estão no
> [CLAUDE.md](CLAUDE.md).

## Requisitos

- Docker (Laravel Sail) **ou** PHP 8.3+, Composer, Node 22+, PostgreSQL e Redis
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

```bash
php artisan test   # Pest
npm test           # Vitest
```

## Configuração

Veja `config/djen.php` e as variáveis `DJEN_*` em `.env.example`.

## Licença

MIT.
