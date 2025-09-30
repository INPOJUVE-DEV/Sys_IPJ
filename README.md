# Sys IPJ 2025 — Documentación del Proyecto

Este repositorio contiene la aplicación web “Sys IPJ 2025” para gestión y registro de beneficiarios. El sistema está construido con Laravel 11, Blade y Vite, y corre con Docker (PHP-FPM + Nginx + MySQL + Node).

- Código de la app: `sys_beneficiarios/`
- Orquestación: `docker-compose.yml`
- Config Nginx: `.docker/nginx/default.conf`

## Quickstart (Docker)

Requisitos: Docker Desktop (o Docker Engine) y Docker Compose.

### 1. Preparar variables de entorno

```bash
cp sys_beneficiarios/.env.example sys_beneficiarios/.env
```

Valores clave en `sys_beneficiarios/.env` (ajústalos si necesitas conectar a otro motor de BD):

| Variable | Descripción | Valor por defecto |
| --- | --- | --- |
| `APP_NAME` | Nombre mostrado en la aplicación | `Sys IPJ 2025` |
| `APP_URL` | URL base de la app | `http://localhost` |
| `DB_CONNECTION` | Driver de base de datos | `mysql` |
| `DB_HOST` | Host de la BD | `mysql` (nombre del servicio en `docker-compose.yml`) |
| `DB_PORT` | Puerto del contenedor MySQL | `3306` |
| `DB_DATABASE` | Nombre de la base | `sys_beneficiarios` |
| `DB_USERNAME` | Usuario de MySQL | `root` |
| `DB_PASSWORD` | Contraseña de MySQL | `secret` |

> **Nota:** si ya cuentas con un servidor MySQL externo puedes cambiar `DB_HOST`, `DB_PORT`, `DB_USERNAME` y `DB_PASSWORD` para apuntar a él. En ese caso recuerda actualizar también la sección `mysql` en `docker-compose.yml` o eliminarla si no la usarás.

### 2. Configurar almacenamiento persistente (opcional)

El archivo `docker-compose.yml` define volúmenes para MySQL (`mysql_data`) y para `storage/` de Laravel (`app_storage`). Si deseas almacenar los datos en rutas locales específicas, edita las entradas `volumes:` de cada servicio antes de levantar los contenedores.

### 3. Construir y levantar los servicios

```bash
docker compose up -d --build
```

Servicios que quedarás ejecutando:

- **app:** PHP-FPM con Laravel (usa `sys_beneficiarios/` como código fuente).
- **web:** Nginx sirviendo `sys_beneficiarios/public` y enlazado al puerto 80 del host.
- **mysql:** Base de datos MySQL 8 configurada con el usuario/contraseña del `.env`.
- **node:** Contenedor Node 20 para compilar assets con Vite.

### 4. Inicializar la aplicación

```bash
docker compose exec app php artisan key:generate
# Migraciones + seeders base (roles, admin y catálogos si existen CSVs)
docker compose exec app php artisan migrate --seed
# Dependencias y build de assets
docker compose exec node npm install
docker compose exec node npm run build
```

Si necesitas datos de prueba adicionales puedes ejecutar `docker compose exec app php artisan db:seed --class=NombreSeeder` o importar catálogos (ver sección más abajo).

### 5. Acceder a la aplicación

- URL principal: `http://localhost`
- API pública: `http://localhost/api/...`

Credenciales iniciales (creadas por los seeders):

- Usuario Admin: `admin@example.com` / `Password123`
- Roles disponibles: `admin`, `capturista`, `encargado_360`, `encargado_bienestar`, `psicologo`

## Guías de despliegue

### Windows 11 + Docker Desktop

1. **Activa WSL 2 y Virtual Machine Platform** si no lo has hecho:
   ```powershell
   dism.exe /online /enable-feature /featurename:Microsoft-Windows-Subsystem-Linux /all /norestart
   dism.exe /online /enable-feature /featurename:VirtualMachinePlatform /all /norestart
   wsl --set-default-version 2
   ```
2. **Instala Docker Desktop** (última versión estable) y habilita la integración con WSL para la distribución donde trabajarás.
3. **Clona el repositorio** dentro de tu directorio WSL (ej. Ubuntu) para evitar problemas de permisos:
   ```bash
   git clone https://github.com/tu-org/Sys_ipj_2025-web.git
   cd Sys_ipj_2025-web/sys_beneficiarios
   cp .env.example .env
   ```
4. **Ajusta recursos de Docker Desktop**: asigna al menos 4 GB de RAM y 2 CPUs desde *Settings → Resources*.
5. **Levanta los contenedores** desde la terminal de WSL:
   ```bash
   cd /path/al/proyecto
   docker compose up -d --build
   docker compose exec app php artisan key:generate
   docker compose exec app php artisan migrate --seed
   docker compose exec node npm install
   docker compose exec node npm run build
   ```
6. **Accede** desde tu navegador en Windows a `http://localhost`.

### Ubuntu Server (20.04/22.04)

1. **Instala dependencias básicas**:
   ```bash
   sudo apt update && sudo apt install -y ca-certificates curl gnupg git
   ```
2. **Instala Docker Engine y el plugin de Compose** siguiendo la guía oficial (resumen):
   ```bash
   curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /usr/share/keyrings/docker-archive-keyring.gpg
   echo "deb [arch=$(dpkg --print-architecture) signed-by=/usr/share/keyrings/docker-archive-keyring.gpg] https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
   sudo apt update
   sudo apt install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin
   sudo usermod -aG docker $USER
   newgrp docker
   ```
3. **Clona el proyecto y configura variables**:
   ```bash
   git clone https://github.com/tu-org/Sys_ipj_2025-web.git
   cd Sys_ipj_2025-web/sys_beneficiarios
   cp .env.example .env
   sed -i 's|APP_URL=http://localhost|APP_URL=https://tudominio|g' .env
   ```
4. **Arranca los servicios y prepara la aplicación**:
   ```bash
   cd .. # raíz del repositorio
   docker compose up -d --build
   docker compose exec app php artisan key:generate
   docker compose exec app php artisan migrate --seed
   docker compose exec node npm install
   docker compose exec node npm run build
   docker compose exec app php artisan config:cache
   docker compose exec app php artisan route:cache
   docker compose exec app php artisan view:cache
   ```
5. **Configura Nginx/SSL externo** si expones el sitio públicamente (puedes usar un proxy reverso como Nginx o Traefik apuntando al servicio `web` definido en `docker-compose.yml`).

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
