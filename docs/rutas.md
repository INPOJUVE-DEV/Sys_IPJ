# Rutas principales

Archivo: `sys_beneficiarios/routes/web.php`

- `/` redirige según rol autenticado (`admin` → `/admin`, `capturista` → `/capturista`). Si no autenticado, muestra login.
- `/dashboard` (autenticado + verificado): dashboard base

## Admin (middleware: `auth`, `role:admin`)

- `GET /admin` → vista dashboard admin
- `GET /admin/kpis` → KPIs JSON
- `GET /admin/usuarios` → CRUD usuarios
- `GET /admin/catalogos` → vista importación catálogos
- `POST /admin/catalogos/import` → ejecutar importación
- `GET /admin/beneficiarios` → listado
- `GET /admin/beneficiarios/export` → exportación
- `GET /admin/beneficiarios/{beneficiario}` → detalle

## Encargado 360 (middleware: `auth`, `role:encargado_360`)

- `GET /s360/enc360` → vista dashboard Enc360
- `GET /s360/enc360/dash` → KPIs JSON
- `GET /s360/enc360/asignaciones` → vista asignaciones
- `POST /s360/enc360/assign` y `PUT /s360/enc360/assign/{beneficiario}` → asignar/reasignar

## Capturista (middleware: `auth`, `role:capturista`)

- `GET /capturista` → panel
- `GET /capturista/kpis` → KPIs personales JSON
- `GET /mi-progreso/kpis` → alias compatibilidad
- `GET /mis-registros` `GET /mis-registros/{id}` `PUT /mis-registros/{id}` → gestionar los propios registros

## Recursos (middleware: `auth`, `role:admin|capturista`)

- `Route::resource('beneficiarios', BeneficiarioController)` (excepto `show`)
- `Route::resource('domicilios', DomicilioController)` (excepto `show`)

## Perfil (middleware: `auth`)

- `GET /profile` `PATCH /profile` `DELETE /profile`

## API pública

Archivo: `sys_beneficiarios/routes/api.php`

- `GET /api/secciones/{seccional}` (throttle `30,1`) → datos de seccional
