# OCR INE Service

Servicio Python (FastAPI) para extracción de datos de credenciales INE mexicanas mediante OCR.

## Campos que extrae

| Campo | Fuente | Técnica |
|-------|--------|---------|
| nombre | Frente | OCR + parsing |
| apellido_paterno | Frente | OCR + split |
| apellido_materno | Frente | OCR + split |
| curp | Reverso | OCR + regex validation |
| fecha_nacimiento | CURP | Derivado (posiciones 5-10) |
| sexo | CURP | Derivado (posición 11) |
| id_ine | Reverso | OCR MRZ/IDMEX con retries |
| domicilio.calle | Frente | OCR |
| domicilio.colonia | Frente | OCR |
| domicilio.codigo_postal | Frente | OCR + regex |
| domicilio.seccional | Frente | OCR (4 dígitos) |

## Requisitos

- Python 3.11+
- Tesseract OCR + paquete español: `tesseract-ocr tesseract-ocr-spa`

## Desarrollo local

```bash
# Instalar dependencias
pip install -r requirements.txt

# Ejecutar servidor
uvicorn app.main:app --port 8001 --reload

# Ejecutar tests
pytest tests/ -v
```

## Docker

```bash
docker build -t ocr-ine .
docker run -p 8001:8001 -e API_KEY=mi-clave ocr-ine
```

## Endpoint

### `POST /v1/ine/extract`

**Headers**: `X-Api-Key: <api-key>` (cuando API_KEY ≠ default)

**Body** (multipart/form-data):
- `front_image` — JPEG/PNG, ≤ 5MB
- `back_image` — JPEG/PNG, ≤ 5MB

**Response 200**:
```json
{
  "model_id": "MODEL_QRHD_2019_PRESENT",
  "beneficiarios": {
    "nombre": { "value": "JUAN", "confidence": 0.91, "requires_review": false },
    "curp": { "value": "PELJ000101HDFRPNA1", "confidence": 0.95, "requires_review": false },
    "id_ine": { "value": "ABC123456789012345", "confidence": 0.84, "requires_review": false }
  },
  "domicilio": {
    "calle": { "value": "AV REFORMA 100", "confidence": 0.72 },
    "seccional": { "value": "0234", "confidence": 0.93 }
  },
  "quality": { "front": { "quality_grade": "good" }, "back": { "quality_grade": "good" } },
  "processing_ms": 5200,
  "attempts": 2
}
```

### `GET /health`

Retorna `{"status": "ok"}`.

## Variables de entorno

| Variable | Default | Descripción |
|----------|---------|-------------|
| `API_KEY` | change-me-in-production | Clave para autenticar requests |
| `TESSERACT_CMD` | tesseract | Ruta al ejecutable de Tesseract |
| `MAX_IMAGE_SIZE_MB` | 5 | Tamaño máximo de imagen |
| `TIME_BUDGET_MS` | 9500 | Presupuesto de tiempo total |
| `MAX_RETRIES` | 2 | Reintentos máximos para id_ine |

## Integración con Laravel

En el `.env` de `sys_beneficiarios`:
```
OCR_INE_SERVICE_URL=http://localhost:8001
OCR_INE_API_KEY=mi-clave
```
