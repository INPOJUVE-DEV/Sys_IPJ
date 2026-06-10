# Plan de implementación paso a paso

Este documento define el orden recomendado para implementar la sincronización entre Sys_IPJ, API_TJ y Unidad de Informática.

## Objetivo

Implementar integración entre sistemas sin modificar la tabla `beneficiarios` ni el modelo core `Beneficiario`.

La implementación debe seguir este orden:

1. documentación aprobada;
2. migraciones aisladas;
3. servicios internos;
4. endpoints controlados;
5. pruebas;
6. validación local;
7. validación en staging;
8. despliegue controlado.

## Restricción obligatoria

No modificar:

- `beneficiarios`;
- modelo `Beneficiario` para metadatos de integración;
- `created_by` para hacerlo nullable por integración;
- observers globales sobre `Beneficiario` para sincronización.

Si aparece una necesidad de estado, debe resolverse con tablas separadas.

## Fase 0 — Preparación

### 0.1 Confirmar ramas

Crear una rama específica para implementación:

```bash
git checkout main
git pull origin main
git checkout -b feature/integracion-api-tj-sys-ipj
```

### 0.2 Confirmar respaldo

Antes de migraciones:

```bash
mysqldump -h HOST -u USER -p DB_NAME > backup_sys_ipj_pre_integracion.sql
```

### 0.3 Confirmar configuración externa

Validar:

- URL base de API_TJ;
- audiencia esperada de API_TJ;
- audiencia esperada de Sys_IPJ;
- llaves públicas/privadas;
- scopes;
- ambiente local/staging.

## Fase 1 — Migraciones aisladas en Sys_IPJ

Crear tablas nuevas. No modificar tablas core.

### 1.1 `integration_sync_runs`

Uso:

- registrar corridas de Sys_IPJ hacia API_TJ.

Campos mínimos:

```txt
id
system_target
operation
status
requested_by
started_at
finished_at
total_items
success_count
failed_count
error_message
created_at
updated_at
```

### 1.2 `integration_sync_items`

Uso:

- registrar resultado por beneficiario enviado a API_TJ.

Campos mínimos:

```txt
id
sync_run_id
beneficiario_id
payload_hash
status
response_code
response_body
error_message
created_at
updated_at
```

Puede referenciar `beneficiarios.id`, pero no modifica `beneficiarios`.

### 1.3 `integration_inbound_requests`

Uso:

- registrar requests de API_TJ hacia Sys_IPJ.

Campos mínimos:

```txt
id
source_system
external_request_id
operation
request_hash
request_payload
status
response_code
response_body
error_message
received_at
processed_at
created_at
updated_at
```

### 1.4 `integration_jti_logs`

Uso:

- prevenir replay de JWT.

Campos mínimos:

```txt
id
issuer
jti
expires_at
created_at
```

Restricción:

```txt
UNIQUE(issuer, jti)
```

## Fase 2 — Configuración de entorno

Agregar variables sin meter llaves privadas al repo.

### 2.1 Sys_IPJ como emisor hacia API_TJ

```env
API_TJ_BASE_URL=https://api-tj.example.com
API_TJ_AUDIENCE=api_tj
SYS_IPJ_JWT_KID=sys_ipj-current
SYS_IPJ_PRIVATE_KEY_PATH=storage/app/keys/sys_ipj_private.pem
SYS_IPJ_INTEGRATION_SCOPE=cardholders.sync
CURP_HASH_SECRET=definir-secreto-largo
```

### 2.2 Sys_IPJ como receptor desde API_TJ

```env
SYS_IPJ_AUDIENCE=sys_ipj
API_TJ_JWT_KID=api_tj-current
API_TJ_PUBLIC_KEY="-----BEGIN PUBLIC KEY-----..."
API_TJ_ALLOWED_SCOPES=["beneficiarios.staging.push"]
```

### 2.3 Reglas

- Llaves privadas fuera del repositorio.
- Llaves públicas pueden ir en variables de entorno.
- No subir `.pem` reales.
- No usar secretos compartidos entre sistemas.

## Fase 3 — Servicios internos

### 3.1 Servicio de hash CURP

Crear servicio responsable de:

- normalizar CURP;
- validar CURP;
- generar `curp_hash` en memoria;
- generar `curp_masked`.

Regla:

- No guardar `curp_hash` en `beneficiarios`.

### 3.2 Servicio JWT de integración

Responsable de:

- firmar JWT RS256 como Sys_IPJ;
- validar JWT RS256 recibido desde API_TJ;
- validar `kid`;
- validar `iss`, `aud`, `exp`, `iat`, `scope`;
- registrar `jti`.

### 3.3 Cliente API_TJ

Responsable de:

- construir request hacia API_TJ;
- enviar padrón mínimo;
- manejar timeout;
- capturar respuesta;
- normalizar errores.

### 3.4 Servicio de sincronización de padrón

Responsable de:

- crear `integration_sync_runs`;
- seleccionar beneficiarios elegibles;
- construir payload mínimo;
- llamar a API_TJ;
- guardar resultados en `integration_sync_items`;
- marcar corrida como `success`, `partial` o `failed`.

### 3.5 Servicio inbound API_TJ

Responsable de:

- recibir expediente staging;
- validar idempotencia;
- registrar `integration_inbound_requests`;
- validar payload;
- crear beneficiario oficial si procede;
- responder resultado estructurado.

## Fase 4 — Endpoints

### 4.1 Endpoint de recepción desde API_TJ

Propuesto:

```txt
POST /api/v1/integrations/api-tj/staging/accept
```

Middleware:

```txt
api
integration.jwt
throttle
```

Scope requerido:

```txt
beneficiarios.staging.push
```

### 4.2 Endpoint o acción interna de sync hacia API_TJ

Primera versión recomendada:

- acción web administrativa;
- solo rol `admin`;
- confirmación manual;
- no endpoint público externo.

Ruta interna sugerida:

```txt
POST /admin/integraciones/api-tj/cardholders/sync
```

Esto dispara el servicio que llama a API_TJ.

## Fase 5 — Validaciones de negocio

Para staging API_TJ → Sys_IPJ:

- `external_request_id` requerido;
- `source = api_tj`;
- CURP válida;
- CURP no duplicada;
- nombre y apellidos requeridos;
- fecha de nacimiento válida;
- sexo permitido;
- discapacidad boolean;
- teléfono válido;
- domicilio completo;
- seccional existente;
- municipio consistente con seccional;
- tarjeta válida si se envía;
- `created_by` definido por política institucional.

## Fase 6 — Usuario técnico o actor institucional

No usar `created_by = null`.

Opciones válidas:

### Opción A — Usuario técnico

Crear usuario institucional:

```txt
integracion.api_tj@inpojuve.local
```

Usarlo como `created_by` para altas provenientes de API_TJ.

### Opción B — Usuario aprobador interno

Mapear `submitted_by` de API_TJ contra un usuario institucional autorizado.

Riesgo:

- requiere sincronización de identidades.

Recomendación inicial:

- usar usuario técnico claramente identificado;
- guardar detalle del aprobador API_TJ en `integration_inbound_requests`.

## Fase 7 — Pruebas

### 7.1 Unitarias

Probar:

- normalización CURP;
- hash CURP;
- máscara CURP;
- firma JWT;
- validación JWT;
- validación de scopes;
- idempotencia por `external_request_id`.

### 7.2 Feature tests

Probar endpoint inbound:

- token ausente → `401`;
- token inválido → `401`;
- scope incorrecto → `403`;
- payload inválido → `422`;
- CURP duplicada → `409`;
- request correcto → `201`;
- mismo `external_request_id` → `200` o `already_processed`.

### 7.3 Integración hacia API_TJ

Probar sync:

- API_TJ responde éxito total;
- API_TJ responde parcial;
- API_TJ responde error de autenticación;
- API_TJ no responde;
- timeout;
- error de payload.

## Fase 8 — Validación local

Checklist local:

- [ ] Migraciones corren en base limpia.
- [ ] Migraciones no alteran `beneficiarios`.
- [ ] Tests pasan.
- [ ] Sync manual genera corrida.
- [ ] Sync manual registra items.
- [ ] Endpoint inbound valida JWT.
- [ ] Endpoint inbound crea beneficiario válido.
- [ ] Endpoint inbound rechaza duplicados.
- [ ] Logs no exponen secretos.

## Fase 9 — Validación staging

Checklist staging:

- [ ] Variables reales configuradas.
- [ ] Llaves públicas/privadas correctas.
- [ ] Prueba de firma entre sistemas.
- [ ] Sync de lote pequeño.
- [ ] Staging API_TJ de prueba.
- [ ] Push API_TJ → Sys_IPJ de prueba.
- [ ] Auditoría revisada.
- [ ] Sin cambios en `beneficiarios` fuera de altas esperadas.

## Fase 10 — Deploy controlado

Pasos:

1. Confirmar backup.
2. Desactivar deploy automático si hay riesgo.
3. Deploy de código.
4. Ejecutar migraciones.
5. Limpiar caches.
6. Validar login admin.
7. Validar rutas API.
8. Ejecutar prueba de salud.
9. Ejecutar sync pequeño.
10. Revisar logs.

## Fase 11 — Operación inicial

Durante la primera etapa:

- sync manual;
- lotes pequeños;
- monitoreo de logs;
- revisión manual de errores;
- sin automatización nocturna;
- sin reintentos automáticos.

## Fase 12 — Automatización futura

Solo considerar después de operación estable.

Requisitos previos:

- idempotencia probada;
- auditoría completa;
- alertas;
- reintentos seguros;
- límites de lote;
- monitoreo.

## Criterios de aceptación

La implementación se acepta si:

- no modifica `beneficiarios`;
- tiene tablas aisladas;
- tiene pruebas;
- usa JWT RS256;
- audita requests;
- maneja idempotencia;
- conserva Sys_IPJ como fuente de verdad;
- API_TJ no escribe directo en BD;
- Unidad de Informática no toca Sys_IPJ directamente.

## Criterios de rechazo

Rechazar implementación si:

- agrega columnas de integración a `beneficiarios`;
- hace `created_by` nullable;
- mete observers globales de sync;
- usa token estático compartido;
- no tiene auditoría;
- no tiene idempotencia;
- mezcla staging con alta oficial sin validación.

## Resumen

Primero aislar, luego conectar.

La integración debe rodear al core, no invadirlo.
