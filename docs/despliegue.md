# Despliegue de Sys_IPJ

Este documento resume los pasos mínimos para desplegar Sys_IPJ y las reglas obligatorias para cambios de base de datos en producción.

## Requisitos

- Servidor con Docker y Docker Compose.
- Dominio o IP pública.
- Acceso a la base de datos MySQL compatible.
- Credenciales de producción separadas de desarrollo.
- Respaldo vigente antes de ejecutar migraciones.

## Variables de entorno

Configura `sys_beneficiarios/.env` con valores de producción:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tu-dominio
DB_CONNECTION=mysql
DB_HOST=host-produccion
DB_PORT=3306
DB_DATABASE=sys_beneficiarios
DB_USERNAME=usuario-produccion
DB_PASSWORD=contraseña-produccion
```

`APP_KEY` debe generarse una sola vez por ambiente y conservarse. No debe rotarse sin plan, porque afecta datos cifrados y sesiones.

```bash
php artisan key:generate
```

## Nginx

Edita `.docker/nginx/default.conf`:

- Ajusta `server_name` con el dominio real.
- Asegura `root /var/www/html/public;`.
- Usa proxy TLS externo si el contenedor no termina HTTPS directamente.

Para TLS pueden usarse:

- Nginx externo con Let's Encrypt.
- Caddy.
- Traefik.
- Balanceador/reverse proxy administrado.

## Build y caches

Dentro de contenedores:

```bash
docker compose exec node npm ci
docker compose exec node npm run build

docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache
```

Para limpiar caches durante diagnóstico:

```bash
docker compose exec app php artisan config:clear
docker compose exec app php artisan route:clear
docker compose exec app php artisan view:clear
docker compose exec app php artisan cache:clear
```

## Migraciones y seeders

Antes de ejecutar migraciones en producción:

1. Confirmar rama y commit a desplegar.
2. Revisar `php artisan migrate:status`.
3. Tomar respaldo de base de datos.
4. Revisar que las migraciones no modifiquen tablas core sin aprobación.
5. Ejecutar migraciones con `--force`.

```bash
docker compose exec app php artisan migrate:status
docker compose exec app php artisan migrate --force
```

Seeders en producción solo deben ejecutarse si son idempotentes y están documentados.

```bash
docker compose exec app php artisan db:seed --force
```

## Política de base de datos

### Tablas core protegidas

Estas tablas forman parte del núcleo operativo de Sys_IPJ:

- `beneficiarios`
- `domicilios`
- `tarjetas`
- `users`
- `municipios`
- `secciones`

La tabla `beneficiarios` es la columna principal del proyecto. No debe alterarse para resolver integraciones externas.

### Prohibido sin diseño aprobado

No se permite en migraciones de integración:

- Agregar columnas API_TJ a `beneficiarios`.
- Agregar columnas de sincronización a `beneficiarios`.
- Agregar `curp_hash` a `beneficiarios`.
- Agregar `source_system` o `source_external_request_id` a `beneficiarios`.
- Agregar estados como `api_tj_sync_status` a `beneficiarios`.
- Hacer `created_by` nullable para permitir altas externas.
- Registrar observers globales que alteren cada guardado de `Beneficiario` por motivos de integración.

### Permitido

Si una integración requiere persistencia adicional, debe implementarse con tablas separadas, por ejemplo:

- tablas de auditoría de integración;
- tablas de staging;
- tablas outbox;
- tablas de sync runs;
- tablas de sync items;
- logs de requests externos.

Estas tablas pueden referenciar `beneficiarios.id` cuando sea necesario, pero no deben modificar la estructura de `beneficiarios`.

## Backups antes de desplegar

Antes de ejecutar migraciones o deploys con cambios de base de datos:

```bash
mysqldump -h HOST -u USER -p DB_NAME > backup_sys_ipj_$(date +%Y%m%d_%H%M%S).sql
```

En Windows PowerShell, si se ejecuta desde una máquina cliente:

```powershell
mysqldump -h HOST -u USER -p DB_NAME > backup_sys_ipj.sql
```

Verifica que el archivo no esté vacío y pueda abrirse antes de continuar.

## Render

En Render, define `RUN_MIGRATIONS=1` solo cuando quieras que el contenedor ejecute migraciones al arrancar.

Ese flujo debe usarse con cuidado:

- no activar si no hay respaldo reciente;
- no activar si hay migraciones pendientes que afecten tablas core;
- no activar si el deploy no fue revisado.

Si necesitas aplicar cambios manuales desde Shell del servicio:

```bash
php artisan migrate:status
php artisan migrate --force
php artisan permission:cache-reset
```

Para catálogos:

```bash
php artisan db:seed --class=OficinaSeeder --force
php artisan catalogos:import
php artisan db:seed --class=EventoTipoSeeder --force
php artisan permission:cache-reset
```

Por seguridad, `catalogos:import` solo inserta y actualiza. Para eliminar municipios o secciones que ya no estén en CSV:

```bash
php artisan catalogos:import --prune
```

También puede usarse:

```env
CATALOGOS_PRUNE=1
```

## Salud del sistema

Revisar:

- Nginx `access.log` y `error.log`.
- Laravel logs en `sys_beneficiarios/storage/logs/`.
- Estado de base de datos externa.
- Espacio en disco.
- Permisos de `storage/` y `bootstrap/cache/`.

Comandos útiles:

```bash
docker compose ps
docker compose logs nginx --tail=100
docker compose logs app --tail=100
docker compose exec app php artisan about
```

## Rollback de código

Si el deploy falla por código:

1. Desactivar deploy automático si aplica.
2. Volver a la rama o tag estable.
3. Reconstruir contenedores.
4. Limpiar caches.
5. Validar rutas y login.

Ejemplo:

```bash
git checkout main
git pull origin main
docker compose up -d --build
docker compose exec app php artisan config:clear
docker compose exec app php artisan route:clear
docker compose exec app php artisan view:clear
docker compose exec app php artisan cache:clear
```

## Rollback de base de datos

No hacer rollback de migraciones en producción a ciegas.

Antes de revertir base de datos:

- revisar qué migraciones fueron aplicadas;
- revisar si hay datos nuevos dependientes de esas migraciones;
- tomar respaldo;
- preparar script correctivo específico;
- probar en staging o copia local.

Comando de inspección:

```bash
php artisan migrate:status
```

## Integraciones externas

Toda integración nueva debe documentarse antes de tocar producción.

Requisitos mínimos:

- contrato API;
- autenticación sistema-a-sistema;
- scopes;
- auditoría;
- rate limit;
- manejo de errores;
- tablas aisladas si requiere persistencia;
- pruebas de integración.

Regla final:

```txt
Las integraciones no modifican el modelo core de beneficiarios.
```
