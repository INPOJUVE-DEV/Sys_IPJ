# QA end-to-end API_TJ ↔ Sys_IPJ

## Objetivo

Validar que `API_TJ` sea compatible con el contrato implementado en `Sys_IPJ` para los flujos de integración entre sistemas.

Este documento cubre la perspectiva de `API_TJ`:

1. Recibir padrón mínimo desde `Sys_IPJ`.
2. Permitir lookup de Unidad Informática.
3. Crear staging de beneficiarios desde Unidad Informática.
4. Enviar staging aprobado hacia `Sys_IPJ`.
5. Auditar cada intercambio sin exponer PII innecesaria.

## Contrato requerido

### Sys_IPJ → API_TJ

API_TJ debe aceptar:

```txt
POST /api/v1/cardholders/sync
Authorization: Bearer <jwt_rs256>
Scope: cardholders.sync
```

Payload:

```json
{
  "sync_id": "SYS-IPJ-QA-001",
  "items": [
    {
      "curp_hash": "64_hex_chars",
      "curp_masked": "PELJ************09",
      "tarjeta_numero": "TJ-QA-0001",
      "status": "active"
    }
  ]
}
```

### Unidad Informática → API_TJ

Lookup:

```txt
POST /api/v1/cardholders/lookup
Authorization: Bearer <jwt_rs256>
Scope: cardholders.lookup
```

Staging:

```txt
POST /api/v1/beneficiarios-staging
Authorization: Bearer <jwt_rs256>
Scope: beneficiarios.staging.create
```

### API_TJ → Sys_IPJ

API_TJ debe enviar:

```txt
POST {SYS_IPJ_PUSH_URL}
Authorization: Bearer <jwt_rs256>
Idempotency-Key: <external_request_id>
Scope: beneficiarios.staging.push
```

Payload obligatorio:

```json
{
  "external_request_id": "USI-QA-0001",
  "source": "api_tj",
  "submitted_by": {
    "system": "api_tj"
  },
  "beneficiario": {
    "folio_tarjeta": "TJ-QA-9001",
    "nombre": "LAURA",
    "apellido_paterno": "MARTINEZ",
    "apellido_materno": "SOTO",
    "curp": "MOCJ050521MSPNRL01",
    "fecha_nacimiento": "2005-05-21",
    "sexo": "F",
    "discapacidad": false,
    "id_ine": "INEQA123456",
    "telefono": "4441234567",
    "domicilio": {
      "calle": "AV QA",
      "numero_ext": "100",
      "numero_int": null,
      "colonia": "CENTRO",
      "municipio_id": 1,
      "codigo_postal": "78000",
      "seccional": "001"
    }
  }
}
```

## Variables críticas

```env
CURP_HASH_SECRET=<mismo_valor_que_Sys_IPJ>

INTEGRATION_JWT_AUDIENCE=api_tj
SYS_IPJ_JWT_PUBLIC_KEY=-----BEGIN PUBLIC KEY-----...
SYS_IPJ_JWT_KID=sys_ipj-current
SYS_IPJ_ALLOWED_SCOPES=["cardholders.sync"]

INFORMATICA_JWT_PUBLIC_KEY=-----BEGIN PUBLIC KEY-----...
INFORMATICA_JWT_KID=unidad_informatica-current
INFORMATICA_ALLOWED_SCOPES=["cardholders.lookup","beneficiarios.staging.create"]

SYS_IPJ_PUSH_URL=https://<sys-ipj-url>/api/v1/integrations/api-tj/staging/accept
SYS_IPJ_PUSH_TIMEOUT_MS=8000
API_TJ_TO_SYS_IPJ_PRIVATE_KEY_PATH=/app/keys/api_tj_private.pem
API_TJ_TO_SYS_IPJ_JWT_KID=api_tj-current
API_TJ_TO_SYS_IPJ_ISSUER=api_tj
API_TJ_TO_SYS_IPJ_SUBJECT=api_tj
API_TJ_TO_SYS_IPJ_AUDIENCE=sys_ipj
API_TJ_TO_SYS_IPJ_SCOPE=beneficiarios.staging.push
API_TJ_TO_SYS_IPJ_JWT_EXPIRES_IN=5m
```

## Suite A. Recepción de padrón desde Sys_IPJ

### A1. Sync exitoso

Request esperado:

```json
{
  "sync_id": "SYS-IPJ-QA-001",
  "items": [
    {
      "curp_hash": "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa",
      "curp_masked": "PELJ************09",
      "tarjeta_numero": "TJ-QA-0001",
      "status": "active"
    }
  ]
}
```

Esperado:

- HTTP 200.
- Insert/update en `cardholders_sync`.
- Registro en `sync_audit_log` con `direction = SYS_IPJ_TO_API_TJ`.

Validación SQL:

```sql
SELECT curp_hash, tarjeta_numero, status
FROM cardholders_sync
WHERE tarjeta_numero = 'TJ-QA-0001';
```

### A2. Sync con item inválido

Payload inválido:

```json
{
  "curp_hash": "INVALID_HASH",
  "curp_masked": "PELJ************09",
  "tarjeta_numero": "TJ-QA-0001",
  "status": "active"
}
```

Esperado:

- No insertar item inválido.
- Incrementar `skipped`.
- Responder resultado granular por índice.

Respuesta recomendada:

```json
{
  "accepted": true,
  "status": "partial",
  "processed": 1,
  "inserted": 0,
  "updated": 0,
  "skipped": 1,
  "conflict": 0,
  "results": [
    {
      "index": 0,
      "status": "skipped",
      "message": "curp_hash invalido"
    }
  ]
}
```

### A3. Conflicto por tarjeta

Caso:

- Existe `tarjeta_numero = TJ-QA-0001` con `curp_hash = A`.
- Llega `tarjeta_numero = TJ-QA-0001` con `curp_hash = B`.

Esperado:

```json
{
  "accepted": true,
  "status": "partial",
  "conflict": 1,
  "results": [
    {
      "index": 0,
      "status": "conflict",
      "message": "tarjeta_numero ya existe con otro curp_hash"
    }
  ]
}
```

## Suite B. Lookup Unidad Informática

### B1. Lookup registrado

Request:

```json
{
  "curp": "PELJ000101HMNRRS09"
}
```

Esperado:

```json
{
  "registered": true,
  "folio_tarjeta": "TJ-QA-0001"
}
```

Criterio PASS:

- JWT con scope `cardholders.lookup`.
- `CURP_HASH_SECRET` coincide con Sys_IPJ.
- Existe registro en `cardholders_sync`.

### B2. Lookup no registrado

Esperado:

```txt
HTTP 404
registered = false
```

### B3. Lookup sin scope

Esperado:

```txt
HTTP 403
```

## Suite C. Crear staging

### C1. Crear staging válido

Request:

```json
{
  "external_request_id": "USI-QA-0001",
  "beneficiario": {
    "folio_tarjeta": "TJ-QA-9001",
    "nombre": "LAURA",
    "apellido_paterno": "MARTINEZ",
    "apellido_materno": "SOTO",
    "curp": "MOCJ050521MSPNRL01",
    "fecha_nacimiento": "2005-05-21",
    "sexo": "F",
    "discapacidad": false,
    "id_ine": "INEQA123456",
    "telefono": "4441234567",
    "domicilio": {
      "calle": "AV QA",
      "numero_ext": "100",
      "numero_int": null,
      "colonia": "CENTRO",
      "municipio_id": 1,
      "codigo_postal": "78000",
      "seccional": "001"
    }
  }
}
```

Esperado:

```json
{
  "created": true,
  "status": "pending",
  "staging_id": 123
}
```

Validar:

```sql
SELECT external_request_id, curp_masked, status
FROM beneficiario_staging
WHERE external_request_id = 'USI-QA-0001';
```

### C2. Validación preventiva de staging

Casos que deben rechazarse antes de intentar push a Sys_IPJ:

| Campo | Valor inválido | Esperado |
|---|---|---|
| `telefono` | `444ABC` | 422 |
| `sexo` | `Z` | 422 |
| `fecha_nacimiento` | `ayer` | 422 |
| `municipio_id` | `0` | 422 |
| `seccional` | vacío | 422 |

## Suite D. Push API_TJ → Sys_IPJ

### D1. Push válido

Precondiciones:

- `SYS_IPJ_PUSH_URL` apunta a `/api/v1/integrations/api-tj/staging/accept`.
- API_TJ firma con `scope = beneficiarios.staging.push`.
- API_TJ manda `source = api_tj`.
- Sys_IPJ tiene llave pública de API_TJ activa.

Acción:

```txt
POST /api/v1/admin/beneficiarios-staging/:id/push
```

Esperado en API_TJ:

```json
{
  "sent": true,
  "message": "Beneficiario enviado a Sys_IPJ",
  "sys_ipj_status": 201
}
```

Esperado en Sys_IPJ:

```json
{
  "accepted": true,
  "status": "created",
  "beneficiario_id": "<uuid>"
}
```

### D2. Push con scope incorrecto

Scope:

```txt
beneficiarios.create
```

Esperado:

```txt
Sys_IPJ HTTP 403
API_TJ registra intento fallido
```

### D3. Push sin `source`

Body sin:

```json
{
  "source": "api_tj"
}
```

Esperado:

```txt
Sys_IPJ HTTP 422
status = validation_error
```

### D4. Push repetido

Mismo `external_request_id` y mismo payload.

Esperado:

```txt
Sys_IPJ status = already_processed
API_TJ no duplica beneficiario
```

### D5. Mismo `external_request_id`, payload distinto

Esperado:

```txt
Sys_IPJ HTTP 409 conflict
API_TJ registra intento rechazado/error
```

## Suite E. Seguridad JWT

| Caso | Esperado |
|---|---|
| Sin Authorization | 401 |
| Token malformado | 401 |
| `kid` no registrado | 401 |
| Scope no permitido | 403 |
| Scope requerido faltante | 403 |
| IP fuera de allowlist | 403 |
| Replay de `jti` | 401 |
| Audiencia incorrecta | 401 |

## Suite F. Auditoría

### F1. Auditoría de sync recibido

```sql
SELECT direction, executed_by, request_count, inserted_count, updated_count, skipped_count, conflict_count, status
FROM sync_audit_log
WHERE direction = 'SYS_IPJ_TO_API_TJ'
ORDER BY created_at DESC
LIMIT 1;
```

### F2. Auditoría de push staging

```sql
SELECT staging_id, external_request_id, actor, response_status, status, error_message
FROM staging_push_attempts
WHERE external_request_id = 'USI-QA-0001'
ORDER BY attempted_at DESC;
```

### F3. Auditoría de llamada de integración

```sql
SELECT client_code, method, path, required_scope, status_code
FROM integration_audit_log
ORDER BY created_at DESC
LIMIT 20;
```

## Resultado QA requerido

| Suite | Resultado mínimo |
|---|---|
| A. Sync padrón | PASS con auditoría |
| B. Lookup Unidad Informática | PASS |
| C. Staging Unidad Informática | PASS |
| D. Push a Sys_IPJ | PASS obligatorio antes de conexión real |
| E. JWT/security | PASS |
| F. Auditoría | PASS |

## Estado actual conocido

Antes de habilitar conexión real, API_TJ debe corregir:

1. `API_TJ_TO_SYS_IPJ_SCOPE` debe ser `beneficiarios.staging.push`.
2. El body de `sysIpjClient` debe incluir `source = api_tj`.
3. El body de `sysIpjClient` debe usar `beneficiario` como objeto operativo principal.
4. `/cardholders/sync` debe devolver `results` por índice para auditoría exacta en Sys_IPJ.
5. Staging debe validar con reglas equivalentes a Sys_IPJ para evitar rechazos tardíos.

## Decisión GO / NO GO

### GO parcial

Permitido:

- Recibir sync de Sys_IPJ.
- Habilitar lookup de Unidad Informática.
- Habilitar creación de staging.

No permitido:

- Push real hacia Sys_IPJ si el contrato saliente no está corregido.

### GO completo

Permitido solo si:

- Push hacia Sys_IPJ usa scope `beneficiarios.staging.push`.
- Push hacia Sys_IPJ manda `source = api_tj`.
- Push crea beneficiario oficial en Sys_IPJ.
- Ambos sistemas registran auditoría.
