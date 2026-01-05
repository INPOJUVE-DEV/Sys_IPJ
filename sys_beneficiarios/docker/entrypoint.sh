#!/usr/bin/env sh
set -e

cd /var/www/html

# Ensure required directories exist
mkdir -p storage/framework/cache \
         storage/framework/sessions \
         storage/framework/views \
         storage/app/public \
         bootstrap/cache

# Fix permissions for Laravel writable dirs
chown -R www-data:www-data storage bootstrap/cache || true
chmod -R ug+rwX storage bootstrap/cache || true

echo "== Boot: clearing Laravel caches safely =="
if [ -f artisan ]; then
  php artisan optimize:clear || true
  php artisan config:clear || true
  php artisan route:clear || true
  php artisan view:clear || true
fi

# Debug secrets mounting (no imprime contenido)
echo "== /etc/secrets listing =="
ls -lah /etc/secrets || true
echo "== MYSQL_ATTR_SSL_CA = ${MYSQL_ATTR_SSL_CA:-<empty>} =="

php -r '
$ca=getenv("MYSQL_ATTR_SSL_CA");
if(!$ca){ echo "CA env empty\n"; exit(0); }
echo "exists=".(file_exists($ca)?"1":"0")."\n";
echo "readable=".(is_readable($ca)?"1":"0")."\n";
echo "size=".(file_exists($ca)?filesize($ca):0)."\n";
'

# No intentes cache:clear si CACHE_STORE usa DB (requiere conexion)
if [ -f artisan ]; then
  if [ "${CACHE_STORE}" != "database" ]; then
    echo "== cache:clear (safe) =="
    php artisan cache:clear || true
  else
    echo "== cache:clear skipped because CACHE_STORE=database (needs DB) =="
  fi
fi

exec "$@"
