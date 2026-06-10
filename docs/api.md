# API externa

## GET /api/secciones/{seccional}

- Límites: `throttle:30,1` (30 solicitudes por minuto)
- Path param: `seccional` (string exacto)
- Respuesta 200:

```
{
  "municipio_id": 12,
  "distrito_local": "05",
  "distrito_federal": "02"
}
```

- Respuesta 404: cuando no existe la seccional

Implementación: `sys_beneficiarios/app/Http/Controllers/Api/SeccionesController.php`

## POST /api/beneficiarios/cache

- Auth: `auth:sanctum` (token via `/api/auth/login`)
- Limites: `throttle:30,1`
- Body JSON (ejemplo):

```
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

- Respuesta 201:

```
{
  "cache_key": "beneficiarios.import.2d6b4b38-7f5d-4d4c-a21b-b1ab6f7c24b2",
  "expires_at": "2026-01-20T12:34:56Z",
  "count": 1
}
```

Implementacion: `sys_beneficiarios/app/Http/Controllers/Api/BeneficiariosImportController.php`

