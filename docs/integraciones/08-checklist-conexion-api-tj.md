# Checklist de conexión Sys_IPJ ↔ API_TJ

## Objetivo

Checklist operativo para habilitar la conexión real entre `Sys_IPJ` y `API_TJ` sin poner en riesgo el modelo core ni los datos existentes de beneficiarios y domicilios.

Este documento complementa el QA end-to-end y debe ejecutarse antes de permitir tráfico real entre sistemas.

## Estado permitido antes de conectar

### Permitido

- Desplegar Sys_IPJ con tablas `integration_*`.
- Ejecutar `php artisan migrate --force`.
- Ejecutar seeders de integración.
- Probar rutas y readiness admin.
- Probar conectividad contra API_TJ en ambiente QA.

### No permitido

- Ejecutar `php artisan migrate:fresh` en producción.
- Modificar tablas core para resolver problemas de integración.
- Crear columnas de integración en `beneficiarios`, `domicilios`, `tarjetas`, `users`, `municipios` o `secciones`.
- Habilitar push real API_TJ → Sys_IPJ si API_TJ sigue usando `scope = beneficiarios.create`.
- Habilitar push real API_TJ → Sys_IPJ si API_TJ no manda `source = api_tj`.

## 1. Preflight de base de datos

Antes del deploy:

```sql
SELECT COUNT(*) AS beneficiarios FROM beneficiarios;
SELECT COUNT(*) AS domicilios FROM domicilios;
SELECT COUNT(*) AS beneficiarios_sin_domicilio
FROM beneficiarios b
LEFT JOIN domicilios d ON d.beneficiario_id = b.id
WHERE d.id IS NULL;
```

Guardar conteos como evidencia.

Verificar migraciones actuales:

```bash
php artisan migrate:status
```

Confirmar que estas migraciones ya existen o quedan listas para ejecución controlada:

```txt
2025_11_22_000700_normalize_secciones_relations
2026_01_22_000100_make_folio_tarjeta_nullable_in_beneficiarios_table
2026_04_06_000210_add_tarjeta_id_to_beneficiarios_table
2026_06_10_000100_create_integration_clients_table
2026_06_10_000200_create_integration_client_keys_table
2026_06_10_000300_create_integration_jti_logs_table
2026_06_10_000400_create_integration_sync_runs_table
2026_06_10_000500_create_integration_sync_items_table
2026_06_10_000600_create_integration_inbound_requests_table
```

## 2. Deploy seguro en Render

En producción usar solo:

```bash
php artisan migrate --force
php artisan db:seed --class=IntegrationClientSeeder --force
php artisan db:seed --class=IntegrationTechnicalUserSeeder --force
php artisan config:cache
php artisan route:cache
```

No usar:

```bash
php artisan migrate:fresh --seed
php artisan migrate:rollback
```

salvo rollback técnico explícito, con ventana controlada y backup.

## 3. Variables requeridas en Sys_IPJ

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<sys-ipj-render-url>

QUEUE_CONNECTION=database

API_TJ_BASE_URL=https://<api-tj-url>
API_TJ_AUDIENCE=api_tj
API_TJ_SYNC_TIMEOUT_SECONDS=15
API_TJ_SYNC_BATCH_SIZE=100
CURP_HASH_SECRET=<mismo_valor_que_API_TJ>

SYS_IPJ_INTEGRATION_AUDIENCE=sys_ipj
SYS_IPJ_JWT_ISSUER=sys_ipj
SYS_IPJ_JWT_SUBJECT=sys_ipj
SYS_IPJ_JWT_KID=sys_ipj-current
SYS_IPJ_PRIVATE_KEY_PATH=/app/storage/app/keys/sys_ipj_private.pem
SYS_IPJ_JWT_TTL_SECONDS=600
SYS_IPJ_SCOPE=cardholders.sync

API_TJ_INTEGRATION_USER_EMAIL=integracion.api_tj@inpojuve.local
INTEGRATION_PAYLOAD_ENCRYPTION_KEY=<llave_dedicada>
```

## 4. Llaves requeridas

### Sys_IPJ firma hacia API_TJ

Sys_IPJ necesita llave privada:

```txt
SYS_IPJ_PRIVATE_KEY_PATH=/app/storage/app/keys/sys_ipj_private.pem
```

API_TJ debe tener la llave pública correspondiente configurada como:

```env
SYS_IPJ_JWT_PUBLIC_KEY=-----BEGIN PUBLIC KEY-----...
SYS_IPJ_JWT_KID=sys_ipj-current
SYS_IPJ_ALLOWED_SCOPES=["cardholders.sync"]
```

### API_TJ firma hacia Sys_IPJ

API_TJ necesita llave privada:

```env
API_TJ_TO_SYS_IPJ_PRIVATE_KEY_PATH=/app/keys/api_tj_private.pem
API_TJ_TO_SYS_IPJ_JWT_KID=api_tj-current
API_TJ_TO_SYS_IPJ_ISSUER=api_tj
API_TJ_TO_SYS_IPJ_AUDIENCE=sys_ipj
API_TJ_TO_SYS_IPJ_SCOPE=beneficiarios.staging.push
```

Sys_IPJ debe tener la llave pública de API_TJ en:

```txt
integration_client_keys
client_code = api_tj
kid = api_tj-current
status = active
```

## 5. Validar rutas expuestas

En Sys_IPJ:

```bash
php artisan route:list | grep integrations
```

Esperado:

```txt
POST api/v1/integrations/api-tj/staging/accept
```

No debe existir ruta alternativa no documentada para inbound de API_TJ.

## 6. Validar readiness admin

Abrir:

```txt
/admin/integraciones/api-tj/sync-runs
/admin/integraciones/api-tj/inbound-requests
```

Validar que no haya faltantes críticos:

- Cliente `api_tj` activo.
- Llave inbound activa para `api_tj`.
- Usuario técnico disponible.
- Llave de cifrado inbound presente.
- Base URL outbound configurada.
- Llave privada outbound legible.

## 7. Validar queue worker

Para outbound manual, debe existir worker:

```bash
php artisan queue:work --sleep=3 --tries=3 --timeout=120
```

Criterio:

- Si no hay worker, las corridas pueden quedarse en `queued`.
- No declarar funcional el outbound hasta validar worker.

## 8. Smoke test outbound

Acción:

```txt
Admin Sys_IPJ → Integraciones → API_TJ → Sync manual
```

Validar:

```sql
SELECT id, target_system, operation, status, total_items, success_count, failed_count, skipped_count
FROM integration_sync_runs
ORDER BY created_at DESC
LIMIT 1;

SELECT status, response_code, error_message
FROM integration_sync_items
WHERE sync_run_id = '<sync_run_id>';
```

Criterio PASS:

- Se crea corrida.
- Se crean items.
- HTTP hacia API_TJ es 2xx.
- API_TJ recibe registros en `cardholders_sync`.

## 9. Smoke test inbound

### Sin JWT

```bash
curl -i -X POST https://<sys-ipj-url>/api/v1/integrations/api-tj/staging/accept \
  -H 'Content-Type: application/json' \
  -d '{}'
```

Esperado:

```txt
HTTP 401
status = unauthorized
```

### Con JWT válido

Enviar payload completo firmado por API_TJ con:

```txt
iss = api_tj
aud = sys_ipj
scope = beneficiarios.staging.push
kid = api_tj-current
```

Esperado:

```txt
HTTP 201
status = created
```

Validar:

```sql
SELECT source_system, external_request_id, status, response_code, error_message
FROM integration_inbound_requests
ORDER BY received_at DESC
LIMIT 5;
```

## 10. Criterio GO / NO GO

### GO

Se permite conexión real si:

- Backup reciente de DB confirmado.
- Conteos de `beneficiarios` y `domicilios` preservados tras deploy.
- `php artisan migrate --force` ejecutado sin error.
- `integration_clients` tiene `api_tj` y `sys_ipj` activos.
- Llave pública de API_TJ cargada en Sys_IPJ.
- Llave pública de Sys_IPJ configurada en API_TJ.
- `CURP_HASH_SECRET` coincide.
- Worker activo.
- Outbound smoke test PASS.
- Inbound smoke test PASS.

### NO GO

Bloquear conexión si:

- API_TJ usa `API_TJ_TO_SYS_IPJ_SCOPE=beneficiarios.create`.
- API_TJ no manda `source = api_tj`.
- No hay llave pública activa para `api_tj` en Sys_IPJ.
- No hay llave pública de `sys_ipj` en API_TJ.
- No existe usuario técnico.
- `CURP_HASH_SECRET` difiere.
- El worker no está activo.
- Cualquier migración intenta alterar tablas core para integración.

## 11. Rollback funcional

Preferido:

```sql
UPDATE integration_clients
SET status = 'inactive'
WHERE client_code = 'api_tj';
```

O desactivar llave:

```sql
UPDATE integration_client_keys
SET status = 'inactive'
WHERE kid = 'api_tj-current';
```

Esto corta inbound API_TJ → Sys_IPJ sin tocar core.

Para outbound, suspender disparos manuales desde UI y detener worker si es necesario.

## 12. Evidencia de cierre

Guardar:

- Conteos antes/después de `beneficiarios`.
- Conteos antes/después de `domicilios`.
- Salida de `migrate --force`.
- Salida de `route:list | grep integrations`.
- Captura de readiness admin.
- Registro `integration_sync_runs` de prueba.
- Registro `integration_inbound_requests` de prueba.
- Confirmación de llaves configuradas.
- Decisión GO/NO GO firmada por responsable operativo.
