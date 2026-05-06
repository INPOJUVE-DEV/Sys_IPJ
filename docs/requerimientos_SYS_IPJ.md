# Requerimientos de actualización – Sys_IPJ

## 1. Contexto

API_TJ ya fue actualizada para operar con:

- padrón mínimo en `cardholders_sync`;
- matching por `curp_hash`;
- autenticación sistema-a-sistema con JWT RS256;
- staging cifrado para expedientes recibidos por Unidad de Informática;
- push manual de expedientes desde API_TJ hacia Sys_IPJ;
- activación de cuenta en APP_TARJETAJOVEN mediante Auth0.

Sys_IPJ debe conservarse como la fuente oficial de beneficiarios. API_TJ no debe convertirse en fuente de verdad.

---

## 2. Objetivo del cambio en Sys_IPJ

Actualizar Sys_IPJ para que:

1. sincronice manualmente el padrón mínimo hacia API_TJ;
2. calcule `curp_hash` con el mismo secreto que API_TJ;
3. envíe únicamente datos mínimos a API_TJ;
4. firme las peticiones hacia API_TJ con JWT RS256;
5. reciba expedientes completos enviados desde API_TJ;
6. registre beneficiarios oficiales a partir de esos expedientes;
7. mantenga auditoría de sincronizaciones y recepciones;
8. garantice idempotencia para evitar duplicados.

---

## 3. Datos base disponibles en Sys_IPJ

Sys_IPJ cuenta con modelo `Beneficiario`, con campos como:

- `folio_tarjeta`;
- `tarjeta_id`;
- `nombre`;
- `apellido_paterno`;
- `apellido_materno`;
- `curp`;
- `fecha_nacimiento`;
- `edad`;
- `sexo`;
- `discapacidad`;
- `id_ine`;
- `telefono`;
- `municipio_id`;
- `seccion_id`.

También cuenta con relación a:

- `tarjeta`;
- `municipio`;
- `seccion`;
- `domicilio`.

El modelo `Tarjeta` cuenta con:

- `folio`;
- `estatus`;
- `oficina_id`;
- `usuario_uuid`;
- `municipio_id`;
- `beneficiario_id`;
- `observaciones`.

Los estados de tarjeta disponibles incluyen:

- `disponible`;
- `asignada_oficina`;
- `asignada_usuario`;
- `consumida`;
- `devuelta`;
- `extraviada`;
- `bloqueada`.

---

## 4. Módulo requerido: Sincronización manual con API_TJ

## 4.1 Botón “Sincronizar con app”

Agregar acción manual en Sys_IPJ:

```txt
Sincronizar con app
```

Usuarios autorizados:

- admin;
- delegado.

No debe ejecutarse automáticamente en esta etapa.

---

## 4.2 Endpoint destino en API_TJ

Sys_IPJ debe consumir:

```http
POST /api/v1/cardholders/sync
```

Base URL configurable:

```env
API_TJ_BASE_URL=https://api-tj-url/api/v1
```

---

## 4.3 Payload requerido

Sys_IPJ debe enviar:

```json
{
  "sync_id": "SYSIPJ-20260426-001",
  "items": [
    {
      "curp_hash": "HASH_HMAC_SHA256",
      "curp_masked": "ABCD**********12",
      "tarjeta_numero": "TJ-000123",
      "status": "active"
    }
  ]
}
```

---

## 4.4 Datos mínimos por beneficiario

Cada item debe contener únicamente:

- `curp_hash`;
- `curp_masked`;
- `tarjeta_numero`;
- `status`.

No enviar:

- CURP completa;
- nombre;
- domicilio;
- teléfono;
- fecha de nacimiento;
- datos personales adicionales.

---

## 5. Reglas para construir el padrón mínimo

## 5.1 Beneficiarios elegibles

Enviar solo beneficiarios que:

- no estén eliminados;
- tengan CURP válida;
- tengan tarjeta/folio asignado;
- tengan información suficiente para identificar tarjeta física.

---

## 5.2 Fuente de número de tarjeta

Prioridad recomendada:

1. `beneficiario.tarjeta.folio`, si existe;
2. `beneficiario.folio_tarjeta`, si aún se usa como fallback.

Campo enviado:

```json
"tarjeta_numero": "TJ-000123"
```

---

## 5.3 Status enviado a API_TJ

Mapeo recomendado:

| Estado Sys_IPJ | Estado API_TJ |
|---|---|
| `consumida` | `active` |
| `asignada_usuario` | `active` |
| `bloqueada` | `blocked` |
| `extraviada` | `blocked` |
| `devuelta` | `inactive` |
| `asignada_oficina` | `inactive` |
| `disponible` | `inactive` |

Si no existe tabla `tarjetas` en la DB activa, usar:

```txt
active
```

para todos los beneficiarios con `folio_tarjeta` válido.

---

## 6. Generación de `curp_hash`

Sys_IPJ debe calcular:

```txt
HMAC-SHA256(CURP_NORMALIZADA, CURP_HASH_SECRET)
```

Reglas:

- normalizar CURP con `trim` y `uppercase`;
- usar exactamente el mismo `CURP_HASH_SECRET` configurado en API_TJ;
- no usar `SHA2()` simple;
- no usar hash sin secreto;
- no enviar CURP en claro a API_TJ.

Ejemplo conceptual:

```php
$curpHash = hash_hmac('sha256', strtoupper(trim($curp)), env('CURP_HASH_SECRET'));
```

---

## 7. Generación de `curp_masked`

Formato requerido:

```txt
Primeras 4 letras + ********** + últimas 2 posiciones
```

Ejemplo:

```txt
MELR**********06
```

Ejemplo conceptual:

```php
$normalized = strtoupper(trim($curp));
$masked = substr($normalized, 0, 4) . '**********' . substr($normalized, -2);
```

---

## 8. Autenticación sistema-a-sistema

## 8.1 Tipo de autenticación

Sys_IPJ debe firmar sus peticiones hacia API_TJ usando:

```txt
JWT RS256
```

No usar token fijo para producción.

---

## 8.2 Claims obligatorios

Payload del JWT:

```json
{
  "iss": "sys_ipj",
  "sub": "sys_ipj",
  "aud": "api_tj",
  "iat": 1710000000,
  "exp": 1710000600,
  "jti": "uuid-unico",
  "scope": "cardholders.sync"
}
```

Header:

```json
{
  "alg": "RS256",
  "typ": "JWT",
  "kid": "sys_ipj-current"
}
```

---

## 8.3 Reglas JWT

- algoritmo obligatorio: RS256;
- `iss`: `sys_ipj`;
- `sub`: `sys_ipj`;
- `aud`: `api_tj`;
- `exp`: máximo 10 minutos;
- `jti`: único por request;
- `scope`: `cardholders.sync`;
- `kid`: debe coincidir con la llave pública registrada en API_TJ.

---

## 8.4 Variables requeridas en Sys_IPJ

Agregar al `.env`:

```env
API_TJ_BASE_URL=https://api-tj-url/api/v1
API_TJ_AUDIENCE=api_tj
API_TJ_CLIENT_CODE=sys_ipj
API_TJ_JWT_KID=sys_ipj-current
API_TJ_PRIVATE_KEY_PATH=storage/app/keys/sys_ipj_private.pem
CURP_HASH_SECRET=misma_clave_que_API_TJ
```

Si se decide guardar la llave privada como variable, debe hacerse con manejo seguro y sin commitearla.

---

## 8.5 Llaves RSA

Sys_IPJ debe generar:

```txt
private.pem
public.pem
```

Responsabilidades:

- `private.pem`: se queda en Sys_IPJ;
- `public.pem`: se registra en API_TJ.

API_TJ debe tener configurado:

```env
SYS_IPJ_JWT_PUBLIC_KEY=...
SYS_IPJ_JWT_KID=sys_ipj-current
SYS_IPJ_ALLOWED_SCOPES=["cardholders.sync"]
```

---

## 9. Auditoría de sincronización

Sys_IPJ debe registrar cada sync manual.

Campos mínimos:

- `id`;
- `sync_id`;
- `executed_by`;
- `role`;
- `started_at`;
- `finished_at`;
- `request_count`;
- `success_count`;
- `failed_count`;
- `api_status_code`;
- `api_response_body`;
- `status`;
- `error_message`.

Estados sugeridos:

- `pending`;
- `success`;
- `partial`;
- `failed`.

---

## 10. UI requerida en Sys_IPJ

## 10.1 Vista o acción en panel admin/delegado

Agregar botón:

```txt
Sincronizar con app
```

Debe mostrar:

- total de beneficiarios elegibles;
- confirmación antes de enviar;
- resultado del envío;
- errores si existen.

---

## 10.2 Confirmación previa

Antes de ejecutar:

```txt
Se enviará el padrón mínimo de beneficiarios con tarjeta a API_TJ.
No se enviarán CURP completas ni datos personales.
¿Deseas continuar?
```

---

## 10.3 Resultado

Mostrar:

- total procesado;
- insertados/actualizados según respuesta API_TJ;
- errores;
- `sync_id`.

---

## 11. Recepción de expedientes desde API_TJ

API_TJ podrá enviar a Sys_IPJ expedientes completos que fueron capturados por la Unidad de Informática y almacenados en staging.

Sys_IPJ debe exponer un endpoint receptor.

Endpoint sugerido:

```http
POST /api/integrations/api-tj/beneficiarios
```

---

## 11.1 Payload esperado

Ejemplo:

```json
{
  "external_request_id": "INF-20260426-0001",
  "source": "api_tj",
  "beneficiario": {
    "curp": "CURP_DEL_USUARIO",
    "nombre": "NOMBRE",
    "apellido_paterno": "APELLIDO",
    "apellido_materno": "APELLIDO",
    "fecha_nacimiento": "2000-02-02",
    "sexo": "M",
    "discapacidad": false,
    "id_ine": "INE0001",
    "telefono": "4441234567",
    "domicilio": {
      "calle": "CALLE 1",
      "numero_ext": "10",
      "numero_int": "2",
      "colonia": "CENTRO",
      "municipio_id": 1,
      "codigo_postal": "78000",
      "seccional": "0001"
    }
  }
}
```

---

## 11.2 Validaciones al recibir expediente

Sys_IPJ debe validar:

- `external_request_id` obligatorio;
- CURP obligatoria y válida;
- CURP no duplicada en beneficiarios;
- nombre obligatorio;
- apellidos obligatorios;
- fecha de nacimiento válida;
- sexo válido;
- teléfono válido;
- domicilio completo;
- municipio existente;
- seccional existente o resoluble.

---

## 11.3 Idempotencia

`external_request_id` debe ser único.

Reglas:

- si llega el mismo `external_request_id` ya procesado, no crear duplicado;
- si llega la misma CURP con otro `external_request_id`, responder conflicto;
- si el primer intento falló por error transitorio, permitir reintento controlado.

---

## 11.4 Respuestas esperadas

### Creado

```json
{
  "accepted": true,
  "status": "created",
  "beneficiario_id": "uuid",
  "external_request_id": "INF-20260426-0001"
}
```

### Duplicado por `external_request_id`

```json
{
  "accepted": true,
  "status": "already_processed",
  "beneficiario_id": "uuid",
  "external_request_id": "INF-20260426-0001"
}
```

### Conflicto por CURP

```json
{
  "accepted": false,
  "status": "conflict",
  "message": "La CURP ya existe en Sys_IPJ"
}
```

### Validación fallida

```json
{
  "accepted": false,
  "status": "validation_error",
  "errors": {
    "beneficiario.curp": ["CURP inválida"]
  }
}
```

---

## 12. Autenticación para recepción desde API_TJ

Sys_IPJ debe proteger el endpoint receptor.

Opciones:

1. JWT RS256 firmado por API_TJ;
2. API key temporal solo para pruebas controladas.

Recomendación definitiva:

```txt
JWT RS256 sistema-a-sistema
```

Si se usa API key temporal, debe quedar documentado como no productivo.

---

## 13. Base de datos requerida en Sys_IPJ

Crear tabla para auditoría/idempotencia de expedientes recibidos.

Tabla sugerida:

```txt
api_tj_inbound_requests
```

Campos mínimos:

- `id`;
- `external_request_id` unique;
- `source`;
- `beneficiario_id` nullable;
- `status`;
- `request_hash`;
- `response_status`;
- `error_message`;
- `received_at`;
- `processed_at`;
- `created_by_system`.

---

## 14. Reglas de privacidad

Sys_IPJ puede conservar CURP porque es fuente de verdad del padrón.

Pero para integraciones:

- no enviar CURP completa a API_TJ durante sync;
- no registrar CURP completa en logs técnicos de integración;
- en auditorías técnicas usar `curp_masked` o `curp_hash`;
- proteger payloads de entrada desde API_TJ.

---

## 15. Pruebas requeridas

## 15.1 Sync hacia API_TJ

- sync con beneficiarios válidos;
- sync sin beneficiarios elegibles;
- sync con token válido;
- sync con token vencido;
- sync con `aud` incorrecto;
- sync con scope incorrecto;
- sync con `jti` repetido;
- sync con cambio de tarjeta para mismo beneficiario;
- sync sin enviar CURP completa.

---

## 15.2 Recepción desde API_TJ

- expediente válido;
- expediente duplicado por `external_request_id`;
- expediente duplicado por CURP;
- expediente con domicilio incompleto;
- expediente con municipio inválido;
- expediente con seccional inválida;
- reintento controlado después de error;
- auditoría correcta.

---

## 15.3 UI

- botón visible solo para admin/delegado;
- confirmación antes de sync;
- resultado visible;
- errores legibles;
- registro en auditoría.

---

## 16. Criterios de aceptación

1. Sys_IPJ genera padrón mínimo sin CURP completa.
2. Sys_IPJ calcula `curp_hash` con HMAC-SHA256.
3. Sys_IPJ firma requests hacia API_TJ con JWT RS256.
4. Sys_IPJ puede ejecutar sync manual desde UI.
5. API_TJ recibe y procesa sync correctamente.
6. Sys_IPJ guarda auditoría de cada sync.
7. Sys_IPJ expone receptor para expedientes enviados por API_TJ.
8. Sys_IPJ evita duplicados por `external_request_id`.
9. Sys_IPJ evita duplicados por CURP.
10. Sys_IPJ registra beneficiario oficial a partir del expediente.
11. No se comparte base de datos entre Sys_IPJ y API_TJ.
12. No se envía CURP en claro durante la sincronización del padrón mínimo.

---

## 17. Fuera de alcance

No corresponde a Sys_IPJ:

- crear cuentas de usuario de APP_TARJETAJOVEN;
- manejar Auth0 del usuario final;
- generar QR de la app;
- controlar sesión de la app;
- almacenar credenciales de usuario final;
- administrar beneficios de la app.

---

## 18. Prioridad sugerida

1. Implementar generación de `curp_hash` y `curp_masked`.
2. Implementar cliente HTTP hacia API_TJ.
3. Implementar firma JWT RS256.
4. Implementar botón manual de sync.
5. Implementar auditoría de sync.
6. Implementar receptor de expedientes desde API_TJ.
7. Implementar idempotencia por `external_request_id`.
8. QA completo de sync y recepción.
