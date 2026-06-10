# Modelo de datos core de Sys_IPJ

Este documento define las reglas del modelo de datos principal de Sys_IPJ y los límites que deben respetar futuras integraciones.

## Principio rector

Sys_IPJ es la fuente de verdad del padrón oficial de beneficiarios.

La tabla `beneficiarios` representa la columna principal del proyecto. No debe convertirse en tabla de integración, staging, sincronización ni bitácora de otros sistemas.

## Tablas core protegidas

Las siguientes tablas forman parte del núcleo operativo:

- `beneficiarios`
- `domicilios`
- `tarjetas`
- `users`
- `municipios`
- `secciones`

Estas tablas pueden evolucionar por necesidades funcionales propias de Sys_IPJ, pero no deben modificarse para resolver detalles técnicos de sistemas externos.

## Regla específica sobre `beneficiarios`

La tabla `beneficiarios` no debe recibir columnas de integración.

No se permite agregar campos como:

- `source_system`
- `source_external_request_id`
- `curp_hash`
- `curp_masked`
- `api_tj_sync_status`
- `api_tj_sync_attempts`
- `api_tj_last_sync_error`
- `api_tj_last_synced_at`
- `external_status`
- `integration_status`

Tampoco se debe modificar `created_by` para permitir altas externas sin usuario institucional responsable.

## Qué no debe hacerse

Queda prohibido para integraciones externas:

1. Usar `beneficiarios` como staging.
2. Usar `beneficiarios` como tabla de estado de sincronización.
3. Agregar metadatos de API_TJ, Unidad de Informática u otro sistema externo en `beneficiarios`.
4. Registrar observers globales sobre `Beneficiario` para sincronizar con sistemas externos.
5. Alterar validaciones core de beneficiarios para adaptarlas a contratos externos.
6. Relajar integridad de datos del padrón oficial por conveniencia de integración.

## Qué sí se permite

Si una integración necesita persistencia, debe usar tablas separadas.

Ejemplos permitidos:

- `integration_sync_runs`
- `integration_sync_items`
- `integration_inbound_requests`
- `integration_inbound_errors`
- `integration_outbox_messages`
- `integration_audit_logs`

Estas tablas pueden referenciar `beneficiarios.id` cuando sea necesario, pero no deben modificar la estructura de `beneficiarios`.

## Patrón recomendado: sync run + sync item

Para envíos o sincronizaciones desde Sys_IPJ hacia otros sistemas:

### `integration_sync_runs`

Representa una ejecución de sincronización.

Campos sugeridos:

- `id`
- `target_system`
- `operation`
- `status`
- `started_at`
- `finished_at`
- `requested_by`
- `total_items`
- `success_count`
- `failed_count`
- `error_message`
- `created_at`
- `updated_at`

### `integration_sync_items`

Representa cada beneficiario o entidad incluida en una sincronización.

Campos sugeridos:

- `id`
- `sync_run_id`
- `beneficiario_id`
- `payload_hash`
- `status`
- `response_code`
- `response_body`
- `error_message`
- `created_at`
- `updated_at`

Regla:

- El estado de envío queda en `integration_sync_items`.
- El padrón oficial queda intacto en `beneficiarios`.

## Patrón recomendado: inbound request

Para solicitudes recibidas desde sistemas externos:

### `integration_inbound_requests`

Representa una solicitud externa recibida por Sys_IPJ.

Campos sugeridos:

- `id`
- `source_system`
- `external_request_id`
- `operation`
- `status`
- `request_hash`
- `request_payload`
- `response_code`
- `response_body`
- `error_message`
- `received_at`
- `processed_at`
- `created_at`
- `updated_at`

Regla:

- La solicitud externa se audita en tabla separada.
- Si el expediente es válido, Sys_IPJ crea o actualiza entidades core mediante servicios de dominio propios.
- La auditoría de integración no vive dentro de `beneficiarios`.

## Relación con API_TJ

API_TJ no debe escribir directamente en la base de datos de Sys_IPJ.

La relación correcta es mediante API documentada:

- Sys_IPJ envía padrón mínimo a API_TJ.
- API_TJ guarda staging temporal cifrado.
- API_TJ envía expedientes aprobados hacia Sys_IPJ mediante endpoint de integración.
- Sys_IPJ valida y decide si crea el beneficiario oficial.

## Relación con Unidad de Informática

Unidad de Informática no debe consumir directamente la base de datos de Sys_IPJ.

Su flujo debe pasar por API_TJ cuando el objetivo sea:

- consultar elegibilidad por CURP;
- crear expedientes temporales para revisión;
- evitar duplicidad contra el padrón sincronizado.

## Criterio para aceptar una migración nueva

Antes de aceptar una migración relacionada con integraciones, debe responderse:

1. ¿Modifica una tabla core?
2. ¿Puede resolverse con tabla separada?
3. ¿Tiene contrato documentado?
4. ¿Tiene rollback seguro?
5. ¿Tiene pruebas?
6. ¿Tiene política de auditoría?
7. ¿Evita almacenar CURP en claro fuera del core?

Si la migración modifica `beneficiarios` para una integración externa, debe rechazarse salvo aprobación explícita de arquitectura.

## Resumen ejecutivo

El modelo core no se adapta a cada integración.

Las integraciones se adaptan al modelo core mediante contratos, servicios y tablas separadas.
