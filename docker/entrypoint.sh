#!/bin/sh
# Entrypoint untuk app container.
#
# Mode:
#   - "init"     : sekali-jalan, run migrate + storage:link + cache, lalu exit
#   - default    : skip init, langsung exec command (php-fpm)
#
# Init dipisah ke service `app-init` di docker-compose supaya:
# - Tidak race condition antar replica
# - Migrate jalan SEBELUM app start serving traffic
# - Cache build sekali, dipakai semua container

set -e

cd /var/www/html

run_init() {
    echo "[entrypoint] Running init phase..."

    # 0. Copy public assets ke shared volume (supaya nginx bisa serve)
    echo "[entrypoint] Syncing public assets..."
    cp -a /var/www/html/public/. /shared-public/

    # 1. Storage symlink (idempotent: kalau sudah ada akan force re-create)
    php artisan storage:link --force || true

    # 2. Database migrate (paksa, tanpa interactive prompt)
    php artisan migrate --force --no-interaction

    # 3. Build production caches (config, routes, views, events)
    # Sengaja TIDAK pakai `optimize` karena event:cache kadang gagal
    # kalau ada listener yang bind di runtime.
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache

    echo "[entrypoint] Init complete."
}

case "${1:-}" in
    init)
        run_init
        exit 0
        ;;
    *)
        # Default: jalankan command yang dikasih (CMD dari Dockerfile = php-fpm)
        exec "$@"
        ;;
esac
