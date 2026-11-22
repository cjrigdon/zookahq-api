#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

echo "[api] waiting for mysql..."
for i in $(seq 1 60); do
  if php -r 'try { new PDO("mysql:host=".getenv("DB_HOST").";port=".getenv("DB_PORT"), getenv("DB_USERNAME"), getenv("DB_PASSWORD")); exit(0);} catch (Throwable $e) { exit(1);}'; then
    break
  fi
  sleep 1
done

if [[ ! -f vendor/autoload.php ]]; then
  echo "[api] composer install..."
  composer install --no-interaction --prefer-dist
fi

mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache || true

if [[ -f .env ]] && ! grep -q '^APP_KEY=base64:' .env; then
  php artisan key:generate --force || true
fi

# Drop any host-generated config cache (often baked with DB_HOST=127.0.0.1).
rm -f bootstrap/cache/config.php
php artisan config:clear >/dev/null 2>&1 || true

echo "[api] DB_HOST=${DB_HOST:-unset} serving on 0.0.0.0:8000"
# --no-reload is required so Compose env (DB_HOST=mysql, etc.) is passed through to
# the PHP built-in server. Without it, ServeCommand blanks non-passthrough vars and
# the worker reloads .env with DB_HOST=127.0.0.1 → connection refused inside Docker.
exec php artisan serve --host=0.0.0.0 --port=8000 --no-reload
