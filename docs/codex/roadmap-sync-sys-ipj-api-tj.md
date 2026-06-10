# Roadmap Sync Sys_IPJ API_TJ

Documento de trabajo para ejecutar `implementacion-sync-sys-ipj-api-tj.md` sobre el estado real de la rama `main`.

## 1. Resumen repo vs documento

| Area | Estado real en `main` | Brecha frente al documento base | Nota operativa |
| --- | --- | --- | --- |
| Prefijo API | Existe `/api/v1`, pero tambien rutas legacy bajo `/api/api-tj/*` | Hay deriva contractual | El contrato nuevo no debe montarse sobre las rutas legacy |
| Seguridad inbound | Existe middleware `ValidateApiTjJwt` y `ApiTjJwtService` | Parcial y acoplado | Falta modelo formal de clientes, llaves, scopes y anti-replay persistido |
| Outbound | Existen `ApiTjClient` y `ApiTjSyncService` | Implementacion funcional pero no compliant | Hoy invade el core y usa estados/campos no permitidos |
| Auditoria | Existen `api_tj_inbound_requests` y `api_tj_sync_runs` | Auditoria parcial | Faltan clientes, llaves, `jti`, items por corrida e inbound generico |
| Modelo core | `beneficiarios` y `tarjetas` ya fueron alteradas para integracion | Brecha critica | Contradice restricciones no negociables |
| Queue | Ya existe infraestructura de cola con DB | Brecha baja | Sirve para el modo `manual + job queued` |
| Pruebas | Ya existen tests `ApiTjIntegrationTest` y `ApiTjUiTest` | Validan legado, no el contrato objetivo | Se conservan temporalmente como caracterizacion |

## 2. Conclusion de arranque

- El trabajo no parte de cero.
- `main` ya tiene una integracion API_TJ viva, pero no alineada al documento base.
- La estrategia correcta es construir la capa compliant sin seguir profundizando el legado y despues planear la remediacion controlada.

## 3. Estado de fases

| Fase | Estado | Resultado esperado |
| --- | --- | --- |
| Fase 0 | Completada | Decisiones base cerradas y politica de realineacion definida |
| Fase 1 | Pendiente | Persistencia nueva de integracion fuera del core |
| Fase 2 | Pendiente | Seguridad JWT RS256 formal con clientes, llaves y `jti` |
| Fase 3 | Pendiente | Outbound compliant Sys_IPJ -> API_TJ |
| Fase 4 | Pendiente | Inbound compliant API_TJ -> Sys_IPJ |
| Fase 5 | Pendiente | Superficie admin y observabilidad |
| Fase 6 | Pendiente | Pruebas, rollout y cierre |

## 4. Fase 0 cerrada

Evidencia:

- `docs/codex/fase-0-decisiones-realineacion-sync-sys-ipj-api-tj.md`

Decisiones cerradas:

- JWT RS256 con libreria mantenida
- `tarjeta_numero` valido solo con tarjeta `consumida`, luego fallback a `folio_tarjeta`
- contrato JSON dedicado para inbound
- estructura nueva bajo `Integrations\...`
- cifrado de payload con llave dedicada
- congelamiento del legado actual como referencia, no como base final

## 5. Fase 1. Persistencia de integracion

Estado: `Pendiente`

Objetivo:

Crear almacenamiento compliant fuera del core.

Checklist:

- [ ] Crear `integration_clients`
- [ ] Crear `integration_client_keys`
- [ ] Crear `integration_jti_logs`
- [ ] Crear `integration_sync_runs`
- [ ] Crear `integration_sync_items`
- [ ] Crear `integration_inbound_requests`
- [ ] Crear modelos Eloquent bajo `App\Models\Integrations\...`
- [ ] Agregar seed inicial para `api_tj` y `sys_ipj`

Criterio de salida:

- Las tablas nuevas existen sin alterar mas el modelo core.

## 6. Fase 2. Seguridad y autenticacion

Estado: `Pendiente`

Objetivo:

Sustituir la seguridad estatica actual por una capa formal de integracion.

Checklist:

- [ ] Agregar dependencia JWT elegida
- [ ] Crear `IntegrationJwtSigner`
- [ ] Crear `IntegrationJwtVerifier`
- [ ] Crear `IntegrationJtiService`
- [ ] Crear `IntegrationAuthContext`
- [ ] Crear `ValidateIntegrationJwt`
- [ ] Registrar alias `integration.jwt`
- [ ] Probar `iss`, `aud`, `scope`, `kid`, firma y replay

Criterio de salida:

- El endpoint nuevo solo acepta JWT RS256 valido con scope `beneficiarios.staging.push`.

## 7. Fase 3. Outbound compliant

Estado: `Pendiente`

Objetivo:

Construir el flujo Sys_IPJ -> API_TJ sin metadatos en tablas core.

Checklist:

- [ ] Crear `CurpFingerprintService`
- [ ] Crear `CardholderSyncSelector`
- [ ] Crear `CardholderPayloadFactory`
- [ ] Crear `ApiTjClient` compliant
- [ ] Crear `CardholderSyncService`
- [ ] Crear `RunCardholderSyncJob`
- [ ] Registrar corridas e items
- [ ] Marcar `skipped` cuando falte `tarjeta_numero`

Criterio de salida:

- Una corrida manual genera job y deja trazabilidad completa por corrida e item.

## 8. Fase 4. Inbound compliant

Estado: `Pendiente`

Objetivo:

Recibir staging aprobado y convertirlo en beneficiario oficial sin invadir el core.

Checklist:

- [ ] Crear `InboundIdempotencyService`
- [ ] Crear `ApiTjStagingPayloadValidator`
- [ ] Crear `ApiTjTechnicalUserResolver`
- [ ] Extraer `BeneficiarioRegistrationService`
- [ ] Crear `ApiTjStagingAcceptService`
- [ ] Crear `ApiTjStagingAcceptController`
- [ ] Registrar `POST /api/v1/integrations/api-tj/staging/accept`
- [ ] Persistir request inbound cifrado y auditado

Criterio de salida:

- Un payload valido crea beneficiario con `created_by` del usuario tecnico y con idempotencia fuerte.

## 9. Fase 5. Superficie admin y observabilidad

Estado: `Pendiente`

Checklist:

- [ ] Crear disparo admin de sync compliant
- [ ] Crear vista de corridas compliant
- [ ] Crear vista de inbound requests compliant
- [ ] Exponer readiness y errores operativos
- [ ] Documentar variables nuevas

## 10. Fase 6. Pruebas y rollout

Estado: `Pendiente`

Checklist:

- [ ] Unit tests de seguridad y CURP
- [ ] Feature tests inbound compliant
- [ ] Feature tests outbound compliant
- [ ] Pruebas de migracion
- [ ] Checklist de rollout
- [ ] Estrategia de convivencia y apagado del legado

## 11. Orden recomendado

1. Fase 1
2. Fase 2
3. Extraccion de `BeneficiarioRegistrationService`
4. Fase 3
5. Fase 4
6. Fase 5
7. Fase 6

## 12. Primer bloque siguiente

El siguiente bloque debe atacar Fase 1:

- crear persistencia compliant;
- no agregar mas campos al core;
- dejar listo el piso para reemplazar la seguridad actual sin ruptura innecesaria.
