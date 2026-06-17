# Contrato de integracion Sys_IPJ ↔ API_TJ

Este documento define el contrato operativo actual entre `Sys_IPJ` y `API_TJ`.

## Principios

1. `Sys_IPJ` es fuente de verdad del padron oficial.
2. `API_TJ` es canal digital y staging temporal.
3. La integracion no invade tablas core de `Sys_IPJ`.
4. Toda comunicacion ocurre por API documentada.
5. Ningun sistema externo escribe directo en la base de datos de `Sys_IPJ`.

## Direccion 1: Sys_IPJ -> API_TJ

### Operacion

```txt
POST API_TJ /api/v1/cardholders/sync
```

### Payload minimo

```json
{
  "sync_id": "SYS-IPJ-2026-06-10-001",
  "items": [
    {
      "curp_hash": "hmac-sha256",
      "curp_masked": "MELR**********06",
      "tarjeta_numero": "TJ-0080",
      "status": "active",
      "synced_at": "2026-06-10T12:00:00-06:00"
    }
  ]
}
```

### Campos

| Campo | Requerido | Descripcion |
| --- | --- | --- |
| `sync_id` | Si | Identificador unico de corrida. |
| `items` | Si | Arreglo de registros minimos. |
| `curp_hash` | Si | HMAC-SHA-256 de CURP normalizada. |
| `curp_masked` | Si | CURP enmascarada para auditoria no sensible. |
| `tarjeta_numero` | Si | Folio de tarjeta valido. |
| `status` | Si | Estado operativo del registro. |
| `synced_at` | Si | Fecha de generacion del registro. |

### Reglas de Sys_IPJ

- No persistir `curp_hash` en `beneficiarios`.
- No persistir estado de `API_TJ` en `beneficiarios`.
- No modificar `tarjetas` para registrar sincronizacion.
- Registrar corridas en `integration_sync_runs`.
- Registrar resultados por item en `integration_sync_items`.

### Respuesta esperada de API_TJ

```json
{
  "accepted": true,
  "status": "partial",
  "results": [
    {
      "index": 0,
      "status": "accepted",
      "action": "inserted"
    }
  ]
}
```

## Direccion 2: API_TJ -> Sys_IPJ

### Operacion

```txt
POST Sys_IPJ /api/v1/integrations/api-tj/staging/accept
```

### Payload

```json
{
  "external_request_id": "API-TJ-STG-2026-0001",
  "source": "api_tj",
  "submitted_by": {
    "system": "api_tj",
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

### Campos minimos

| Campo | Requerido | Descripcion |
| --- | --- | --- |
| `external_request_id` | Si | ID unico del staging en API_TJ. |
| `source` | Si | Debe ser `api_tj`. |
| `submitted_by` | No | Contexto opcional del aprobador. Si se envia, debe ser objeto. |
| `submitted_by.system` | No | Sistema origen del aprobador. |
| `submitted_by.user_id` | No | Identificador del aprobador en API_TJ. |
| `submitted_by.name` | No | Nombre del aprobador en API_TJ. |
| `beneficiario` | Si | Expediente completo. |
| `beneficiario.curp` | Si | CURP para validacion oficial. |
| `beneficiario.domicilio` | Si | Domicilio completo. |

### Reglas de Sys_IPJ al recibir

`Sys_IPJ` debe:

1. Autenticar el sistema emisor.
2. Validar scope de integracion.
3. Validar idempotencia por `external_request_id`.
4. Auditar el request en `integration_inbound_requests`.
5. Validar estructura del expediente.
6. Validar CURP, seccional y municipio.
7. Validar duplicados contra el padron oficial.
8. Crear beneficiario y domicilio solo si procede.
9. Responder resultado estructurado.

`Sys_IPJ` no debe:

- Guardar `external_request_id` en `beneficiarios`.
- Guardar `source = api_tj` en `beneficiarios`.
- Hacer `created_by = null`.
- Saltarse reglas core por tratarse de `API_TJ`.

### Respuestas esperadas

Creado:

```json
{
  "accepted": true,
  "status": "created",
  "external_request_id": "API-TJ-STG-2026-0001",
  "beneficiario_id": "uuid"
}
```

Ya procesado:

```json
{
  "accepted": true,
  "status": "already_processed",
  "external_request_id": "API-TJ-STG-2026-0001"
}
```

Duplicado:

```json
{
  "accepted": false,
  "status": "duplicate",
  "external_request_id": "API-TJ-STG-2026-0001"
}
```

Validacion:

```json
{
  "accepted": false,
  "status": "validation_error",
  "external_request_id": "API-TJ-STG-2026-0001"
}
```

## Idempotencia

La llave operativa inbound es:

```txt
source_system + external_request_id
```

Reglas:

- mismo `external_request_id` + mismo payload -> `already_processed`;
- mismo `external_request_id` + payload distinto -> `conflict`;
- no crear duplicados.

## Auditoria

`Sys_IPJ` registra la integracion en tablas separadas:

- `integration_sync_runs`
- `integration_sync_items`
- `integration_inbound_requests`
- `integration_jti_logs`

## Seguridad

La comunicacion usa:

- HTTPS;
- JWT RS256;
- scopes por sistema;
- expiracion corta;
- `jti` anti-replay;
- auditoria;
- rate limit.

Detalle adicional:

```txt
docs/integraciones/02-seguridad-jwt-rs256.md
```
