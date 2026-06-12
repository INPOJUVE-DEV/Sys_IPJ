# Checklist Rollout Sync Sys_IPJ API_TJ

Fecha base: `2026-06-12`

Objetivo:

Desplegar la integracion Sys_IPJ ↔ API_TJ con control operativo, validacion minima y ruta clara de contencion.

## 1. Previo al despliegue

- Confirmar que la rama a desplegar incluye Fase 1 a Fase 6 completas.
- Confirmar respaldo reciente de base de datos del ambiente objetivo.
- Confirmar ventana operativa y responsable funcional para validacion post despliegue.
- Confirmar acceso admin a `Sys_IPJ` para revisar `Integraciones`.

## 2. Variables y secretos

Validar en el ambiente objetivo:

- `API_TJ_BASE_URL`
- `API_TJ_AUDIENCE`
- `API_TJ_SYNC_TIMEOUT_SECONDS`
- `API_TJ_SYNC_BATCH_SIZE`
- `CURP_HASH_SECRET`
- `SYS_IPJ_INTEGRATION_AUDIENCE`
- `SYS_IPJ_JWT_ISSUER`
- `SYS_IPJ_JWT_SUBJECT`
- `SYS_IPJ_JWT_KID`
- `SYS_IPJ_PRIVATE_KEY_PATH`
- `SYS_IPJ_JWT_TTL_SECONDS`
- `SYS_IPJ_SCOPE`
- `API_TJ_INTEGRATION_USER_EMAIL`
- `INTEGRATION_PAYLOAD_ENCRYPTION_KEY`

Validaciones operativas:

- La ruta configurada en `SYS_IPJ_PRIVATE_KEY_PATH` existe y es legible.
- `INTEGRATION_PAYLOAD_ENCRYPTION_KEY` no esta vacia.
- El correo de `API_TJ_INTEGRATION_USER_EMAIL` corresponde al usuario tecnico institucional correcto.

## 3. Base de datos

Ejecutar:

```bash
php artisan migrate --force
php artisan db:seed --class=IntegrationClientSeeder --force
php artisan db:seed --class=IntegrationTechnicalUserSeeder --force
```

Verificar:

- Existen tablas `integration_clients`, `integration_client_keys`, `integration_jti_logs`, `integration_sync_runs`, `integration_sync_items`, `integration_inbound_requests`.
- No se agregaron columnas de integracion a `beneficiarios`.
- `created_by` sigue obligatorio en `beneficiarios`.

## 4. Seguridad y clientes

Confirmar en base:

- Existe `integration_clients.client_code = api_tj` con `status = active`.
- Existe `integration_clients.client_code = sys_ipj` con `status = active`.
- Existe al menos una llave activa en `integration_client_keys` para `api_tj`.
- Existe al menos una llave valida para firma outbound de `sys_ipj` en el entorno operativo correspondiente.

## 5. Smoke tests post despliegue

Admin UI:

- Abrir `/admin/integraciones/api-tj/sync-runs`.
- Verificar que el panel `Readiness operativo` no muestre faltantes criticos.
- Abrir `/admin/integraciones/api-tj/inbound-requests`.

Outbound:

- Disparar una corrida manual desde `Integraciones`.
- Confirmar que se crea un registro en `integration_sync_runs`.
- Confirmar que la corrida deja `items` auditables aunque todos sean `skipped`.

Inbound:

- Confirmar que `POST /api/v1/integrations/api-tj/staging/accept` responde `401` sin JWT.
- Ejecutar una solicitud firmada de prueba desde `API_TJ` o harness controlado.
- Confirmar que se crea registro en `integration_inbound_requests`.
- Confirmar que un mismo `external_request_id` regresa `already_processed`.

## 6. Observacion inicial

Durante las primeras corridas:

- Revisar `integration_sync_runs.status`, `failed_count`, `partial_count` funcionalmente a traves de la UI.
- Revisar `integration_inbound_requests.status`, `response_code` y `error_message`.
- Validar que no aparezcan beneficiarios con metadatos de integracion en tablas core.
- Validar que los beneficiarios creados inbound usen el usuario tecnico institucional en `created_by`.

## 7. Contencion

Si falla inbound:

- Desactivar la llave activa de `api_tj` en `integration_client_keys` o poner el cliente `api_tj` en `inactive`.
- No modificar tablas core para contener el incidente.

Si falla outbound:

- Suspender disparos manuales desde la superficie admin.
- Corregir configuracion de `API_TJ_BASE_URL`, llave privada o timeout antes de reintentar.

## 8. Rollback

Rollback funcional preferido:

- Desactivar cliente o llaves de integracion para detener trafico.
- Mantener tablas `integration_*` para conservar auditoria.

Rollback tecnico de migraciones:

- Solo en ventana controlada y si la integracion debe retirarse completamente.
- Revertir primero migraciones `integration_*` en orden inverso.
- No revertir tablas core como parte del rollback de integracion.

## 9. Evidencia de cierre

Guardar evidencia de:

- salida de migracion ejecutada;
- validacion de seeders;
- captura o export de una corrida outbound;
- captura o export de un inbound request aceptado;
- decision de habilitacion final del responsable operativo.
