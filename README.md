# Sys_IPJ 2025 — Documentación del proyecto

Sys_IPJ es la aplicación web institucional para gestión, registro y seguimiento de beneficiarios, tarjetas, catálogos y módulos operativos del Instituto Potosino de la Juventud.

El sistema está construido con Laravel 11, Blade, Bootstrap, Vite y Docker.

- Código principal: `sys_beneficiarios/`
- Orquestación local: `docker-compose.yml`
- Configuración Nginx: `.docker/nginx/default.conf`
- Documentación técnica: `docs/`

## Rol del sistema

Sys_IPJ es la fuente de verdad del padrón oficial de beneficiarios.

La tabla `beneficiarios` y su modelo Eloquent son parte del núcleo del proyecto. No deben modificarse para resolver integraciones externas, sincronización con otros sistemas ni staging temporal.

### Reglas de arquitectura

- `beneficiarios` no debe recibir columnas de integración externa.
- No se deben agregar campos como `source_system`, `curp_hash`, `api_tj_sync_status`, `source_external_request_id` o similares en `beneficiarios`.
- No se debe cambiar `created_by` para permitir registros externos sin usuario responsable.
- No se deben registrar observers globales sobre `Beneficiario` para sincronización externa.
- Las integraciones deben resolverse con servicios, contratos explícitos y tablas separadas de auditoría, outbox, staging o bitácora.
- Si una integración requiere nuevas tablas, deben quedar aisladas del modelo core y documentadas antes de implementarse.

## Quickstart con Docker

Requisitos:

- Docker Desktop o Docker Engine
- Docker Compose
- Acceso a una base de datos MySQL compatible

### 1. Preparar variables de entorno

```bash
cp sys_beneficiarios/.env.example sys_beneficiarios/.env
```

Valores clave en `sys_beneficiarios/.env`:

| Variable | Descripción | Valor de referencia |
| --- | --- | --- |
| `APP_NAME` | Nombre mostrado en la aplicación | `Sys IPJ 2025` |
| `APP_URL` | URL base de la app | `http://localhost` |
| `DB_CONNECTION` | Driver de base de datos | `mysql` |
| `DB_HOST` | Host de la base de datos | `0.0.0.0` o servidor externo |
| `DB_PORT` | Puerto de MySQL | `3306` |
| `DB_DATABASE` | Nombre de la base | `sys_beneficiarios` |
| `DB_USERNAME` | Usuario de MySQL | `app` |
| `DB_PASSWORD` | Contraseña de MySQL | definir en entorno |

El proyecto no levanta un contenedor MySQL por defecto. Debe apuntar al servidor externo configurado en `.env`.

### 2. Configurar almacenamiento persistente

`docker-compose.yml` define volúmenes nombrados para conservar:

- `storage/`
- `bootstrap/cache`

Si necesitas mapear rutas locales específicas, edita la sección `volumes:` antes de levantar los servicios.

### 3. Construir y levantar servicios

```bash
docker compose up -d --build
```

Servicios principales:

- `app`: PHP-FPM con Laravel.
- `nginx`: servidor web que expone `sys_beneficiarios/public`.
- `node`: contenedor Node 20 para compilar assets con Vite.
- Base de datos externa: MySQL configurado en `.env`.

### 4. Inicializar aplicación

```bash
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec node npm install
docker compose exec node npm run build
```

Si necesitas datos adicionales:

```bash
docker compose exec app php artisan db:seed --class=NombreSeeder
```

### 5. Acceder a la aplicación

- Sitio principal: `http://localhost`
- API: `http://localhost/api/v1/...`

Credenciales iniciales, si los seeders están habilitados:

- Admin: `admin@example.com`
- Contraseña: `Password123`

Roles base:

- `admin`
- `capturista`
- `encargado_360`
- `encargado_bienestar`
- `psicologo`

## Guías de despliegue

### Windows 11 con Docker Desktop

1. Activa WSL 2 y Virtual Machine Platform si no lo has hecho:

```powershell
dism.exe /online /enable-feature /featurename:Microsoft-Windows-Subsystem-Linux /all /norestart
dism.exe /online /enable-feature /featurename:VirtualMachinePlatform /all /norestart
wsl --set-default-version 2
```

2. Instala Docker Desktop y habilita integración con WSL.
3. Clona el repositorio dentro de WSL para evitar problemas de permisos.
4. Asigna al menos 4 GB de RAM y 2 CPUs a Docker.
5. Levanta servicios:

```bash
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec node npm install
docker compose exec node npm run build
```

### Ubuntu Server

1. Instala dependencias básicas:

```bash
sudo apt update && sudo apt install -y ca-certificates curl gnupg git
```

2. Instala Docker Engine y Compose.
3. Clona el proyecto, configura `.env` y levanta servicios:

```bash
git clone https://github.com/INPOJUVE-DEV/Sys_IPJ.git
cd Sys_IPJ
cp sys_beneficiarios/.env.example sys_beneficiarios/.env
docker compose up -d --build
```

4. Prepara aplicación:

```bash
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec node npm install
docker compose exec node npm run build
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache
```

## Catálogos

Para importar municipios y secciones desde CSV, coloca archivos en:

```txt
sys_beneficiarios/database/seeders/data/
```

Archivos esperados:

- `municipios.csv`: columnas `clave,nombre`
- `secciones.csv`: columnas `seccional,distrito_local,distrito_federal` y una de `municipio_id` o `municipio_clave`

Ejecuta:

```bash
docker compose exec app php artisan catalogos:import --path=database/seeders/data
```

Opciones:

- `--fresh`: limpia tablas antes de importar.
- `--sql=/ruta/a/archivo.sql`: ejecuta SQL previo a la importación.

## Estructura y stack

- Backend: Laravel 11, PHP 8.2
- Frontend: Blade, Bootstrap 5, Vite
- Autenticación: Laravel Breeze
- Autorización: Spatie Permission
- Auditoría: Spatie Activitylog
- Base de datos: MySQL compatible
- Servidor web: Nginx

## Rutas y roles

Resumen:

- Admin:
  - `/admin`
  - `/admin/kpis`
  - `/admin/usuarios`
  - `/admin/beneficiarios`
  - `/admin/catalogos`
- Capturista:
  - `/capturista`
  - `/capturista/kpis`
  - `/mis-registros`
- Salud360:
  - `/s360/enc360`
  - `/s360/enc360/dash`
  - `/s360/enc360/asignaciones`
- API:
  - base path: `/api/v1`
  - detalle: `docs/api.md`

Más detalle en:

- `docs/rutas.md`
- `docs/api.md`
- `docs/despliegue.md`

## Integraciones externas

Toda integración debe partir de estas reglas:

1. Sys_IPJ conserva el padrón oficial.
2. El modelo `Beneficiario` no se modifica para sincronización externa.
3. Los datos externos se reciben mediante contratos explícitos.
4. Las bitácoras, staging, outbox o auditorías viven en tablas separadas.
5. La integración se documenta antes de implementar migraciones o endpoints.

La documentación específica de sincronización entre Sys_IPJ, API_TJ y Unidad de Informática debe vivir en `docs/integraciones/`.

## Desarrollo local

Servidor Vite en modo desarrollo:

```bash
docker compose exec node npm run dev
```

Comandos útiles:

```bash
docker compose exec app php artisan migrate

docker compose exec app php artisan tinker

docker compose exec app php artisan queue:listen

docker compose exec app php artisan test
```

## Pruebas

Ejecuta PHPUnit:

```bash
docker compose exec app php artisan test
```

Las pruebas viven en:

```txt
sys_beneficiarios/tests/
```

## Despliegue

Antes de desplegar:

- Configura `.env` de producción.
- Ejecuta respaldo de base de datos.
- Revisa migraciones pendientes.
- Compila assets.
- Optimiza caches.

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Consulta la guía extendida en `docs/despliegue.md`.
