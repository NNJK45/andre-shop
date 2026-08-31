#!/usr/bin/env bash
set -e

APP_ROOT=/home/site/wwwroot
PERSISTENT_UPLOADS=/home/data/andre-shop-public

cp "$APP_ROOT/azure/nginx-default" /etc/nginx/sites-available/default

mkdir -p "$PERSISTENT_UPLOADS" "$APP_ROOT/storage/app"

if [ ! -L "$APP_ROOT/storage/app/public" ]; then
    if [ -d "$APP_ROOT/storage/app/public" ]; then
        cp -rn "$APP_ROOT/storage/app/public/." "$PERSISTENT_UPLOADS/" || true
        rm -rf "$APP_ROOT/storage/app/public"
    fi

    ln -s "$PERSISTENT_UPLOADS" "$APP_ROOT/storage/app/public"
fi

cd "$APP_ROOT"

php artisan storage:link --force
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

service nginx reload
