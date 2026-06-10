"""Pydantic models for OCR INE API request/response schemas."""

from __future__ import annotations

from pydantic import BaseModel


class FieldResult(BaseModel):
    """A single extracted field with its confidence score."""
    value: str | None = None
    confidence: float = 0.0
    requires_review: bool = False


class QualitySide(BaseModel):
    """Quality metrics for one side of the INE card."""
    blur: float = 0.0
    glare: float = 0.0
    exposure: float = 0.0
    perspective_ok: bool = True
    quality_grade: str = "unknown"  # good | fair | poor | unknown


class QualityMetrics(BaseModel):
    """Quality metrics for both sides."""
    front: QualitySide = QualitySide()
    back: QualitySide = QualitySide()


class BeneficiarioFields(BaseModel):
    """Personal data fields extracted from the INE."""
    nombre: FieldResult = FieldResult()
    apellido_paterno: FieldResult = FieldResult()
    apellido_materno: FieldResult = FieldResult()
    curp: FieldResult = FieldResult()
    fecha_nacimiento: FieldResult = FieldResult()
    sexo: FieldResult = FieldResult()
    id_ine: FieldResult = FieldResult()


class DomicilioFields(BaseModel):
    """Address fields extracted from the INE."""
    calle: FieldResult = FieldResult()
    colonia: FieldResult = FieldResult()
    codigo_postal: FieldResult = FieldResult()
    seccional: FieldResult = FieldResult()


class OcrResponse(BaseModel):
    """Full OCR extraction response."""
    model_id: str = "MODEL_UNKNOWN"
    beneficiarios: BeneficiarioFields = BeneficiarioFields()
    domicilio: DomicilioFields = DomicilioFields()
    quality: QualityMetrics = QualityMetrics()
    warnings: list[str] = []
    processing_ms: int = 0
    attempts: int = 1


class ErrorResponse(BaseModel):
    """Error response schema."""
    error_code: str
    message: str
    details: dict | None = None
