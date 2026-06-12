# Fase 0 Decisiones de Base Sync Sys_IPJ API_TJ

Estado: `Completada`

Fecha de cierre: `2026-06-10`

## 1. Objetivo de esta fase

Cerrar las decisiones base antes de tocar migraciones, seguridad, endpoints o flujos de sincronizacion.

La verificacion de `main` confirma una base limpia respecto a invasion API_TJ del core. En el estado actual:

- `Beneficiario` no contiene metadatos de integracion.
- La migracion base de `beneficiarios` mantiene `created_by` obligatorio.
- `routes/api.php` no expone rutas `api-tj`.
- No hay trazas de `ApiTj*`, `api_tj_*`, `source_system`, `curp_hash` ni `BeneficiarioObserver`.

Con esto, la Fase 0 se cierra como fase de definicion arquitectonica, no de remediacion.

## 2. Hallazgos verificados en `main`

- `sys_beneficiarios/app/Models/Beneficiario.php` solo contiene campos core del dominio.
- `sys_beneficiarios/database/migrations/2025_08_31_010300_create_beneficiarios_table.php` define `created_by` obligatorio.
- `sys_beneficiarios/routes/api.php` solo expone salud, paginas, auth, secciones, `beneficiarios/cache` y OCR.
- La busqueda de `ApiTj*`, `api_tj_*`, `source_system`, `curp_hash` y `BeneficiarioObserver` no devolvio coincidencias.

## 3. Decision principal

La integracion Sys_IPJ API_TJ se implementara como una capa nueva fuera del core.

Esto significa:

- no se modificara `Beneficiario` para metadatos de integracion;
- no se agregaran campos de integracion a tablas core;
- no se haran nullable campos core para conveniencia de integracion;
- no se agregaran observers globales para sincronizacion;
- toda pieza nueva nacerá bajo namespaces `Integrations\...`.

## 4. Decisiones cerradas

## D-01. Estrategia JWT RS256

Decision:

- La capa de seguridad usara `firebase/php-jwt`.

Motivo:

- Evita implementar firma y verificacion manual.
- Encaja con el requisito RS256 del documento base.
- Permite concentrar la logica propia en clientes, scopes, `kid`, `jti` e idempotencia.

## D-02. Regla de `tarjeta_numero` valida para outbound

Decision:

- Se considerara valida una tarjeta relacionada con `estatus = consumida`.
- Si no existe esa relacion valida, se usara `beneficiarios.folio_tarjeta`.
- Si ambos faltan o estan vacios, el item se marca `skipped`.

Motivo:

- `consumida` representa la tarjeta ya ligada al beneficiario.
- Los demas estados siguen siendo inventario o preasignacion.

## D-03. Contrato de respuestas inbound

Decision:

- El endpoint inbound compliant devolvera JSON de integracion propio.
- No se usara `ProblemJsonMiddleware` como contrato principal de ese endpoint.

Motivo:

- El documento base define respuestas de negocio especificas como `created`, `already_processed`, `duplicate` y `validation_error`.

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

- Permite construir una capa de integraciones alrededor del core sin invadirlo.

## D-05. Cifrado de payload inbound almacenado

Decision:

- `request_payload_encrypted` se implementara con un encrypter dedicado alimentado por `INTEGRATION_PAYLOAD_ENCRYPTION_KEY`.
- No se usara como solucion final el `Crypt` global atado a `APP_KEY`.

Motivo:

- Separa la llave de integracion de la llave general de la aplicacion.
- Alinea el diseño con el documento base.

## D-06. Politica de implementacion

Decision:

- La Fase 1 arranca desde base limpia.
- No se crearan endpoints ni UI en la siguiente fase.
- El primer paso sera solo persistencia nueva de integracion fuera del core.

## 5. Resultado de Fase 0

La Fase 0 queda cubierta porque ya resolvimos:

- la estrategia de JWT;
- la regla funcional de `tarjeta_numero`;
- el contrato de respuestas inbound;
- la estructura de codigo nueva;
- la estrategia de cifrado de payload;
- la politica de arrancar desde base limpia sin tocar el core.

El siguiente bloque puede entrar a Fase 1 con un objetivo acotado: persistencia nueva de integracion fuera del core.
