# Sys_IPJ — OCR INE (2017→Presente) — SPEC TÉCNICO (Para Desarrollo)
**Objetivo**: En `beneficiarios/create` agregar acción **“Escanear INE”** para **autocompletar `beneficiarios.id_ine`** (solo ese dato) mediante un **servicio OCR externo**.  
**Alcance OCR**: INE vigente **2017 a la fecha**, **frontal + reverso obligatorios**, extracción **solo del reverso**.  
**Estado**: Requerimiento cerrado para comenzar implementación.  
**Generado**: 2026-02-16 19:59 (UTC local sandbox)

---

## 0) Decisiones cerradas (NO interpretar / NO deducir)
1. **Datos a extraer**: **únicamente** `beneficiarios.id_ine`.  
   - Se interpreta como el identificador extraíble del reverso (MRZ/IDMEX o equivalente alfanumérico).  
   - No extraer CURP, nombre, domicilio, sección, etc.
2. **Imágenes**: **frontal y reverso son obligatorios** desde ya (aunque solo usemos reverso).  
3. **Persistencia**:
   - **No guardar imágenes**.
   - **No guardar raw OCR** (texto completo).
   - Se permite **cache de resultado** (JSON) por **72h** (3 días) para re-consultas/reintentos, sin PII adicional.
4. **Performance/SLA**:
   - Meta UX: respuesta en **< 10s**.
   - Presupuesto interno OCR: **9.5s** (para no romper UX).
5. **Reintentos automáticos OCR**: **máximo 2** (total intentos: 1 + 2 retries), **solo** para mejorar `id_ine`.
6. **Arquitectura**: OCR en **servicio Python API aparte** (no dentro de Laravel).  
7. **DB**: TiDB la maneja Sys_IPJ; el OCR **NO** escribe en DB del dominio.

---

## 1) Arquitectura objetivo
### 1.1 Componentes
- **Sys_IPJ (Laravel)**: UI + validación + persistencia en TiDB.
- **OCR INE Service (Python)**: endpoint `extract-only` que recibe imágenes y devuelve JSON con `id_ine` + metadatos.
- **Redis**:
  - En Sys_IPJ: rate-limit del endpoint interno y/o locks si aplica.
  - En OCR service: rate-limit + cache de resultados 72h (opcional pero recomendado).

### 1.2 Flujo (alto nivel)
1) Capturista en `beneficiarios/create` pulsa **Escanear INE**  
2) Se suben **front_image** y **back_image** al backend (Laravel)  
3) Laravel llama al OCR Service  
4) OCR Service procesa, devuelve `id_ine` (value + confidence + warnings)  
5) UI autocompleta `id_ine`  
6) Capturista revisa y guarda beneficiario (dominio normal de Sys_IPJ)

---

## 2) Contratos de API

### 2.1 Endpoint interno en Sys_IPJ (Laravel)
**POST** `/api/ocr/ine/extract`  
**Auth**: requerido (rol capturista o equivalente).  
**Rate limit**: recomendado `10/min` por usuario.

**Request**: `multipart/form-data`
- `front_image` (required) `image/jpeg|image/png`, <= 5MB
- `back_image` (required) `image/jpeg|image/png`, <= 5MB
- `client_request_id` (optional) string/uuid

**Response 200** (proxy de OCR service, con normalización mínima)
```json
{
  "model_id": "MODEL_QRHD_2019_PRESENT",
  "beneficiarios": {
    "id_ine": {
      "value": "ABC123...",
      "confidence": 0.84,
      "requires_review": false
    }
  },
  "quality": {
    "back": {
      "blur": 0.71,
      "glare": 0.03,
      "exposure": 0.62,
      "perspective_ok": true,
      "quality_grade": "good"
    }
  },
  "warnings": [],
  "processing_ms": 4100,
  "attempts": 2
}
```

**Errores Laravel (recomendado)**:
- `422` faltan imágenes / no decodificables / tamaño fuera
- `429` rate limit
- `502` OCR service no disponible
- `504` OCR service timeout (solo transporte; preferible devolver `502/504` y que el usuario reintente manualmente)

> Nota: Laravel NO aplica los 2 reintentos OCR: eso vive dentro del OCR service. Laravel solo puede hacer **1 retry de transporte** (HTTP) si falla por red.

---

### 2.2 OCR INE Service (Python)
**POST** `/v1/ine/extract`

**Request**: `multipart/form-data`
- `front_image` (required)
- `back_image` (required)
- `client_request_id` (optional)

**Response 200**: igual al contrato anterior.

**Errores OCR service (cerrado)**:
- `415 UNSUPPORTED_MEDIA_TYPE`
- `422 MISSING_REQUIRED_IMAGE | IMAGE_DECODE_FAILED | IMAGE_TOO_LARGE | IMAGE_TOO_SMALL`
- `429 RATE_LIMITED`
- `500 OCR_INTERNAL_ERROR`

Ejemplo error:
```json
{
  "error_code": "IMAGE_DECODE_FAILED",
  "message": "Invalid image payload",
  "details": { "which": "back_image" }
}
```

---

## 3) OCR Service — Funcionamiento técnico (determinista con coordenadas)

> **Estrategia obligatoria**: **tipificar → rectificar → alinear por feature → aplicar template ROI → OCR → parse/score**.  
> Referencia de layouts: dataset aportado por el usuario (**Pruebas OCR.pdf**) para calibración visual.

### 3.1 Tipificación de reverso (Model-ID) — sin OCR, solo visión
El OCR service debe clasificar el reverso en 1 de 2 familias:

- `MODEL_QRHD_2019_PRESENT`  
  **Criterio**: se detecta ≥1 QR (idealmente 2 grandes).  
  **Feature**: bbox(s) de QR.

- `MODEL_PDF417_2017_2018`  
  **Criterio**: se detecta PDF417 (rectángulo ancho con patrón denso).  
  **Feature**: bbox del PDF417.

Si no se detecta ni QR ni PDF417 → `MODEL_UNKNOWN` con warning `model_unknown` y usar fallback por banda (ver 3.4).

Implementación recomendada:
- QR: OpenCV `QRCodeDetector` (o ZXing si se necesita robustez extra).
- PDF417: morfología + contorno rectangular ancho + densidad de líneas (gradiente/Hough).

### 3.2 Rectificación geométrica (obligatoria)
Objetivo: normalizar a “tarjeta plana” para aplicar ROIs normalizadas 0..1.
- Intento A: detectar contorno de tarjeta (4 puntos) + `warpPerspective`
- Fallback: deskew (Hough) + recorte por bounding box

Exponer en respuesta:
- `quality.back.perspective_ok` (true si warpPerspective ok)

### 3.3 Alineación por feature bbox (obligatoria)
Una vez tipificado:
- `MODEL_QRHD_2019_PRESENT`: usar bbox del QR izquierdo/derecho (unión o centroides).
- `MODEL_PDF417_2017_2018`: usar bbox PDF417.

Se calcula corrección afín ligera (traslación + escala):
- `dx, dy` por centros (expected vs observed)
- `scale` por tamaño relativo del feature (expected vs observed)

Luego, **aplicar esa corrección a ROIs** antes de recortar.

### 3.4 Templates de ROIs (coordenadas normalizadas)
Los templates se guardan como JSON (0..1) y se aplican sobre la imagen rectificada + alineada.

**Nota importante**: El set **Pruebas OCR.pdf** se usa como referencia visual para calibrar y ajustar ROIs y tolerancias.  
El dev debe derivar/ajustar los valores finales del template tomando como baseline estos ROIs sugeridos (ya validados conceptualmente por el set):

#### Template A — `MODEL_QRHD_2019_PRESENT`
- ROI objetivo para lectura: banda MRZ/IDMEX (texto alfanumérico largo)
```json
{
  "model_id": "MODEL_QRHD_2019_PRESENT",
  "rois": {
    "back_id_ine_mrz": [0.13, 0.47, 0.99, 0.69]
  }
}
```

#### Template B — `MODEL_PDF417_2017_2018`
```json
{
  "model_id": "MODEL_PDF417_2017_2018",
  "rois": {
    "back_id_ine_mrz": [0.07, 0.58, 0.98, 0.80]
  }
}
```

#### Fallback — `MODEL_UNKNOWN`
- OCR en banda central-baja (conservador):
```json
{
  "model_id": "MODEL_UNKNOWN",
  "rois": {
    "back_id_ine_band_fallback": [0.05, 0.45, 0.98, 0.85]
  }
}
```

**Expansión controlada de ROI (solo en retries)**:
- Si `id_ine` sale null o confidence < 0.65, expandir ROI en retry:
  - `expand_x = +0.03` (a cada lado)
  - `expand_y = +0.02` (a cada lado)
  - clamp 0..1
- No expandir más allá para evitar ruido del QR/PDF417.

---

## 4) OCR y Parsing (solo `id_ine`)

### 4.1 Config de OCR (Tesseract recomendado)
- `lang=spa`
- `oem=1`
- `psm=7` (single line) para MRZ/linea alfanumérica
- whitelist:
  - `A-Z0-9<` (si se observa uso de MRZ con separadores `<` en el set)
  - si no se usa `<` en práctica: `A-Z0-9`

### 4.2 Normalización de texto
- uppercase
- trim
- colapsar espacios
- remover caracteres fuera de whitelist (sin inventar)

### 4.3 Selección de candidato `id_ine`
Extraer tokens `A-Z0-9` (y opcional `<` si se usa MRZ) y elegir el “mejor”:

**Reglas**
- Longitud preferida: **18**
- Aceptar: **16–20** (con penalización)
- Candidato = token alfanumérico con mejor score

**Score base**
- +3 si len==18
- +2 si len==17 o 19
- +1 si len==16 o 20
- -5 si fuera de rango

### 4.4 Correcciones de OCR (máx 2 sustituciones)
Permitir solo si mejora score y con máximo 2 cambios:
- `O↔0`, `I↔1`, `S↔5`

Si se aplican correcciones:
- warning `id_ine_corrected_chars`
- bajar confidence levemente (penalización)

### 4.5 Confidence (0..1)
`confidence = 0.55*pattern + 0.25*quality + 0.20*context`

- `pattern`: score de longitud + charset (normalizado)
- `quality`: derivado de blur/glare/exposure (0..1)
- `context`: 1.0 si model tipificado + alineación feature ok; 0.6 si tipificado sin feature robusta; 0.3 si fallback unknown

**Thresholds**
- HIGH >= 0.85
- MED  0.65–0.84
- LOW  < 0.65

**requires_review**
- `true` si `confidence < 0.65` o se usó fallback (`MODEL_UNKNOWN`) o `elector_key_fallback_used`
- `false` en caso contrario

---

## 5) Reintentos internos (máx 2) — reglas cerradas

### 5.1 Disparadores de retry
Retry si:
- `id_ine.value` es null/vacío, o
- `id_ine.confidence < 0.65`

### 5.2 Estrategia por intento
**Attempt 1 (default)**
- adaptive threshold (Gaussian)
- ROI template normal

**Attempt 2 (retry 1)**
- Otsu threshold
- contraste + sharpen ligero **solo ROI**
- ROI expandido (expand_x/expand_y)

**Attempt 3 (retry 2 final)**
- upscale ROI (1.5x)
- denoise ligero
- ROI expandido (mismo)
- si aún falla: devolver null + warnings (no “inventar”)

### 5.3 Time budget
- Hard limit total OCR: 9500ms
- Si se alcanza: cortar retries pendientes y devolver mejor resultado + warning `time_budget_exceeded`

---

## 6) Catálogo de warnings (cerrado)

### Calidad
- `back_low_blur`
- `back_high_glare`
- `back_bad_exposure`
- `back_perspective_failed`

### Tipificación / feature
- `model_unknown`
- `qr_feature_not_found`
- `pdf417_feature_not_found`
- `elector_key_fallback_used` (si se usó banda fallback)
- `time_budget_exceeded`

### Campo
- `id_ine_not_found`
- `id_ine_invalid_length`
- `id_ine_non_alnum`
- `id_ine_corrected_chars`

---

## 7) Seguridad y cumplimiento mínimo
- OCR service **no** loguea imágenes.
- Logs sin PII: solo `client_request_id`, tiempos, model_id, warnings, status.
- Transporte recomendado: API key + allowlist por IP (inicial), y después HMAC/mTLS si se requiere.

---

## 8) Definition of Done (DoD) — para cerrar la tarea
1) **OCR service** entrega endpoint `/v1/ine/extract` con:
   - tipificación QR/PDF417
   - rectificación
   - alineación por feature
   - template ROI por modelo
   - extracción id_ine + confidence + warnings
   - 2 retries internos
   - time budget 9.5s
2) **Sys_IPJ** agrega endpoint `/api/ocr/ine/extract` que:
   - valida archivos
   - llama OCR service
   - devuelve JSON al frontend
   - aplica rate limit
3) En `beneficiarios/create`:
   - botón “Escanear INE”
   - al recibir respuesta, autocompleta `id_ine`
   - muestra advertencias si `requires_review=true` o warnings no vacíos
4) QA:
   - probar con el set **Pruebas OCR.pdf**
   - KPI: 90%+ de `id_ine` detectado con confidence >= MED en condiciones normales (luz/blur moderados)
   - p95 < 10s

---

## 9) Notas de calibración (para el dev)
- El INE cambió de PDF417 a QR de alta densidad; por eso se soportan 2 familias de reverso.  
- Las ROIs iniciales están basadas en el set de referencia; el dev debe ajustar mínimamente si en pruebas reales la MRZ cae ligeramente fuera.  
- Ajustes permitidos:
  - pequeñas correcciones en ROIs (+/- 0.02) conservando la filosofía “determinista + feature alignment”.
  - aumentar robustez del detector QR/PDF417 si los teléfonos generan ruido.

---
