# Resumen de sistemas e integración

Este documento define el rol de cada sistema dentro del ecosistema Tarjeta Joven y la relación esperada entre ellos.

## Sistemas involucrados

### Sys_IPJ

Sistema institucional de administración.

Responsabilidades:

- Ser fuente de verdad del padrón oficial de beneficiarios.
- Administrar captura, edición, consulta y exportación de beneficiarios.
- Administrar domicilio, municipio, seccional y datos institucionales asociados.
- Administrar tarjetas físicas o digitales cuando corresponda al flujo institucional.
- Exponer contratos controlados para integraciones.
- Recibir expedientes aprobados desde API_TJ cuando deban convertirse en beneficiarios oficiales.

Restricciones:

- No debe convertir `beneficiarios` en tabla de staging.
- No debe agregar campos de API_TJ o Unidad de Informática al modelo core.
- No debe permitir escritura directa de sistemas externos a la base de datos.

### API_TJ

API del canal digital Tarjeta Joven.

Responsabilidades:

- Mantener padrón mínimo sincronizado para validación y activación.
- Permitir lookup de elegibilidad por CURP sin persistir CURP en claro.
- Guardar expedientes temporales en staging cifrado.
- Permitir revisión y envío manual de staging hacia Sys_IPJ.
- Gestionar activación de cuenta digital.
- Separar autenticación de beneficiarios, administradores e integraciones sistema-a-sistema.

Restricciones:

- No es fuente de verdad del padrón oficial.
- No debe dar de alta beneficiarios oficiales por sí mismo.
- No debe escribir directamente en la base de Sys_IPJ.

### Unidad de Informática

Sistema externo consumidor de API_TJ.

Responsabilidades:

- Consultar si una CURP ya existe en el padrón sincronizado.
- Enviar expediente completo a staging de API_TJ cuando la CURP no exista.
- Firmar sus peticiones con credenciales propias.

Restricciones:

- No debe escribir directamente en Sys_IPJ.
- No debe consumir endpoints internos de administración.
- No debe compartir llaves privadas ni tokens con frontend.

### App_TJ / PWA

Canal de usuario final.

Responsabilidades:

- Permitir activación de cuenta.
- Validar identidad con `tarjeta_numero + CURP` cuando aplique.
- Consumir información pública y datos permitidos por API_TJ.

Restricciones:

- No debe portar llaves privadas.
- No debe consumir endpoints sistema-a-sistema.
- No debe enviar expedientes institucionales completos directamente a Sys_IPJ.

## Flujo general acordado

```txt
Sys_IPJ
  └── fuente oficial de beneficiarios
        ↓ sync manual de padrón mínimo
API_TJ
  ├── cardholders_sync
  ├── lookup por CURP hash
  ├── staging cifrado
  └── activación digital
        ↑
Unidad de Informática
  └── consulta y crea staging si no existe

API_TJ
  └── push manual de staging aprobado
        ↓
Sys_IPJ
  └── valida y crea beneficiario oficial
```

## Flujo 1: padrón mínimo Sys_IPJ → API_TJ

Objetivo:

- Permitir que API_TJ valide si una persona ya existe y si tiene tarjeta.

Datos enviados:

- `curp_hash`
- `curp_masked`
- `tarjeta_numero`
- `status`
- `synced_at`

Reglas:

- Sys_IPJ no modifica `beneficiarios` para producir este payload.
- El estado de sincronización se guarda en tablas separadas de integración.
- API_TJ no recibe el expediente completo en este flujo.

## Flujo 2: lookup Unidad de Informática → API_TJ

Objetivo:

- Consultar si una CURP ya existe en el padrón sincronizado.

Reglas:

- Unidad de Informática envía CURP a API_TJ.
- API_TJ normaliza, calcula hash y busca.
- API_TJ no persiste la CURP en claro.
- Si existe, responde tarjeta asociada.
- Si no existe, responde `404`.

## Flujo 3: staging Unidad de Informática → API_TJ

Objetivo:

- Guardar temporalmente el expediente completo de una persona no encontrada.

Reglas:

- Solo se usa cuando lookup responde `404`.
- API_TJ cifra el expediente.
- API_TJ marca el registro como `pending`.
- Este registro no es beneficiario oficial.

## Flujo 4: staging aprobado API_TJ → Sys_IPJ

Objetivo:

- Convertir un expediente temporal revisado en beneficiario oficial.

Reglas:

- Un usuario autorizado de API_TJ inicia el push.
- API_TJ manda el expediente a un endpoint documentado de Sys_IPJ.
- Sys_IPJ valida datos, seccional, municipio, duplicados y reglas de captura.
- Sys_IPJ crea el beneficiario oficial si procede.
- API_TJ guarda la respuesta y actualiza el staging.

## Decisiones cerradas

Estas decisiones no deben reabrirse salvo cambio formal de arquitectura:

1. Sys_IPJ es fuente de verdad.
2. API_TJ no es sistema de alta oficial.
3. La tabla `beneficiarios` no se modifica por integración.
4. El staging vive fuera de Sys_IPJ o en tablas separadas cuando Sys_IPJ reciba solicitudes externas.
5. La sincronización de padrón es manual hasta que se apruebe automatización.
6. La CURP no debe persistirse en claro fuera de flujos estrictamente necesarios.
7. Las integraciones usan autenticación sistema-a-sistema con scopes.
8. La Unidad de Informática no tiene acceso directo a base de datos ni rutas internas.

## Antipatrones prohibidos

No implementar:

- API_TJ escribiendo directo en tablas de Sys_IPJ.
- Campos API_TJ dentro de `beneficiarios`.
- `created_by = null` para altas externas.
- Observers globales en `Beneficiario` para sincronización.
- Endpoints que mezclen staging con alta oficial sin revisión.
- Tokens estáticos compartidos entre sistemas.

## Documentos relacionados

- `docs/arquitectura/modelo-datos-core.md`
- `docs/integraciones/01-contrato-sys-ipj-api-tj.md`
- `docs/integraciones/02-seguridad-jwt-rs256.md`
