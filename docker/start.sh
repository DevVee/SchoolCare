#!/bin/sh
set -e

# =============================================================================
# SchoolCare — Docker container startup script (runs on every boot)
#
#   1. Validate APP_KEY (must be stable — never regenerated at boot)
#   2. Resolve APP_URL / ASSET_URL
#   3. Substitute PORT into the nginx config
#   4. Prepare the persistent data dir, create/migrate the DB, seed defaults
#   5. Cache config, routes and views
#   6. Fix permissions and start supervisord (nginx, php-fpm, scheduler, queue)
# =============================================================================

# ─── 1. APP_KEY ──────────────────────────────────────────────────────────────
# Generate once with `php artisan key:generate --show` and keep it in the env
# file. Changing it logs everyone out and invalidates CSRF tokens.
if [ -z "$APP_KEY" ]; then
    echo "FATAL: APP_KEY is not set." >&2
    echo "       Generate one with: php artisan key:generate --show" >&2
    exit 1
fi
if ! echo "$APP_KEY" | grep -q "^base64:"; then
    APP_KEY="base64:${APP_KEY}"
    export APP_KEY
fi

# ─── 2. APP_URL ──────────────────────────────────────────────────────────────
APP_URL="${APP_URL:-http://localhost:8080}"
ASSET_URL="${ASSET_URL:-$APP_URL}"
export APP_URL ASSET_URL

echo "==> APP_URL       : ${APP_URL}"
echo "==> SESSION_DRIVER: ${SESSION_DRIVER:-database}"
echo "==> CACHE_STORE   : ${CACHE_STORE:-database}"
echo "==> Vite manifest : $(ls /var/www/html/public/build/manifest.json >/dev/null 2>&1 && echo 'found' || echo 'MISSING!')"

# ─── 3. Wire nginx to the correct port ───────────────────────────────────────
# The nginx template uses ${PORT} as a placeholder (default 8080).
PORT="${PORT:-8080}"
export PORT
envsubst '${PORT}' < /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf

# ─── 4. Persistent data directory ────────────────────────────────────────────
# Production data (SQLite database + uploaded files) must live on a persistent
# volume mounted at DATA_DIR (docker-compose.yml mounts one at /var/data).
# Without a mounted disk everything falls back to the container filesystem,
# which is wiped on every deploy/restart — a loud warning is printed.
DATA_DIR="${DATA_DIR:-/var/data}"
APP_DIR="/var/www/html"
if [ -d "$DATA_DIR" ] && [ -w "$DATA_DIR" ]; then
    mkdir -p "$DATA_DIR/public"
    DB_DATABASE="${DB_DATABASE:-$DATA_DIR/database.sqlite}"
    # Uploaded files (logos, photos) → persistent disk
    if [ ! -L "$APP_DIR/storage/app/public" ]; then
        cp -a "$APP_DIR/storage/app/public/." "$DATA_DIR/public/" 2>/dev/null || true
        rm -rf "$APP_DIR/storage/app/public"
        ln -s "$DATA_DIR/public" "$APP_DIR/storage/app/public"
    fi
    chown -R www-data:www-data "$DATA_DIR"
    echo "==> Data dir      : $DATA_DIR (persistent)"
else
    DB_DATABASE="${DB_DATABASE:-$APP_DIR/database/database.sqlite}"
    echo "==> WARNING: no persistent disk at $DATA_DIR — data will be LOST on redeploy!" >&2
fi
export DB_DATABASE
echo "==> Database      : $DB_DATABASE"

# ─── 4b. Create / migrate the database ──────────────────────────────────────
# Migrations are forward-only and safe to run on every boot. System defaults
# (roles, permissions, settings, first administrator) are inserted only when
# missing, so admin changes are never overwritten.
if [ ! -f "$DB_DATABASE" ]; then
    echo "==> Creating new database"
    touch "$DB_DATABASE"
fi
chown www-data:www-data "$DB_DATABASE"
php artisan migrate --force
php artisan db:seed --force

# ─── 4c. SQLite WAL mode (persisted in the file header, idempotent) ─────────
# WAL allows concurrent readers while a writer is active — essential under
# nginx + php-fpm multi-worker. Without it, write contention produces
# "database is locked" 500 errors under simultaneous requests.
sqlite3 "$DB_DATABASE"     "PRAGMA journal_mode=WAL; PRAGMA synchronous=NORMAL; PRAGMA busy_timeout=5000;"     >/dev/null 2>&1 && echo "==> SQLite WAL mode: enabled"     || echo "==> SQLite WAL mode: sqlite3 not found — config-level fallback active"

# ─── 5. Cache Laravel config / routes / views ────────────────────────────────
# This bakes the current environment variables (APP_URL, APP_KEY, SESSION_DRIVER,
# etc.) into serialized PHP caches in bootstrap/cache/.
# IMPORTANT: Run AFTER all env vars are finalized (steps 0–3 above).
# There is no .env in the image; env vars are injected at runtime.
php artisan config:cache
php artisan route:cache
php artisan view:cache

# ─── 5b. Ensure storage:link exists ───────────────────────────────────────────
php artisan storage:link --force 2>/dev/null || true

# ─── 6. Fix permissions (covers volume-mount edge cases) ──────────────────────
chown -R www-data:www-data \
    /var/www/html/storage \
    /var/www/html/bootstrap/cache \
    /var/www/html/database
chmod -R 775 \
    /var/www/html/storage \
    /var/www/html/bootstrap/cache
chmod 664 "$DB_DATABASE"

# ─── 7. Start services via supervisor ────────────────────────────────────────
echo ""
echo "==================================================="
echo "  SchoolCare is starting…"
echo "  URL  : ${APP_URL}"
echo "  Port : ${PORT}"
echo "==================================================="
echo ""

exec supervisord -c /etc/supervisord.conf
