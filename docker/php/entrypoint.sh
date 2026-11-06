#!/bin/sh
set -e
cd /var/www/html

if [ ! -f vendor/autoload_runtime.php ]; then
    echo ">> Installing Composer dependencies (first start only, can take a few minutes)..."
    composer install --no-interaction --prefer-dist
fi

mkdir -p var/cache var/log

echo ">> Creating / updating database tables..."
php bin/console doctrine:schema:update --force --complete --no-interaction
echo ">> Seeding dialysis centers..."
php bin/console app:seed

chown -R www-data:www-data var
echo ">> KATOC is ready: http://localhost:8082"
exec "$@"
