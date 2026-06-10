# Deuda Tecnica Sync Sys_IPJ API_TJ

Registro de deuda tecnica y desalineaciones detectadas en la rama `main`.

## Estados permitidos

- `Abierta`
- `En curso`
- `Mitigada`
- `Cerrada`

## Registro

| ID | Area | Hallazgo actual | Impacto | Tratamiento recomendado | Momento sugerido | Estado |
| --- | --- | --- | --- | --- | --- | --- |
| DT-01 | Dominio beneficiarios | La logica de alta y guardado de domicilio ya esta duplicada entre `BeneficiarioController` e `InscripcionController` | Facilita una tercera variante inconsistente para inbound | Extraer `BeneficiarioRegistrationService` y reutilizarlo | Antes de cerrar Fase 4 | Abierta |
| DT-02 | Contratos API | `ProblemJsonMiddleware` convive con respuestas JSON de negocio especificas para integracion | Riesgo de contratos inconsistentes | Mantener contrato dedicado para integracion y no depender de RFC 7807 en inbound | Cerrada en Fase 0 | Cerrada |
| DT-03 | Seguridad criptografica | Existe JWT casero sin modelo formal de clientes, llaves rotables ni `jti` persistido | Riesgo de seguridad y mantenimiento | Reemplazar por capa formal `Integrations\Security\...` | Fase 2 | En curso |
| DT-04 | Configuracion | Ya existe `config/api_tj.php`, pero no una config general compliant de integraciones | Riesgo de configuracion mezclada entre legado y objetivo | Introducir config de integraciones y separar legado de capa nueva | Fase 1 o 2 | Abierta |
| DT-05 | Operacion de clientes | No existen tablas ni flujo formal para administrar clientes y llaves de integracion | Operacion manual fragil | Crear `integration_clients` e `integration_client_keys` | Fase 1 | Abierta |
| DT-06 | Observabilidad | Las tablas actuales no cubren clientes, llaves, replay ni detalle por item | Soporte y auditoria incompletos | Crear auditoria compliant por corrida, item y request inbound | Fase 1, 3 y 4 | Abierta |
| DT-07 | Testing | Las pruebas actuales caracterizan el legado, no el contrato objetivo | Puede ocultar regresiones del diseno nuevo | Agregar suite nueva y conservar la legacy temporalmente | Fase 2 a 6 | Abierta |
| DT-08 | Regla de tarjeta valida | El documento base no fijaba que estado de tarjeta es valido para outbound | Ambiguedad funcional | Quedo resuelto en Fase 0: solo `consumida`, luego fallback a `folio_tarjeta` | Cerrada en Fase 0 | Cerrada |
| DT-09 | Cifrado de payload inbound | No existia estrategia cerrada para `request_payload_encrypted` | Riesgo de solucion improvisada | Quedo resuelto en Fase 0: llave dedicada `INTEGRATION_PAYLOAD_ENCRYPTION_KEY` | Cerrada en Fase 0 | Cerrada |
| DT-10 | Superficie admin | Ya hay UI legacy API_TJ, pero no una superficie compliant separada | Puede consolidar flujos incorrectos | Crear superficie admin nueva o refactorizada para la capa compliant | Fase 5 | Abierta |
| DT-11 | Usuario tecnico | No hay evidencia de un seeder formal del usuario tecnico requerido por el documento base | Riesgo de fallas de despliegue | Crear resolver + precondicion operativa y evaluar seeder | Fase 4 | Abierta |
| DT-12 | Reglas de dominio dispersas | La resolucion de seccion y municipio vive en varios puntos del sistema | Reutilizacion dificil desde integracion | Unificar reglas en servicio de aplicacion | Durante extraccion de servicio de registro | Abierta |
| DT-13 | Invasion del core | `beneficiarios` ya contiene `source_system`, `source_external_request_id`, `curp_hash`, `status`, `api_tj_sync_*` | Contradiccion directa con el documento base | Dejar de depender de esos campos en trabajo nuevo y planear remediacion | Antes de cerrar Fase 1 | Abierta |
| DT-14 | Nullable no permitido | `created_by` de `beneficiarios` ya fue hecho nullable en `main` | Rompe una restriccion explicita del diseno objetivo | Recuperar estrategia con usuario tecnico institucional y retirar dependencia del nullable | Antes de cerrar Fase 4 | Abierta |
| DT-15 | Observer global | Existe `BeneficiarioObserver` con logica de integracion | Acopla el core a un integrador puntual | Removerlo del flujo compliant y mover la logica a servicios explicitos | Antes de cerrar Fase 4 | Abierta |
| DT-16 | Drift de contrato | Las rutas y scopes actuales no coinciden con el contrato objetivo | Riesgo de consolidar endpoints equivocados | Tratar las rutas actuales como legado y abrir contrato nuevo en `/api/v1/integrations/api-tj/...` | Fase 2 y 4 | Abierta |
| DT-17 | Logica funcional divergente | El legado actual auto-sincroniza inbound, genera folios digitales y usa payloads distintos al minimo definido | Riesgo de seguir construyendo sobre supuestos no aprobados | Congelar legado y reconstruir compliant con criterios del documento base | Fase 3 y 4 | Abierta |
| DT-18 | Tarjetas invadidas | `tarjetas` ya recibio campos de integracion como `source_system` e `is_digital` | Segunda invasion del core | Evaluar si sobreviven por negocio propio o salen con la remediacion | Despues de Fase 1, antes de cierre final | Abierta |

## Priorizacion del MVP

Debe resolverse dentro del MVP compliant:

- DT-01
- DT-03
- DT-04
- DT-05
- DT-06
- DT-07
- DT-11
- DT-12
- DT-13
- DT-14
- DT-15
- DT-16
- DT-17

Puede mitigarse y cerrarse despues del MVP si queda evidencia operativa:

- DT-10
- DT-18

## Notas de cierre de Fase 0

Quedaron cubiertas y cerradas en documentacion:

- DT-02
- DT-08
- DT-09
