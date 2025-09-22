# Sys IPJ 2025 — Documentación del Proyecto

Este repositorio contiene la aplicación web “Sys IPJ 2025” para gestión y registro de beneficiarios. El sistema está construido con Laravel 11, Blade y Vite, y corre con Docker (PHP-FPM + Nginx + MySQL + Node).

- Código de la app: `sys_beneficiarios/`
- Orquestación: `docker-compose.yml`
- Config Nginx: `.docker/nginx/default.conf`

## Quickstart (Docker)

Requisitos: Docker Desktop (o Docker Engine) y Docker Compose.

1) Configura variables de entorno (dentro de `sys_beneficiarios/`):

```
cp sys_beneficiarios/.env.example sys_beneficiarios/.env
```

Revisa en `sys_beneficiarios/.env`:
- `APP_URL=http://localhost`
- `DB_CONNECTION=mysql`
- `DB_HOST=mysql`
- `DB_PORT=3306`
- `DB_DATABASE=sys_beneficiarios`
- `DB_USERNAME=root`
- `DB_PASSWORD=secret`

2) Levanta los servicios:

```
docker compose up -d --build
```

3) Inicializa la app (clave, migraciones, seeders, assets):

```
docker compose exec app php artisan key:generate
# Migraciones + seeders base (roles, admin y catálogos si existen CSVs)
docker compose exec app php artisan migrate --seed
# Construcción de assets
docker compose exec node npm install
docker compose exec node npm run build
```

4) Abre el sistema en el navegador:

- URL: `http://localhost`

Credenciales iniciales (por seeders):
- Admin: `admin@example.com` / `Password123`
- Roles disponibles: `admin`, `capturista`, `encargado_360`, `encargado_bienestar`, `psicologo`

## Catálogos (Municipios y Secciones)

Para importar catálogos desde CSV coloca archivos en `sys_beneficiarios/database/seeders/data/`:
- `municipios.csv`: columnas `clave,nombre`
- `secciones.csv`: columnas `seccional,distrito_local,distrito_federal` y una de `municipio_id` o `municipio_clave`

Luego ejecuta:

```
docker compose exec app php artisan catalogos:import --path=database/seeders/data
```

Opciones:
- `--fresh` limpia tablas antes de importar
- `--sql=/ruta/a/archivo.sql` ejecuta SQL previo a la importación

## Estructura y stack

- Backend: Laravel 11 (PHP 8.2)
- Frontend: Blade + Bootstrap 5 + Vite (Node 20)
- Autenticación: Laravel Breeze
- Autorización: Spatie Permission (roles y permisos)
- Auditoría: Spatie Activitylog
- BD: MySQL 8
- Servidor web: Nginx (sirve `sys_beneficiarios/public`)

## Rutas y roles (resumen)

- Admin:
  - Panel: `/admin`
  - KPIs: `/admin/kpis`
  - Usuarios: `/admin/usuarios`
  - Beneficiarios: `/admin/beneficiarios` (+ export)
  - Catálogos: `/admin/catalogos`
- Encargado 360 (Salud360):
  - Panel: `/s360/enc360` y `dash`
  - Asignaciones: `/s360/enc360/asignaciones`, `/s360/enc360/assign*`
- Capturista:
  - Panel: `/capturista`
  - KPIs personales: `/capturista/kpis` (alias de compatibilidad: `/mi-progreso/kpis`)
  - Mis registros: `/mis-registros`
- Recursos comunes (autenticado con rol): `beneficiarios` y `domicilios` (roles: `admin|capturista`)
- API pública (rate limit): `GET /api/secciones/{seccional}`

Más detalle en `docs/rutas.md` y `docs/api.md`.

## Desarrollo local

- Servir en vivo (si prefieres Vite en modo dev):

```
docker compose exec node npm run dev
```

- Comandos útiles dentro del contenedor `app`:

```
php artisan migrate:fresh --seed
php artisan tinker
php artisan queue:listen
php artisan test
```

## Pruebas

Ejecuta el suite de PHPUnit:

```
docker compose exec app php artisan test
```

En `sys_beneficiarios/tests/` hay pruebas de acceso, autenticación y KPIs.

## Despliegue

- Ajusta `server_name` en `.docker/nginx/default.conf`
- Configura variables de entorno de producción en `sys_beneficiarios/.env`
- Construye assets con `npm run build`
- Optimiza caches: `php artisan config:cache && php artisan route:cache && php artisan view:cache`

Guía extendida en `docs/despliegue.md`.

## Problemas comunes

- Pantalla en blanco o 500: revisa logs en `sys_beneficiarios/storage/logs/`
- Error de conexión a MySQL: valida `DB_HOST=mysql` y que el servicio `mysql` esté arriba
- Assets no cargan: ejecuta `npm run build` y verifica `@vite` en layouts
- 502/Bad Gateway: confirma que `app:9000` está accesible desde Nginx y que `APP_KEY` está configurada

## Licencia

Proyecto interno. Ver políticas de uso del equipo.
