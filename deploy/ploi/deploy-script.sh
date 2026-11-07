# Script de deploy do FarolJuris no Ploi (aba "Deploy script" do site).
# Cole o conteúdo abaixo no Ploi. As variáveis entre chaves são do próprio Ploi.
# Guia completo: docs/13-deploy-ploi.md

cd {SITE_DIRECTORY}
git pull origin {BRANCH}

composer install --no-dev --optimize-autoloader --no-interaction

# O build gera as rotas do front com o PHP (Wayfinder): rode no servidor.
npm ci
npm run build

php artisan migrate --force
php artisan optimize

# Os workers recarregam o código novo ao terminar o job atual.
php artisan queue:restart

{RELOAD_PHP_FPM}

echo "FarolJuris publicado"
