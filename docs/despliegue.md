# Despliegue (producción)

## Requisitos

- Servidor con Docker y Docker Compose
- Dominio o IP pública

## Variables de entorno

Configura `sys_beneficiarios/.env` con valores de producción:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://tu-dominio`
- `APP_KEY` (usa `php artisan key:generate` en local o en el contenedor)
- Conexión a BD (host, DB, usuario y contraseña)

## Nginx

Edita `.docker/nginx/default.conf`:

- Ajusta `server_name` con tu dominio
- Asegura `root /var/www/html/public;`

Expón el puerto 80 (o 443 si agregas proxy TLS externo). Para TLS, puedes poner Nginx detrás de un reverse proxy con certificados (Caddy, Traefik, Nginx con Let’s Encrypt, etc.).

## Build y caches

Dentro de contenedores:

```
docker compose exec node npm ci
docker compose exec node npm run build

docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache
```

## Migraciones y seeders

```
docker compose exec app php artisan migrate --force
# Opcional (sólo si necesitas datos iniciales):
docker compose exec app php artisan db:seed --force
```

## Render

En Render, define `RUN_MIGRATIONS=1` para que el contenedor ejecute migraciones al arrancar. Ese mismo paso sincroniza roles base, oficinas/regiones, municipios/secciones desde `database/seeders/data` y tipos de evento.

Si necesitas aplicar el cambio sin esperar otro despliegue, abre una Shell del servicio web en Render y ejecuta:

```
php artisan db:seed --class=OficinaSeeder --force
php artisan catalogos:import
php artisan db:seed --class=EventoTipoSeeder --force
php artisan permission:cache-reset
```

Por seguridad, `catalogos:import` solo inserta y actualiza. Para eliminar municipios o secciones que ya no esten en los CSV, define `CATALOGOS_PRUNE=1` o ejecuta:

```
php artisan catalogos:import --prune
```

## Salud del sistema

- Nginx `access.log`/`error.log`
- Laravel logs: `sys_beneficiarios/storage/logs/`
- MySQL: supervisa el servidor externo configurado en `.env` (accesibilidad, respaldos, permisos de red).

