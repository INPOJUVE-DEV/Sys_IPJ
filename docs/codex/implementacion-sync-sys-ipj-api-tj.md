# Implementación de sincronización Sys_IPJ ↔ API_TJ

Documento para guiar a Codex en el desarrollo de la actualización de sincronización entre Sys_IPJ y API_TJ.

## 1. Objetivo

Implementar las funciones que le corresponden a Sys_IPJ para integrarse con API_TJ sin modificar el modelo de datos core.

Sys_IPJ debe cubrir dos responsabilidades principales:

1. Emitir padrón mínimo hacia API_TJ.
2. Recibir staging aprobado desde API_TJ y convertirlo, si procede, en beneficiario oficial.

Todo estado de integración, auditoría, idempotencia y seguridad debe vivir fuera de las tablas core.

---

## 2. Restricciones no negociables

No modificar:

- `beneficiarios`
- `domicilios`
- `tarjetas`
- `users`
- `municipios`
- `secciones`
- modelo `App\Models\Beneficiario` para metadatos de integración
- `created_by` para hacerlo nullable
- observers globales sobre `Beneficiario` para sincronización

No agregar a `beneficiarios` campos como:

- `source_system`
- `source_external_request_id`
- `curp_hash`
- `curp_masked`
- `api_tj_sync_status`
- `api_tj_sync_attempts`
- `api_tj_last_synced_at`
- `api_tj_last_sync_error`
- `integration_status`
- `external_status`

Regla central:

```txt
Las integraciones rodean al modelo core; no lo invaden.
```

---

## 3. Decisiones cerradas

### 3.1 Fuente de `tarjeta_numero`

Prioridad:

1. Si existe tarjeta relacionada válida, usar `tarjetas.folio`.
2. Si no existe tarjeta relacionada válida, usar `beneficiarios.folio_tarjeta`.
3. Si no hay folio válido, omitir del sync y registrar item como `skipped` o `rejected`.

### 3.2 Usuario técnico para altas desde API_TJ

Usar un usuario técnico institucional.

Variable propuesta:

```env
API_TJ_INTEGRATION_USER_EMAIL=integracion.api_tj@inpojuve.local
```

Reglas:

- El usuario debe existir antes de probar integración.
- `created_by` nunca debe ser `null`.
- Si el usuario técnico no existe, Sys_IPJ debe fallar de forma controlada y registrar error en la tabla de inbound requests.

### 3.3 Payload hacia API_TJ

API_TJ espera `items`.

Usar:

```json
{
  "sync_id": "SYS-IPJ-2026-06-10-001",
  "items": []
}
```

No usar:

```json
{
  "records": []
}
```

### 3.4 Seguridad inbound API_TJ → Sys_IPJ

Sys_IPJ no debe aceptar push desde API_TJ sin JWT firmado.

Requisito:

```txt
Authorization: Bearer <jwt_rs256>
```

Scope requerido:

```txt
beneficiarios.staging.push
```

Implicación:

- API_TJ deberá firmar el request hacia Sys_IPJ.
- No basta con `Idempotency-Key`.
- `Idempotency-Key` puede conservarse como encabezado auxiliar, pero la fuente de seguridad debe ser JWT RS256.

### 3.5 Ejecución del sync

Primera versión:

```txt
manual + job queued
```

El administrador dispara la sincronización manualmente desde Sys_IPJ, pero el procesamiento corre en job para evitar timeouts.

No implementar en primera versión:

- cron automático;
- reintentos automáticos;
- sync nocturno;
- webhooks;
- sincronización en cada guardado de beneficiario.

---

## 4. Estado actual esperado

Sys_IPJ ya cuenta con:

- modelo `Beneficiario` como modelo core;
- tabla `beneficiarios` con `created_by` obligatorio;
- tabla `tarjetas` con `folio`, `estatus` y `beneficiario_id`;
- rutas API bajo `/api/v1`;
- ruta legacy `/api/v1/beneficiarios/cache`, que no debe usarse para esta sincronización;
- documentación de arquitectura e integración previamente agregada.

API_TJ ya cuenta con:

- `POST /api/v1/cardholders/sync`;
- `POST /api/v1/cardholders/lookup`;
- `POST /api/v1/beneficiarios-staging`;
- staging cifrado;
- integración JWT RS256 para endpoints recibidos;
- push manual de staging hacia Sys_IPJ, pendiente de alinear con JWT hacia Sys_IPJ.

---

## 5. Funciones que debe implementar Sys_IPJ

## 5.1 Sys_IPJ → API_TJ: sincronización de padrón mínimo

### Responsabilidad

Enviar a API_TJ un padrón mínimo para validación y activación digital.

Datos por registro:

```json
{
  "curp_hash": "64_hex_chars",
  "curp_masked": "MELR**********06",
  "tarjeta_numero": "TJ-0080",
  "status": "active"
}
```

### Endpoint destino

En API_TJ:

```txt
POST /api/v1/cardholders/sync
```

### Body

```json
{
  "sync_id": "SYS-IPJ-2026-06-10-001",
  "items": [
    {
      "curp_hash": "64_hex_chars",
      "curp_masked": "MELR**********06",
      "tarjeta_numero": "TJ-0080",
      "status": "active"
    }
  ]
}
```

### Header

```txt
Authorization: Bearer <jwt_rs256>
Content-Type: application/json
```

### Scope

```txt
cardholders.sync
```

---

## 5.2 API_TJ → Sys_IPJ: recepción de staging aprobado

### Responsabilidad

Recibir desde API_TJ un expediente aprobado manualmente y convertirlo en beneficiario oficial si pasa validaciones.

### Endpoint en Sys_IPJ

```txt
POST /api/v1/integrations/api-tj/staging/accept
```

### Header

```txt
Authorization: Bearer <jwt_rs256>
Content-Type: application/json
Idempotency-Key: <external_request_id>
```

`Idempotency-Key` es auxiliar. La idempotencia oficial se basa en `source_system + external_request_id`.

### Scope

```txt
beneficiarios.staging.push
```

### Body

```json
{
  "external_request_id": "API-TJ-STG-2026-0001",
  "source": "api_tj",
  "submitted_by": {
    "user_id": "123",
    "name": "Administrador API_TJ"
  },
  "beneficiario": {
    "folio_tarjeta": "TJ-0099",
    "nombre": "JULIETA",
    "apellido_paterno": "MORALES",
    "apellido_materno": "CANO",
    "curp": "MOCJ050521MSPNRL01",
    "fecha_nacimiento": "2005-05-21",
    "sexo": "M",
    "discapacidad": false,
    "id_ine": "INE123456",
    "telefono": "4441234567",
    "domicilio": {
      "calle": "AV REVOLUCION",
      "numero_ext": "321B",
      "numero_int": null,
      "colonia": "ZONA CENTRO",
      "municipio_id": 1,
      "codigo_postal": "22000",
      "seccional": "001"
    }
  }
}
```

---

## 6. Tablas nuevas requeridas

Estas tablas son permitidas porque no modifican el modelo core.

### 6.1 `integration_clients`

Clientes autorizados para integraciones sistema-a-sistema.

Campos:

```txt
id uuid primary
client_code string unique
name string
status string index
allowed_scopes json nullable
ip_allowlist json nullable
last_used_at timestamp nullable
created_at
updated_at
```

Valores iniciales sugeridos:

```txt
client_code = api_tj
name = API Tarjeta Joven
status = active
allowed_scopes = ["beneficiarios.staging.push"]
```

```txt
client_code = sys_ipj
name = Sys_IPJ
status = active
allowed_scopes = ["cardholders.sync"]
```

### 6.2 `integration_client_keys`

Llaves públicas por cliente para validar JWT recibidos.

Campos:

```txt
id uuid primary
client_id uuid foreign key integration_clients.id
kid string
public_key text
status string index
valid_from timestamp nullable
valid_until timestamp nullable
created_at
updated_at
```

Índices/restricciones:

```txt
unique(client_id, kid)
index(status)
```

Uso:

- Sys_IPJ valida JWT de API_TJ usando `api_tj` + `kid`.
- Permite rotación de llaves sin cambiar código.

### 6.3 `integration_jti_logs`

Prevención de replay.

Campos:

```txt
id uuid primary
client_id uuid nullable foreign key integration_clients.id
issuer string
jti string
expires_at timestamp
created_at
```

Índices/restricciones:

```txt
unique(issuer, jti)
index(expires_at)
```

### 6.4 `integration_sync_runs`

Corridas de sincronización Sys_IPJ → API_TJ.

Campos:

```txt
id uuid primary
target_system string index
operation string index
status string index
requested_by char(36) nullable foreign key users.uuid
started_at timestamp nullable
finished_at timestamp nullable
total_items unsignedInteger default 0
success_count unsignedInteger default 0
failed_count unsignedInteger default 0
skipped_count unsignedInteger default 0
error_message text nullable
created_at
updated_at
```

Estados:

```txt
pending
queued
running
success
partial
failed
cancelled
```

### 6.5 `integration_sync_items`

Resultado por beneficiario dentro de una corrida.

Campos:

```txt
id uuid primary
sync_run_id uuid foreign key integration_sync_runs.id cascadeOnDelete
beneficiario_id uuid nullable foreign key beneficiarios.id nullOnDelete
payload_hash string(64)
status string index
response_code unsignedSmallInteger nullable
response_body json nullable
error_message text nullable
created_at
updated_at
```

Estados:

```txt
pending
sent
accepted
rejected
skipped
error
```

### 6.6 `integration_inbound_requests`

Solicitudes externas recibidas por Sys_IPJ.

Campos:

```txt
id uuid primary
source_system string index
external_request_id string
operation string index
request_hash string(64)
request_payload_encrypted longText nullable
status string index
response_code unsignedSmallInteger nullable
response_body json nullable
error_message text nullable
received_at timestamp
processed_at timestamp nullable
created_at
updated_at
```

Restricción:

```txt
unique(source_system, external_request_id)
```

Estados:

```txt
received
processing
accepted
rejected
failed
already_processed
```

---

## 7. Variables de entorno

### 7.1 Sys_IPJ como emisor hacia API_TJ

```env
API_TJ_BASE_URL=https://api-tj.example.com
API_TJ_AUDIENCE=api_tj
SYS_IPJ_JWT_ISSUER=sys_ipj
SYS_IPJ_JWT_KID=sys_ipj-current
SYS_IPJ_PRIVATE_KEY_PATH=storage/app/keys/sys_ipj_private.pem
CURP_HASH_SECRET=definir-secreto-largo
API_TJ_SYNC_TIMEOUT_SECONDS=15
```

### 7.2 Sys_IPJ como receptor desde API_TJ

```env
SYS_IPJ_INTEGRATION_AUDIENCE=sys_ipj
API_TJ_INTEGRATION_USER_EMAIL=integracion.api_tj@inpojuve.local
INTEGRATION_PAYLOAD_ENCRYPTION_KEY=base64-32-bytes
```

Notas:

- No subir llaves privadas al repositorio.
- No guardar `.pem` reales en Git.
- La llave pública de API_TJ debe guardarse en `integration_client_keys`.

---

## 8. Servicios a crear

## 8.1 Seguridad

### `App\Services\Integrations\Security\IntegrationJwtSigner`

Responsable de firmar JWT salientes.

Método:

```php
public function makeToken(string $audience, string $scope, ?string $issuer = null): string
```

Debe incluir:

```txt
iss
sub
aud
scope
jti
iat
exp
kid
```

### `App\Services\Integrations\Security\IntegrationJwtVerifier`

Responsable de validar JWT entrantes.

Método:

```php
public function verify(Request $request, string $requiredScope): IntegrationAuthContext
```

Validaciones:

```txt
Authorization Bearer
kid
iss
aud
exp
iat
firma RS256
cliente activo
llave activa
scope permitido para cliente
scope requerido presente
jti no reutilizado
ip allowlist si aplica
```

### `App\Services\Integrations\Security\IntegrationJtiService`

Responsable de registrar y limpiar `jti`.

Métodos:

```php
public function assertNotReplayed(IntegrationClient $client, string $issuer, string $jti, Carbon $expiresAt): void
public function cleanupExpired(): int
```

### `App\Http\Middleware\ValidateIntegrationJwt`

Middleware para endpoints inbound.

Uso esperado:

```php
Route::middleware(['api', 'integration.jwt:beneficiarios.staging.push'])
```

---

## 8.2 Outbound Sys_IPJ → API_TJ

### `App\Services\Integrations\ApiTj\CurpFingerprintService`

Métodos:

```php
public function normalize(string $curp): string
public function hash(string $curp): string
public function mask(string $curp): string
```

Reglas:

```txt
normalize = uppercase + trim
hash = HMAC-SHA-256 con CURP_HASH_SECRET
mask = primeros 4 + asteriscos + últimos 2
```

### `App\Services\Integrations\ApiTj\CardholderSyncSelector`

Método:

```php
public function queryEligible(): Builder
```

Debe seleccionar beneficiarios sincronizables.

### `App\Services\Integrations\ApiTj\CardholderPayloadFactory`

Método:

```php
public function makeItem(Beneficiario $beneficiario): array
```

Debe resolver `tarjeta_numero` con esta prioridad:

```txt
1. tarjetas.folio válida
2. beneficiarios.folio_tarjeta
3. skip si no hay folio
```

### `App\Services\Integrations\ApiTj\ApiTjClient`

Método:

```php
public function syncCardholders(string $syncId, array $items): ApiTjSyncResponse
```

Debe enviar:

```txt
POST {API_TJ_BASE_URL}/api/v1/cardholders/sync
```

Con body:

```json
{
  "sync_id": "...",
  "items": []
}
```

### `App\Services\Integrations\ApiTj\CardholderSyncService`

Método:

```php
public function queue(User $actor, array $options = []): IntegrationSyncRun
public function run(IntegrationSyncRun $run): void
```

Responsabilidad:

```txt
crear corrida
crear items
despachar job
procesar lotes
llamar API_TJ
registrar resultados
no tocar beneficiarios
```

### `App\Jobs\Integrations\ApiTj\RunCardholderSyncJob`

Job queued.

Responsabilidad:

```txt
ejecutar CardholderSyncService::run()
evitar timeout HTTP
actualizar estado de corrida
registrar errores controlados
```

---

## 8.3 Inbound API_TJ → Sys_IPJ

### `App\Http\Controllers\Api\Integrations\ApiTjStagingAcceptController`

Método:

```php
public function __invoke(Request $request, ApiTjStagingAcceptService $service): JsonResponse
```

### `App\Services\Integrations\Inbound\InboundIdempotencyService`

Método:

```php
public function resolveOrCreate(string $sourceSystem, string $externalRequestId, array $payload): IntegrationInboundRequest
```

Reglas:

```txt
si ya accepted → already_processed
si processing → conflict
si failed/error → permitir reproceso controlado
si nuevo → crear received
```

### `App\Services\Integrations\ApiTj\ApiTjStagingPayloadValidator`

Método:

```php
public function validate(array $payload): array
```

Debe validar:

```txt
external_request_id
source = api_tj
beneficiario.curp
beneficiario.nombre
beneficiario.apellido_paterno
beneficiario.apellido_materno
beneficiario.fecha_nacimiento
beneficiario.sexo
beneficiario.discapacidad
beneficiario.id_ine
beneficiario.telefono
beneficiario.domicilio
domicilio.calle
domicilio.numero_ext
domicilio.colonia
domicilio.municipio_id
domicilio.codigo_postal
domicilio.seccional
```

### `App\Services\Integrations\ApiTj\ApiTjTechnicalUserResolver`

Método:

```php
public function resolve(): User
```

Debe buscar por:

```env
API_TJ_INTEGRATION_USER_EMAIL
```

Si no existe:

```txt
fallar controladamente
no crear beneficiario
registrar error
```

### `App\Services\Beneficiarios\BeneficiarioRegistrationService`

Método mínimo para integración:

```php
public function createFromIntegration(array $data, User $technicalActor, IntegrationInboundRequest $request): Beneficiario
```

Debe:

```txt
usar DB::transaction
validar duplicados
validar seccional
validar municipio
asociar seccion
asignar municipio_id
crear beneficiario
created_by = technicalActor.uuid
crear domicilio
registrar actividad normal
```

No debe:

```txt
guardar source_system en beneficiarios
guardar external_request_id en beneficiarios
hacer created_by null
saltar reglas core
```

### `App\Services\Integrations\ApiTj\ApiTjStagingAcceptService`

Método:

```php
public function accept(array $payload, IntegrationAuthContext $auth): ApiTjStagingAcceptResult
```

Responsabilidad:

```txt
crear/consultar inbound request
validar idempotencia
validar payload
resolver usuario técnico
crear beneficiario oficial
actualizar inbound request
devolver respuesta estructurada
```

---

## 9. Rutas a crear

### 9.1 API inbound

En `sys_beneficiarios/routes/api.php`:

```php
Route::prefix('integrations/api-tj')
    ->middleware(['api', 'integration.jwt:beneficiarios.staging.push', 'throttle:30,1'])
    ->group(function () {
        Route::post('/staging/accept', \App\Http\Controllers\Api\Integrations\ApiTjStagingAcceptController::class);
    });
```

Ruta final:

```txt
POST /api/v1/integrations/api-tj/staging/accept
```

### 9.2 Admin sync

En `sys_beneficiarios/routes/web.php`:

```php
Route::middleware(['auth', 'role:admin'])
    ->prefix('admin/integraciones/api-tj')
    ->name('admin.integraciones.api_tj.')
    ->group(function () {
        Route::post('/cardholders/sync', [ApiTjCardholderSyncController::class, 'store'])->name('cardholders.sync');
        Route::get('/sync-runs', [ApiTjSyncRunController::class, 'index'])->name('sync-runs.index');
        Route::get('/sync-runs/{run}', [ApiTjSyncRunController::class, 'show'])->name('sync-runs.show');
    });
```

---

## 10. Respuestas esperadas

## 10.1 API_TJ → Sys_IPJ staging accept

### Creado

HTTP `201`

```json
{
  "accepted": true,
  "status": "created",
  "external_request_id": "API-TJ-STG-2026-0001",
  "beneficiario_id": "uuid",
  "message": "Beneficiario creado correctamente"
}
```

### Ya procesado

HTTP `200`

```json
{
  "accepted": true,
  "status": "already_processed",
  "external_request_id": "API-TJ-STG-2026-0001",
  "beneficiario_id": "uuid",
  "message": "Solicitud ya procesada previamente"
}
```

### Duplicado

HTTP `409`

```json
{
  "accepted": false,
  "status": "duplicate",
  "external_request_id": "API-TJ-STG-2026-0001",
  "message": "Ya existe un beneficiario con la CURP proporcionada"
}
```

### Validación

HTTP `422`

```json
{
  "accepted": false,
  "status": "validation_error",
  "external_request_id": "API-TJ-STG-2026-0001",
  "errors": {
    "beneficiario.curp": ["CURP inválida"]
  }
}
```

### Seguridad

HTTP `401`

```json
{
  "message": "Token de integración inválido"
}
```

HTTP `403`

```json
{
  "message": "Permisos insuficientes"
}
```

---

## 11. Pruebas requeridas

## 11.1 Unitarias

Crear pruebas para:

```txt
CurpFingerprintService::normalize
CurpFingerprintService::hash
CurpFingerprintService::mask
IntegrationJwtSigner
IntegrationJwtVerifier
IntegrationJtiService
CardholderPayloadFactory
ApiTjStagingPayloadValidator
InboundIdempotencyService
ApiTjTechnicalUserResolver
```

## 11.2 Feature tests inbound

Casos:

```txt
POST staging/accept sin token → 401
token inválido → 401
token expirado → 401
scope incorrecto → 403
jti repetido → 401
payload inválido → 422
CURP duplicada → 409
usuario técnico inexistente → 500 controlado
request correcto → 201
mismo external_request_id → 200 already_processed
```

## 11.3 Feature tests outbound

Casos:

```txt
admin dispara sync → crea run queued
job ejecuta sync → llama ApiTjClient
API_TJ responde éxito → run success
API_TJ responde parcial → run partial
API_TJ responde 401 → run failed
API_TJ timeout → run failed
beneficiario sin tarjeta/folio → item skipped
```

## 11.4 Pruebas de migraciones

Validar:

```txt
migrate fresh funciona
rollback de migraciones de integración funciona
no hay alter table sobre beneficiarios
no hay alter table sobre tablas core
```

---

## 12. Criterios de aceptación

La implementación se acepta si:

- no modifica `beneficiarios`;
- no modifica `Beneficiario` para metadatos de integración;
- no hace `created_by` nullable;
- no agrega observers globales de sync;
- crea tablas separadas para integración;
- usa JWT RS256;
- usa `jti` anti-replay;
- valida scopes;
- valida idempotencia por `source_system + external_request_id`;
- registra auditoría;
- usa usuario técnico para `created_by`;
- respeta reglas actuales de captura;
- envía payload a API_TJ con `items`;
- no usa `beneficiarios/cache` para la integración nueva;
- no permite escritura directa de API_TJ en la base de Sys_IPJ.

---

## 13. Criterios de rechazo

Rechazar cualquier implementación que:

- agregue columnas de integración a `beneficiarios`;
- agregue `curp_hash` a `beneficiarios`;
- agregue `source_system` a `beneficiarios`;
- agregue estado API_TJ a `beneficiarios`;
- haga `created_by` nullable;
- cree observers globales para sincronización;
- use tokens estáticos compartidos;
- omita auditoría;
- omita idempotencia;
- acepte push inbound sin JWT;
- mezcle staging con alta oficial sin validación;
- modifique tablas core para comodidad de integración.

---

## 14. Orden recomendado de implementación

Codex debe trabajar en este orden:

1. Migraciones de tablas de integración.
2. Modelos de integración.
3. Servicios de seguridad JWT.
4. Middleware de integración.
5. Servicios outbound Sys_IPJ → API_TJ.
6. Job de sincronización.
7. Controladores admin de sync.
8. Servicios inbound API_TJ → Sys_IPJ.
9. Endpoint inbound.
10. Pruebas unitarias.
11. Pruebas feature.
12. Documentación breve de variables `.env`.

No cambiar código de API_TJ desde este documento, excepto dejar anotado que API_TJ debe firmar el push hacia Sys_IPJ en su propio ajuste.

---

## 15. Nota para API_TJ

API_TJ actualmente debe alinearse para enviar JWT en el push hacia Sys_IPJ.

Cambio esperado en API_TJ, en su propio scope:

```txt
sysIpjClient debe agregar Authorization: Bearer <jwt_rs256>
```

El body puede conservar:

```json
{
  "external_request_id": "...",
  "beneficiario": {}
}
```

Y puede conservar header auxiliar:

```txt
Idempotency-Key: <external_request_id>
```

Pero Sys_IPJ no debe aceptar la solicitud sin JWT válido.

---

## 16. Resumen ejecutivo

La implementación debe crear una capa formal de integración alrededor de Sys_IPJ.

Sys_IPJ conserva el padrón oficial. API_TJ conserva staging y canal digital. La Unidad de Informática opera contra API_TJ, no contra Sys_IPJ.

La base de datos se mantiene estable porque las tablas core no se alteran. Las nuevas necesidades se resuelven con tablas nuevas, servicios especializados, JWT, auditoría e idempotencia.
