# Deuda Tecnica Sync Sys_IPJ API_TJ

Registro de deuda tecnica y riesgos reales detectados en `main`.

## Estados permitidos

- `Abierta`
- `En curso`
- `Mitigada`
- `Cerrada`

## Registro

| ID | Area | Hallazgo actual | Impacto | Tratamiento recomendado | Momento sugerido | Estado |
| --- | --- | --- | --- | --- | --- | --- |
| DT-01 | Dominio beneficiarios | La duplicacion principal de alta y guardado de domicilio ya fue extraida a `BeneficiarioRegistrationService` y reutilizada en los flujos de beneficiario e inscripcion | El riesgo principal de una tercera variante inconsistente para inbound quedo removido | Mantener el servicio compartido como unico punto de escritura para altas y actualizaciones de beneficiarios | Cubierta antes de Fase 4 | Cerrada |
| DT-02 | Contratos API | La integracion necesita respuestas JSON de negocio propias y no debe depender del contrato general de errores | Riesgo de mezclar contratos si no se define desde el inicio | Mantener contrato dedicado para inbound | Cerrada en Fase 0 | Cerrada |
| DT-03 | Seguridad criptografica | La capa formal JWT ya quedo instalada y validada en runtime con `firebase/php-jwt` | Riesgo principal mitigado para autenticacion inbound | Mantener pruebas y rotacion de llaves al avanzar a flujos reales | Cerrada en Fase 2 | Cerrada |
| DT-04 | Configuracion | Ya existe `config/integrations.php`, pero su uso por ambiente aun debe validarse en despliegues reales | Riesgo de configuracion parcial o inconsistente entre entornos | Validar variables y llaves en cada ambiente al habilitar outbound e inbound | Fase 3 y 4 | Mitigada |
| DT-05 | Operacion de clientes | Ya existe persistencia base para clientes y llaves, y Fase 3 ya consume firma saliente, pero aun no hay flujo operativo completo de administracion y rotacion | Operacion parcial | Construir administracion y rotacion real sobre la persistencia ya creada | Fase 4 en adelante | Mitigada |
| DT-06 | Observabilidad | Inbound y outbound ya registran trazabilidad base y ya existen vistas admin de consulta y readiness, pero aun faltan logs estructurados de explotacion y observabilidad de rollout | Observabilidad operativa base cubierta, endurecimiento pendiente | Completar diagnostico operativo y trazas de explotacion al cerrar rollout | Fase 6 | Mitigada |
| DT-07 | Testing | Ya existen y ya corrieron pruebas focalizadas de persistencia, signer, middleware JWT, outbound, inbound y migraciones de integracion, pero la validacion E2E con ambientes reales sigue siendo operativa y no automatizada | Cobertura fuerte para el alcance implementado; cierre operativo pendiente | Ejecutar rollout real con evidencia por ambiente y ampliar solo si aparecen huecos de explotacion | Post implementacion | Mitigada |
| DT-08 | Regla de tarjeta valida | El documento base no fijaba que estado de tarjeta es valido para outbound | Ambiguedad funcional | Quedo resuelto en Fase 0: solo `consumida`, luego fallback a `folio_tarjeta` | Cerrada en Fase 0 | Cerrada |
| DT-09 | Cifrado de payload inbound | No existia estrategia cerrada para `request_payload_encrypted` | Riesgo de solucion improvisada | Quedo resuelto en Fase 0: llave dedicada `INTEGRATION_PAYLOAD_ENCRYPTION_KEY` | Cerrada en Fase 0 | Cerrada |
| DT-10 | Superficie admin | Ya existe una superficie admin para disparar sync, consultar corridas e inspeccionar inbound requests | La operacion base ya tiene punto de control institucional | Mantener la superficie alineada al rollout y a las necesidades reales de operacion | Cerrada en Fase 5 | Cerrada |
| DT-11 | Usuario tecnico | Ya existe `ApiTjTechnicalUserResolver` y `IntegrationTechnicalUserSeeder` para sostener `created_by` obligatorio en altas inbound | El bloqueo operativo para abrir el endpoint inbound quedo resuelto a nivel aplicacion y pruebas | Mantener configurado `API_TJ_INTEGRATION_USER_EMAIL` por ambiente | Cubierta antes de Fase 4 | Cerrada |
| DT-12 | Reglas de dominio dispersas | La resolucion de seccion y municipio ya quedo centralizada para altas y actualizaciones de beneficiarios en `BeneficiarioLocationResolver`, pero aun existen validaciones afines fuera de ese flujo | Queda dispersion residual fuera del camino principal de registro | Seguir convergiendo validaciones relacionadas al resolver compartido cuando se toquen esos modulos | Durante Fase 4 y endurecimiento posterior | Mitigada |
| DT-13 | Tooling local | En Windows con PHP portatil, `artisan test` no hereda de forma confiable las extensiones CLI; la ejecucion reproducible actual es `vendor/bin/phpunit` o CI con PHP configurado | Friccion operativa local, no bloqueo funcional | Documentar wrapper o script de ejecucion reproducible para Composer y PHPUnit | Fase 3 | Mitigada |
| DT-14 | Tooling Docker | El entrypoint ya evita `cache:clear` por defecto y elimino el bloqueo por permisos, pero aun emite ruido al listar `/etc/secrets` cuando el contenedor no monta secretos | Riesgo bajo, pero deja salida ruidosa en ejecuciones Docker locales | Ajustar el entrypoint para que la inspeccion de secretos sea condicional al directorio montado | Post MVP o al endurecer DX local | Mitigada |

## Priorizacion vigente

No queda deuda critica abierta que bloquee el cierre de implementacion.

Debe seguirse dentro del MVP compliant:

- DT-12

Puede mitigarse y cerrarse despues del MVP si queda evidencia operativa:

- DT-14
- DT-13

## Notas de cierre de Fase 0

Quedaron cubiertas y cerradas en documentacion:

- DT-02
- DT-08
- DT-09

## Notas de cierre de Fase 2

Quedo cubierta y cerrada en implementacion validada:

- DT-03

## Notas de cierre de Fase 3

Quedaron mitigadas con implementacion y pruebas:

- DT-06
- DT-07

## Notas de cobertura previas a Fase 4

Quedaron cubiertas antes de avanzar:

- DT-01
- DT-11
- DT-14

Quedo mitigada con extraccion de servicios compartidos:

- DT-12

## Notas de cierre de Fase 4

Quedaron reforzadas con implementacion validada:

- DT-06
- DT-07

## Notas de cierre de Fase 5

Quedo cerrada la deuda de superficie operativa:

- DT-10

Quedo reforzada la observabilidad base:

- DT-06

## Notas de cierre de Fase 6

Quedo reforzada la cobertura de pruebas y despliegue documental:

- DT-07
