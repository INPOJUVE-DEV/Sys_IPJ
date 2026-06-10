# Flujo de sincronización de padrón mínimo

Este documento describe el flujo operativo para sincronizar desde Sys_IPJ hacia API_TJ un padrón mínimo de beneficiarios.

## Objetivo

Permitir que API_TJ valide elegibilidad y activación digital sin replicar el padrón completo de Sys_IPJ.

El flujo debe enviar únicamente datos mínimos:

- `curp_hash`
- `curp_masked`
- `tarjeta_numero`
- `status`
- `synced_at`

## Principio crítico

La sincronización no modifica la tabla `beneficiarios`.

Sys_IPJ puede leer datos del padrón oficial para construir el payload, pero cualquier estado de sincronización debe registrarse en tablas separadas.

## Sistemas participantes

| Sistema | Rol |
| --- | --- |
| Sys_IPJ | Fuente de verdad y emisor del padrón mínimo. |
| API_TJ | Receptor del padrón mínimo y sistema de validación digital. |
| Administrador Sys_IPJ | Usuario que inicia la sincronización manual. |

## Flujo operativo

```txt
Administrador Sys_IPJ
  ↓ inicia sincronización manual
Sys_IPJ
  ↓ consulta beneficiarios oficiales activos
Sys_IPJ
  ↓ construye payload mínimo
Sys_IPJ
  ↓ firma JWT RS256
API_TJ
  ↓ valida JWT, scope y jti
API_TJ
  ↓ actualiza cardholders_sync
API_TJ
  ↓ responde resultados
Sys_IPJ
  ↓ guarda auditoría en tablas separadas
```

## Paso a paso

### 1. Inicio manual

Un usuario autorizado en Sys_IPJ inicia la sincronización.

Recomendación inicial:

- rol permitido: `admin`;
- acción disponible desde panel administrativo;
- confirmación previa antes de enviar lote.

No automatizar en la primera versión.

### 2. Crear corrida local

Sys_IPJ crea un registro de corrida en tabla separada.

Tabla sugerida:

```txt
integration_sync_runs
```

Datos mínimos:

- `target_system = api_tj`
- `operation = cardholders.sync`
- `status = pending`
- `requested_by`
- `started_at`

### 3. Seleccionar registros

Sys_IPJ selecciona beneficiarios elegibles para sincronización.

Criterios sugeridos:

- beneficiario activo;
- CURP válida;
- tarjeta asociada o folio válido;
- no eliminado;
- datos mínimos completos.

No se debe agregar ninguna columna a `beneficiarios` para marcar elegibilidad.

### 4. Construir payload mínimo

Por cada beneficiario, Sys_IPJ genera:

```json
{
  "curp_hash": "hmac-sha256",
  "curp_masked": "MELR**********06",
  "tarjeta_numero": "TJ-0080",
  "status": "active",
  "synced_at": "2026-06-10T12:00:00-06:00"
}
```

Reglas:

- `curp_hash` se calcula en memoria durante el proceso.
- `curp_hash` no se guarda en `beneficiarios`.
- `curp_masked` se usa para trazabilidad no sensible.
- El payload completo puede guardarse en auditoría separada si está justificado y protegido.

### 5. Firmar request

Sys_IPJ genera un JWT de integración.

Claims mínimos:

```json
{
  "iss": "sys_ipj",
  "sub": "sys_ipj",
  "aud": "api_tj",
  "scope": "cardholders.sync",
  "jti": "uuid-unico",
  "iat": 1713960000,
  "exp": 1713960300
}
```

Debe firmarse con RS256.

### 6. Enviar a API_TJ

Endpoint objetivo en API_TJ:

```txt
POST /api/v1/cardholders/sync
```

Body general:

```json
{
  "sync_id": "SYS-IPJ-2026-06-10-001",
  "source": "sys_ipj",
  "records": []
}
```

### 7. API_TJ valida

API_TJ debe validar:

- firma JWT;
- `kid`;
- `aud`;
- `iss`;
- `exp`;
- `jti` no reutilizado;
- scope `cardholders.sync`;
- payload;
- duplicados internos.

### 8. API_TJ actualiza padrón mínimo

API_TJ actualiza su tabla `cardholders_sync`.

Reglas en API_TJ:

- No persistir CURP en claro.
- Usar `curp_hash` para lookup.
- Usar `tarjeta_numero` para activación.
- Mantener `status` para elegibilidad.

### 9. API_TJ responde resultados

Respuesta esperada:

```json
{
  "accepted": true,
  "sync_id": "SYS-IPJ-2026-06-10-001",
  "total": 100,
  "accepted_count": 98,
  "rejected_count": 2,
  "results": [
    {
      "index": 0,
      "status": "upserted",
      "tarjeta_numero": "TJ-0080"
    }
  ]
}
```

### 10. Sys_IPJ registra resultados

Sys_IPJ actualiza:

- `integration_sync_runs`
- `integration_sync_items`

No actualiza `beneficiarios` con estado de sync.

## Estados recomendados

### Corrida

```txt
pending
running
success
partial
failed
cancelled
```

### Ítem

```txt
pending
sent
accepted
rejected
error
```

## Manejo de errores

### Error de autenticación

API_TJ responde:

```txt
401 Unauthorized
```

Sys_IPJ registra corrida `failed`.

### Error de scope

API_TJ responde:

```txt
403 Forbidden
```

Sys_IPJ registra corrida `failed`.

### Error parcial

API_TJ procesa parte del lote y rechaza algunos registros.

Sys_IPJ registra corrida:

```txt
partial
```

Cada registro rechazado debe tener error separado en `integration_sync_items`.

### Error de red

Sys_IPJ registra:

- request intentado;
- error técnico;
- fecha;
- usuario que inició;
- estado `failed` o `error`.

No reintentar automáticamente en primera versión.

## Lotes

Recomendación inicial:

- tamaño de lote: 100 a 500 registros;
- timeout controlado;
- resumen de resultados por lote;
- sin ejecución automática hasta validar manualmente.

## Reintentos

Primera versión:

- reintento manual desde panel administrativo;
- no usar reintentos automáticos sin idempotencia probada;
- cada reintento crea nueva corrida o registra intento separado.

## Auditoría mínima

Debe quedar registrado:

- usuario que inició;
- fecha de inicio;
- fecha de fin;
- cantidad enviada;
- cantidad aceptada;
- cantidad rechazada;
- sistema destino;
- endpoint destino;
- código de respuesta;
- error general si aplica.

## Checklist de seguridad

Antes de habilitar el flujo:

- [ ] API_TJ tiene llave pública de Sys_IPJ.
- [ ] Sys_IPJ tiene llave privada fuera del repositorio.
- [ ] Scope `cardholders.sync` existe en API_TJ.
- [ ] `CURP_HASH_SECRET` o mecanismo equivalente está definido.
- [ ] No se agrega `curp_hash` a `beneficiarios`.
- [ ] Existen tablas separadas para auditoría de sync.
- [ ] Hay respaldo antes de migraciones.
- [ ] Hay pruebas de payload y firma JWT.

## Fuera de alcance

No incluye:

- automatización nocturna;
- colas;
- interfaz final de administración;
- migraciones concretas;
- implementación de endpoint en API_TJ.

## Resumen

Sys_IPJ envía padrón mínimo. API_TJ lo usa para validación digital. El modelo core de beneficiarios permanece intacto.
