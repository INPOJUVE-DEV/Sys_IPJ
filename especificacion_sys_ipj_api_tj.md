# Especificación técnica – Integración Sys_IPJ con API_TJ

## 1. Objetivo

Implementar en Sys_IPJ el receptor formal de expedientes enviados desde API_TJ y el mecanismo de sincronización manual del padrón mínimo hacia API_TJ.

El flujo final debe conservar a Sys_IPJ como fuente oficial de beneficiarios y tarjetas.

---

## 2. Flujo funcional completo

### 2.1 Alta de beneficiario desde Unidad de Informática

1. Unidad de Informática consulta API_TJ por CURP.
2. Si API_TJ no encuentra CURP en `cardholders_sync`, Unidad de Informática envía expediente completo a API_TJ.
3. API_TJ guarda el expediente en `beneficiario_staging`, cifrado y en estado `pending`.
4. Un administrador ejecuta el push manual desde API_TJ hacia Sys_IPJ.
5. Sys_IPJ recibe el expediente.
6. Sys_IPJ valida duplicados y estructura.
7. Sys_IPJ crea el beneficiario oficial y su domicilio.
8. Sys_IPJ registra auditoría e idempotencia.
9. Sys_IPJ responde a API_TJ.
10. Cuando el beneficiario tenga tarjeta asignada en Sys_IPJ, Sys_IPJ sincroniza el padrón mínimo hacia API_TJ.
11. API_TJ actualiza `cardholders_sync`.
12. APP_TARJETAJOVEN puede activar cuenta usando tarjeta + CURP.

---

## 3. Cambios requeridos en Sys_IPJ

### 3.1 Crear endpoint receptor desde API_TJ

Agregar endpoint:

```http
POST /api/integrations/api-tj/beneficiarios
```

Responsabilidad:

- Recibir expedientes enviados desde API_TJ.
- Validar autenticación.
- Validar idempotencia.
- Validar CURP y datos obligatorios.
- Crear beneficiario oficial.
- Crear domicilio asociado.
- Registrar auditoría.
- Responder con contrato estable.

### 3.2 Payload esperado desde API_TJ

```json
{
  "external_request_id": "INF-20260426-0001",
  "beneficiario": {
    "curp": "CURP_DEL_USUARIO",
    "nombre": "NOMBRE",
    "apellido_paterno": "APELLIDO_PATERNO",
    "apellido_materno": "APELLIDO_MATERNO",
    "fecha_nacimiento": "2000-02-02",
    "sexo": "M",
    "discapacidad": false,
    "id_ine": "INE0001",
    "telefono": "4441234567",
    "domicilio": {
      "calle": "CALLE 1",
      "numero_ext": "10",
      "numero_int": null,
      "colonia": "CENTRO",
      "municipio_id": 1,
      "codigo_postal": "78000",
      "seccional": "0001"
    }
  }
}
```

### 3.3 Validaciones obligatorias

Sys_IPJ debe validar:

- `external_request_id` obligatorio.
- `external_request_id` único.
- CURP obligatoria.
- CURP con formato válido.
- CURP no duplicada en `beneficiarios`.
- Nombre obligatorio.
- Apellido paterno obligatorio.
- Apellido materno obligatorio.
- Fecha de nacimiento válida.
- Sexo válido.
- Discapacidad booleana.
- ID INE obligatorio.
- Teléfono obligatorio.
- Domicilio obligatorio.
- Calle obligatoria.
- Número exterior obligatorio.
- Colonia obligatoria.
- Municipio válido.
- Código postal válido.
- Seccional válido o resoluble a `seccion_id`.

### 3.4 Idempotencia

Crear tabla:

```txt
api_tj_inbound_requests
```

Campos sugeridos:

```txt
id
external_request_id UNIQUE
curp_masked
beneficiario_id nullable
status
request_hash
response_code
error_message
received_at
processed_at
created_at
updated_at
```

Estados sugeridos:

```txt
received
created
already_processed
rejected
conflict
error
```

Reglas:

- Si llega el mismo `external_request_id` ya procesado, no crear beneficiario duplicado.
- Si llega la misma CURP con diferente `external_request_id`, responder conflicto.
- Si hay error de validación, guardar estado `rejected`.
- Si hay error técnico, guardar estado `error`.
- Si se creó correctamente, guardar `beneficiario_id`.

### 3.5 Creación de beneficiario

Mapear el payload recibido a las tablas actuales de Sys_IPJ.

Campos base:

```txt
curp
nombre
apellido_paterno
apellido_materno
fecha_nacimiento
sexo
discapacidad
id_ine
telefono
municipio_id
seccion_id
```

Regla:

- Sys_IPJ debe conservar CURP completa porque es la fuente oficial.
- Sys_IPJ no debe asignar tarjeta automáticamente desde este endpoint, salvo decisión formal del equipo.
- El beneficiario debe quedar en un estado operativo como `pendiente_tarjeta`, `registrado_sin_tarjeta` o equivalente.

### 3.6 Creación de domicilio

Crear domicilio asociado al beneficiario con:

```txt
beneficiario_id
calle
numero_ext
numero_int
colonia
municipio_id
codigo_postal
seccional / seccion_id
```

No aceptar expedientes sin domicilio completo.

### 3.7 Resolución de seccional

API_TJ envía:

```txt
domicilio.seccional
```

Sys_IPJ debe resolverlo hacia su modelo interno:

```txt
seccion_id
```

Reglas:

- Si la seccional existe, asociar `seccion_id`.
- Si no existe, responder error 422.
- No crear seccionales nuevas automáticamente en este flujo.

### 3.8 Respuestas esperadas hacia API_TJ

#### Creado correctamente

```json
{
  "accepted": true,
  "status": "created",
  "beneficiario_id": 123,
  "external_request_id": "INF-20260426-0001"
}
```

Código HTTP:

```txt
201
```

#### Ya procesado

```json
{
  "accepted": true,
  "status": "already_processed",
  "beneficiario_id": 123,
  "external_request_id": "INF-20260426-0001"
}
```

Código HTTP:

```txt
200
```

#### Conflicto por CURP

```json
{
  "accepted": false,
  "status": "conflict",
  "message": "La CURP ya existe en Sys_IPJ"
}
```

Código HTTP:

```txt
409
```

#### Error de validación

```json
{
  "accepted": false,
  "status": "validation_error",
  "errors": {
    "beneficiario.curp": [
      "CURP inválida"
    ]
  }
}
```

Código HTTP:

```txt
422
```

#### Error interno

```json
{
  "accepted": false,
  "status": "error",
  "message": "Error interno al procesar expediente"
}
```

Código HTTP:

```txt
500
```

---

## 4. Seguridad

### 4.1 Autenticación entrante desde API_TJ

El endpoint receptor debe estar protegido.

Objetivo productivo:

```txt
JWT RS256 sistema-a-sistema
```

API_TJ firmará el token y Sys_IPJ validará la llave pública.

Claims esperados:

```json
{
  "iss": "api_tj",
  "sub": "api_tj",
  "aud": "sys_ipj",
  "scope": "beneficiarios.create",
  "jti": "uuid-unico",
  "iat": 1710000000,
  "exp": 1710000600
}
```

Header esperado:

```json
{
  "alg": "RS256",
  "typ": "JWT",
  "kid": "api_tj-current"
}
```

Reglas:

- `alg` obligatorio: RS256.
- `aud`: `sys_ipj`.
- `iss`: `api_tj`.
- `scope`: `beneficiarios.create`.
- `exp`: máximo 10 minutos.
- `jti`: único por request.
- `kid`: debe coincidir con llave pública registrada.

### 4.2 Variables de entorno sugeridas

```env
API_TJ_PUBLIC_KEY=
API_TJ_JWT_KID=api_tj-current
API_TJ_AUDIENCE=sys_ipj
API_TJ_ALLOWED_SCOPES=beneficiarios.create
```

### 4.3 Protección de logs

No registrar CURP completa en logs técnicos.

Usar:

```txt
curp_masked
external_request_id
request_hash
```

No guardar payload completo en logs planos.

---

## 5. UI administrativa en Sys_IPJ

Agregar vista:

```txt
Solicitudes recibidas de API_TJ
```

Debe mostrar:

```txt
external_request_id
curp_masked
nombre
estatus
fecha de recepción
beneficiario_id
error_message
```

Acciones mínimas:

- Ver detalle.
- Ver beneficiario creado.
- Reprocesar si estado es `error`.
- Consultar auditoría.

No permitir editar datos directamente desde esta vista, salvo que el equipo defina un flujo administrativo formal.

---

## 6. Sincronización manual Sys_IPJ → API_TJ

### 6.1 Botón requerido

Agregar botón:

```txt
Sincronizar con app
```

Usuarios permitidos:

```txt
admin
delegado
```

### 6.2 Endpoint destino en API_TJ

```http
POST /api/v1/cardholders/sync
```

### 6.3 Payload de sincronización

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

### 6.4 Datos mínimos permitidos

Enviar únicamente:

```txt
curp_hash
curp_masked
tarjeta_numero
status
```

No enviar:

```txt
CURP completa
nombre
domicilio
teléfono
fecha de nacimiento
id_ine
```

### 6.5 Generación de `curp_hash`

Sys_IPJ debe calcular:

```txt
HMAC-SHA256(CURP_NORMALIZADA, CURP_HASH_SECRET)
```

Ejemplo conceptual en PHP:

```php
$curpHash = hash_hmac('sha256', strtoupper(trim($curp)), env('CURP_HASH_SECRET'));
```

Reglas:

- Usar misma clave que API_TJ.
- No usar SHA simple.
- No enviar CURP en claro.
- Normalizar CURP antes de hashear.

### 6.6 Generación de `curp_masked`

Formato:

```txt
Primeras 4 posiciones + ********** + últimas 2 posiciones
```

Ejemplo:

```txt
MELR**********06
```

### 6.7 Mapeo de estado de tarjeta

Mapeo recomendado:

```txt
consumida           → active
asignada_usuario    → active
bloqueada           → blocked
extraviada          → blocked
devuelta            → inactive
asignada_oficina    → inactive
disponible          → inactive
```

Si en el ambiente activo no existe tabla de tarjetas, usar fallback:

```txt
beneficiarios.folio_tarjeta → tarjeta_numero
status → active
```

solo para registros con folio válido.

### 6.8 Autenticación hacia API_TJ

Sys_IPJ debe firmar requests hacia API_TJ con JWT RS256.

Claims esperados:

```json
{
  "iss": "sys_ipj",
  "sub": "sys_ipj",
  "aud": "api_tj",
  "scope": "cardholders.sync",
  "jti": "uuid-unico",
  "iat": 1710000000,
  "exp": 1710000600
}
```

### 6.9 Variables de entorno sugeridas

```env
API_TJ_BASE_URL=https://api-tj-url/api/v1
API_TJ_AUDIENCE=api_tj
API_TJ_CLIENT_CODE=sys_ipj
API_TJ_JWT_KID=sys_ipj-current
API_TJ_PRIVATE_KEY_PATH=storage/app/keys/sys_ipj_private.pem
CURP_HASH_SECRET=misma_clave_que_API_TJ
```

---

## 7. Auditoría

Sys_IPJ debe auditar:

### Recepción desde API_TJ

```txt
external_request_id
curp_masked
status
beneficiario_id
request_hash
response_code
error_message
received_at
processed_at
```

### Sync hacia API_TJ

```txt
sync_id
executed_by
role
started_at
finished_at
request_count
success_count
failed_count
api_status_code
api_response_body
status
error_message
```

---

## 8. Pruebas mínimas

### 8.1 Receptor API_TJ → Sys_IPJ

- Recibir expediente válido.
- Rechazar expediente sin `external_request_id`.
- Rechazar CURP inválida.
- Rechazar CURP duplicada.
- Rechazar domicilio incompleto.
- Rechazar municipio inexistente.
- Rechazar seccional inexistente.
- Evitar duplicado por `external_request_id`.
- Responder `already_processed` si llega el mismo request.
- Registrar auditoría.
- No registrar CURP completa en logs.

### 8.2 Sync Sys_IPJ → API_TJ

- Generar lote con beneficiarios activos.
- Enviar `curp_hash`.
- Enviar `curp_masked`.
- No enviar CURP completa.
- Enviar tarjeta_numero.
- Mapear status correctamente.
- Firmar JWT RS256.
- Manejar respuesta `success`.
- Manejar respuesta `partial`.
- Registrar auditoría.

---

## 9. Criterios de aceptación

- Sys_IPJ expone endpoint receptor para API_TJ.
- Sys_IPJ valida autenticación del request.
- Sys_IPJ evita duplicados por `external_request_id`.
- Sys_IPJ evita duplicados por CURP.
- Sys_IPJ crea beneficiario oficial.
- Sys_IPJ crea domicilio asociado.
- Sys_IPJ resuelve seccional a `seccion_id`.
- Sys_IPJ responde contrato JSON estable.
- Sys_IPJ registra auditoría.
- Sys_IPJ permite sincronización manual hacia API_TJ.
- Sys_IPJ no envía CURP completa en sync.
- Sys_IPJ calcula `curp_hash` con HMAC-SHA256.
- Sys_IPJ firma llamadas hacia API_TJ.
- El flujo completo permite que API_TJ active cuentas solo después de recibir el padrón formal sincronizado.

---

## 10. Fuera de alcance

No corresponde a Sys_IPJ:

- Crear cuentas Auth0.
- Manejar login de APP_TARJETAJOVEN.
- Generar QR de la app.
- Guardar credenciales de usuarios finales.
- Usar API_TJ como fuente de verdad.
- Permitir activación de cuenta sin tarjeta formal sincronizada.

---

## 11. Orden recomendado de implementación

1. Crear migración `api_tj_inbound_requests`.
2. Crear endpoint receptor.
3. Crear controlador de recepción.
4. Implementar validaciones.
5. Implementar idempotencia.
6. Crear beneficiario y domicilio.
7. Agregar auditoría.
8. Implementar seguridad JWT RS256 entrante.
9. Agregar vista administrativa.
10. Implementar sync manual hacia API_TJ.
11. Implementar firma JWT RS256 saliente.
12. Ejecutar pruebas E2E con API_TJ.
