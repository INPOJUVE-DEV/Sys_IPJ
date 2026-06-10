"""Confidence scoring per field."""

from __future__ import annotations

from .models import FieldResult


def score_field(
    value: str | None,
    pattern_score: float,
    quality_score: float,
    context_score: float,
) -> FieldResult:
    """Calculate confidence for a field and return a FieldResult.

    Formula: confidence = 0.55*pattern + 0.25*quality + 0.20*context

    Thresholds:
        HIGH >= 0.85
        MED  0.65–0.84
        LOW  < 0.65
    """
    if not value:
        return FieldResult(value=None, confidence=0.0, requires_review=True)

    confidence = 0.55 * pattern_score + 0.25 * quality_score + 0.20 * context_score
    confidence = max(0.0, min(1.0, confidence))

    requires_review = confidence < 0.65

    return FieldResult(
        value=value,
        confidence=round(confidence, 3),
        requires_review=requires_review,
    )


def pattern_score_name(value: str) -> float:
    """Score a name field based on pattern quality."""
    if not value:
        return 0.0
    # Must be at least 2 chars, all alpha + spaces
    if len(value) < 2:
        return 0.2
    if not all(c.isalpha() or c.isspace() for c in value):
        return 0.4
    if len(value) > 50:
        return 0.5
    return 0.9


def pattern_score_curp(value: str) -> float:
    """Score a CURP based on format validity."""
    from .curp_utils import is_valid_curp
    if not value:
        return 0.0
    if is_valid_curp(value):
        return 1.0
    if len(value) == 18:
        return 0.5
    return 0.2


def pattern_score_id_ine(value: str, corrections: int = 0) -> float:
    """Score an id_ine based on length and corrections applied."""
    if not value:
        return 0.0
    length = len(value)
    if length == 18:
        base = 1.0
    elif length in (17, 19):
        base = 0.8
    elif length in (16, 20):
        base = 0.6
    else:
        base = 0.2
    # Penalise corrections
    base -= corrections * 0.05
    return max(0.0, base)


def pattern_score_address(value: str) -> float:
    """Score an address field."""
    if not value:
        return 0.0
    if len(value) < 3:
        return 0.3
    return 0.75


def pattern_score_seccion(value: str) -> float:
    """Score a sección electoral field (should be 3-4 digits)."""
    if not value:
        return 0.0
    clean = value.strip().lstrip("0")
    if clean.isdigit() and 1 <= len(clean) <= 4:
        return 1.0
    return 0.3


def pattern_score_cp(value: str) -> float:
    """Score a código postal (should be exactly 5 digits)."""
    if not value:
        return 0.0
    if len(value) == 5 and value.isdigit():
        return 1.0
    return 0.2


def context_score(model_id: str, perspective_ok: bool) -> float:
    """Context score based on model classification and alignment quality."""
    if model_id == "MODEL_UNKNOWN":
        return 0.3
    if perspective_ok:
        return 1.0
    return 0.6
