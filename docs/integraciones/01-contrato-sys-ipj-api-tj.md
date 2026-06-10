# Contrato de integración Sys_IPJ ↔ API_TJ

Este documento define el contrato conceptual entre Sys_IPJ y API_TJ. No representa implementación existente hasta que un PR de código lo agregue explícitamente.

## Principios

1. Sys_IPJ es fuente de verdad del padrón oficial.
2. API_TJ es canal digital, padrón mínimo sincronizado y staging temporal.
3. La tabla `beneficiarios` de Sys_IPJ no se modifica para soportar sincronización.
4. Toda comunicación entre sistemas se realiza por API documentada.
5. Ningún sistema externo escribe directo en la base de datos de Sys_IPJ.

## Dirección 1: Sys_IPJ → API_TJ

### Objetivo

Enviar a API_TJ el padrón mínimo necesario para:

- validar elegibilidad;
- saber si una persona ya tiene tarjeta;
- permitir activación digital;
- responder lookup de Unidad de Informática.

### Operación propuesta

```txt
POST API_TJ /api/v1/cardholders/sync
```

Este endpoint vive en API_TJ, no en Sys_IPJ.

### Actor

Sys_IPJ.

### Frecuencia

Manual en primera etapa.

La automatización programada queda fuera de alcance hasta que exista operación estable y trazabilidad.

### Payload mínimo

```json
{
  "sync_id": "SYS-IPJ-2026-06-10-001",
  "source": "sys_ipj",
  "records": [
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

| Campo | Requerido | Descripción |
| --- | --- | --- |
| `sync_id` | Sí | Identificador único de corrida. |
| `source` | Sí | Debe ser `sys_ipj`. |
| `records` | Sí | Arreglo de registros mínimos. |
| `curp_hash` | Sí | HMAC-SHA-256 de CURP normalizada. |
| `curp_masked` | Sí | CURP enmascarada para auditoría no sensible. |
| `tarjeta_numero` | Sí | Folio o número de tarjeta. |
| `status` | Sí | Estado operativo del registro. |
| `synced_at` | Sí | Fecha de generación del registro. |

### Estados permitidos

```txt
active
inactive
blocked
```

### Reglas de Sys_IPJ

- No persistir `curp_hash` en `beneficiarios`.
- No persistir estado de API_TJ en `beneficiarios`.
- No modificar `tarjetas` para registrar sincronización.
- Registrar la corrida en tabla separada, por ejemplo `integration_sync_runs`.
- Registrar cada resultado en tabla separada, por ejemplo `integration_sync_items`.

### Respuesta esperada de API_TJ

```json
{
  "accepted": true,
  "sync_id": "SYS-IPJ-2026-06-10-001",
  "total": 1,
  "accepted_count": 1,
  "rejected_count": 0,
  "results": [
    {
      "index": 0,
      "status": "upserted",
      "tarjeta_numero": "TJ-0080"
    }
  ]
}
```

## Dirección 2: API_TJ → Sys_IPJ

### Objetivo

Enviar a Sys_IPJ un expediente temporal aprobado en API_TJ para alta oficial.

### Operación propuesta

```txt
POST Sys_IPJ /api/v1/integrations/api-tj/staging/accept
```

Este endpoint vive en Sys_IPJ.

### Actor

API_TJ, después de acción manual de usuario autorizado.

### Payload propuesto

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

### Campos mínimos

| Campo | Requerido | Descripción |
| --- | --- | --- |
| `external_request_id` | Sí | ID único del staging en API_TJ. |
| `source` | Sí | Debe ser `api_tj`. |
| `submitted_by` | Sí | Usuario interno que aprobó el envío. |
| `beneficiario` | Sí | Expediente completo. |
| `beneficiario.curp` | Sí | CURP para validación oficial. |
| `beneficiario.domicilio` | Sí | Domicilio completo. |

### Reglas de Sys_IPJ al recibir

Sys_IPJ debe:

1. Autenticar el sistema emisor.
2. Validar scope de integración.
3. Validar idempotencia por `external_request_id`.
4. Auditar request completo en tabla separada.
5. Validar estructura del expediente.
6. Validar CURP.
7. Validar seccional y municipio.
8. Validar duplicados contra padrón oficial.
9. Crear beneficiario oficial solo si procede.
10. Crear domicilio asociado.
11. Responder resultado estructurado.

Sys_IPJ no debe:

- Guardar `external_request_id` en `beneficiarios`.
- Guardar `source = api_tj` en `beneficiarios`.
- Usar `created_by = null`.
- Saltarse reglas de captura por tratarse de API_TJ.

### Respuesta exitosa propuesta

```json
{
  "accepted": true,
  "status": "created",
  "external_request_id": "API-TJ-STG-2026-0001",
  "beneficiario_id": "uuid",
  "message": "Beneficiario creado correctamente"
}
```

### Respuesta por duplicado

```json
{
  "accepted": false,
  "status": "duplicate",
  "external_request_id": "API-TJ-STG-2026-0001",
  "message": "Ya existe un beneficiario con la CURP proporcionada"
}
```

### Respuesta por validación

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

## Idempotencia

Todo request externo debe incluir un identificador único.

Para API_TJ hacia Sys_IPJ:

```txt
external_request_id
```

Regla:

- Si Sys_IPJ ya procesó ese `external_request_id`, debe devolver la misma respuesta lógica o un estado `already_processed`.
- No debe crear duplicados.

## Auditoría

Sys_IPJ debe registrar solicitudes externas en tabla separada.

Tabla sugerida:

```txt
integration_inbound_requests
```

Datos mínimos:

- sistema origen;
- ID externo;
- operación;
- hash de payload;
- payload recibido o payload cifrado si contiene datos sensibles;
- código HTTP de respuesta;
- estado;
- error;
- fechas.

## Seguridad

La comunicación debe usar:

- HTTPS;
- JWT firmado, preferentemente RS256;
- scopes por sistema;
- expiración corta;
- `jti` anti-replay;
- auditoría;
- rate limit.

Detalle en:

```txt
docs/integraciones/02-seguridad-jwt-rs256.md
```

## Fuera de alcance de este contrato

No se define aquí:

- migraciones concretas;
- controladores Laravel;
- servicios PHP;
- jobs o colas;
- implementación de Auth0;
- automatización nocturna.

Eso corresponde al documento de implementación paso a paso.

## Resumen

Sys_IPJ y API_TJ se conectan por contratos explícitos.

La integración no modifica el core de beneficiarios; lo rodea con auditoría, validación e idempotencia.
