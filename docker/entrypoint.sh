#!/bin/sh
# Supershop container start-up:
#   1. make sure APP_KEY exists (generated once and kept in the storage volume)
#   2. wait for MySQL
#   3. cache config/routes/views, run migrations, optionally load demo data
#   4. start Apache
set -e
cd /var/www/html

log() { echo "[supershop] $*"; }

# --- 1. Application key -------------------------------------------------------
if [ -z "$APP_KEY" ]; then
    KEY_FILE=storage/app/.app_key
    if [ ! -s "$KEY_FILE" ]; then
        mkdir -p storage/app
        php -r 'echo "base64:" . base64_encode(random_bytes(32));' > "$KEY_FILE"
        chmod 600 "$KEY_FILE"
        log "Generated a new APP_KEY and stored it in the storage volume."
        log "Set APP_KEY yourself to keep it if you ever delete that volume."
    fi
    APP_KEY="$(cat "$KEY_FILE")"
    export APP_KEY
fi

# --- 2. Database --------------------------------------------------------------
if [ "${DB_CONNECTION:-mysql}" = "mysql" ]; then
    log "Waiting for MySQL at ${DB_HOST:-127.0.0.1}:${DB_PORT:-3306}…"
    tries=0
    until php -r '
        try {
            new PDO("mysql:host=" . (getenv("DB_HOST") ?: "127.0.0.1") . ";port=" . (getenv("DB_PORT") ?: 3306) . ";dbname=" . getenv("DB_DATABASE"),
                    getenv("DB_USERNAME"), getenv("DB_PASSWORD"), [PDO::ATTR_TIMEOUT => 3]);
        } catch (Throwable $e) { exit(1); }' 2>/dev/null
    do
        tries=$((tries + 1))
        if [ "$tries" -ge 60 ]; then
            log "MySQL is still unreachable after 2 minutes — check DB_HOST / DB_DATABASE / DB_USERNAME / DB_PASSWORD."
            exit 1
        fi
        sleep 2
    done
fi

# --- 3. Laravel ---------------------------------------------------------------
# A freshly mounted (bind) volume may be empty: recreate the folders Laravel needs.
mkdir -p storage/app/public storage/app/private storage/framework/cache/data \
         storage/framework/sessions storage/framework/views storage/logs

php artisan config:cache --quiet
php artisan route:cache --quiet
php artisan view:cache --quiet
php artisan event:cache --quiet

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    log "Running database migrations…"
    php artisan migrate --force --no-interaction
fi

if [ "${SEED_DEMO_DATA:-false}" = "true" ]; then
    php artisan shop:seed-demo --no-interaction
fi

chown -R www-data:www-data storage bootstrap/cache

log "Ready — listening on port 80."
exec "$@"
