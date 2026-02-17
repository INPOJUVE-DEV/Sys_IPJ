"""Back-side parser — extract id_ine and CURP from the INE back."""

from __future__ import annotations

import re

import numpy as np

from .aligner import apply_alignment_to_roi, compute_alignment, crop_roi
from .classifier import classify
from .extractor import ocr_region
from .models import FieldResult
from .roi_loader import expand_roi, get_back_rois
from .curp_utils import find_curp_in_text


def parse_back(
    rectified_back: np.ndarray,
    attempt: int = 1,
    model_id: str | None = None,
    feature_bboxes: list | None = None,
) -> dict:
    """Extract id_ine and CURP from the back of the INE.

    Args:
        rectified_back: Rectified back image.
        attempt: 1-based attempt number.
        model_id: Pre-classified model (if None, will classify).
        feature_bboxes: Pre-detected feature bounding boxes.

    Returns:
        dict with id_ine, curp, model_id, feature_bboxes, warnings.
    """
    warnings: list[str] = []

    # Classify if needed
    if model_id is None:
        model_id, feature_bboxes = classify(rectified_back)
        if feature_bboxes is None:
            feature_bboxes = []

    if model_id == "MODEL_UNKNOWN":
        warnings.append("model_unknown")

    # Get ROIs and compute alignment
    rois = get_back_rois(model_id)
    dx, dy, scale = compute_alignment(
        rectified_back.shape, model_id, feature_bboxes or [],
    )

    if not feature_bboxes:
        if model_id == "MODEL_QRHD_2019_PRESENT":
            warnings.append("qr_feature_not_found")
        elif model_id == "MODEL_PDF417_2017_2018":
            warnings.append("pdf417_feature_not_found")

    results: dict = {
        "model_id": model_id,
        "feature_bboxes": feature_bboxes,
        "warnings": warnings,
    }

    # ── id_ine extraction ────────────────────────────────────────────────
    mrz_key = next((k for k in rois if "mrz" in k or "fallback" in k), None)
    if mrz_key:
        roi = rois[mrz_key]
        roi = apply_alignment_to_roi(roi, dx, dy, scale)
        if attempt > 1:
            roi = expand_roi(roi)
        roi_img = crop_roi(rectified_back, roi)
        raw = ocr_region(roi_img, attempt=attempt, psm=7,
                         whitelist="ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789<")
        id_ine = _parse_id_ine(raw)
        id_ine, ocr_corrections = _apply_ocr_corrections(id_ine)

        if ocr_corrections > 0:
            warnings.append("id_ine_corrected_chars")

        results["id_ine"] = id_ine
    else:
        results["id_ine"] = None

    # ── CURP extraction ──────────────────────────────────────────────────
    curp_key = next((k for k in rois if "curp" in k), None)
    if curp_key:
        roi = rois[curp_key]
        roi = apply_alignment_to_roi(roi, dx, dy, scale)
        roi_img = crop_roi(rectified_back, roi)
        raw = ocr_region(roi_img, attempt=attempt, psm=6,
                         whitelist="ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789")
        curp = find_curp_in_text(raw)
        results["curp"] = curp
    else:
        results["curp"] = None

    return results


def _parse_id_ine(raw_text: str) -> str | None:
    """Parse id_ine from MRZ/IDMEX text.

    Select the best alphanumeric token of length 16-20, preferring 18.
    """
    # Extract all alphanumeric tokens (ignoring < separators)
    text = raw_text.replace("<", " ").replace("\n", " ")
    tokens = re.findall(r"[A-Z0-9]{8,}", text)

    if not tokens:
        return None

    best_token = None
    best_score = -999

    for token in tokens:
        score = _id_ine_score(token)
        if score > best_score:
            best_score = score
            best_token = token

    if best_token and 16 <= len(best_token) <= 20:
        return best_token

    return None


def _id_ine_score(token: str) -> int:
    """Score a candidate id_ine token."""
    length = len(token)
    if length == 18:
        return 3
    elif length in (17, 19):
        return 2
    elif length in (16, 20):
        return 1
    else:
        return -5


def _apply_ocr_corrections(value: str | None) -> tuple[str | None, int]:
    """Apply common OCR character substitutions (max 2).

    Returns:
        (corrected_value, num_corrections)
    """
    if not value:
        return value, 0

    # Only correct if it improves the token
    corrections_map = {"O": "0", "I": "1", "S": "5"}
    corrections = 0
    result = list(value)

    for i, char in enumerate(result):
        if corrections >= 2:
            break
        if char in corrections_map:
            # Only substitute if surrounded by digits (likely a digit context)
            before = result[i - 1] if i > 0 else ""
            after = result[i + 1] if i < len(result) - 1 else ""
            if before.isdigit() or after.isdigit():
                result[i] = corrections_map[char]
                corrections += 1

    return "".join(result), corrections
