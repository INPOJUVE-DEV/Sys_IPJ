# Roadmap Sync Sys_IPJ API_TJ

Documento de trabajo para ejecutar la implementacion descrita en `docs/codex/implementacion-sync-sys-ipj-api-tj.md` contra el estado real del repo.

## 1. Resumen del contraste repo vs documento

### Hallazgos verificados

| Area | Estado actual del repo | Brecha frente al documento | Nota |
| --- | --- | --- | --- |
| Prefijo API | `sys_beneficiarios/app/Providers/RouteServiceProvider.php` ya publica `routes/api.php` bajo `/api/v1` | No hay brecha | La ruta objetivo `/api/v1/integrations/api-tj/staging/accept` cabe sin cambios estructurales |
| Ruta legacy | `sys_beneficiarios/routes/api.php` solo expone `POST /beneficiarios/cache` para import cacheado | Falta ruta nueva de integracion | Confirmado: la ruta legacy existe y no debe reutilizarse |
| Seguridad inbound | `sys_beneficiarios/bootstrap/app.php` no registra middleware `integration.jwt` ni servicios asociados | Falta toda la capa JWT RS256 | Requiere middleware, verificador, manejo de `jti` y contexto auth |
| Outbound API_TJ | No existen clases `ApiTj*`, jobs de sync, ni cliente HTTP dedicado | Falta toda la capa outbound | Solo hay un uso de `Http::` en OCR, util para tomar patron de cliente HTTP |
| Tablas de integracion | No hay migraciones para `integration_clients`, `integration_client_keys`, `integration_jti_logs`, `integration_sync_runs`, `integration_sync_items`, `integration_inbound_requests` | Falta todo el modelo de datos de integracion | La restriccion de no tocar tablas core sigue siendo viable |
| Queue | Existen `config/queue.php`, `database/migrations/0001_01_01_000002_create_jobs_table.php` y `QUEUE_CONNECTION=database` en `.env.example` | Brecha baja | La infraestructura base para `manual + job queued` ya existe |
| Core beneficiarios | `Beneficiario` mantiene `created_by` obligatorio y `folio_tarjeta` nullable; `BeneficiarioController` y `InscripcionController` usan transacciones y resuelven seccion/municipio | Falta extraer servicio reutilizable para integracion | El repo ya ofrece el comportamiento core que el documento quiere preservar |
| Tarjetas | Existe `App\Models\Tarjeta` con estados y relacion opcional a beneficiario | Falta selector de elegibilidad y regla de prioridad para `tarjeta_numero` | Hay que definir que estados cuentan como tarjeta valida para sync |
| Auditoria | Existe `activity_log` por Spatie en modelos core | Falta auditoria especifica de integracion | `activity_log` complementa, pero no sustituye, tablas de integracion |
| Configuracion | `.env.example` no contiene variables `API_TJ_*`, `SYS_IPJ_*`, `CURP_HASH_SECRET`, `INTEGRATION_PAYLOAD_ENCRYPTION_KEY` | Falta documentacion y cableado de config | Conviene centralizar en `config/integrations.php` |
| Testing | No hay pruebas unitarias ni feature de integracion | Falta cobertura completa del alcance | El proyecto ya usa PHPUnit/Pest y `Http::fake()` en OCR |

### Conclusiones

- La implementacion puede hacerse sin alterar `beneficiarios`, `domicilios`, `tarjetas`, `users`, `municipios` ni `secciones`.
- El repo ya tiene tres bases utiles para el MVP: transacciones de dominio, colas por base de datos y prefijo `/api/v1`.
- La mayor brecha no es de modelo core sino de borde de integracion: seguridad, auditoria, idempotencia, cliente outbound y pruebas.
- El punto mas delicado para no introducir regresiones es extraer la creacion de beneficiarios a un servicio comun antes de conectar el endpoint inbound.

## 2. Principios de ejecucion

- No tocar tablas core salvo lectura y asociaciones ya existentes.
- Toda informacion de integracion vive en tablas nuevas.
- La seguridad inbound debe salir primero que el endpoint publico.
- El flujo inbound debe reutilizar reglas core, no duplicarlas una tercera vez.
- La salida del roadmap debe dejar visible que partes son MVP y que partes quedan como deuda tecnica controlada.

## 3. Roadmap por fases

## Fase 0. Preparacion y decisiones cerradas

Estado: `Pendiente`

Objetivo: cerrar decisiones que impactan toda la implementacion antes de escribir migraciones o endpoints.

Checklist:

- [ ] Confirmar la libreria o estrategia para JWT RS256 en Laravel 11.
- [ ] Confirmar cuales estados de `Tarjeta` cuentan como "tarjeta relacionada valida" para el outbound.
- [ ] Confirmar si la respuesta de integracion seguira JSON propio o si se adaptara `ProblemJsonMiddleware` para no mezclar formatos.
- [ ] Definir naming final de namespaces, por ejemplo `App\Services\Integrations\...`.
- [ ] Definir si `request_payload_encrypted` se cifrara con `Crypt` o con una capa dedicada basada en `INTEGRATION_PAYLOAD_ENCRYPTION_KEY`.

Criterio de salida:

- Existe una mini decision record dentro del PR o en `docs/codex/`.

## Fase 1. Persistencia de integracion

Estado: `Pendiente`

Objetivo: crear el almacenamiento aislado para seguridad, corridas, items e inbound requests.

Checklist:

- [ ] Crear migraciones para las 6 tablas de integracion descritas en el documento base.
- [ ] Crear modelos Eloquent de integracion con UUID y casts necesarios.
- [ ] Agregar factories si van a usarse en pruebas.
- [ ] Validar que ninguna migracion altere tablas core.
- [ ] Preparar seed o comando para registrar `integration_clients` iniciales (`api_tj`, `sys_ipj`).

Archivos esperados:

- `sys_beneficiarios/database/migrations/*integration*.php`
- `sys_beneficiarios/app/Models/Integrations/*.php`
- `sys_beneficiarios/database/seeders/*Integration*.php` o comando equivalente

Criterio de salida:

- `php artisan migrate` crea las tablas nuevas sin tocar core.

## Fase 2. Seguridad y autenticacion de integracion

Estado: `Pendiente`

Objetivo: habilitar firma saliente y verificacion entrante con anti-replay.

Checklist:

- [ ] Crear `IntegrationJwtSigner`.
- [ ] Crear `IntegrationJwtVerifier`.
- [ ] Crear `IntegrationJtiService`.
- [ ] Crear objeto de contexto de autenticacion, por ejemplo `IntegrationAuthContext`.
- [ ] Crear middleware `ValidateIntegrationJwt`.
- [ ] Registrar alias `integration.jwt` en `sys_beneficiarios/bootstrap/app.php`.
- [ ] Crear tests unitarios para firma, validacion, scopes y replay.

Dependencias:

- Requiere Fase 1 terminada.

Criterio de salida:

- Un request firmado valido con scope `beneficiarios.staging.push` entra.
- Un token sin scope, expirado o con `jti` repetido es rechazado.

## Fase 3. Outbound Sys_IPJ -> API_TJ

Estado: `Pendiente`

Objetivo: permitir que un admin dispare la sincronizacion del padron minimo y que se procese en job.

Checklist:

- [ ] Crear `CurpFingerprintService`.
- [ ] Crear `CardholderSyncSelector`.
- [ ] Crear `CardholderPayloadFactory`.
- [ ] Crear `ApiTjClient` usando `Http::timeout(...)`.
- [ ] Crear `CardholderSyncService`.
- [ ] Crear `RunCardholderSyncJob`.
- [ ] Registrar corridas e items en las tablas de integracion.
- [ ] Marcar `skipped` cuando no exista `tarjeta_numero` elegible.

Notas de implementacion:

- Reusar `Beneficiario`, `Tarjeta` y sus relaciones actuales.
- No guardar `curp_hash`, `curp_masked` ni estado de sync en `beneficiarios`.
- La prioridad de `tarjeta_numero` debe vivir en la fabrica de payload, no en el modelo core.

Criterio de salida:

- Un admin puede crear una corrida `queued`.
- El job procesa lotes y deja la corrida en `success`, `partial` o `failed`.

## Fase 4. Inbound API_TJ -> Sys_IPJ

Estado: `Pendiente`

Objetivo: recibir staging aprobado, aplicar idempotencia y convertirlo en beneficiario oficial con usuario tecnico.

Checklist:

- [ ] Crear `InboundIdempotencyService`.
- [ ] Crear `ApiTjStagingPayloadValidator`.
- [ ] Crear `ApiTjTechnicalUserResolver`.
- [ ] Extraer `BeneficiarioRegistrationService` desde la logica repetida en `BeneficiarioController` e `InscripcionController`.
- [ ] Crear `ApiTjStagingAcceptService`.
- [ ] Crear `ApiTjStagingAcceptController`.
- [ ] Registrar ruta `POST /api/v1/integrations/api-tj/staging/accept`.
- [ ] Persistir request inbound, respuesta y errores en `integration_inbound_requests`.

Riesgos a controlar:

- No crear un tercer flujo divergente de alta de beneficiarios.
- No devolver `500` genericos si falta el usuario tecnico; el error debe quedar auditado y controlado.
- No romper el formato de respuestas del resto de la API si se usa un manejo especializado para integracion.

Criterio de salida:

- Un payload valido crea beneficiario con `created_by` del usuario tecnico.
- Un `external_request_id` repetido devuelve `already_processed`.
- Una CURP duplicada devuelve conflicto controlado.

## Fase 5. Superficie admin y observabilidad

Estado: `Pendiente`

Objetivo: dejar operable el modulo por administracion y facilitar soporte.

Checklist:

- [ ] Crear controlador para disparar sync manual.
- [ ] Crear vistas o respuestas admin para listar corridas y detalle de items.
- [ ] Exponer errores relevantes de corrida e inbound request.
- [ ] Documentar variables nuevas en `.env.example` y README tecnico corto.

Nota:

- Si la UI completa retrasa el MVP, se puede dejar el disparo via endpoint protegido o comando temporal, pero la consulta de corridas no deberia omitirse.

Criterio de salida:

- Un admin puede disparar sync y revisar historico sin entrar directo a la base.

## Fase 6. Pruebas y cierre de release

Estado: `Pendiente`

Objetivo: cubrir el flujo completo y blindar regresiones sobre core.

Checklist:

- [ ] Unit tests para servicios de seguridad, CURP e idempotencia.
- [ ] Feature tests inbound con JWT valido, invalido, expirado, scope incorrecto y replay.
- [ ] Feature tests outbound con `Http::fake()`.
- [ ] Pruebas de migracion para confirmar que no hay `alter table` sobre core.
- [ ] Smoke test de corrida admin + job.
- [ ] Checklist de rollout con llaves, cliente activo y usuario tecnico existente.

Criterio de salida:

- La suite nueva pasa y cubre los casos de aceptacion del documento base.

## 4. Orden recomendado de ejecucion en el repo

1. Fase 0
2. Fase 1
3. Fase 2
4. Extraccion de `BeneficiarioRegistrationService` dentro de Fase 4
5. Fase 3
6. Fase 4
7. Fase 5
8. Fase 6

Justificacion:

- La seguridad y la persistencia son prerequisitos del endpoint inbound.
- La extraccion del servicio core debe ocurrir antes de terminar inbound para no dejar logica triplicada.
- Outbound e inbound comparten infraestructura, pero outbound es menos riesgoso para el padron oficial y puede usarse para validar la capa de integracion antes de abrir recepcion externa.

## 5. Seguimiento de avance

Usar esta tabla en cada iteracion:

| Hito | Estado | Evidencia esperada |
| --- | --- | --- |
| Fase 0 cerrada | Pendiente | Decision record o notas de implementacion |
| Fase 1 cerrada | Pendiente | Migraciones y modelos de integracion |
| Fase 2 cerrada | Pendiente | Middleware `integration.jwt` y tests unitarios |
| Fase 3 cerrada | Pendiente | Job de sync y registro de corridas |
| Fase 4 cerrada | Pendiente | Endpoint inbound funcional e idempotente |
| Fase 5 cerrada | Pendiente | UI o superficie admin para operar corridas |
| Fase 6 cerrada | Pendiente | Suite de pruebas y checklist de rollout |

## 6. Primer sprint sugerido

Para generar traccion sin abrir huecos de seguridad, el primer sprint deberia cubrir:

- [ ] Fase 0 completa
- [ ] Migraciones y modelos de Fase 1
- [ ] Base de Fase 2: verificador JWT, `jti`, middleware y alias
- [ ] Extraccion inicial de `BeneficiarioRegistrationService` sin exponer aun el endpoint inbound

Entrega esperada del sprint:

- Repo listo para conectar outbound e inbound sin tocar el modelo core y sin duplicar reglas de captura.
