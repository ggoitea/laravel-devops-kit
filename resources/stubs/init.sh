#!/bin/sh

set -e

if [ ! -f .env ]; then
    cp .env.example .env
fi

composer install

app_key=$(sed -n 's/^APP_KEY=//p' .env | head -n 1)

if [ -z "$app_key" ] || [ "$app_key" = '""' ]; then
    php artisan key:generate
fi

npm install
