# API Sys_IPJ

Este documento describe la superficie API actual de Sys_IPJ y delimita qué endpoints pueden usarse para integraciones externas.

## Convenciones

- Base path: `/api/v1`
- Formato de intercambio: JSON
- Las rutas se montan desde `sys_beneficiarios/routes/api.php` mediante `RouteServiceProvider`.
- Las integraciones sistema-a-sistema deben documentarse antes de implementarse.

## Regla de datos core

Sys_IPJ es la fuente de verdad del padrón oficial de beneficiarios.

La tabla `beneficiarios` no debe modificarse para soportar integraciones externas. Cualquier sincronización, staging, auditoría o bitácora debe resolverse mediante tablas separadas y servicios específicos.

No se permite agregar a `beneficiarios` campos como:

- `source_system`
- `source_external_request_id`
- `curp_hash`
- `api_tj_sync_status`
- `api_tj_sync_attempts`
- `api_tj_last_synced_at`

Tampoco se debe modificar `created_by` para aceptar altas externas sin responsable institucional.

## API pública actual

### GET `/api/v1/health`

Verifica disponibilidad básica del API.

Respuesta esperada:

```json
{
  "status": "ok"
}
```

### GET `/api/v1/secciones/{seccional}`

Consulta municipio y distritos asociados a una seccional.

Límites:

- `throttle:30,1`

Parámetros:

| Parámetro | Tipo | Descripción |
| --- | --- | --- |
| `seccional` | string | Seccional exacta a consultar. |

Respuesta `200`:

```json
{
  "municipio_id": 12,
  "distrito_local": "05",
  "distrito_federal": "02"
}
```

Respuesta `404`:

```json
{
  "message": "No encontrado"
}
```

Implementación:

```txt
sys_beneficiarios/app/Http/Controllers/Api/SeccionesController.php
```

### GET `/api/v1/pages/{slug}`

Entrega contenido público publicado para la App/PWA.

Implementación:

```txt
sys_beneficiarios/app/Http/Controllers/PagePublicController.php
```

### GET `/api/v1/components/registry`

Entrega el catálogo público de componentes disponibles para renderizado de páginas dinámicas.

Implementación:

```txt
sys_beneficiarios/app/Http/Controllers/ComponentRegistryController.php
```

### GET `/api/v1/themes/current`

Entrega el tema visual público vigente.

Implementación:

```txt
sys_beneficiarios/app/Http/Controllers/ThemePublicController.php
```

## Autenticación API actual

### POST `/api/v1/auth/login`

Autenticación API.

Implementación:

```txt
sys_beneficiarios/app/Http/Controllers/Auth/LoginController.php
```

### POST `/api/v1/auth/logout`

Cierre de sesión API.

Requiere:

- `auth:sanctum`

Implementación:

```txt
sys_beneficiarios/app/Http/Controllers/Auth/LogoutController.php
```

## Endpoint legacy / en revisión

### POST `/api/v1/beneficiarios/cache`

Estado: **LEGACY / NO USAR PARA NUEVA SINCRONIZACIÓN ENTRE SISTEMAS**.

Este endpoint recibe lotes de beneficiarios completos y genera una llave temporal de cache. No debe utilizarse como contrato principal para la sincronización entre Sys_IPJ, API_TJ o Unidad de Informática.

Motivo:

- Se parece a un mecanismo de entrada externa hacia el padrón.
- No representa el contrato final de integración.
- No debe condicionar cambios al modelo `Beneficiario` ni a la tabla `beneficiarios`.

Autenticación actual:

- `auth:sanctum`

Límites:

- `throttle:30,1`

Body histórico de referencia:

```json
{
  "source": "api-externa",
  "beneficiarios": [
    {
      "folio_tarjeta": "FT-0001",
      "nombre": "JUAN",
      "apellido_paterno": "PEREZ",
      "apellido_materno": "LOPEZ",
      "curp": "PEPJ800101HDFRRN09",
      "fecha_nacimiento": "1980-01-01",
      "sexo": "M",
      "discapacidad": false,
      "id_ine": "ABC123",
      "telefono": "5512345678",
      "domicilio": {
        "calle": "CALLE 1",
        "numero_ext": "10",
        "numero_int": "2",
        "colonia": "CENTRO",
        "municipio_id": 12,
        "codigo_postal": "01000",
        "seccional": "0001"
      }
    }
  ]
}
```

Respuesta histórica `201`:

```json
{
  "cache_key": "beneficiarios.import.2d6b4b38-7f5d-4d4c-a21b-b1ab6f7c24b2",
  "expires_at": "2026-01-20T12:34:56Z",
  "count": 1
}
```

Implementación actual:

```txt
sys_beneficiarios/app/Http/Controllers/Api/BeneficiariosImportController.php
```

## OCR INE

### POST `/api/v1/ocr/ine/extract`

Proxy autenticado hacia el servicio externo de OCR INE.

Requiere:

- `web`
- `auth`
- `throttle:10,1`

Implementación:

```txt
sys_beneficiarios/app/Http/Controllers/Api/OcrIneController.php
```

## Endpoints propuestos para sincronización futura

Estos endpoints son propuesta documental. No deben asumirse como implementados hasta que exista PR específico de código.

### Sys_IPJ hacia API_TJ

Propósito:

- Enviar padrón mínimo de beneficiarios oficiales hacia API_TJ.

Datos mínimos:

- `curp_hash`
- `curp_masked`
- `tarjeta_numero`
- `status`
- `synced_at`

Regla:

- Sys_IPJ calcula los datos de salida sin modificar la tabla `beneficiarios`.
- El estado de sincronización se registra en tablas separadas.

### API_TJ hacia Sys_IPJ

Propósito:

- Enviar expedientes staging aprobados manualmente desde API_TJ para alta oficial en Sys_IPJ.

Regla:

- API_TJ no escribe directamente en la base de datos de Sys_IPJ.
- Sys_IPJ valida el expediente y decide si crea el beneficiario oficial.
- Cualquier auditoría de recepción vive en tablas separadas.

## Integraciones sistema-a-sistema

Toda integración externa debe cumplir:

- HTTPS.
- Autenticación por JWT firmado, preferentemente RS256.
- Scopes por sistema cliente.
- Expiración corta de tokens.
- Prevención de replay mediante `jti`.
- Rate limit por cliente.
- Auditoría de request y response.

## Documentos relacionados

- `README.md`
- `docs/rutas.md`
- `docs/despliegue.md`
- `docs/integraciones/` cuando se agregue la documentación específica de sincronización.
