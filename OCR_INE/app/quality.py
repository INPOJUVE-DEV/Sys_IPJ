"""Image quality assessment — blur, glare, exposure metrics."""

from __future__ import annotations

import cv2
import numpy as np

from .models import QualitySide


def assess_quality(image: np.ndarray) -> QualitySide:
    """Evaluate image quality and return metrics.

    Args:
        image: BGR image (OpenCV format).

    Returns:
        QualitySide with blur, glare, exposure scores (0..1) and a grade.
    """
    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY) if len(image.shape) == 3 else image

    blur = _blur_score(gray)
    glare = _glare_score(gray)
    exposure = _exposure_score(gray)

    # Overall grade
    avg = (blur + (1.0 - glare) + exposure) / 3.0
    if avg >= 0.7:
        grade = "good"
    elif avg >= 0.4:
        grade = "fair"
    else:
        grade = "poor"

    return QualitySide(
        blur=round(blur, 3),
        glare=round(glare, 3),
        exposure=round(exposure, 3),
        quality_grade=grade,
    )


def _blur_score(gray: np.ndarray) -> float:
    """Sharpness via variance of Laplacian. Higher = sharper."""
    lap_var = cv2.Laplacian(gray, cv2.CV_64F).var()
    # Normalise: typical sharp image ~500+, blurry <100
    score = min(lap_var / 500.0, 1.0)
    return float(score)


def _glare_score(gray: np.ndarray) -> float:
    """Fraction of near-white (saturated) pixels. Lower = less glare."""
    total = gray.size
    saturated = int(np.sum(gray > 240))
    return float(saturated / total) if total > 0 else 0.0


def _exposure_score(gray: np.ndarray) -> float:
    """How well-distributed the histogram is. 1.0 = well exposed."""
    hist = cv2.calcHist([gray], [0], None, [256], [0, 256]).flatten()
    hist = hist / hist.sum()  # normalise

    # Measure spread — a well-exposed image uses the full range
    mean = float(np.average(np.arange(256), weights=hist))
    std = float(np.sqrt(np.average((np.arange(256) - mean) ** 2, weights=hist)))

    # Normalise: std ~64 is ideal (uniform dist ≈ 74)
    score = min(std / 64.0, 1.0)
    return score


def get_quality_warnings(q: QualitySide, side: str = "back") -> list[str]:
    """Generate warning codes from quality metrics."""
    warnings: list[str] = []
    if q.blur < 0.3:
        warnings.append(f"{side}_low_blur")
    if q.glare > 0.15:
        warnings.append(f"{side}_high_glare")
    if q.exposure < 0.3:
        warnings.append(f"{side}_bad_exposure")
    return warnings
