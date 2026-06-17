# QA end-to-end Sys_IPJ ↔ API_TJ

## Objetivo

Diseñar y ejecutar pruebas de flujo entre `Sys_IPJ` y `API_TJ` antes de conectar tráfico real entre ambos sistemas.

Este documento se enfoca en la responsabilidad de `Sys_IPJ`:

1. Enviar padrón mínimo a `API_TJ`.
2. Recibir staging aprobado desde `API_TJ`.
3. Mantener auditoría e idempotencia fuera del modelo core.
4. Preservar `beneficiarios`, `domicilios`, `tarjetas`, `users`, `municipios` y `secciones` sin metadatos de integración.

## Contrato base validado

### Sys_IPJ → API_TJ

Endpoint destino:

```txt
POST {API_TJ_BASE_URL}/api/v1/cardholders/sync
```

Headers:

```txt
Authorization: Bearer <jwt_rs256>
Content-Type: application/json
```

Scope requerido por API_TJ:

```txt
cardholders.sync
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

### API_TJ → Sys_IPJ

Endpoint receptor:

```txt
POST /api/v1/integrations/api-tj/staging/accept
```

Headers:

```txt
Authorization: Bearer <jwt_rs256>
Content-Type: application/json
Idempotency-Key: <external_request_id>
```

Scope requerido por Sys_IPJ:

```txt
beneficiarios.staging.push
```

Payload esperado:

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

## Datos QA sugeridos

| Caso | CURP | Tarjeta | Resultado esperado |
|---|---|---|---|
| Beneficiario válido 1 | `PELJ000101HMNRRS09` | `TJ-QA-0001` | Sync accepted |
| Beneficiario válido 2 | `MOCJ050521MSPNRL01` | `TJ-QA-0002` | Sync accepted |
| Sin folio/tarjeta | `GARC010101HSPRRN08` | null | Sync skipped |
| Staging válido | `MOCJ050521MSPNRL01` | `TJ-QA-9001` | Beneficiario creado o duplicate si ya existe |

## Suite A. Outbound Sys_IPJ → API_TJ

### A1. Sync exitoso de padrón mínimo

Precondiciones:

- `API_TJ_BASE_URL` apunta al ambiente QA de API_TJ.
- `CURP_HASH_SECRET` es igual en ambos sistemas.
- API_TJ tiene cliente `sys_ipj` activo.
- API_TJ tiene llave pública de Sys_IPJ con `kid = SYS_IPJ_JWT_KID`.
- `SYS_IPJ_PRIVATE_KEY_PATH` existe y es legible.
- Queue worker activo.

Acción:

```txt
Admin Sys_IPJ → Integraciones → API_TJ → disparar sync manual
```

Esperado en Sys_IPJ:

- Se crea registro en `integration_sync_runs`.
- Se crean registros en `integration_sync_items`.
- Los beneficiarios con tarjeta/folio válido se envían.
- Los beneficiarios sin folio válido quedan como `skipped`.

Esperado en API_TJ:

- Se insertan/actualizan registros en `cardholders_sync`.
- Se registra auditoría `SYS_IPJ_TO_API_TJ`.

Criterio PASS:

```txt
HTTP 2xx desde API_TJ.
integration_sync_runs.status in [success, partial].
cardholders_sync contiene los hashes enviados.
```

### A2. Sync con item inválido

Simulación:

- Forzar un item sin `tarjeta_numero` o con `curp_hash` inválido.

Esperado:

- API_TJ no debe insertar item inválido.
- Sys_IPJ debe auditarlo como `skipped`, `rejected` o `error` según respuesta recibida.

Observación QA:

- Si API_TJ no devuelve `results` por índice, Sys_IPJ puede marcar todos los items 2xx como `accepted`. Esto debe tratarse como riesgo de auditoría, no como bloqueo de conectividad.

### A3. Conflicto por tarjeta

Simulación:

- API_TJ ya tiene `tarjeta_numero = TJ-QA-0001` asociada a un `curp_hash` diferente.
- Sys_IPJ manda la misma tarjeta con otro hash.

Esperado:

- API_TJ reporta conflicto.
- Sys_IPJ debe dejar evidencia en `integration_sync_items`.

Riesgo actual:

- Si API_TJ solo responde conteos agregados, Sys_IPJ no puede atribuir conflicto a un item específico.

## Suite B. Inbound API_TJ → Sys_IPJ

### B1. Push válido de staging

Precondiciones:

- Sys_IPJ tiene cliente `api_tj` activo en `integration_clients`.
- Sys_IPJ tiene llave pública de API_TJ en `integration_client_keys`.
- JWT API_TJ usa:
  - `iss = api_tj`
  - `aud = sys_ipj`
  - `scope = beneficiarios.staging.push`
  - `kid = api_tj-current`
- Existe usuario técnico `API_TJ_INTEGRATION_USER_EMAIL`.

Acción:

```txt
API_TJ POST /api/v1/integrations/api-tj/staging/accept
```

Esperado:

```json
{
  "accepted": true,
  "status": "created",
  "external_request_id": "USI-QA-0001",
  "beneficiario_id": "<uuid>"
}
```

Validar en DB:

```sql
SELECT status, response_code
FROM integration_inbound_requests
WHERE external_request_id = 'USI-QA-0001';

SELECT id, created_by
FROM beneficiarios
WHERE curp = 'MOCJ050521MSPNRL01';

SELECT *
FROM domicilios
WHERE beneficiario_id = '<beneficiario_id>';
```

Criterio PASS:

- `integration_inbound_requests.status = accepted`.
- Se crea beneficiario oficial.
- Se crea domicilio.
- `created_by` corresponde al usuario técnico.
- No se agregan metadatos de integración en `beneficiarios`.

### B2. Push sin JWT

Acción:

```txt
POST /api/v1/integrations/api-tj/staging/accept sin Authorization
```

Esperado:

```json
{
  "accepted": false,
  "status": "unauthorized",
  "message": "Token de integracion invalido"
}
```

Criterio PASS:

- HTTP 401.
- No se crea beneficiario.

### B3. Push con scope incorrecto

Token:

```txt
scope = beneficiarios.create
```

Esperado:

```txt
HTTP 403
status = forbidden
```

Criterio PASS:

- Sys_IPJ bloquea el request.
- No se crea beneficiario.

### B4. Push con payload sin `source`

Payload:

```json
{
  "external_request_id": "USI-QA-0002",
  "beneficiario": {}
}
```

Esperado:

```txt
HTTP 422
status = validation_error
```

Criterio PASS:

- Se registra inbound rechazado si trae `external_request_id`.

### B5. Push duplicado idempotente

Acción:

- Enviar dos veces el mismo `external_request_id` con el mismo payload.

Esperado segundo request:

```json
{
  "accepted": true,
  "status": "already_processed"
}
```

Criterio PASS:

- No se crea beneficiario duplicado.
- `integration_inbound_requests` mantiene el request original.

### B6. Push con mismo `external_request_id` y payload distinto

Acción:

- Enviar `external_request_id = USI-QA-0003` con payload A.
- Reenviar `USI-QA-0003` con payload B.

Esperado:

```txt
HTTP 409
status = conflict
```

Criterio PASS:

- No se sobreescribe `request_hash`.
- No se sobreescribe payload cifrado.
- No se crea beneficiario adicional.

### B7. Push con CURP existente

Acción:

- Enviar staging con CURP ya existente en `beneficiarios`, incluso si está soft-deleted.

Esperado:

```json
{
  "accepted": false,
  "status": "duplicate"
}
```

Criterio PASS:

- No se crea beneficiario nuevo.
- Inbound queda `rejected`.

## Suite C. Seguridad y llaves

### C1. Llave pública API_TJ no registrada

Esperado:

```txt
HTTP 401
```

Criterio PASS:

- Sys_IPJ rechaza tokens cuyo `kid` no esté activo.

### C2. Replay JWT

Acción:

- Reutilizar el mismo JWT con el mismo `jti`.

Esperado:

```txt
Primer request: procesa según payload.
Segundo request: HTTP 401 unauthorized.
```

Criterio PASS:

- Se registra `jti` en `integration_jti_logs`.
- El segundo intento se bloquea.

### C3. Audiencia incorrecta

Token:

```txt
aud = api_tj
```

Esperado en inbound Sys_IPJ:

```txt
HTTP 401
```

Criterio PASS:

- Sys_IPJ solo acepta audiencia `sys_ipj`.

## Suite D. Auditoría

### D1. Auditoría outbound

Validar:

```sql
SELECT * FROM integration_sync_runs ORDER BY created_at DESC LIMIT 1;
SELECT * FROM integration_sync_items WHERE sync_run_id = '<run_id>';
```

Criterio PASS:

- Hay corrida.
- Hay items.
- Hay conteos coherentes.

### D2. Auditoría inbound

Validar:

```sql
SELECT source_system, external_request_id, status, response_code, error_message
FROM integration_inbound_requests
ORDER BY received_at DESC
LIMIT 10;
```

Criterio PASS:

- Cada request externo con `external_request_id` queda auditado.
- Los inválidos quedan `rejected`.
- Los fallos controlados quedan `failed`.

## Resultado QA esperado antes de conexión real

| Suite | Resultado requerido |
|---|---|
| A1 Sync exitoso | PASS |
| A2 Item inválido | PASS o WARN documentado |
| A3 Conflicto tarjeta | PASS o WARN documentado |
| B1 Push válido | PASS |
| B2 Sin JWT | PASS |
| B3 Scope incorrecto | PASS |
| B4 Sin source | PASS |
| B5 Idempotencia repetida | PASS |
| B6 Payload distinto mismo ID | PASS |
| B7 CURP duplicada | PASS |
| C1 Llave no registrada | PASS |
| C2 Replay JWT | PASS |
| C3 Audiencia incorrecta | PASS |
| D1 Auditoría outbound | PASS |
| D2 Auditoría inbound | PASS |

## Decisión GO / NO GO

### GO parcial

Permitido si:

- Sys_IPJ → API_TJ sync funciona.
- Unidad Informática → API_TJ lookup/staging funciona.
- API_TJ → Sys_IPJ push sigue desactivado hasta corregir contrato saliente.

### GO completo

Permitido solo si:

- API_TJ manda `scope = beneficiarios.staging.push`.
- API_TJ manda `source = api_tj`.
- API_TJ manda `beneficiario` como objeto principal.
- Sys_IPJ crea beneficiario y domicilio.
- La auditoría queda en ambos sistemas.

### NO GO

Cualquier caso:

- `CURP_HASH_SECRET` distinto.
- Llaves públicas no cargadas.
- Queue worker inactivo para outbound.
- API_TJ usa `scope = beneficiarios.create` en push hacia Sys_IPJ.
- API_TJ omite `source = api_tj`.
- Producción no tiene backup reciente.

## Pendientes detectados para compatibilidad completa

1. Corregir en API_TJ el scope saliente hacia Sys_IPJ: `beneficiarios.staging.push`.
2. Corregir en API_TJ el body saliente: agregar `source = api_tj` y quitar `records` como campo operativo.
3. Agregar tests de contrato para `sysIpjClient`.
4. Mejorar respuesta de `/api/v1/cardholders/sync` con `results` por índice.
5. Endurecer validación de staging en API_TJ para evitar rechazos tardíos en Sys_IPJ.
6. Agregar en Sys_IPJ comando operativo para cargar/rotar llave pública de API_TJ.
