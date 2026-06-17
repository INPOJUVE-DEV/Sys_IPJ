# Cambios de compatibilidad API_TJ → Sys_IPJ

## Objetivo

Documentar los cambios requeridos en `API_TJ` para cumplir el contrato inbound implementado en `Sys_IPJ`.

Este documento no modifica código. Sirve como guía de implementación para el siguiente PR.

## Resumen ejecutivo

El flujo `Sys_IPJ → API_TJ` está funcionalmente alineado para recibir padrón mínimo con `items` y JWT `cardholders.sync`.

El flujo `API_TJ → Sys_IPJ` requiere ajustes antes de habilitar conexión real:

1. Cambiar scope saliente a `beneficiarios.staging.push`.
2. Enviar `source = api_tj` en el body.
3. Usar `beneficiario` como objeto principal.
4. Evitar depender de `records` para el contrato con Sys_IPJ.
5. Agregar tests de contrato para `sysIpjClient`.
6. Mejorar respuesta granular de `/cardholders/sync`.
7. Endurecer validación de staging para reducir rechazos tardíos.

## 1. Cambio obligatorio: scope saliente

Archivo:

```txt
src/services/sysIpjClient.js
.env.example
```

Actual problemático:

```env
API_TJ_TO_SYS_IPJ_SCOPE=beneficiarios.create
```

Esperado:

```env
API_TJ_TO_SYS_IPJ_SCOPE=beneficiarios.staging.push
```

Motivo:

`Sys_IPJ` protege el endpoint inbound con scope:

```txt
beneficiarios.staging.push
```

Si API_TJ firma con `beneficiarios.create`, Sys_IPJ debe responder `403 forbidden`.

## 2. Cambio obligatorio: body saliente hacia Sys_IPJ

Archivo:

```txt
src/services/sysIpjClient.js
```

Actual problemático:

```js
body: JSON.stringify({
  external_request_id: externalRequestId,
  beneficiario: payload,
  records: [payload]
})
```

Esperado:

```js
body: JSON.stringify({
  external_request_id: externalRequestId,
  source: 'api_tj',
  submitted_by: {
    system: 'api_tj'
  },
  beneficiario: payload
})
```

Motivo:

`Sys_IPJ` valida:

```txt
source = api_tj
beneficiario = objeto requerido
```

Si `source` falta, Sys_IPJ debe responder `422 validation_error`.

`records` no forma parte del contrato inbound actual de Sys_IPJ. Puede eliminarse o dejarse solo como campo legacy documentado, pero no debe ser requerido por Sys_IPJ.

## 3. Cambio obligatorio: URL de push

Variable:

```env
SYS_IPJ_PUSH_URL=https://<sys-ipj-url>/api/v1/integrations/api-tj/staging/accept
```

No usar rutas legacy ni rutas de cache.

No usar:

```txt
/api/v1/beneficiarios/cache
/api/api-tj/inbound
/api/integrations/api-tj/staging/accept
```

## 4. Tests requeridos para `sysIpjClient`

Crear o ajustar pruebas unitarias/contractuales para validar que `pushBeneficiario`:

- Lee `API_TJ_TO_SYS_IPJ_PRIVATE_KEY_PATH`.
- Firma JWT RS256.
- Usa `kid = API_TJ_TO_SYS_IPJ_JWT_KID`.
- Usa `iss = api_tj`.
- Usa `aud = sys_ipj`.
- Usa `scope = beneficiarios.staging.push`.
- Envía header `Authorization: Bearer <token>`.
- Envía header `Idempotency-Key = external_request_id`.
- Envía body con `external_request_id`, `source`, `submitted_by` y `beneficiario`.
- No depende de `records`.

## 5. Test recomendado: mock de Sys_IPJ

Simular servidor mock que valide:

```txt
POST /api/v1/integrations/api-tj/staging/accept
```

Casos:

| Caso | Mock responde | API_TJ debe |
|---|---:|---|
| Push válido | 201 created | Marcar staging `accepted` |
| Scope incorrecto | 403 forbidden | Marcar staging `error` o `rejected` según política |
| Body sin source | 422 validation_error | Marcar staging `rejected` |
| Duplicate CURP | 409 duplicate | Marcar staging `rejected` |
| Timeout | error | Marcar staging `error` |

## 6. Mejora recomendada: respuesta granular de `/cardholders/sync`

Problema actual:

API_TJ responde conteos agregados:

```json
{
  "processed": 10,
  "inserted": 8,
  "updated": 1,
  "skipped": 1,
  "conflict": 0
}
```

Esto no permite que Sys_IPJ marque cada `integration_sync_item` con exactitud.

Respuesta recomendada:

```json
{
  "accepted": true,
  "status": "partial",
  "processed": 10,
  "inserted": 8,
  "updated": 1,
  "skipped": 1,
  "conflict": 0,
  "results": [
    {
      "index": 0,
      "status": "accepted",
      "action": "inserted"
    },
    {
      "index": 1,
      "status": "accepted",
      "action": "updated"
    },
    {
      "index": 2,
      "status": "skipped",
      "message": "curp_hash invalido"
    },
    {
      "index": 3,
      "status": "conflict",
      "message": "tarjeta_numero ya existe con otro curp_hash"
    }
  ]
}
```

Mapeo recomendado:

| Condición | `status` por item |
|---|---|
| Insert correcto | `accepted` |
| Update correcto | `accepted` |
| Payload inválido | `skipped` |
| Tarjeta con otro hash | `conflict` |
| Error inesperado por item | `error` |

## 7. Mejora recomendada: validación staging alineada con Sys_IPJ

API_TJ debe rechazar antes de guardar staging los casos que Sys_IPJ rechazará de forma segura.

Reglas mínimas:

```txt
curp: CURP válida
nombre: requerido
apellido_paterno: requerido
apellido_materno: requerido
fecha_nacimiento: fecha válida
sexo: M, F o X
discapacidad: boolean
id_ine: requerido
telefono: exactamente 10 dígitos
domicilio.calle: requerido
domicilio.numero_ext: requerido
domicilio.colonia: requerido
domicilio.municipio_id: entero positivo
domicilio.codigo_postal: requerido
domicilio.seccional: requerido
```

Objetivo:

- Reducir staging que después se rechaza en Sys_IPJ.
- Mejorar feedback operativo a Unidad Informática.
- Mantener payload cifrado solo cuando el expediente es mínimamente válido.

## 8. Matriz de compatibilidad esperada

| Flujo | Estado después de cambios |
|---|---|
| Sys_IPJ → API_TJ sync | Compatible |
| Unidad Informática → API_TJ lookup | Compatible |
| Unidad Informática → API_TJ staging | Compatible |
| API_TJ → Sys_IPJ push | Compatible |
| Replay JWT | Bloqueado |
| Same external_request_id same payload | Idempotente |
| Same external_request_id different payload | Conflict |
| CURP duplicada en Sys_IPJ | Rejected/duplicate |

## 9. Criterios de aceptación del PR

El PR de compatibilidad debe demostrar:

```txt
[PASS] npm test
[PASS] sysIpjClient firma JWT con scope beneficiarios.staging.push
[PASS] sysIpjClient manda source = api_tj
[PASS] sysIpjClient manda beneficiario como objeto principal
[PASS] SYS_IPJ_PUSH_URL documenta ruta /api/v1/integrations/api-tj/staging/accept
[PASS] /cardholders/sync mantiene conteos agregados
[PASS] /cardholders/sync agrega results por índice
[PASS] staging rechaza teléfono inválido
[PASS] staging rechaza sexo inválido
[PASS] staging rechaza municipio_id inválido
```

## 10. Orden sugerido de implementación

1. Corregir `.env.example`.
2. Corregir `sysIpjClient.js`.
3. Agregar tests de contrato para `sysIpjClient`.
4. Mejorar respuesta de `/cardholders/sync`.
5. Agregar tests de sync granular.
6. Endurecer validación staging.
7. Agregar tests de staging inválido.
8. Ejecutar smoke test con Sys_IPJ QA.

## 11. NO GO

No habilitar push real API_TJ → Sys_IPJ si:

- `API_TJ_TO_SYS_IPJ_SCOPE` sigue en `beneficiarios.create`.
- El body no incluye `source = api_tj`.
- `SYS_IPJ_PUSH_URL` no apunta a `/api/v1/integrations/api-tj/staging/accept`.
- No existe llave pública activa de API_TJ en Sys_IPJ.
- No existe llave pública de Sys_IPJ en API_TJ.
- `CURP_HASH_SECRET` no coincide.
