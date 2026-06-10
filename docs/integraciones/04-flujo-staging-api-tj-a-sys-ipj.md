# Flujo de staging API_TJ hacia Sys_IPJ

Este documento describe cómo un expediente temporal creado en API_TJ puede convertirse en beneficiario oficial dentro de Sys_IPJ.

## Objetivo

Permitir que API_TJ envíe a Sys_IPJ expedientes completos previamente revisados y aprobados, sin que API_TJ escriba directamente en la base de datos de Sys_IPJ.

## Principio crítico

API_TJ no es sistema de alta oficial.

El expediente enviado desde API_TJ debe considerarse una solicitud de alta. Sys_IPJ valida, acepta o rechaza.

## Sistemas participantes

| Sistema | Rol |
| --- | --- |
| Unidad de Informática | Consulta lookup y crea staging en API_TJ si no existe. |
| API_TJ | Guarda staging cifrado, permite revisión y envía expediente aprobado. |
| Sys_IPJ | Valida y crea beneficiario oficial si procede. |
| Admin API_TJ | Usuario que aprueba el envío. |

## Flujo general

```txt
Unidad de Informática
  ↓ lookup por CURP en API_TJ
API_TJ
  ↓ si no existe, recibe expediente completo
API_TJ
  ↓ guarda staging cifrado como pending
Admin API_TJ
  ↓ revisa y aprueba envío
API_TJ
  ↓ firma JWT RS256
Sys_IPJ
  ↓ valida JWT, scope, payload e idempotencia
Sys_IPJ
  ↓ crea beneficiario oficial si procede
API_TJ
  ↓ actualiza estado de staging
```

## Paso a paso

### 1. Lookup inicial

Unidad de Informática consulta API_TJ:

```txt
POST /api/v1/cardholders/lookup
```

Si API_TJ responde `200`, no se crea staging.

Si API_TJ responde `404`, Unidad de Informática puede enviar expediente completo a staging.

### 2. Creación de staging en API_TJ

Endpoint en API_TJ:

```txt
POST /api/v1/beneficiarios-staging
```

API_TJ debe:

- validar estructura;
- calcular `curp_hash`;
- generar `curp_masked`;
- cifrar payload sensible;
- guardar estado `pending`;
- no crear beneficiario oficial.

### 3. Revisión interna en API_TJ

Un usuario autorizado revisa el staging.

Debe verificar:

- datos personales;
- CURP;
- domicilio;
- seccional;
- teléfono;
- duplicidad aparente;
- consistencia del expediente.

### 4. Aprobación manual

Un usuario autorizado en API_TJ marca el staging como aprobado para envío.

Regla:

- La aprobación debe quedar auditada.
- Debe registrarse usuario, fecha y acción.

### 5. API_TJ firma solicitud

API_TJ genera JWT RS256 con scope:

```txt
beneficiarios.staging.push
```

Claims mínimos:

```json
{
  "iss": "api_tj",
  "sub": "api_tj",
  "aud": "sys_ipj",
  "scope": "beneficiarios.staging.push",
  "jti": "uuid-unico",
  "iat": 1713960000,
  "exp": 1713960300
}
```

### 6. API_TJ envía expediente a Sys_IPJ

Endpoint propuesto en Sys_IPJ:

```txt
POST /api/v1/integrations/api-tj/staging/accept
```

Payload propuesto:

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

### 7. Sys_IPJ valida integración

Sys_IPJ debe validar:

- token presente;
- firma RS256;
- `kid` conocido;
- `aud = sys_ipj`;
- `iss = api_tj`;
- `scope = beneficiarios.staging.push`;
- `jti` no reutilizado;
- rate limit;
- IP allowlist si aplica.

### 8. Sys_IPJ registra inbound request

Antes de procesar el expediente, Sys_IPJ debe registrar la solicitud en tabla separada.

Tabla sugerida:

```txt
integration_inbound_requests
```

Campos mínimos:

- `source_system = api_tj`
- `external_request_id`
- `operation = beneficiarios.staging.accept`
- `request_hash`
- `status = received`
- `received_at`

No guardar estos metadatos en `beneficiarios`.

### 9. Sys_IPJ valida idempotencia

Regla:

- Si `external_request_id` ya fue procesado, no crear otro beneficiario.
- Si el request previo fue exitoso, devolver estado `already_processed`.
- Si el request previo falló, permitir reproceso controlado según política.

### 10. Sys_IPJ valida expediente

Validaciones mínimas:

- campos obligatorios;
- formato CURP;
- CURP no duplicada en padrón oficial;
- fecha de nacimiento válida;
- sexo permitido;
- discapacidad boolean;
- teléfono válido;
- seccional existente;
- municipio consistente con seccional;
- tarjeta/folio disponible si aplica;
- reglas institucionales vigentes.

### 11. Sys_IPJ crea beneficiario oficial

Si todo es válido, Sys_IPJ crea:

- beneficiario;
- domicilio;
- relaciones necesarias;
- tarjeta o asociación de tarjeta si aplica.

Reglas:

- `created_by` debe ser un usuario institucional o usuario técnico definido, no `null`.
- No guardar `source_system` en `beneficiarios`.
- No guardar `external_request_id` en `beneficiarios`.
- La relación con el request externo queda en `integration_inbound_requests`.

### 12. Sys_IPJ responde resultado

Respuesta exitosa:

```json
{
  "accepted": true,
  "status": "created",
  "external_request_id": "API-TJ-STG-2026-0001",
  "beneficiario_id": "uuid",
  "message": "Beneficiario creado correctamente"
}
```

Duplicado:

```json
{
  "accepted": false,
  "status": "duplicate",
  "external_request_id": "API-TJ-STG-2026-0001",
  "message": "Ya existe un beneficiario con la CURP proporcionada"
}
```

Validación:

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

### 13. API_TJ actualiza staging

API_TJ debe actualizar su staging según respuesta:

| Respuesta Sys_IPJ | Estado API_TJ sugerido |
| --- | --- |
| `created` | `accepted` |
| `already_processed` | `accepted` |
| `duplicate` | `rejected` |
| `validation_error` | `rejected` |
| error técnico | `error` |

## Estados recomendados en Sys_IPJ

Para inbound requests:

```txt
received
processing
accepted
rejected
failed
already_processed
```

## Errores y respuestas HTTP

| Código | Caso |
| --- | --- |
| `201` | Beneficiario creado. |
| `200` | Ya procesado o respuesta idempotente. |
| `400` | Payload mal formado. |
| `401` | Token ausente o inválido. |
| `403` | Scope insuficiente. |
| `409` | Duplicado o conflicto de negocio. |
| `422` | Error de validación. |
| `429` | Rate limit. |
| `500` | Error interno. |

## Auditoría mínima

Sys_IPJ debe conservar:

- sistema origen;
- usuario que aprobó en API_TJ;
- `external_request_id`;
- hash de payload;
- estado final;
- respuesta enviada;
- errores;
- timestamp de recepción;
- timestamp de procesamiento.

API_TJ debe conservar:

- usuario que aprobó;
- timestamp de envío;
- respuesta de Sys_IPJ;
- estado final del staging;
- error si aplica.

## Checklist antes de implementar

- [ ] PR documental aprobado.
- [ ] Endpoint final acordado.
- [ ] Tabla inbound definida sin modificar `beneficiarios`.
- [ ] Usuario técnico o mecanismo de `created_by` definido.
- [ ] Validaciones de negocio alineadas con captura manual.
- [ ] JWT RS256 configurado.
- [ ] Scope `beneficiarios.staging.push` definido.
- [ ] Idempotencia por `external_request_id` definida.
- [ ] Pruebas de duplicados definidas.
- [ ] Respaldo antes de migraciones.

## Antipatrones prohibidos

No implementar:

- `created_by = null`.
- columnas `source_system` en `beneficiarios`.
- columnas `external_request_id` en `beneficiarios`.
- observer global para detectar origen API_TJ.
- inserciones directas desde API_TJ a la base de Sys_IPJ.
- endpoint público sin firma sistema-a-sistema.

## Resumen

API_TJ puede solicitar alta oficial. Sys_IPJ decide y audita.

El core de beneficiarios permanece limpio.
