#!/usr/bin/env sh
set -e

if [ -z "$PORT" ]; then
  PORT=10000
fi

envsubst '$PORT' < /etc/nginx/conf.d/default.conf.template > /etc/nginx/conf.d/default.conf

cd /var/www/html

# If a CA PEM is provided via env, write it to disk for PDO SSL.
if [ -n "$MYSQL_ATTR_SSL_CA_PEM" ] && [ -z "$MYSQL_ATTR_SSL_CA" ]; then
  SSL_DIR="/var/www/html/storage/ssl"
  SSL_CA_PATH="$SSL_DIR/tidb-ca.pem"
  mkdir -p "$SSL_DIR"
  # Support PEM pasted with real newlines or with \n escapes.
  printf "%b\n" "$MYSQL_ATTR_SSL_CA_PEM" | tr -d '\r' > "$SSL_CA_PATH"
  chmod 600 "$SSL_CA_PATH" || true
  if grep -q "BEGIN CERTIFICATE" "$SSL_CA_PATH"; then
    export MYSQL_ATTR_SSL_CA="$SSL_CA_PATH"
  else
    echo "== MYSQL_ATTR_SSL_CA_PEM invalid; falling back to system CA =="
    rm -f "$SSL_CA_PATH" || true
  fi
fi

if [ -z "$MYSQL_ATTR_SSL_CA" ]; then
  for candidate in /etc/ssl/certs/ca-certificates.crt /etc/ssl/cert.pem /etc/ssl/ca-bundle.pem; do
    if [ -s "$candidate" ]; then
      export MYSQL_ATTR_SSL_CA="$candidate"
      break
    fi
  done
fi

if [ -n "$MYSQL_ATTR_SSL_CA" ] && [ -z "$MYSQL_ATTR_SSL_VERIFY_SERVER_CERT" ]; then
  export MYSQL_ATTR_SSL_VERIFY_SERVER_CERT=true
fi

# Ensure required directories exist
mkdir -p storage/framework/cache \
         storage/framework/sessions \
         storage/framework/views \
         storage/app/public \
         bootstrap/cache

# Fix permissions for Laravel writable dirs
chown -R www-data:www-data storage bootstrap/cache || true
chmod -R ug+rwX storage bootstrap/cache || true

# Clear caches (ignore failures if artisan not ready)
if [ -f artisan ]; then
  php artisan config:clear || true
  php artisan cache:clear || true
  php artisan view:clear || true

  if [ "$RUN_MIGRATIONS" = "1" ]; then
    echo "== RUN_MIGRATIONS=1: running migrations =="
    php artisan migrate --force
  fi
fi

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
