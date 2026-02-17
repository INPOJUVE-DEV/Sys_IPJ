"""Pipeline orchestrator — ties all stages together."""

from __future__ import annotations

import time
import logging

import cv2
import numpy as np

from .config import settings
from .models import (
    BeneficiarioFields,
    DomicilioFields,
    FieldResult,
    OcrResponse,
    QualityMetrics,
)
from .quality import assess_quality, get_quality_warnings
from .rectifier import rectify
from .classifier import classify
from .front_parser import parse_front
from .back_parser import parse_back
from .curp_utils import extract_fecha_nacimiento, extract_sexo, is_valid_curp
from .confidence import (
    context_score,
    pattern_score_address,
    pattern_score_cp,
    pattern_score_curp,
    pattern_score_id_ine,
    pattern_score_name,
    pattern_score_seccion,
    score_field,
)

logger = logging.getLogger(__name__)


def process_ine(front_bytes: bytes, back_bytes: bytes) -> OcrResponse:
    """Full OCR pipeline for an INE card.

    Args:
        front_bytes: Raw bytes of the front image.
        back_bytes: Raw bytes of the back image.

    Returns:
        OcrResponse with all extracted fields.
    """
    t0 = time.monotonic()
    warnings: list[str] = []

    # ── Decode images ────────────────────────────────────────────────────
    front_img = _decode_image(front_bytes)
    back_img = _decode_image(back_bytes)

    if front_img is None or back_img is None:
        return OcrResponse(
            warnings=["image_decode_failed"],
            processing_ms=_elapsed_ms(t0),
        )

    # ── Quality assessment ───────────────────────────────────────────────
    front_quality = assess_quality(front_img)
    back_quality = assess_quality(back_img)
    warnings.extend(get_quality_warnings(front_quality, "front"))
    warnings.extend(get_quality_warnings(back_quality, "back"))

    # ── Rectify both sides ───────────────────────────────────────────────
    rect_front, front_persp_ok = rectify(front_img)
    rect_back, back_persp_ok = rectify(back_img)

    front_quality.perspective_ok = front_persp_ok
    back_quality.perspective_ok = back_persp_ok

    if not back_persp_ok:
        warnings.append("back_perspective_failed")

    # ── Classify back side ───────────────────────────────────────────────
    model_id, feature_bboxes = classify(rect_back)
    ctx_score = context_score(model_id, back_persp_ok)

    # ── Process FRONT ────────────────────────────────────────────────────
    front_data = parse_front(rect_front, attempt=1)
    q_front = (front_quality.blur + (1.0 - front_quality.glare) + front_quality.exposure) / 3.0

    # ── Process BACK (with retries for id_ine) ───────────────────────────
    best_back: dict = {}
    best_id_ine_conf = 0.0
    attempts = 0

    for attempt in range(1, settings.max_retries + 2):  # 1..max_retries+1
        if _elapsed_ms(t0) > settings.time_budget_ms:
            warnings.append("time_budget_exceeded")
            break

        attempts = attempt
        back_data = parse_back(
            rect_back,
            attempt=attempt,
            model_id=model_id,
            feature_bboxes=feature_bboxes,
        )

        # Merge warnings from back parser
        for w in back_data.get("warnings", []):
            if w not in warnings:
                warnings.append(w)

        # Evaluate id_ine quality
        id_ine = back_data.get("id_ine")
        if id_ine:
            p_score = pattern_score_id_ine(id_ine)
            q_back = (back_quality.blur + (1.0 - back_quality.glare) + back_quality.exposure) / 3.0
            conf = 0.55 * p_score + 0.25 * q_back + 0.20 * ctx_score
        else:
            conf = 0.0

        if conf > best_id_ine_conf:
            best_id_ine_conf = conf
            best_back = back_data

        # Stop retrying if confidence is sufficient
        if conf >= 0.65:
            break

    if not best_back.get("id_ine"):
        warnings.append("id_ine_not_found")

    # ── Build response ───────────────────────────────────────────────────
    q_back_avg = (back_quality.blur + (1.0 - back_quality.glare) + back_quality.exposure) / 3.0

    # CURP-derived fields
    curp_val = best_back.get("curp")
    fecha_val = None
    sexo_val = None
    if curp_val and is_valid_curp(curp_val):
        fecha_val = extract_fecha_nacimiento(curp_val)
        sexo_val = extract_sexo(curp_val)

    beneficiarios = BeneficiarioFields(
        nombre=score_field(front_data.get("nombre"), pattern_score_name(front_data.get("nombre", "")), q_front, ctx_score),
        apellido_paterno=score_field(front_data.get("apellido_paterno"), pattern_score_name(front_data.get("apellido_paterno", "")), q_front, ctx_score),
        apellido_materno=score_field(front_data.get("apellido_materno"), pattern_score_name(front_data.get("apellido_materno", "")), q_front, ctx_score),
        curp=score_field(curp_val, pattern_score_curp(curp_val or ""), q_back_avg, ctx_score),
        fecha_nacimiento=score_field(fecha_val, 1.0 if fecha_val else 0.0, q_back_avg, ctx_score),
        sexo=score_field(sexo_val, 1.0 if sexo_val else 0.0, q_back_avg, ctx_score),
        id_ine=score_field(best_back.get("id_ine"), pattern_score_id_ine(best_back.get("id_ine", "")), q_back_avg, ctx_score),
    )

    domicilio = DomicilioFields(
        calle=score_field(front_data.get("domicilio_calle"), pattern_score_address(front_data.get("domicilio_calle", "")), q_front, ctx_score),
        colonia=score_field(front_data.get("domicilio_colonia"), pattern_score_address(front_data.get("domicilio_colonia", "")), q_front, ctx_score),
        codigo_postal=score_field(front_data.get("domicilio_codigo_postal"), pattern_score_cp(front_data.get("domicilio_codigo_postal", "")), q_front, ctx_score),
        seccional=score_field(front_data.get("seccional"), pattern_score_seccion(front_data.get("seccional", "")), q_front, ctx_score),
    )

    return OcrResponse(
        model_id=model_id,
        beneficiarios=beneficiarios,
        domicilio=domicilio,
        quality=QualityMetrics(front=front_quality, back=back_quality),
        warnings=warnings,
        processing_ms=_elapsed_ms(t0),
        attempts=attempts,
    )


def _decode_image(raw_bytes: bytes) -> np.ndarray | None:
    """Decode raw bytes into an OpenCV BGR image."""
    try:
        arr = np.frombuffer(raw_bytes, dtype=np.uint8)
        img = cv2.imdecode(arr, cv2.IMREAD_COLOR)
        return img
    except Exception:
        return None


def _elapsed_ms(t0: float) -> int:
    """Milliseconds since t0."""
    return int((time.monotonic() - t0) * 1000)
