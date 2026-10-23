#!/bin/sh
# Boots the demo container: fills in defaults Render doesn't provide, builds
# the database, caches config and starts the scheduler next to Apache.
set -e
cd /var/www/html

# Apache reads ${PORT} in its config. Render sets PORT; 8080 elsewhere.
export PORT="${PORT:-8080}"
sed -i "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf

export APP_URL="${APP_URL:-${RENDER_EXTERNAL_URL:-http://localhost:${PORT}}}"
export DB_DATABASE="${DB_DATABASE:-/var/www/html/database/database.sqlite}"

# Without a configured key, generate one per boot. Sessions don't survive a
# restart anyway, because the demo database is rebuilt.
if [ -z "${APP_KEY}" ]; then
    APP_KEY="$(php artisan key:generate --show --no-ansi)"
    export APP_KEY
fi

if [ "${APP_DEMO}" = "true" ]; then
    php artisan demo:reset
else
    touch "${DB_DATABASE}"
    php artisan migrate --force
    php artisan permissions:sync
fi

php artisan optimize
chown -R www-data:www-data database storage bootstrap/cache

runuser -u www-data -- php artisan schedule:work >/proc/self/fd/1 2>&1 &

exec "$@"
