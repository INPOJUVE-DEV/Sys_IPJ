# Deuda Tecnica Sync Sys_IPJ API_TJ

Registro de deuda tecnica detectada al comparar `docs/codex/implementacion-sync-sys-ipj-api-tj.md` contra el estado actual del repo.

## Como usar este documento

- Cada item debe mantenerse con estado `Abierta`, `En curso`, `Mitigada` o `Cerrada`.
- La deuda tecnica aqui listada no sustituye el roadmap; sirve para no perder de vista riesgos estructurales mientras se implementa el MVP.
- Cuando una deuda se atienda dentro de una fase del roadmap, enlazar el PR o commit en la columna de seguimiento.

## Registro

| ID | Area | Hallazgo actual | Impacto | Tratamiento recomendado | Momento sugerido | Estado | Seguimiento |
| --- | --- | --- | --- | --- | --- | --- | --- |
| DT-01 | Dominio beneficiarios | La logica de alta y guardado de domicilio ya esta duplicada entre `BeneficiarioController` e `InscripcionController` | Alta probabilidad de una tercera variante inconsistente al implementar inbound | Extraer `BeneficiarioRegistrationService` y reutilizarlo en web, inscripciones e integracion | Antes de cerrar inbound | Abierta | |
| DT-02 | Contratos API | `ProblemJsonMiddleware` devuelve RFC 7807 para errores API, mientras el documento de integracion define respuestas JSON de negocio como `accepted`, `status`, `message` | Riesgo de respuestas inconsistentes o de adaptar excepciones de forma ad hoc | Definir una estrategia explicita para endpoints de integracion: responder manualmente o encapsular un responder dedicado | Fase 0 | Abierta | |
| DT-03 | Seguridad criptografica | El proyecto no tiene aun una abstraccion JWT RS256 ni una politica de rotacion de llaves implementada | Error de seguridad o deuda operativa si se improvisa sobre el endpoint inbound | Crear capa de seguridad separada, soporte `kid` y procedimiento de alta/rotacion de llaves | Fase 2 | Abierta | |
| DT-04 | Configuracion | `.env.example` no contiene variables de integracion y hoy no existe `config/integrations.php` | Riesgo de configuracion dispersa, onboarding lento y errores por ambiente | Centralizar variables en un archivo de config y documentar defaults seguros | Fase 1 o 2 | Abierta | |
| DT-05 | Operacion de clientes | No existe comando, seeder o panel para administrar `integration_clients` e `integration_client_keys` | Dependencia manual peligrosa para despliegues y rotacion de llaves | Crear seed inicial y evaluar comando admin para altas/rotaciones | Fase 1 | Abierta | |
| DT-06 | Observabilidad | Hay `activity_log` del dominio, pero no hay estandar de observabilidad para corridas de integracion, reintentos, request IDs ni alertado | Dificulta soporte, auditoria y diagnostico de fallas | Usar tablas de integracion como fuente principal y agregar logs estructurados con `sync_id` y `external_request_id` | Fase 3 y 4 | Abierta | |
| DT-07 | Testing | No hay helpers de prueba para JWT firmado, `jti` replay ni corridas con colas | La cobertura puede quedar costosa de mantener y facil de omitir | Crear fixtures/utilidades de testing para tokens, claves y payloads base | Fase 2 antes de ampliar feature tests | Abierta | |
| DT-08 | Regla de tarjeta valida | `Tarjeta` tiene varios estados (`disponible`, `asignada_oficina`, `asignada_usuario`, `consumida`, `devuelta`, `extraviada`, `bloqueada`) y el documento solo dice "tarjeta relacionada valida" | Ambiguedad funcional en el selector outbound y riesgo de sincronizar tarjetas incorrectas | Cerrar criterio funcional y dejarlo codificado en `CardholderSyncSelector` con tests | Fase 0 | Abierta | |
| DT-09 | Cifrado de payload inbound | El documento pide `request_payload_encrypted`, pero el repo no tiene hoy una estrategia especifica para cifrado aplicacion-a-tabla fuera de `Crypt` | Riesgo de inconsistencia entre ambientes o imposibilidad de reprocesar/inspeccionar soporte | Elegir mecanismo, documentar formato y validar rotacion/backup de llaves | Fase 0 o 1 | Abierta | |
| DT-10 | Superficie admin | Existen modulos admin para catalogos, inventario y beneficiarios, pero no hay un patron aun para pantallas de integracion y detalle de corridas | El MVP puede quedar operable solo por base de datos o endpoint sin visibilidad suficiente | Definir una UI minima de corridas y detalle de errores antes del rollout | Fase 5 | Abierta | |
| DT-11 | Dependencia de usuario tecnico | No hay evidencia en seeders de un usuario tecnico `API_TJ_INTEGRATION_USER_EMAIL` | El endpoint inbound podria fallar en despliegue por prerequisito faltante | Incorporar validacion de health/readiness o seeder explicito para ambientes controlados | Fase 4 y rollout | Abierta | |
| DT-12 | Reglas de dominio dispersas | La resolucion de seccion y validacion de municipio vive hoy repartida entre controladores, request classes y `SeccionResolver` | Dificulta reutilizacion limpia desde integracion y aumenta la deuda accidental | Reunir reglas en un servicio de aplicacion o helper de dominio con pruebas unitarias | Durante extraccion del servicio de registro | Abierta | |

## Priorizacion sugerida

### Debe resolverse dentro del MVP

- DT-01
- DT-02
- DT-03
- DT-04
- DT-05
- DT-07
- DT-08
- DT-09
- DT-11
- DT-12

### Puede mitigarse y cerrar despues del MVP si hay evidencia operativa suficiente

- DT-06
- DT-10

## Criterios para cerrar un item

- Debe existir cambio de codigo o documento verificable en el repo.
- Debe quedar referencia del PR, commit o evidencia de prueba.
- Si el item no se resuelve por completo, cambiarlo a `Mitigada` y anotar el riesgo remanente.

## Riesgos de no dar seguimiento

- Duplicar reglas de captura y crear divergencias entre alta manual e inbound.
- Abrir una integracion segura en papel pero fragil en operacion real.
- Tener corridas y requests sin trazabilidad suficiente para soporte.
- Mezclar deuda estructural con el MVP y perder visibilidad de que quedo pendiente.
