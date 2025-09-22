# Arquitectura

## Visión general

- Framework: Laravel 11 (PHP 8.2)
- Patrón: MVC con Blade para vistas
- Frontend: Bootstrap 5 con Vite (bundling) y algunos recursos en `resources/js` y `resources/scss`
- Autenticación: Laravel Breeze (Blade)
- Autorización: Spatie Permission (roles `admin`, `capturista`, `encargado_360`, `encargado_bienestar`, `psicologo`)
- Auditoría: Spatie Activitylog (tabla `activity_log`)
- BD: MySQL 8
- Servidor: Nginx + PHP-FPM

## Carpetas clave (`sys_beneficiarios/`)

- `app/Models`: `User`, `Beneficiario`, `Municipio`, `Seccion`, `Domicilio`
- `app/Http/Controllers`: controladores por dominio (Admin, Encargado, API, Auth)
- `app/Console/Commands`: comandos artisan (p.ej. `CatalogosImport`)
- `database/migrations`: esquema de BD (usuarios, permisos/roles, beneficiarios, domicilios, catálogos, etc.)
- `database/seeders`: seeders (Roles, Admin, Catálogos, Usuarios de prueba)
- `resources/views`: Blade templates (layouts, roles, CRUDs, dashboard)
- `resources/js` y `resources/scss`: assets de UI
- `routes/web.php`: rutas web y middleware por rol
- `routes/api.php`: endpoints públicos (throttle)

## KPIs y paneles

- Controlador: `app/Http/Controllers/DashboardController.php`
- Endpoints JSON para KPIs de Admin, Encargado y Capturista (últimos 30 días, series, totales, top por municipio/seccional/capturista)
- Filtros soportados: `municipio_id`, `seccional`, `capturista`, `estado` (`borrador`/`registrado`), `from`, `to`

## API pública

- Ruta: `GET /api/secciones/{seccional}` (ver `app/Http/Controllers/Api/SeccionesController.php`)
- Throttle: `30,1` (30 req/min)
- Respuesta JSON: `{ municipio_id, distrito_local, distrito_federal }`

## Permisos y roles

- Paquete: `spatie/laravel-permission`
- Seeder: `RoleSeeder` crea `admin`, `capturista` (roles de Salud360 en `Salud360RolesSeeder`)
- Asignación inicial en `RolesUsersSeeder` y `AdminUserSeeder`

## Logs de actividad

- Paquete: `spatie/laravel-activitylog`
- Migraciones incluidas (`activity_log`); configuración por defecto
