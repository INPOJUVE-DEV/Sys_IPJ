"""Tesseract OCR wrapper with preprocessing strategies per attempt."""

from __future__ import annotations

import cv2
import numpy as np
import pytesseract

from .config import settings


def ocr_region(
    roi_image: np.ndarray,
    attempt: int = 1,
    psm: int = 7,
    whitelist: str = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789<",
    lang: str = "spa",
) -> str:
    """Run Tesseract OCR on a ROI image with attempt-specific preprocessing.

    Args:
        roi_image: Cropped BGR region.
        attempt: 1-based attempt number (affects preprocessing).
        psm: Tesseract page segmentation mode. 7=single line, 6=block.
        whitelist: Allowed characters.
        lang: Tesseract language.

    Returns:
        Raw OCR text (uppercase, trimmed).
    """
    if roi_image is None or roi_image.size == 0:
        return ""

    gray = cv2.cvtColor(roi_image, cv2.COLOR_BGR2GRAY) if len(roi_image.shape) == 3 else roi_image
    processed = _preprocess(gray, attempt)

    config = f"--oem 1 --psm {psm} -c tessedit_char_whitelist={whitelist}"

    try:
        pytesseract.pytesseract.tesseract_cmd = settings.tesseract_cmd
        text = pytesseract.image_to_string(processed, lang=lang, config=config)
    except (pytesseract.TesseractError, FileNotFoundError, OSError):
        return ""

    return _normalise(text, whitelist)


def ocr_block(
    roi_image: np.ndarray,
    attempt: int = 1,
    lang: str = "spa",
) -> str:
    """Run Tesseract OCR in block mode (psm=6) for multi-line text.

    Uses a broader whitelist suitable for names and addresses.
    """
    whitelist = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789 .,-/#"
    return ocr_region(roi_image, attempt=attempt, psm=6, whitelist=whitelist, lang=lang)


def _preprocess(gray: np.ndarray, attempt: int) -> np.ndarray:
    """Apply attempt-specific preprocessing."""
    if attempt == 1:
        # Adaptive threshold (Gaussian)
        return cv2.adaptiveThreshold(
            gray, 255, cv2.ADAPTIVE_THRESH_GAUSSIAN_C,
            cv2.THRESH_BINARY, 15, 8,
        )
    elif attempt == 2:
        # Otsu + contrast + sharpen
        clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8, 8))
        enhanced = clahe.apply(gray)
        kernel = np.array([[-1, -1, -1], [-1, 9, -1], [-1, -1, -1]])
        sharpened = cv2.filter2D(enhanced, -1, kernel)
        _, binary = cv2.threshold(sharpened, 0, 255, cv2.THRESH_BINARY | cv2.THRESH_OTSU)
        return binary
    else:
        # Attempt 3: upscale 1.5x + denoise + Otsu
        h, w = gray.shape[:2]
        upscaled = cv2.resize(gray, (int(w * 1.5), int(h * 1.5)),
                              interpolation=cv2.INTER_CUBIC)
        denoised = cv2.fastNlMeansDenoising(upscaled, h=10)
        _, binary = cv2.threshold(denoised, 0, 255, cv2.THRESH_BINARY | cv2.THRESH_OTSU)
        return binary


def _normalise(text: str, whitelist: str) -> str:
    """Normalise OCR output: uppercase, trim, keep only whitelisted chars."""
    text = text.upper().strip()
    # Collapse multiple spaces
    while "  " in text:
        text = text.replace("  ", " ")
    # Remove characters not in whitelist (keep spaces for block mode)
    allowed = set(whitelist + " \n")
    text = "".join(c for c in text if c in allowed)
    return text.strip()
