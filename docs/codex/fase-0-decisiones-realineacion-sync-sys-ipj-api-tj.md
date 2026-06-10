# Fase 0 Decisiones de Realineacion Sync Sys_IPJ API_TJ

Estado: `Completada`

Fecha de cierre: `2026-06-10`

## 1. Objetivo de esta fase

Cerrar las decisiones base antes de tocar migraciones, seguridad, endpoints o flujos de sincronizacion.

En `main` el trabajo no parte de cero. Ya existe una implementacion API_TJ activa, por lo que la Fase 0 se enfoca en definir como vamos a realinear esa implementacion al documento base `implementacion-sync-sys-ipj-api-tj.md`.

## 2. Hallazgos verificados en `main`

- Ya existen rutas legacy de integracion bajo `/api/api-tj/*`.
- Ya existen clases `ApiTj*` en servicios, middleware, controladores, modelos y pruebas.
- Ya existen tablas `api_tj_inbound_requests` y `api_tj_sync_runs`.
- `beneficiarios` ya fue alterada con campos de integracion y estado de sync.
- `tarjetas` tambien recibio campos de integracion.
- `created_by` de `beneficiarios` ya fue hecho nullable por migracion.
- Existe un `BeneficiarioObserver` global con logica de integracion.
- La seguridad JWT actual existe, pero esta resuelta con config estatica y sin modelo formal de clientes, llaves ni `jti` persistido en tablas dedicadas.

## 3. Decision principal

La implementacion actual de `ApiTj*` se considera `legado de transicion`.

Esto significa:

- No sera la base final del diseno.
- No seguiremos ampliando ese acoplamiento al modelo core.
- La meta oficial sigue siendo la capa de integracion externa al core descrita en el documento base.
- Las piezas nuevas deben nacer bajo namespaces `Integrations\...`.

## 4. Decisiones cerradas

## D-01. Estrategia JWT RS256

Decision:

- La capa compliant usara una libreria mantenida para JWT en lugar de seguir extendiendo el servicio casero actual.
- La opcion elegida para implementacion es `firebase/php-jwt`.

Motivo:

- Reduce riesgo criptografico frente a seguir manteniendo parseo, firma y verificacion manual.
- Encaja bien con el uso de RS256 ya requerido por el documento base.
- Permite concentrar la logica propia en clientes, scopes, `kid`, `jti` e idempotencia.

Alcance:

- El header se podra leer para extraer `kid`, pero ningun claim se tratara como confiable hasta despues de verificar firma y claims requeridos.

## D-02. Regla de `tarjeta_numero` valida para outbound

Decision:

- Se considerara valida una tarjeta relacionada con `estatus = consumida`.
- Si no existe esa relacion valida, se usara `beneficiarios.folio_tarjeta`.
- Si ambos faltan o estan vacios, el item se marca `skipped`.

Motivo:

- En el flujo actual de inventario, `consumida` representa la tarjeta ya ligada a una persona del padron.
- Estados como `disponible`, `asignada_oficina`, `asignada_usuario` o `devuelta` siguen siendo inventario o preasignacion.

## D-03. Contrato de respuestas inbound

Decision:

- El endpoint compliant devolvera JSON de integracion propio.
- `ProblemJsonMiddleware` no sera el contrato principal de este endpoint.

Motivo:

- El documento base define respuestas de negocio especificas como `created`, `already_processed`, `duplicate` y `validation_error`.
- Mezclar RFC 7807 con ese contrato aumentaria la ambiguedad.

## D-04. Estructura de codigo nueva

Decision:

- Las piezas nuevas se organizaran asi:
  - `App\Services\Integrations\Security\...`
  - `App\Services\Integrations\ApiTj\...`
  - `App\Services\Integrations\Inbound\...`
  - `App\Http\Controllers\Api\Integrations\...`
  - `App\Http\Middleware\ValidateIntegrationJwt`
  - `App\Models\Integrations\...`

Motivo:

- Permite construir una capa general de integraciones y no seguir acoplando el sistema a una sola implementacion puntual.

## D-05. Cifrado de payload inbound almacenado

Decision:

- `request_payload_encrypted` se implementara con un encrypter dedicado alimentado por `INTEGRATION_PAYLOAD_ENCRYPTION_KEY`.
- No se adoptara como solucion final el `Crypt` global atado a `APP_KEY`.

Motivo:

- Separa la llave de integracion de la llave general de la aplicacion.
- Alinea el diseño con el documento base.

## D-06. Politica de remediacion del legado

Decision:

- La implementacion actual se congela como referencia operativa.
- Ningun desarrollo nuevo debe seguir dependiendo de:
  - columnas de integracion en `beneficiarios`;
  - columnas de integracion en `tarjetas`;
  - `BeneficiarioObserver`;
  - rutas `/api/api-tj/inbound` y `/api/api-tj/sync`;
  - scopes legacy como `beneficiarios.create`.

Ruta objetivo:

- inbound nuevo: `POST /api/v1/integrations/api-tj/staging/accept`
- scope inbound nuevo: `beneficiarios.staging.push`
- outbound nuevo: `POST /api/v1/cardholders/sync`
- payload outbound nuevo: `sync_id` + `items` minimos definidos en el documento base

## 5. Que se conserva temporalmente

- `api_tj_inbound_requests`
- `api_tj_sync_runs`
- pruebas feature actuales de `ApiTj*`
- `config/api_tj.php`

Estas piezas se conservan como referencia y para no perder trazabilidad mientras construimos la capa compliant.

## 6. Que se reemplaza en fases siguientes

- columnas `source_system`, `source_external_request_id`, `curp_hash`, `status`, `api_tj_sync_*` en `beneficiarios`
- `created_by` nullable en `beneficiarios`
- columnas `source_system` e `is_digital` en `tarjetas` si no tienen justificacion fuera de integracion
- `BeneficiarioObserver`
- `ValidateApiTjJwt`
- `ApiTjJwtService`
- rutas y controladores legacy `ApiTj*` que no cumplan el contrato objetivo

## 7. Resultado de Fase 0

La Fase 0 queda cubierta porque ya resolvimos:

- la estrategia de JWT;
- la regla funcional de `tarjeta_numero`;
- el contrato de respuestas inbound;
- la estructura de codigo nueva;
- la estrategia de cifrado de payload;
- la postura oficial frente al legado de `main`.

El siguiente bloque ya puede entrar a Fase 1 sin seguir profundizando el acoplamiento actual al core.
