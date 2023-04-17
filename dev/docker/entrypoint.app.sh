#!/bin/bash

set -e

env

if [[ -n "$1" ]]; then
    exec "$@"
else
    composer install
    wait-for-it $DB_HOST:3306 -t 45
    php artisan migrate --database=mysql --force
    chown -R www-data:www-data storage
    npm install
    npm run build
    exec apache2-foreground
fi
