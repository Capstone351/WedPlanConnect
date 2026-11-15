#!/usr/bin/env bash
#
# One-time setup of WedPlanConnect on a fresh DigitalOcean Droplet (Ubuntu 24.04).
# Installs Nginx, PHP 8.4, MySQL, Composer and Node, downloads the app from GitHub,
# creates the database and first Admin account, and turns on the daily jobs.
#
# Run it as root from the Droplet's web console:
#   curl -fsSL https://raw.githubusercontent.com/Capstone351/WedPlanConnect/main/wedplanconnect/deploy/setup-droplet.sh -o setup.sh
#   bash setup.sh
#
set -euo pipefail

REPO_URL="https://github.com/Capstone351/WedPlanConnect.git"
REPO_DIR="/var/www/WedPlanConnect"
APP_DIR="$REPO_DIR/wedplanconnect"
export DEBIAN_FRONTEND=noninteractive
export COMPOSER_ALLOW_SUPERUSER=1

if [ "$(id -u)" -ne 0 ]; then
    echo "Please run this as root (the Droplet console logs you in as root)." >&2
    exit 1
fi

echo "=== WedPlanConnect setup ==="
echo "Press Enter to skip the optional questions."
read -rp "Domain name for the site (optional, e.g. wedplanconnect.me): " DOMAIN
read -rp "Admin email (used to log in as Admin): " ADMIN_EMAIL
while true; do
    read -rsp "Admin password (at least 8 characters): " ADMIN_PASSWORD; echo
    if [ "${#ADMIN_PASSWORD}" -lt 8 ]; then
        echo "Too short, try again."
    elif [[ "$ADMIN_PASSWORD" == *"'"* ]]; then
        echo "Please don't use the ' character, try again."
    else
        break
    fi
done
read -rp "Gemini API key for the chatbot (optional): " GEMINI_KEY
if [ -n "$DOMAIN" ]; then
    read -rp "Email for the free HTTPS certificate notices: " CERT_EMAIL
fi

PUBLIC_IP="$(curl -fsS http://169.254.169.254/metadata/v1/interfaces/public/0/ipv4/address || hostname -I | awk '{print $1}')"

echo "--- Adding 2 GB of swap so the build doesn't run out of memory"
if [ ! -f /swapfile ]; then
    fallocate -l 2G /swapfile
    chmod 600 /swapfile
    mkswap /swapfile
    swapon /swapfile
    echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi

echo "--- Installing Nginx, PHP 8.4, MySQL, Node"
apt-get update -y
apt-get install -y software-properties-common curl git unzip ufw
add-apt-repository -y ppa:ondrej/php
apt-get update -y
apt-get install -y nginx mysql-server \
    php8.4-fpm php8.4-cli php8.4-mysql php8.4-mbstring php8.4-xml php8.4-curl \
    php8.4-zip php8.4-gd php8.4-bcmath php8.4-intl
curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
apt-get install -y nodejs
if ! command -v composer >/dev/null; then
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

echo "--- Allowing photo uploads up to 20 MB per request"
cat > /etc/php/8.4/fpm/conf.d/99-wedplanconnect.ini <<'INI'
upload_max_filesize = 20M
post_max_size = 25M
memory_limit = 256M
INI

echo "--- Creating the database"
DB_PASSWORD="$(openssl rand -hex 16)"
mysql <<SQL
CREATE DATABASE IF NOT EXISTS wedplanconnect CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'wedplan'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
ALTER USER 'wedplan'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON wedplanconnect.* TO 'wedplan'@'localhost';
FLUSH PRIVILEGES;
SQL

echo "--- Downloading WedPlanConnect"
# The app folder belongs to the web server; let root run git in it.
git config --global --get-all safe.directory | grep -qx "$REPO_DIR" || git config --global --add safe.directory "$REPO_DIR"
if [ -d "$REPO_DIR/.git" ]; then
    git -C "$REPO_DIR" pull --ff-only
else
    git clone "$REPO_URL" "$REPO_DIR"
fi
cd "$APP_DIR"

if [ -n "$DOMAIN" ]; then
    APP_URL="https://$DOMAIN"
    SECURE_COOKIE=true
else
    APP_URL="http://$PUBLIC_IP"
    SECURE_COOKIE=false
fi

[ -f .env ] || cp .env.example .env
# Single quotes keep values literal in .env (a $ in a password stays a $).
set_env() {
    local key="$1" value="$2"
    grep -v "^${key}=" .env > .env.tmp || true
    printf "%s='%s'\n" "$key" "$value" >> .env.tmp
    mv .env.tmp .env
}
set_env APP_ENV production
set_env APP_DEBUG false
set_env APP_URL "$APP_URL"
set_env PUBLIC_URL ""
set_env LOG_LEVEL warning
set_env DB_HOST 127.0.0.1
set_env DB_DATABASE wedplanconnect
set_env DB_USERNAME wedplan
set_env DB_PASSWORD "$DB_PASSWORD"
set_env SESSION_SECURE_COOKIE "$SECURE_COOKIE"
set_env ADMIN_EMAIL "$ADMIN_EMAIL"
set_env ADMIN_PASSWORD "$ADMIN_PASSWORD"
set_env GEMINI_API_KEY "$GEMINI_KEY"

echo "--- Installing the app (this takes a few minutes)"
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build
grep -q '^APP_KEY=.\+' .env || php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --force
# The password is only needed to create the Admin; don't leave it in the file.
set_env ADMIN_PASSWORD ""
php artisan optimize

chown -R www-data:www-data "$REPO_DIR"
chmod -R ug+rwX storage bootstrap/cache

echo "--- Configuring Nginx"
SERVER_NAME="${DOMAIN:-_}"
cat > /etc/nginx/sites-available/wedplanconnect <<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name ${SERVER_NAME};
    root ${APP_DIR}/public;
    index index.php;
    client_max_body_size 25M;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINX
ln -sf /etc/nginx/sites-available/wedplanconnect /etc/nginx/sites-enabled/wedplanconnect
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx
systemctl restart php8.4-fpm

echo "--- Turning on the daily jobs (overdue tasks, expired booking holds)"
cat > /etc/cron.d/wedplanconnect <<CRON
* * * * * www-data cd ${APP_DIR} && php artisan schedule:run >> /dev/null 2>&1
CRON

echo "--- Firewall: allowing only SSH and web traffic"
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw --force enable

if [ -n "$DOMAIN" ]; then
    echo "--- Getting a free HTTPS certificate for $DOMAIN"
    apt-get install -y certbot python3-certbot-nginx
    if ! certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos -m "$CERT_EMAIL" --redirect; then
        echo "!!! HTTPS failed. Check that $DOMAIN points to $PUBLIC_IP, then run:"
        echo "    certbot --nginx -d $DOMAIN --redirect"
    fi
fi

echo
echo "=== Done! ==="
echo "Open: $APP_URL"
echo "Log in as Admin with: $ADMIN_EMAIL"
echo "To install later updates from GitHub, run:  bash $APP_DIR/deploy/update.sh"
