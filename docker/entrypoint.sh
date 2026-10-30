#!/bin/sh
set -e

cd /app

# Only the web container migrates and warms caches; the scheduler waits for it.
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force --no-interaction
    php artisan config:cache
    php artisan route:cache
fi

exec "$@"
