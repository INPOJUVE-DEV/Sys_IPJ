"""Front-side parser — extract nombre, apellidos, domicilio, sección from the INE front."""

from __future__ import annotations

import re

import numpy as np

from .aligner import crop_roi
from .extractor import ocr_block
from .models import FieldResult
from .roi_loader import get_front_rois


def parse_front(
    rectified_front: np.ndarray,
    attempt: int = 1,
) -> dict:
    """Extract all front-side fields from a rectified INE front image.

    Returns:
        dict with keys: nombre, apellido_paterno, apellido_materno,
        domicilio_calle, domicilio_colonia, domicilio_codigo_postal, seccional
    """
    rois = get_front_rois()
    results: dict = {}

    # ── Apellidos ────────────────────────────────────────────────────────
    if "apellidos" in rois:
        roi_img = crop_roi(rectified_front, rois["apellidos"])
        raw = ocr_block(roi_img, attempt=attempt)
        ap, am = _split_apellidos(raw)
        results["apellido_paterno"] = ap
        results["apellido_materno"] = am

    # ── Nombre ───────────────────────────────────────────────────────────
    if "nombre" in rois:
        roi_img = crop_roi(rectified_front, rois["nombre"])
        raw = ocr_block(roi_img, attempt=attempt)
        results["nombre"] = _clean_name(raw)

    # ── Domicilio ────────────────────────────────────────────────────────
    if "domicilio" in rois:
        roi_img = crop_roi(rectified_front, rois["domicilio"])
        raw = ocr_block(roi_img, attempt=attempt)
        calle, colonia, cp = _parse_domicilio(raw)
        results["domicilio_calle"] = calle
        results["domicilio_colonia"] = colonia
        results["domicilio_codigo_postal"] = cp

    # ── Sección electoral ────────────────────────────────────────────────
    if "seccion" in rois:
        roi_img = crop_roi(rectified_front, rois["seccion"])
        raw = ocr_block(roi_img, attempt=attempt)
        results["seccional"] = _parse_seccion(raw)

    return results


def _split_apellidos(text: str) -> tuple[str, str]:
    """Split OCR text into apellido_paterno and apellido_materno.

    INE format is typically two lines or separated by space.
    """
    text = text.strip()
    lines = [line.strip() for line in text.split("\n") if line.strip()]

    if len(lines) >= 2:
        return _clean_name(lines[0]), _clean_name(lines[1])

    # Single line — split by space heuristic
    words = text.split()
    if len(words) >= 2:
        return _clean_name(words[0]), _clean_name(" ".join(words[1:]))

    return _clean_name(text), ""


def _clean_name(text: str) -> str:
    """Clean a name field — remove non-alpha chars, title case."""
    text = text.strip().upper()
    # Remove digits and special chars but keep spaces
    text = re.sub(r"[^A-ZÀ-ÿ\s]", "", text, flags=re.IGNORECASE)
    text = re.sub(r"\s+", " ", text).strip()
    return text


def _parse_domicilio(text: str) -> tuple[str, str, str]:
    """Parse domicilio text into (calle, colonia, codigo_postal).

    INE domicilio is typically multi-line:
    - Line 1: street + number
    - Line 2-3: colonia, municipality, CP
    """
    text = text.strip()
    lines = [line.strip() for line in text.split("\n") if line.strip()]

    calle = ""
    colonia = ""
    cp = ""

    # Extract postal code (5 digits) from anywhere in text
    cp_match = re.search(r"\b(\d{5})\b", text)
    if cp_match:
        cp = cp_match.group(1)

    if len(lines) >= 1:
        calle = _clean_address(lines[0])

    if len(lines) >= 2:
        # Second line often contains colonia
        col_text = lines[1]
        # Remove CP if present
        col_text = re.sub(r"\b\d{5}\b", "", col_text)
        # Remove common prefixes
        col_text = re.sub(r"^(COL\.?|COLONIA)\s*", "", col_text, flags=re.IGNORECASE)
        colonia = _clean_address(col_text)

    if not colonia and len(lines) >= 3:
        colonia = _clean_address(lines[2])

    return calle, colonia, cp


def _clean_address(text: str) -> str:
    """Clean an address field."""
    text = text.strip().upper()
    text = re.sub(r"[^A-ZÀ-ÿ0-9\s.,#/-]", "", text, flags=re.IGNORECASE)
    text = re.sub(r"\s+", " ", text).strip()
    return text


def _parse_seccion(text: str) -> str:
    """Extract sección electoral — 4-digit number."""
    text = text.strip()
    # Look for "SECCION" or "SECCIÓN" followed by digits
    match = re.search(r"(?:SECCI[OÓ]N\s*)?(\d{3,4})", text, re.IGNORECASE)
    if match:
        num = match.group(1)
        return num.zfill(4)  # pad to 4 digits
    return ""
