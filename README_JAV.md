# README JAV — Jóvenes al Volante

El módulo "Jóvenes al Volante" (JAV) ofrece administración de sedes, grupos, pagos, inscripciones y tableros de seguimiento para el programa homónimo creado automáticamente durante la siembra inicial del sistema.【F:sys_beneficiarios/database/seeders/ProgramJAVSeeder.php†L18-L57】【F:sys_beneficiarios/database/seeders/DatabaseSeeder.php†L16-L25】

## Instalación y seeders a ejecutar
1. Configura el entorno duplicando el `.env` base dentro de `sys_beneficiarios/`.【F:README.md†L13-L26】
2. Levanta la plataforma con Docker Compose y reconstrucción inicial de contenedores.【F:README.md†L28-L33】
3. Dentro del contenedor `app` genera la clave de la aplicación, ejecuta migraciones con seeders y compila los assets front-end; en el contenedor `node` instala dependencias y construye Vite.【F:README.md†L34-L43】
4. (Opcional) Importa catálogos de municipios/secciones cuando existan CSV en `database/seeders/data/`.【F:README.md†L53-L67】

El comando `php artisan migrate --seed` encadena los siguientes seeders relevantes para JAV：【F:sys_beneficiarios/database/seeders/DatabaseSeeder.php†L16-L25】

- **RoleSeeder**: garantiza la existencia de los roles base `admin` y `capturista` para el ecosistema de permisos.【F:sys_beneficiarios/database/seeders/RoleSeeder.php†L10-L14】
- **AdminUserSeeder**: crea el usuario administrador inicial (`admin@example.com` / `Password123`) y lo asocia al rol `admin`.【F:sys_beneficiarios/database/seeders/AdminUserSeeder.php†L12-L23】
- **CatalogosSeeder**: importa municipios y secciones desde CSV si están presentes, alimentando catálogos usados en los registros de beneficiarios.【F:sys_beneficiarios/database/seeders/CatalogosSeeder.php†L12-L99】
- **VolSiteSeeder**: registra sedes iniciales (Capital y Cd. Valles) marcadas como activas para operar el programa.【F:sys_beneficiarios/database/seeders/VolSiteSeeder.php†L12-L35】
- **ProgramJAVSeeder**: inserta o actualiza la fila del programa "Jóvenes al Volante" con área Bienestar y estado activo.【F:sys_beneficiarios/database/seeders/ProgramJAVSeeder.php†L18-L57】
- **VolPermissionsSeeder**: publica los permisos `vol.*` y los asigna a admin, Encargado Bienestar y Encargado JAV según sus responsabilidades.【F:sys_beneficiarios/database/seeders/VolPermissionsSeeder.php†L13-L54】

> Si requieres datos demo de Salud 360 puedes habilitarlos ejecutando el `Salud360DemoSeeder` en entornos locales, tal como lo condiciona el `DatabaseSeeder`.【F:sys_beneficiarios/database/seeders/DatabaseSeeder.php†L27-L30】

## Roles, permisos y matriz de acceso
Las rutas de la API de Bienestar/JAV están protegidas por `auth:sanctum`, auditoría `vol.audit` y permisos Spatie específicos, por lo que cada rol debe autenticarse con token antes de consumirlas.【F:sys_beneficiarios/routes/api.php†L41-L75】 Los permisos se otorgan así:

- **Administrador** recibe todos los permisos `vol.*` (gestión total).【F:sys_beneficiarios/database/seeders/VolPermissionsSeeder.php†L13-L54】
- **Encargado Bienestar** obtiene `vol.groups.manage`, `vol.sites.manage` y `vol.reports.view` para administrar infraestructura y analizar indicadores.【F:sys_beneficiarios/database/seeders/VolPermissionsSeeder.php†L29-L33】
- **Encargado JAV** cuenta con `vol.enrollments.manage`, `vol.groups.view` y `vol.reports.view`, centrado en operación de inscripciones y consulta de grupos.【F:sys_beneficiarios/database/seeders/VolPermissionsSeeder.php†L34-L38】

| Operación clave | Admin | Encargado Bienestar | Encargado JAV | Otros roles |
| --- | --- | --- | --- | --- |
| Sedes: listar/crear/editar/eliminar | ✅ | ✅ | ❌ | ❌ |
| Grupos: listar/ver detalle | ✅ | ✅ | ✅ | ❌ |
| Grupos: crear/editar/publicar/cerrar | ✅ | ✅ | ❌ | ❌ |
| Pagos: listar/registrar | ✅ | ✅ | ❌ | ❌ |
| Inscripciones: listar | ✅ | ✅ | ✅ | ❌ |
| Inscripciones: alta/baja | ✅ | ❌ | ✅ | ❌ |
| Reportes (mensual, trimestral, disponibilidad) | ✅ | ✅ | ✅ | ❌ |

Las políticas de autorización refuerzan esta matriz: gestión de sedes exige rol admin o permiso `vol.sites.manage`; grupos requieren admin o Encargado Bienestar para mutaciones mientras Encargado JAV solo lee; pagos dependen de admin o `vol.groups.manage`; inscripciones se limitan a admin/Encargado JAV para altas/bajas y admiten a Encargado Bienestar en modo lectura; los reportes solo responden a quienes poseen `vol.reports.view`.【F:sys_beneficiarios/app/Policies/VolSitePolicy.php†L10-L48】【F:sys_beneficiarios/app/Policies/VolGroupPolicy.php†L10-L56】【F:sys_beneficiarios/app/Policies/VolPaymentPolicy.php†L10-L48】【F:sys_beneficiarios/app/Policies/VolEnrollmentPolicy.php†L10-L56】 Roles ajenos al dominio (p. ej. `capturista`) no obtienen permisos `vol.*`, por lo que no acceden a los endpoints JAV.【F:sys_beneficiarios/database/seeders/VolPermissionsSeeder.php†L29-L38】

## Flujos operativos (Pago → Inscripción → Reportes)
Antes de inscribir a un beneficiario, se valida que el grupo no esté cerrado, que el beneficiario no haya sido inscrito previamente, que exista un pago válido, que no exceda una inscripción mensual y que haya cupo disponible; solo entonces se crea el registro y se registra la actividad.【F:sys_beneficiarios/app/Http/Controllers/Volante/EnrollmentController.php†L40-L134】【F:sys_beneficiarios/app/Services/Vol/PaymentGuard.php†L9-L14】【F:sys_beneficiarios/app/Services/Vol/MonthlyEnrollmentGuard.php†L10-L18】【F:sys_beneficiarios/app/Services/Vol/CapacityGuard.php†L9-L15】 Los reportes posteriores consumen exclusivamente inscripciones activas (`status=inscrito`) agrupadas por fecha de inscripción y sede/grupo.【F:sys_beneficiarios/app/Http/Controllers/Volante/ReportController.php†L120-L213】【F:sys_beneficiarios/app/Models/VolEnrollment.php†L33-L58】

```mermaid
flowchart TD
    Pago[Registrar pago<br/>POST /payments] --> ValidacionPago{Reglas de pago
(payment_type, fecha, guardado)}
    ValidacionPago -->|201| PagoListo[Pago almacenado]
    PagoListo --> Alta[Solicitar inscripción<br/>POST /groups/{id}/enrollments]
    Alta --> GrupoCerrado{Grupo cerrado?}
    GrupoCerrado -->|Sí| ErrorCierre[422: no admite inscripciones]
    GrupoCerrado -->|No| PagoValido{Pago vigente?}
    PagoValido -->|No| ErrorPago[422: pago inválido]
    PagoValido -->|Sí| CupoMes{¿Ya tiene inscripción este mes?}
    CupoMes -->|Sí| ErrorMes[422: límite mensual]
    CupoMes -->|No| Capacidad{Cupo disponible?}
    Capacidad -->|No| ErrorCupo[422: sin cupo]
    Capacidad -->|Sí| Inscrito[Inscripción creada + actividad]
    Inscrito --> Reportes[GET /reports/*
(mensual, trimestral, disponibilidad)]
```

## Endpoints con ejemplos JSON
Todos los endpoints viven bajo `/api/bienestar/volante` y requieren token Sanctum, además de los permisos indicados en cada sección.【F:sys_beneficiarios/routes/api.php†L41-L75】 Las respuestas de listados usan la paginación estándar de Laravel (`current_page`, `data`, `meta`, etc.).

### Sitios (sedes)
- `GET /api/bienestar/volante/sites` — Lista paginada; filtros opcionales `with_trashed` y `active`. Permiso: `vol.sites.manage`.【F:sys_beneficiarios/routes/api.php†L44-L48】【F:sys_beneficiarios/app/Http/Controllers/Volante/SiteController.php†L12-L23】
- `POST /api/bienestar/volante/sites` — Crea una sede con `name`, `state`, `city`, `address` y `active` opcional. Responde 201 con el registro.【F:sys_beneficiarios/app/Http/Controllers/Volante/SiteController.php†L25-L45】
- `PUT /api/bienestar/volante/sites/{id}` — Actualiza campos opcionales respetando unicidad de nombre. Responde 200 con el recurso actualizado.【F:sys_beneficiarios/app/Http/Controllers/Volante/SiteController.php†L47-L69】
- `DELETE /api/bienestar/volante/sites/{id}` — Baja lógica de la sede (SoftDelete).【F:sys_beneficiarios/app/Http/Controllers/Volante/SiteController.php†L72-L78】

Ejemplo de alta de sede:
```json
POST /api/bienestar/volante/sites
{
  "name": "Soledad",
  "state": "San Luis Potosi",
  "city": "Soledad de G.S.",
  "address": "Av. de la Juventud 123",
  "active": true
}
```
Respuesta:
```json
{
  "data": {
    "id": 3,
    "name": "Soledad",
    "state": "San Luis Potosi",
    "city": "Soledad de G.S.",
    "address": "Av. de la Juventud 123",
    "active": true,
    "created_at": "2025-09-01T10:15:00Z",
    "updated_at": "2025-09-01T10:15:00Z"
  }
}
```
Los campos obligatorios y el formato de respuesta provienen de la validación/creación realizada en el controlador y del modelo `VolSite`.【F:sys_beneficiarios/app/Http/Controllers/Volante/SiteController.php†L25-L45】【F:sys_beneficiarios/app/Models/VolSite.php†L16-L30】

### Grupos JAV
- `GET /api/bienestar/volante/groups` — Filtra por `site_id`, `state`, `type`, búsqueda `q`, e incluye eliminados con `with_trashed`. Permiso: `vol.groups.view` o `vol.groups.manage`.【F:sys_beneficiarios/routes/api.php†L51-L54】【F:sys_beneficiarios/app/Http/Controllers/Volante/GroupController.php†L16-L37】
- `GET /api/bienestar/volante/groups/{id}` — Devuelve el grupo con sus inscripciones y sede asociada.【F:sys_beneficiarios/app/Http/Controllers/Volante/GroupController.php†L39-L44】
- `POST /api/bienestar/volante/groups` — Crea grupos en estado `borrador`, asigna código `JAV-yy###`, capacidad por defecto 12 y permite indicar `program_id`, `site_id`, `type`, `schedule_template`, fechas y cupo.【F:sys_beneficiarios/routes/api.php†L56-L58】【F:sys_beneficiarios/app/Http/Controllers/Volante/GroupController.php†L46-L72】【F:sys_beneficiarios/app/Http/Controllers/Volante/GroupController.php†L144-L151】
- `PUT /api/bienestar/volante/groups/{id}` — Actualiza campos opcionales y valida que `end_date` no sea anterior a `start_date`.【F:sys_beneficiarios/app/Http/Controllers/Volante/GroupController.php†L74-L105】
- `POST /api/bienestar/volante/groups/{id}/publish` — Cambia a `publicado` verificando que la capacidad sea mayor a cero.【F:sys_beneficiarios/app/Http/Controllers/Volante/GroupController.php†L107-L122】
- `POST /api/bienestar/volante/groups/{id}/close` — Marca estado `cerrado` para bloquear nuevas inscripciones.【F:sys_beneficiarios/app/Http/Controllers/Volante/GroupController.php†L124-L133】
- `DELETE /api/bienestar/volante/groups/{id}` — Soft delete del grupo.【F:sys_beneficiarios/app/Http/Controllers/Volante/GroupController.php†L135-L142】

Ejemplo de creación de grupo:
```json
POST /api/bienestar/volante/groups
{
  "program_id": 1,
  "site_id": 1,
  "name": "Grupo Sabatino 01",
  "type": "sabatino",
  "schedule_template": "sab",
  "start_date": "2025-09-15",
  "capacity": 15
}
```
Respuesta 201:
```json
{
  "data": {
    "id": 12,
    "program_id": 1,
    "site_id": 1,
    "code": "JAV-25012",
    "name": "Grupo Sabatino 01",
    "type": "sabatino",
    "schedule_template": "sab",
    "start_date": "2025-09-15",
    "end_date": null,
    "capacity": 15,
    "state": "borrador",
    "created_at": "2025-09-01T10:20:00Z",
    "updated_at": "2025-09-01T10:20:00Z",
    "site": {
      "id": 1,
      "name": "Capital"
    }
  }
}
```
Los campos requeridos, el código autogenerado y el estado inicial provienen de la lógica del controlador y del modelo de grupos.【F:sys_beneficiarios/app/Http/Controllers/Volante/GroupController.php†L50-L72】【F:sys_beneficiarios/app/Http/Controllers/Volante/GroupController.php†L144-L151】【F:sys_beneficiarios/app/Models/VolGroup.php†L18-L78】

### Pagos
- `GET /api/bienestar/volante/payments` — Filtra por `beneficiario_id`, `date_from`, `date_to` y ordena descendente por fecha. Permiso: `vol.groups.manage`.【F:sys_beneficiarios/routes/api.php†L56-L63】【F:sys_beneficiarios/app/Http/Controllers/Volante/PaymentController.php†L14-L25】
- `POST /api/bienestar/volante/payments` — Registra un pago indicando `beneficiario_id` (UUID existente), `payment_type` (`transferencia`, `tarjeta` o `deposito`), `payment_date` y `receipt_ref` opcional. Devuelve 201 con el pago creado.【F:sys_beneficiarios/app/Http/Controllers/Volante/PaymentController.php†L28-L48】

Ejemplo de solicitud:
```json
POST /api/bienestar/volante/payments
{
  "beneficiario_id": "6f1c2d58-1b3e-4e9f-8cb0-1234567890ab",
  "payment_type": "transferencia",
  "payment_date": "2025-09-02",
  "receipt_ref": "FOLIO-0931"
}
```
Respuesta 201:
```json
{
  "data": {
    "id": 45,
    "beneficiario_id": "6f1c2d58-1b3e-4e9f-8cb0-1234567890ab",
    "payment_type": "transferencia",
    "payment_date": "2025-09-02",
    "receipt_ref": "FOLIO-0931",
    "created_by": 7,
    "created_at": "2025-09-02T17:30:00Z",
    "updated_at": "2025-09-02T17:30:00Z"
  }
}
```
Los campos aceptados, las opciones de tipo de pago y el payload de respuesta están definidos en el controlador y el modelo `VolPayment`.【F:sys_beneficiarios/app/Http/Controllers/Volante/PaymentController.php†L32-L47】【F:sys_beneficiarios/app/Models/VolPayment.php†L13-L33】

### Inscripciones
- `GET /api/bienestar/volante/groups/{group}/enrollments` — Lista inscripciones activas del grupo con datos del beneficiario. Permiso: `vol.enrollments.manage` + autorización de vista del grupo.【F:sys_beneficiarios/routes/api.php†L66-L68】【F:sys_beneficiarios/app/Http/Controllers/Volante/EnrollmentController.php†L27-L38】
- `POST /api/bienestar/volante/groups/{group}/enrollments` — Registra inscripción validando pago vigente, límite mensual y cupo. Solo requiere `beneficiario_id` en el cuerpo. Responde 201 con la inscripción y relaciones cargadas.【F:sys_beneficiarios/app/Http/Controllers/Volante/EnrollmentController.php†L40-L107】【F:sys_beneficiarios/app/Http/Requests/EnrollmentRequest.php†L15-L19】
- `DELETE /api/bienestar/volante/enrollments/{id}` — Marca la inscripción en `baja`, almacena fecha y motivo opcional.【F:sys_beneficiarios/routes/api.php†L68-L69】【F:sys_beneficiarios/app/Http/Controllers/Volante/EnrollmentController.php†L109-L134】

Ejemplo de alta:
```json
POST /api/bienestar/volante/groups/12/enrollments
{
  "beneficiario_id": "6f1c2d58-1b3e-4e9f-8cb0-1234567890ab"
}
```
Respuesta 201:
```json
{
  "data": {
    "id": 87,
    "group_id": 12,
    "beneficiario_id": "6f1c2d58-1b3e-4e9f-8cb0-1234567890ab",
    "status": "inscrito",
    "enrolled_at": "2025-09-03T14:05:00Z",
    "created_by": 7,
    "group": {
      "id": 12,
      "name": "Grupo Sabatino 01"
    },
    "beneficiario": {
      "id": "6f1c2d58-1b3e-4e9f-8cb0-1234567890ab",
      "nombre": "María",
      "apellido_paterno": "López",
      "apellido_materno": "García"
    }
  }
}
```
Las validaciones de estado de grupo, pago, límite mensual y cupo provienen del controlador y de los servicios de guardia mencionados arriba.【F:sys_beneficiarios/app/Http/Controllers/Volante/EnrollmentController.php†L45-L107】【F:sys_beneficiarios/app/Services/Vol/PaymentGuard.php†L9-L14】【F:sys_beneficiarios/app/Services/Vol/MonthlyEnrollmentGuard.php†L10-L18】【F:sys_beneficiarios/app/Services/Vol/CapacityGuard.php†L9-L15】

### Reportes
- `GET /api/bienestar/volante/reports/monthly` — Parámetros opcionales: `month` (`YYYY-MM`) y `site_id`. Devuelve totales del mes, agregados por sede y grupo, más listado de beneficiarios recientes.【F:sys_beneficiarios/routes/api.php†L72-L74】【F:sys_beneficiarios/app/Http/Controllers/Volante/ReportController.php†L16-L174】
- `GET /api/bienestar/volante/reports/quarterly` — Parámetros opcionales: `year`, `q` (1-4) y `site_id`. Retorna sumatoria trimestral, desglose por mes/sede/grupo.【F:sys_beneficiarios/routes/api.php†L72-L75】【F:sys_beneficiarios/app/Http/Controllers/Volante/ReportController.php†L34-L213】
- `GET /api/bienestar/volante/reports/availability` — Muestra cupo disponible por grupo con totales globales (usa `withAvailability`).【F:sys_beneficiarios/routes/api.php†L72-L75】【F:sys_beneficiarios/app/Http/Controllers/Volante/ReportController.php†L216-L246】【F:sys_beneficiarios/app/Models/VolGroup.php†L42-L78】

Ejemplo de reporte mensual:
```json
GET /api/bienestar/volante/reports/monthly?month=2025-09&site_id=1
{
  "period": "2025-09",
  "start": "2025-09-01",
  "end": "2025-09-30",
  "site_id": 1,
  "total": 42,
  "per_site": [
    { "site_id": 1, "site_name": "Capital", "total": 42 }
  ],
  "per_group": [
    { "group_id": 12, "group_name": "Grupo Sabatino 01", "code": "JAV-25012", "site_name": "Capital", "total": 20 }
  ],
  "beneficiaries": [
    {
      "beneficiario_id": "6f1c2d58-1b3e-4e9f-8cb0-1234567890ab",
      "nombre": "María López García",
      "group_id": 12,
      "group_name": "Grupo Sabatino 01",
      "site_id": 1,
      "site_name": "Capital",
      "enrolled_at": "2025-09-03T14:05:00"
    }
  ]
}
```
El formato de este reporte y de los trimestrales se construye con `buildMonthlyData` y `buildQuarterlyData`, que filtran inscripciones activas por fechas de inscripción y agregan la información requerida.【F:sys_beneficiarios/app/Http/Controllers/Volante/ReportController.php†L120-L213】【F:sys_beneficiarios/app/Models/VolEnrollment.php†L33-L58】

## Convenciones de reporte
- **Mensual por fecha de inscripción:** el parámetro `month` (por defecto el mes actual) se normaliza al primer día del mes y se filtran inscripciones activas (`status=inscrito`) cuyo `enrolled_at` cae dentro del rango mensual; los resultados incluyen totales, agregados por sede/grupo y listado de beneficiarios recientes.【F:sys_beneficiarios/app/Http/Controllers/Volante/ReportController.php†L16-L174】【F:sys_beneficiarios/app/Models/VolEnrollment.php†L33-L49】
- **Trimestral por Q:** se aceptan `year` y `q` (1-4); en ausencia de parámetros se usa el trimestre correspondiente a la fecha actual. Los cálculos convierten el trimestre en un rango de meses consecutivos y agrupan por mes, sede y grupo sobre inscripciones activas.【F:sys_beneficiarios/app/Http/Controllers/Volante/ReportController.php†L34-L213】【F:sys_beneficiarios/app/Models/VolEnrollment.php†L33-L58】
- **Disponibilidad:** el reporte de disponibilidad evalúa cada grupo con `withAvailability`, exponiendo capacidad, inscritos y lugares restantes para monitorear cupos abiertos.【F:sys_beneficiarios/app/Http/Controllers/Volante/ReportController.php†L216-L246】【F:sys_beneficiarios/app/Models/VolGroup.php†L42-L78】

Estas convenciones garantizan que los tableros mensuales y trimestrales reflejen el ritmo real de nuevas inscripciones, mientras que la vista de disponibilidad permite anticipar la apertura de nuevos grupos cuando el cupo remanente se reduce.
mo`mo`mo`momo
momo
mo#mo#mo#mo moImonmosmocmormoimopmocmoimo�monmomo
mo`mo`mo`momo
moPmoOmoSmoTmo mo/moamopmoimo/mobmoimoemonmoemosmotmoamormo/movmoomolmoamonmotmoemo/mogmormoomoumopmosmo/mo{mogmormoomoumopmo}mo/moemonmormoomolmolmomoemonmotmosmomo
mo{momo
mo mo mo"mobmoemonmoemofmoimocmoimoamormoimoomo_moimodmo"mo:mo mo"moumoumoimodmo"momo
mo}momo
mo`mo`mo`momo
mo-mo moVmoamolmoimodmoamo mopmoamogmoomo mopmormoemovmoimoomo mo(mo`movmoomolmo_mopmoamoymomoemonmotmosmo`mo)mo.momo
mo-mo moImomopmoimodmoemo modmoumopmolmoimocmoimodmoamodmo moemonmo moemolmo momoimosmomoomo momoemosmo mo(mo`mosmotmoamotmoumosmo=moimonmosmocmormoimotmoomo`mo)mo.momo
mo-mo moRmoemocmohmoamozmoamo mosmoimo mo`mocmoamopmoamocmoimotmoymo mo<mo=mo moimonmosmocmormoimotmoomosmo moamocmotmoimovmoomosmo`mo.momo
momo
mo#mo#mo#mo moRmoemopmoomormotmoemo momoemonmosmoumoamolmomo
mo`mo`mo`momo
moGmoEmoTmo mo/moamopmoimo/mobmoimoemonmoemosmotmoamormo/movmoomolmoamonmotmoemo/mormoemopmoomormotmosmo/momoomonmotmohmolmoymoómomoomonmotmohmo=mo2mo0mo2mo5mo-mo0mo9mo&mosmoimotmoemo_moimodmo=mo1momo
mo`mo`mo`momo
moRmoemosmopmoumoemosmotmoamo:momo
mo`mo`mo`mojmosmoomonmomo
mo{momo
mo mo mo"mopmoemormoimoomodmo"mo:mo mo"mo2mo0mo2mo5mo-mo0mo9mo"mo,momo
mo mo mo"motmoomotmoamolmo"mo:mo mo2mo4mo,momo
mo mo mo"mopmoemormo_mosmoimotmoemo"mo:mo mo[mo mo{mo mo"mosmoimotmoemo_moimodmo"mo:mo mo1mo,mo mo"mosmoimotmoemo_monmoamomoemo"mo:mo mo"moCmoamopmoimotmoamolmo"mo,mo mo"motmoomotmoamolmo"mo:mo mo1mo4mo mo}mo mo]mo,momo
mo mo mo"mopmoemormo_mogmormoomoumopmo"mo:mo mo[mo mo{mo mo"mogmormoomoumopmo_moimodmo"mo:mo mo5mo,mo mo"mogmormoomoumopmo_monmoamomoemo"mo:mo mo"moGmormoumopmoomo moAmo"mo,mo mo"motmoomotmoamolmo"mo:mo mo9mo mo}mo mo]mo,momo
mo mo mo"mobmoemonmoemofmoimocmoimoamormoimoemosmo"mo:mo mo[mo mo{mo mo"mobmoemonmoemofmoimocmoimoamormoimoomo_moimodmo"mo:mo mo"moumoumoimodmo"mo,mo mo"monmoomomobmormoemo"mo:mo mo"moAmonmoamo moPmo�mormoemozmo"mo,mo mo"mogmormoomoumopmo_monmoamomoemo"mo:mo mo"moGmormoumopmoomo moAmo"mo,mo mo"moemonmormoomolmolmoemodmo_moamotmo"mo:mo mo"mo2mo0mo2mo5mo-mo0mo9mo-mo0mo5mo mo0mo9mo:mo3mo0mo:mo0mo0mo"mo mo}mo mo]momo
mo}momo
mo`mo`mo`momo
momo
mo#mo#mo#mo moRmoemopmoomormotmoemo motmormoimomoemosmotmormoamolmomo
mo`mo`mo`momo
moGmoEmoTmo mo/moamopmoimo/mobmoimoemonmoemosmotmoamormo/movmoomolmoamonmotmoemo/mormoemopmoomormotmosmo/moqmoumoamormotmoemormolmoymoómoymoemoamormo=mo2mo0mo2mo5mo&moqmo=mo3momo
mo`mo`mo`momo
moRmoemosmopmoumoemosmotmoamo:momo
mo`mo`mo`mojmosmoomonmomo
mo{momo
mo mo mo"moymoemoamormo"mo:mo mo2mo0mo2mo5mo,momo
mo mo mo"moqmoumoamormotmoemormo"mo:mo mo3mo,momo
mo mo mo"motmoomotmoamolmo"mo:mo mo6mo8mo,momo
mo mo mo"mopmoemormo_momoomonmotmohmo"mo:mo mo[momo
mo mo mo mo mo{mo mo"mopmoemormoimoomodmo"mo:mo mo"mo2mo0mo2mo5mo-mo0mo7mo"mo,mo mo"motmoomotmoamolmo"mo:mo mo2mo1mo mo}mo,momo
mo mo mo mo mo{mo mo"mopmoemormoimoomodmo"mo:mo mo"mo2mo0mo2mo5mo-mo0mo8mo"mo,mo mo"motmoomotmoamolmo"mo:mo mo1mo8mo mo}mo,momo
mo mo mo mo mo{mo mo"mopmoemormoimoomodmo"mo:mo mo"mo2mo0mo2mo5mo-mo0mo9mo"mo,mo mo"motmoomotmoamolmo"mo:mo mo2mo9mo mo}momo
mo mo mo]mo,momo
mo mo mo"mopmoemormo_mosmoimotmoemo"mo:mo mo[mo mo.mo.mo.mo mo]mo,momo
mo mo mo"mopmoemormo_mogmormoomoumopmo"mo:mo mo[mo mo.mo.mo.mo mo]momo
mo}momo
mo`mo`mo`momo
momo
mo#mo#mo#mo moDmoimosmopmoomonmoimobmoimolmoimodmoamodmo moemonmo movmoimovmoomomo
mo`mo`mo`momo
moGmoEmoTmo mo/moamopmoimo/mobmoimoemonmoemosmotmoamormo/movmoomolmoamonmotmoemo/mormoemopmoomormotmosmo/moamovmoamoimolmoamobmoimolmoimotmoymoómosmoimotmoemo_moimodmo=mo1momo
mo`mo`mo`mo
mo`mocmoamopmoamocmoimotmoymo`mo,mo mo`moamocmotmoimovmoemo`mo moemo mo`moamovmoamoimolmoamobmolmoemo`mo mosmoemo mocmoamolmocmoumolmoamonmo mocmoomonmo mo`moVmoomolmoGmormoomoumopmo:mo:mowmoimotmohmoAmovmoamoimolmoamobmoimolmoimotmoymo(mo)mo`mo.momo
momo
mo#mo#mo moCmoomonmovmoemonmocmoimoomonmoemosmo modmoemo mormoemopmoomormotmoemosmomo
momo
mo-mo mo*mo*momoemonmosmoumoamolmo:mo*mo*mo mosmoemo moamogmormoumopmoamo mopmoomormo mo`moDmoAmoTmoEmo(moemonmormoomolmolmoemodmo_moamotmo)mo`mo,mo moumosmoamonmodmoomo moemolmo mopmormoimomoemormo moamolmo mo�molmotmoimomoomo modmo�moamo modmoemolmo momoemosmo mo(mo`moYmoYmoYmoYmo-momomo`mo)mo.momo
mo-mo mo*mo*moTmormoimomoemosmotmormoamolmo:mo*mo*mo mosmoemo moamogmormoumopmoamo mopmoomormo motmormoimomoemosmotmormoemo mocmoamolmoemonmodmoamormoimoomo mo(moQmo1mo=moEmonmoemo-momoamormo,mo moQmo2mo=moAmobmormo-moJmoumonmo,mo moQmo3mo=moJmoumolmo-moSmoemopmo,mo moQmo4mo=moOmocmotmo-moDmoimocmo)mo mocmoomonmosmoimodmoemormoamonmodmoomo moemolmo mo`moemonmormoomolmolmoemodmo_moamotmo`mo modmoemo mocmoamodmoamo moimonmosmocmormoimopmocmoimo�monmo.momo
mo-mo mo*mo*moDmoimosmopmoomonmoimobmoimolmoimodmoamodmo:mo*mo*mo mocmoamolmocmoumolmoamo moimonmosmocmormoimotmoomosmo moamocmotmoimovmoomosmo mo(mo`mosmotmoamotmoumosmo=moimonmosmocmormoimotmoomo`mo)mo moymo mopmolmoamozmoamosmo modmoimosmopmoomonmoimobmolmoemosmo mo(mo`mocmoamopmoamocmoimotmoymo mo-mo moamocmotmoimovmoomosmo`mo)mo.momo
momo
mo#mo#mo moDmoamosmohmobmoomoamormodmo mowmoemobmomo
momo
mo-mo moRmoumotmoamo mopmormoomotmoemogmoimodmoamo:mo mo`mo/movmoomolmo/modmoamosmohmobmoomoamormodmo`mo mo(mopmoemormomoimosmoomo mo`movmoomolmo.mormoemopmoomormotmosmo.movmoimoemowmo`mo)mo.momo
mo-mo moPmormoemosmoemonmotmoamo moKmoPmoImosmo momoemonmosmoumoamolmoemosmo/motmormoimomoemosmotmormoamolmoemosmo,mo motmoamobmolmoamo modmoemo mobmoemonmoemofmoimocmoimoamormoimoomosmo moymo mopmoamonmoemolmo modmoemo modmoimosmopmoomonmoimobmoimolmoimodmoamodmo.momo
mo-mo moNmoomotmoamo moomopmoemormoamotmoimovmoamo moimonmocmolmoumoimodmoamo:mo mopmolmoamonmotmoimolmolmoamosmo mosmoemomoamonmoamolmoemosmo moLmomoVmo mo/mo momoJmo mo/mo moSmoamobmo.mo