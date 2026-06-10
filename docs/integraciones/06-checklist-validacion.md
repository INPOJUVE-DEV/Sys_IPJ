# Checklist de validación de integración

Este checklist debe usarse antes de aprobar PRs relacionados con la integración entre Sys_IPJ, API_TJ y Unidad de Informática.

## 1. Documentación

- [ ] Existe documento de arquitectura actualizado.
- [ ] Existe contrato API actualizado.
- [ ] Existe flujo operativo actualizado.
- [ ] Existe plan de implementación actualizado.
- [ ] El PR indica si toca código, migraciones o solo documentación.

## 2. Modelo de datos

- [ ] No se modifica la tabla `beneficiarios`.
- [ ] No se modifica el modelo `Beneficiario` para metadatos de integración.
- [ ] No se agregan columnas `source_system` o equivalentes a tablas core.
- [ ] No se agrega `curp_hash` a `beneficiarios`.
- [ ] No se agregan estados de API_TJ a `beneficiarios`.
- [ ] No se hace `created_by` nullable por integración.
- [ ] Las nuevas tablas tienen propósito de integración claro.
- [ ] Las nuevas tablas tienen índices y restricciones necesarias.
- [ ] Las nuevas tablas tienen rollback razonable.

## 3. Seguridad

- [ ] Usa JWT firmado RS256 o alternativa aprobada.
- [ ] Valida `iss`.
- [ ] Valida `aud`.
- [ ] Valida `exp`.
- [ ] Valida `iat`.
- [ ] Valida `kid`.
- [ ] Valida `scope`.
- [ ] Implementa anti-replay con `jti`.
- [ ] Usa HTTPS en ambientes reales.
- [ ] No hay llaves privadas en el repositorio.
- [ ] No hay tokens hardcodeados.
- [ ] No se registran tokens completos en logs.

## 4. Sincronización Sys_IPJ → API_TJ

- [ ] El flujo es manual en primera versión.
- [ ] Se registra una corrida de sincronización.
- [ ] Se registra resultado por ítem.
- [ ] El payload contiene solo padrón mínimo.
- [ ] No se envía expediente completo.
- [ ] No se persiste CURP en claro fuera del core.
- [ ] Se maneja error total.
- [ ] Se maneja error parcial.
- [ ] Se maneja timeout.
- [ ] Se evita reintento automático no controlado.

## 5. Staging API_TJ → Sys_IPJ

- [ ] API_TJ envía solo staging aprobado manualmente.
- [ ] Sys_IPJ valida JWT y scope.
- [ ] Sys_IPJ valida idempotencia por `external_request_id`.
- [ ] Sys_IPJ audita el request en tabla separada.
- [ ] Sys_IPJ valida CURP.
- [ ] Sys_IPJ valida duplicados.
- [ ] Sys_IPJ valida domicilio.
- [ ] Sys_IPJ valida seccional y municipio.
- [ ] Sys_IPJ usa `created_by` institucional definido.
- [ ] Sys_IPJ no guarda metadatos externos en `beneficiarios`.

## 6. Pruebas

- [ ] Tests unitarios de hash CURP.
- [ ] Tests unitarios de máscara CURP.
- [ ] Tests de firma JWT.
- [ ] Tests de validación JWT.
- [ ] Tests de scope incorrecto.
- [ ] Tests de `jti` repetido.
- [ ] Tests de payload inválido.
- [ ] Tests de duplicado.
- [ ] Tests de éxito.
- [ ] Tests de idempotencia.

## 7. Deploy

- [ ] Hay backup antes de migraciones.
- [ ] Se revisó `php artisan migrate:status`.
- [ ] Las variables de entorno están configuradas.
- [ ] Las llaves privadas están fuera del repositorio.
- [ ] Se validó en staging.
- [ ] Se tiene plan de rollback de código.
- [ ] Se tiene plan de rollback/corrección de base de datos.

## 8. Criterios de bloqueo

Bloquear el PR si ocurre cualquiera de estos casos:

- [ ] Modifica `beneficiarios` para integración.
- [ ] Hace `created_by` nullable.
- [ ] Agrega observer global sobre `Beneficiario` para sync.
- [ ] Usa token estático compartido.
- [ ] No tiene auditoría.
- [ ] No tiene idempotencia.
- [ ] Guarda llaves privadas.
- [ ] Permite escritura directa desde API_TJ a la base de Sys_IPJ.
- [ ] Permite que Unidad de Informática escriba en Sys_IPJ.

## 9. Aprobación mínima

Antes de mergear implementación:

- [ ] Revisión técnica.
- [ ] Revisión de modelo de datos.
- [ ] Revisión de seguridad.
- [ ] Revisión funcional.
- [ ] Pruebas locales.
- [ ] Pruebas en staging.

## 10. Regla final

Si una solución requiere tocar `beneficiarios` para resolver integración, la solución debe rediseñarse.
