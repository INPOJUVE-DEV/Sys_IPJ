# Roadmap Sync Sys_IPJ API_TJ

Documento de trabajo para ejecutar `implementacion-sync-sys-ipj-api-tj.md` sobre el estado real actual de `main`.

## 1. Resumen repo vs documento

| Area | Estado real en `main` | Brecha frente al documento base | Nota operativa |
| --- | --- | --- | --- |
| Prefijo API | Existe `/api/v1` por `RouteServiceProvider` | No hay brecha | La ruta objetivo futura cabe sin cambios estructurales |
| Rutas API actuales | `routes/api.php` ya expone `POST /api/v1/integrations/api-tj/staging/accept` | Brecha cerrada para Fase 4 inbound | La superficie inbound ya quedo aislada del core |
| Modelo core | `Beneficiario` esta limpio de metadatos de integracion | No hay brecha | Se debe conservar asi |
| Migracion base | `created_by` sigue obligatorio en `beneficiarios` | No hay brecha | Se debe conservar asi |
| Seguridad inbound | Ya existe la capa formal en codigo y ya fue validada en runtime: config, servicios JWT, `IntegrationJtiService` y middleware `integration.jwt` | Brecha cerrada para Fase 2 | Queda lista la base para cerrar inbound |
| Persistencia de integracion | Ya existen tablas `integration_*` y modelos dedicados fuera del core | Brecha cerrada para Fase 1 | Base lista para outbound e inbound |
| Queue | Ya existe infraestructura de colas por DB | Brecha baja | Sirve para el modo `manual + job queued` |
| Testing | Ya existen y ya corrieron pruebas focalizadas de persistencia, signer, middleware JWT, outbound e inbound compliant | Cobertura parcial | Falta ampliar la suite hacia rollout y escenarios E2E |

## 2. Conclusion de arranque

- `main` esta limpio respecto a invasion API_TJ del core.
- La implementacion puede arrancar desde base limpia alrededor del dominio actual.
- La persistencia nueva ya existe, la seguridad formal ya quedo cerrada y el outbound base ya esta implementado.
- El siguiente paso correcto es cerrar Fase 6 sobre una base inbound, outbound y admin ya funcional.

## 3. Estado de fases

| Fase | Estado | Resultado esperado |
| --- | --- | --- |
| Fase 0 | Completada | Decisiones base cerradas |
| Fase 1 | Completada | Persistencia nueva de integracion fuera del core |
| Fase 2 | Completada | Seguridad JWT RS256 formal |
| Fase 3 | Completada | Outbound compliant Sys_IPJ -> API_TJ |
| Fase 4 | Completada | Inbound compliant API_TJ -> Sys_IPJ |
| Fase 5 | Completada | Superficie admin y observabilidad |
| Fase 6 | Pendiente | Pruebas, rollout y cierre |

## 4. Fase 0 cerrada

Evidencia:

- `docs/codex/fase-0-decisiones-realineacion-sync-sys-ipj-api-tj.md`

Decisiones cerradas:

- JWT RS256 con `firebase/php-jwt`
- `tarjeta_numero` valido solo con tarjeta `consumida`, luego fallback a `folio_tarjeta`
- contrato JSON dedicado para inbound
- estructura nueva bajo `Integrations\...`
- cifrado de payload con llave dedicada
- politica de implementacion desde base limpia sin tocar el core

## 5. Fase 1. Persistencia de integracion

Estado: `Completada`

Objetivo:

Crear almacenamiento compliant fuera del core.

Alcance de esta fase:

- crear tablas nuevas de integracion;
- no tocar tablas core;
- no modificar `Beneficiario`;
- no crear endpoints todavia;
- no crear UI todavia.

Checklist:

- [x] Crear `integration_clients`
- [x] Crear `integration_client_keys`
- [x] Crear `integration_jti_logs`
- [x] Crear `integration_sync_runs`
- [x] Crear `integration_sync_items`
- [x] Crear `integration_inbound_requests`
- [x] Crear modelos Eloquent bajo `App\Models\Integrations\...`
- [x] Agregar seed inicial para `api_tj` y `sys_ipj`

Criterio de salida:

- Las tablas nuevas existen sin modificar tablas core ni modelos core.

Evidencia:

- migraciones `2026_06_10_000100` a `2026_06_10_000600`
- modelos bajo `sys_beneficiarios/app/Models/Integrations/`
- `Database\Seeders\IntegrationClientSeeder`
- `tests/Feature/IntegrationPersistencePhaseOneTest.php`

## 6. Fase 2. Seguridad y autenticacion

Estado: `Completada`

Objetivo:

Construir la capa formal de integracion JWT RS256.

Checklist:

- [x] Agregar dependencia JWT elegida
- [x] Declarar e instalar `firebase/php-jwt`
- [x] Crear `IntegrationJwtSigner`
- [x] Crear `IntegrationJwtVerifier`
- [x] Crear `IntegrationJtiService`
- [x] Crear `IntegrationAuthContext`
- [x] Crear `ValidateIntegrationJwt`
- [x] Registrar alias `integration.jwt`
- [x] Probar `iss`, `aud`, `scope`, `kid`, firma y replay

Criterio de salida:

- La capa `integration.jwt` solo acepta JWT RS256 valido con scope `beneficiarios.staging.push`.

Evidencia:

- `sys_beneficiarios/composer.lock` fija `firebase/php-jwt` en `v7.0.5`
- `sys_beneficiarios/app/Services/Integrations/Security/`
- `sys_beneficiarios/app/Http/Middleware/ValidateIntegrationJwt.php`
- `sys_beneficiarios/tests/Unit/IntegrationJwtSignerTest.php`
- `sys_beneficiarios/tests/Feature/IntegrationJwtMiddlewareTest.php`
- validacion ejecutada con `vendor/bin/phpunit` sobre PHP 8.2 portable

## 7. Fase 3. Outbound compliant

Estado: `Completada`

Objetivo:

Construir el flujo Sys_IPJ -> API_TJ sin metadatos en tablas core.

Checklist:

- [x] Crear `CurpFingerprintService`
- [x] Crear `CardholderSyncSelector`
- [x] Crear `CardholderPayloadFactory`
- [x] Crear `ApiTjClient`
- [x] Crear `CardholderSyncService`
- [x] Crear `RunCardholderSyncJob`
- [x] Registrar corridas e items
- [x] Marcar `skipped` cuando falte `tarjeta_numero`

Criterio de salida:

- Una corrida manual crea trazabilidad por corrida e item y envia a API_TJ solo el padron minimo compliant.

Evidencia:

- `sys_beneficiarios/app/Services/Integrations/ApiTj/CurpFingerprintService.php`
- `sys_beneficiarios/app/Services/Integrations/ApiTj/CardholderSyncSelector.php`
- `sys_beneficiarios/app/Services/Integrations/ApiTj/CardholderPayloadFactory.php`
- `sys_beneficiarios/app/Services/Integrations/ApiTj/ApiTjClient.php`
- `sys_beneficiarios/app/Services/Integrations/ApiTj/CardholderSyncService.php`
- `sys_beneficiarios/app/Jobs/Integrations/ApiTj/RunCardholderSyncJob.php`
- `sys_beneficiarios/tests/Unit/CurpFingerprintServiceTest.php`
- `sys_beneficiarios/tests/Feature/CardholderPayloadFactoryTest.php`
- `sys_beneficiarios/tests/Feature/CardholderSyncServiceTest.php`
- validacion ejecutada con `vendor/bin/phpunit` local y via `docker compose run --rm app`

## 8. Fase 4. Inbound compliant

Estado: `Completada`

Objetivo:

Recibir staging aprobado y convertirlo en beneficiario oficial sin invadir el core.

Precondiciones ya cubiertas:

- `ApiTjTechnicalUserResolver` ya existe para sostener `created_by` obligatorio.
- `BeneficiarioRegistrationService` ya concentra la escritura principal de beneficiarios.
- `BeneficiarioLocationResolver` ya concentra la resolucion base de seccion y municipio para ese flujo.

Checklist:

- [x] Crear `InboundIdempotencyService`
- [x] Crear `ApiTjStagingPayloadValidator`
- [x] Crear `ApiTjTechnicalUserResolver`
- [x] Extraer `BeneficiarioRegistrationService`
- [x] Crear `ApiTjStagingAcceptService`
- [x] Crear `ApiTjStagingAcceptController`
- [x] Registrar `POST /api/v1/integrations/api-tj/staging/accept`
- [x] Persistir request inbound cifrado y auditado

Criterio de salida:

- El endpoint inbound acepta JWT RS256 valido, registra auditoria cifrada e idempotente y crea beneficiarios oficiales via servicios compartidos sin tocar tablas core.

Evidencia:

- `sys_beneficiarios/app/Services/Integrations/Inbound/InboundIdempotencyService.php`
- `sys_beneficiarios/app/Services/Integrations/Inbound/InboundPayloadEncrypter.php`
- `sys_beneficiarios/app/Services/Integrations/ApiTj/ApiTjStagingPayloadValidator.php`
- `sys_beneficiarios/app/Services/Integrations/ApiTj/ApiTjStagingAcceptService.php`
- `sys_beneficiarios/app/Http/Controllers/Api/Integrations/ApiTjStagingAcceptController.php`
- `sys_beneficiarios/routes/api.php`
- `sys_beneficiarios/tests/Feature/ApiTjStagingAcceptTest.php`
- `sys_beneficiarios/tests/Feature/InboundIdempotencyServiceTest.php`
- `sys_beneficiarios/tests/Unit/ApiTjStagingPayloadValidatorTest.php`
- validacion ejecutada con `docker compose run --rm app php vendor/bin/phpunit`

## 9. Fase 5. Superficie admin y observabilidad

Estado: `Completada`

Checklist:

- [x] Crear disparo admin de sync compliant
- [x] Crear vista de corridas compliant
- [x] Crear vista de inbound requests compliant
- [x] Exponer readiness y errores operativos
- [x] Documentar variables nuevas

Criterio de salida:

- El admin puede disparar sync manual, consultar corridas outbound, consultar inbound requests y revisar readiness/errores operativos sin tocar tablas core.

Evidencia:

- `sys_beneficiarios/app/Http/Controllers/Admin/ApiTjCardholderSyncController.php`
- `sys_beneficiarios/app/Http/Controllers/Admin/ApiTjSyncRunController.php`
- `sys_beneficiarios/app/Http/Controllers/Admin/ApiTjInboundRequestController.php`
- `sys_beneficiarios/app/Services/Integrations/ApiTj/ApiTjOperationalStatusService.php`
- `sys_beneficiarios/resources/views/admin/integrations/api_tj/`
- `sys_beneficiarios/routes/web.php`
- `sys_beneficiarios/resources/views/layouts/navigation.blade.php`
- `sys_beneficiarios/tests/Feature/Admin/ApiTjAdminIntegrationTest.php`
- No se agregaron variables nuevas en Fase 5; la superficie opera con las variables documentadas en fases previas.

## 10. Fase 6. Pruebas y rollout

Estado: `Pendiente`

Checklist:

- [x] Unit tests de seguridad y CURP
- [x] Feature tests inbound compliant
- [x] Feature tests outbound compliant
- [ ] Pruebas de migracion
- [ ] Checklist de rollout

## 11. Orden recomendado

1. Fase 6

## 12. Siguiente paso

El siguiente bloque debe atacar Fase 6:

- endurecer pruebas de migracion y checklist de rollout;
- apoyarse en la persistencia, seguridad, flujos inbound/outbound y superficie admin ya cubiertos;
- seguir sin tocar tablas core;
- mantener `Beneficiario` sin metadatos de integracion.
