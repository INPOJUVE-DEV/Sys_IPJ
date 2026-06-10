# Rutas principales

Este documento resume las rutas principales de Sys_IPJ y separa rutas web, rutas API y rutas de integración.

## Convenciones

- Rutas web: definidas en `sys_beneficiarios/routes/web.php`.
- Rutas API: definidas en `sys_beneficiarios/routes/api.php`.
- Base path API: `/api/v1`.
- Las rutas web no deben usarse para integraciones sistema-a-sistema.

## Web

### Entrada principal

- `GET /` → redirige según rol autenticado:
  - `admin` → `/admin`
  - `capturista` → `/capturista`
  - usuario no autenticado → login
- `GET /dashboard` → dashboard base para usuario autenticado y verificado.

## Admin

Middleware general:

- `auth`
- `role:admin`

Rutas principales:

- `GET /admin` → panel administrativo.
- `GET /admin/kpis` → KPIs JSON.
- `GET /admin/usuarios` → CRUD de usuarios.
- `GET /admin/catalogos` → vista de importación de catálogos.
- `POST /admin/catalogos/import` → ejecutar importación de catálogos.
- `GET /admin/beneficiarios` → listado administrativo de beneficiarios.
- `GET /admin/beneficiarios/export` → exportación de beneficiarios.
- `GET /admin/beneficiarios/{beneficiario}` → detalle administrativo.

## Encargado 360

Middleware general:

- `auth`
- `role:encargado_360`

Rutas principales:

- `GET /s360/enc360` → panel Encargado 360.
- `GET /s360/enc360/dash` → KPIs JSON.
- `GET /s360/enc360/asignaciones` → vista de asignaciones.
- `POST /s360/enc360/assign` → asignar beneficiario.
- `PUT /s360/enc360/assign/{beneficiario}` → reasignar beneficiario.

## Capturista

Middleware general:

- `auth`
- `role:capturista`

Rutas principales:

- `GET /capturista` → panel capturista.
- `GET /capturista/kpis` → KPIs personales JSON.
- `GET /mi-progreso/kpis` → alias de compatibilidad.
- `GET /mis-registros` → listado de registros propios.
- `GET /mis-registros/{id}` → detalle de registro propio.
- `PUT /mis-registros/{id}` → actualización de registro propio.

## Recursos compartidos

Middleware general:

- `auth`
- `role:admin|capturista`

Recursos:

- `Route::resource('beneficiarios', BeneficiarioController)` excepto `show`.
- `Route::resource('domicilios', DomicilioController)` excepto `show`.

## Perfil

Middleware:

- `auth`

Rutas:

- `GET /profile`
- `PATCH /profile`
- `DELETE /profile`

## API pública y autenticada

Archivo:

```txt
sys_beneficiarios/routes/api.php
```

Base path:

```txt
/api/v1
```

Rutas actuales:

- `GET /api/v1/health` → salud básica del API.
- `GET /api/v1/pages/{slug}` → página pública publicada.
- `GET /api/v1/components/registry` → catálogo público de componentes.
- `GET /api/v1/themes/current` → tema público vigente.
- `POST /api/v1/auth/login` → login API.
- `POST /api/v1/auth/logout` → logout API con `auth:sanctum`.
- `GET /api/v1/secciones/{seccional}` → datos de seccional con `throttle:30,1`.
- `POST /api/v1/beneficiarios/cache` → legacy/en revisión; no usar para nueva sincronización entre sistemas.
- `POST /api/v1/ocr/ine/extract` → OCR INE autenticado.

Más detalle en:

```txt
docs/api.md
```

## Integraciones sistema-a-sistema

Las integraciones no deben usar rutas web ni formularios internos.

Cualquier integración nueva debe cumplir:

- contrato API documentado;
- autenticación sistema-a-sistema;
- auditoría;
- rate limit;
- tablas separadas para staging, outbox o bitácora;
- no modificar la tabla `beneficiarios` ni el modelo core `Beneficiario`.

## Restricción sobre beneficiarios

La tabla `beneficiarios` es columna principal del proyecto. No debe alterarse para agregar campos de sincronización, origen externo, hash de CURP, estados API_TJ o metadatos de integración.

Si se necesita registrar estado de una integración, debe hacerse en tablas separadas referenciando al beneficiario por ID cuando sea necesario.
