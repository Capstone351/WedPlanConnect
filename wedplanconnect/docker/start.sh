#!/usr/bin/env bash
#
# Container start-up on Render: connect to the database, update its tables, then start the web server.
#
set -euo pipefail
cd /var/www/html

# Render tells the app which port to listen on.
PORT="${PORT:-10000}"
sed -ri "s/^Listen [0-9]+/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# The site's own address, for links and QR codes (Render provides it automatically).
export APP_URL="${APP_URL:-${RENDER_EXTERNAL_URL:-http://localhost}}"

# Aiven only accepts encrypted connections: save its CA certificate (pasted into DB_SSL_CA) to a file.
if [ -n "${DB_SSL_CA:-}" ]; then
    printf '%s\n' "$DB_SSL_CA" > /tmp/db-ca.pem
    export MYSQL_ATTR_SSL_CA=/tmp/db-ca.pem
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

# The database can take a moment to accept connections after a cold start.
for attempt in 1 2 3 4 5; do
    if php artisan migrate --force; then
        break
    fi
    if [ "$attempt" -eq 5 ]; then
        echo "Could not update the database. Check the DB_* settings on Render." >&2
        exit 1
    fi
    sleep 5
done

# Creates the first Admin from ADMIN_EMAIL / ADMIN_PASSWORD; does nothing once it exists.
php artisan db:seed --force

chown -R www-data:www-data storage bootstrap/cache

exec apache2-foreground
