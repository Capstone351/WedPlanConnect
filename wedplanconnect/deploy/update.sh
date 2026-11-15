#!/usr/bin/env bash
#
# Installs the latest WedPlanConnect from GitHub on the Droplet.
# Run as root:  bash /var/www/WedPlanConnect/wedplanconnect/deploy/update.sh
#
set -euo pipefail

REPO_DIR="/var/www/WedPlanConnect"
APP_DIR="$REPO_DIR/wedplanconnect"
export COMPOSER_ALLOW_SUPERUSER=1

git config --global --get-all safe.directory | grep -qx "$REPO_DIR" || git config --global --add safe.directory "$REPO_DIR"
cd "$APP_DIR"
php artisan down --retry=30 || true
trap 'php artisan up' EXIT

git -C "$REPO_DIR" pull --ff-only
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan migrate --force
php artisan optimize

chown -R www-data:www-data "$REPO_DIR"
systemctl reload php8.4-fpm

echo "Updated to: $(git -C "$REPO_DIR" log -1 --format='%h %s')"
