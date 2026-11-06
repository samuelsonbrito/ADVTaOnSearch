#!/usr/bin/env bash
# Deploy simples em servidor próprio (VPS com IP no Brasil).
# Uso, a partir da pasta do projeto no servidor:  ./deploy/deploy.sh [branch]
set -euo pipefail

BRANCH="${1:-main}"
cd "$(dirname "$0")/.."

echo "==> Modo manutenção"
php artisan down --retry=60 --refresh=15 || true
trap 'php artisan up' EXIT

echo "==> Código ($BRANCH)"
git fetch --prune origin "$BRANCH"
git checkout -q "$BRANCH"
git pull --ff-only origin "$BRANCH"

echo "==> Dependências PHP"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

echo "==> Front-end"
npm ci
npm run build

echo "==> Banco"
php artisan migrate --force

echo "==> Caches"
php artisan optimize

echo "==> Workers (recarregam o código novo ao terminar o job atual)"
php artisan queue:restart

echo "==> Saída do modo manutenção"
php artisan up
trap - EXIT

echo "==> Diagnóstico"
php artisan djen:diagnostico || echo "AVISO: diagnóstico com falhas (veja acima)."
