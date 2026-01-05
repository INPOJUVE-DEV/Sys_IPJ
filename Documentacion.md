# Documentacion del modulo de Beneficiarios (Sys IPJ 2025)

## 1. Panorama general
- Aplicacion Laravel 11 (PHP 8.3) enfocada en registrar, auditar y consultar beneficiarios y sus domicilios.
- Autenticacion basada en Breeze y autorizacion con Spatie Permission (roles `admin` y `capturista`; se reservan `encargado_360`, `encargado_bienestar` y `psicologo` para futuros modulos).
- Paneles con KPIs por rol, captura guiada, exportaciones y administracion de catalogos territoriales (municipios y secciones).
- Incluye infraestructura Docker (PHP-FPM, Nginx y Node) y utilerias para importar catalogos, gestionar paginas dinamicas y exponer una API publica de consulta de secciones.

## 2. Arquitectura y stack
- **Backend:** Laravel 11, Eloquent, colas/jobs nativas (tablas `jobs` y `failed_jobs` listas aunque no hay workers configurados en docker-compose).
- **Frontend:** Blade + Bootstrap 5, Vite para assets (`resources/js` y `resources/scss`), componentes dinamicos definidos por JSON (`components_catalog`) y temas (`themes`).
- **Autenticacion/Autorizacion:** Laravel Breeze (sesiones) y `spatie/laravel-permission` (roles/permisos). Usuarios identificados por UUID (`users.uuid`) para rastrear al creador de registros.
- **Auditoria:** `spatie/laravel-activitylog` sobre la tabla `activity_log` con soporte para UUID en `subject_id`.
- **API publica:** endpoints versionados en `routes/api.php` (`/api/health`, `/api/pages/{slug}`, `/api/components/registry`, `/api/themes/current`, `/api/secciones/{seccional}`). Respuestas cacheables via middleware `etag` y limites de peticiones.
- **Catalogos y contenidos:** comandos artisan (`catalogos:import`) para cargar CSV y tablas auxiliares para paginas/versiones, temas y componentes.

## 3. Servicios Docker y entorno local
1. **Prerequisitos:** duplicar variables con `cp sys_beneficiarios/.env.example sys_beneficiarios/.env` y ajustar la conexion a la base externa (MySQL 8) en `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.
2. **Levantamiento:** desde la raiz del repo ejecutar `docker compose up -d --build` la primera vez. Servicios disponibles:
   - `app` (PHP-FPM) expuesto a `nginx` por socket interno.
   - `nginx` escuchando en `http://localhost:80` y sirviendo `sys_beneficiarios/public`.
   - `node` (volumen con `node_modules`) escuchando en el host **5175** -> contenedor 5173 para Vite (`npm run dev`). Este puerto se ajusto para evitar el conflicto con otros proyectos que ocupan 5173.
3. **Inicializacion:** dentro del contenedor `app` ejecutar:
   ```bash
   docker compose exec app php artisan key:generate
   docker compose exec app php artisan migrate --seed
   docker compose exec app php artisan catalogos:import --path=database/seeders/data  # opcional para CSV de municipios/secciones
   docker compose exec node npm install
   docker compose exec node npm run build   # o npm run dev si se trabajara con hot reload
   ```
4. **Acceso:** `http://localhost` (admin por defecto `admin@example.com` / `Password123`). Para desarrollo front ejecutar `docker compose exec node npm run dev` y consumir `http://localhost:5175` como dev server de Vite.
5. **Utilerias:** pruebas (`docker compose exec app php artisan test`), refrescar BD (`migrate:fresh --seed`), importar catalogos con flags `--fresh` o `--sql=/ruta/export.sql`.

## 4. Funcionalidades principales
### 4.1 Autenticacion y roles
- Inicio de sesion via Breeze (vistas en `resources/views/auth`). Verificacion de email opcional (middleware `verified` en `/dashboard`).
- Roles administrados por Spatie (`RoleSeeder`, `RolesUsersSeeder`, `AdminUserSeeder`, `TestUsersSeeder`). Middleware `role:` controla acceso a cada seccion.
- Perfil de usuario (`/profile`) permite editar datos basicos y borrar cuenta.

### 4.2 Panel y KPIs para Admin
- Dashboard `/admin` con tarjetas de avance y graficas agregadas en `DashboardController::admin` y `adminKpis` (series, totales y top por municipio/seccional/capturista con filtros por rango de fechas).
- Gestion de usuarios (`/admin/usuarios`), soporte para asignar roles y resetear contrasenas.
- Administracion integral de beneficiarios (`/admin/beneficiarios` + detalle + exportaciones CSV/Excel desde `Admin\BeneficiariosController`).
- Importacion de catalogos (`/admin/catalogos` y `catalogos:import`) leyendo CSV de `database/seeders/data` con validaciones y opcion de limpieza previa.
- Configuracion de paginas dinamicas (`/admin/pages/...`), versiones, publicacion/rollback, y builder basado en JSON Schema para componentes reutilizables.
- Catalogo de componentes (`/admin/components`) editable (crear/actualizar schema y habilitacion) y temas (`/admin/themes/current`) para tokens de diseno (colores, tipografia, espaciamientos).

### 4.3 Flujos de capturista
- Panel `/capturista` con KPIs personales (`/capturista/kpis` o alias `/mi-progreso/kpis`) mostrando registros creados, ritmo semanal y metas.
- CRUD de beneficiarios/domcilios (compartido con admin) limitado a registros propios mediante politicas + scopes en controladores.
- Modulo "Mis registros" (`/mis-registros/...`) que muestra listado, detalle editable y filtros por estado para que cada capturista administre solo sus folios.

### 4.4 Catalogos territoriales y comandos
- Tablas `municipios` y `secciones` alimentadas via seeders y comando `catalogos:import` (admite `--fresh` y rutas personalizadas). CSV esperados:
  - `municipios.csv`: `clave,nombre`
  - `secciones.csv`: `seccional,distrito_local,distrito_federal,municipio_id|municipio_clave`
- Filtros por municipio/seccional en dashboards, formularios y API.
- La captura solicita solo la seccional y por medio del catálogo se completa `seccion_id`, municipio y distritos para beneficiario y domicilio, evitando columnas duplicadas.

### 4.5 API publica y contenido dinamico
- `GET /api/health`: verificacion rapida con cabecera `ETag`.
- `GET /api/pages/{slug}` devuelve la ultima version publicada (layout JSON) para construir paginas publicas desde un front desacoplado.
- `GET /api/components/registry` y `GET /api/themes/current` exponen el inventario de componentes y el tema activo a clientes front.
- `GET /api/secciones/{seccional}` (throttle 30/min) usado por formularios para autocompletar distrito y municipio. Controladores: `PagePublicController`, `ComponentRegistryController`, `ThemePublicController` y `Api\SeccionesController`.

### 4.6 Auditoria y trazabilidad
- `spatie/laravel-activitylog` registra altas/ediciones/bajas en `beneficiarios` y `domicilios` (ver traits en los modelos). `created_by` guarda el `uuid` del usuario creador.
- Middleware `auth` + `role` + `verified` aseguran que cada flujo quede vinculado a un usuario identificado (auditable).
- Exportaciones generan reportes con filtros persistentes para soporte de auditorias externas.

### 4.7 Desarrollo y pruebas
- `php artisan test` cubre casos de rutas, permisos y flujos de captura (ver `tests/Feature` y `tests/TestCase.php`).
- Configuracion de tooling: Pint (`./vendor/bin/pint`) para PSR-12, Vite para assets, soporte de `npm run dev` y `npm run build` dentro del contenedor Node.

## 5. Modelo de datos (resumen)
| Tabla | Proposito | Campos clave | Relaciones |
| ----- | --------- | ------------ | ---------- |
| `users` | Usuarios del sistema autenticados con Breeze. | `id`, `uuid`, `name`, `email`, `password`, timestamps. | `uuid` se usa como FK en `beneficiarios.created_by`, `pages.created_by/updated_by`. |
| `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` | Matriz de acceso de Spatie Permission. | Claves auto incremental + `name`, `guard_name`. | Relacionan usuarios con roles/permisos (morph). |
| `municipios` | Catalogo territorial base. | `id`, `clave` unica, `nombre`. | `hasMany` hacia `beneficiarios` y `secciones`. |
| `secciones` | Repositorio de seccionales electorales. | `id`, `seccional` unico, `municipio_id`, distritos. | FK a `municipios`; consumida por API y formularios. |
| `beneficiarios` | Entidad principal de registro. | `id` UUID, `folio_tarjeta`, `nombre` y apellidos, `curp`, `fecha_nacimiento`, `edad`, `sexo`, `discapacidad`, `id_ine`, `telefono`, `municipio_id`, `seccion_id`, `created_by`. | FK `municipio_id` a `municipios`, `seccion_id` a `secciones` (de donde se obtienen seccional y distritos), `created_by` a `users.uuid`, relacion 1:1 con `domicilios`. Soft deletes y activity log. |
| `domicilios` | Domicilio asociado a un beneficiario. | `id` UUID, `beneficiario_id`, direccion completa, `municipio_id`, `seccion_id`, `codigo_postal`. | FK `beneficiario_id` -> `beneficiarios.id`, `municipio_id` -> `municipios.id`, `seccion_id` -> `secciones.id`. Cascada al eliminar. |
| `pages` | Registro de paginas editable por admins. | `slug` unico, `created_by`, `updated_by`, `published_version_id`. | `hasMany` `page_versions`; `published_version_id` apunta a version publicada. |
| `page_versions` | Versionado de paginas. | `page_id`, `version`, `status` (`draft`/`published`/`archived`), `title`, `layout_json`, `notes`, `published_at`. | `belongsTo` `pages`. Scopes `draft()` y `published()`. |
| `components_catalog` | Catalogo de bloques UI con esquema JSON (usado por builder). | `key` unico, `name`, `description`, `schema` JSON, `enabled`. | Consumido por UI admin y endpoint `/api/components/registry`. |
| `themes` | Tokens de diseno para front. | `name`, `tokens` JSON (colores, tipografia, spacing), `is_active`. | `scopeActive()` expone tema vigente via API. |
| `activity_log` | Bitacora de cambios via Spatie Activitylog. | `log_name`, `description`, `event`, `subject_type/id`, `causer_type/id`, `properties`, `batch_uuid`. | Registra acciones de `beneficiarios` y `domicilios` (y extensible). |
| `jobs`, `failed_jobs`, `cache`, `sessions`, `password_reset_tokens` | Infraestructura de cola, cache y sesiones. | Laravel default. | Permiten colas futuras y soporte multi-servidor. |

### Relaciones destacadas
- **Usuarios -> Beneficiarios:** `users.uuid` se usa como `beneficiarios.created_by` y se expone en dashboards para KPIs por capturista.
- **Beneficiarios -> Domicilios:** relacion 1:1 (domicilio guarda la FK) y mantiene consistencia en cascada, ambos vinculados a la misma `seccion_id`.
- **Municipios/Secciones:** `beneficiarios.seccion_id` y `domicilios.seccion_id` resuelven la seccional, distritos y municipio asociado sin duplicar datos; los formularios usan el endpoint `/api/secciones/{seccional}` para autocompletar esta información.
- **Paginas/Componentes/Temas:** `pages` + `page_versions` usan los esquemas definidos en `components_catalog` y tokens en `themes` para componer contenido publico y se publican mediante `Admin\PageController`.
- **Auditoria:** `activity_log` referencia (morph) a cualquier modelo y se usa con UUIDs para los beneficiarios.

## 6. Referencias rapidas
- **Rutas web:** `routes/web.php` (agrupadas por middleware `auth` + `role`).
- **Rutas API:** `routes/api.php` (proteccion por throttle y `auth:sanctum` donde aplica).
- **Controladores clave:** `DashboardController`, `BeneficiarioController`, `DomicilioController`, `Admin\UserController`, `Admin\CatalogosController`, `Admin\ComponentCatalogController`, `Admin\ThemeController`, `Admin\PageController`, `Api\SeccionesController`.
- **Seeders:** ubicados en `sys_beneficiarios/database/seeders` (roles, admin, usuarios de prueba y catalogos).
- **Logs:** Laravel (`storage/logs/laravel.log`) y Nginx (`/var/log/nginx`). Ver `docs/troubleshooting.md` para diagnostico rapido.

Con este documento puedes levantar rapido el entorno dockerizado, entender el alcance funcional del modulo y navegar el modelo de datos para ampliar funcionalidades o integrar nuevas fuentes.
