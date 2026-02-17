"""Feature-based alignment — adjust ROIs using detected feature positions."""

from __future__ import annotations

import cv2
import numpy as np


# Expected relative feature centres for each model (normalised 0..1)
_EXPECTED_FEATURES: dict[str, tuple[float, float, float, float]] = {
    # (cx, cy, relative_width, relative_height) of features
    "MODEL_QRHD_2019_PRESENT": (0.50, 0.30, 0.30, 0.30),  # QR cluster centre
    "MODEL_PDF417_2017_2018":  (0.50, 0.70, 0.70, 0.15),  # PDF417 centre
}


def compute_alignment(
    image_shape: tuple[int, ...],
    model_id: str,
    feature_bboxes: list[np.ndarray],
) -> tuple[float, float, float]:
    """Compute alignment correction (dx, dy, scale) from detected features.

    Returns:
        (dx, dy, scale) where dx/dy are normalised shifts and scale is
        a multiplier (1.0 = no change).
    """
    if model_id not in _EXPECTED_FEATURES or not feature_bboxes:
        return 0.0, 0.0, 1.0

    h, w = image_shape[:2]
    expected = _EXPECTED_FEATURES[model_id]
    exp_cx, exp_cy = expected[0], expected[1]
    exp_w, exp_h = expected[2], expected[3]

    # Compute observed feature centre (union of all bboxes)
    all_points = np.vstack(feature_bboxes)
    x_min, y_min = all_points.min(axis=0)[:2]
    x_max, y_max = all_points.max(axis=0)[:2]

    obs_cx = ((x_min + x_max) / 2.0) / w
    obs_cy = ((y_min + y_max) / 2.0) / h
    obs_w = (x_max - x_min) / w
    obs_h = (y_max - y_min) / h

    # Compute corrections
    dx = obs_cx - exp_cx
    dy = obs_cy - exp_cy

    # Scale based on observed vs expected size
    obs_size = max(obs_w, obs_h)
    exp_size = max(exp_w, exp_h)
    scale = obs_size / exp_size if exp_size > 0 else 1.0
    scale = max(0.7, min(1.5, scale))  # clamp to reasonable range

    return float(dx), float(dy), float(scale)


def apply_alignment_to_roi(
    roi: list[float],
    dx: float,
    dy: float,
    scale: float,
) -> list[float]:
    """Apply alignment correction to a normalised ROI [x1, y1, x2, y2].

    Returns:
        Adjusted ROI clamped to [0, 1].
    """
    x1, y1, x2, y2 = roi
    cx = (x1 + x2) / 2.0
    cy = (y1 + y2) / 2.0
    w = x2 - x1
    h = y2 - y1

    # Apply shift
    cx += dx
    cy += dy

    # Apply scale (around centre)
    w *= scale
    h *= scale

    # Reconstruct
    new_x1 = max(0.0, cx - w / 2.0)
    new_y1 = max(0.0, cy - h / 2.0)
    new_x2 = min(1.0, cx + w / 2.0)
    new_y2 = min(1.0, cy + h / 2.0)

    return [new_x1, new_y1, new_x2, new_y2]


def crop_roi(image: np.ndarray, roi: list[float]) -> np.ndarray:
    """Crop a region from an image using normalised ROI coordinates."""
    h, w = image.shape[:2]
    x1 = int(roi[0] * w)
    y1 = int(roi[1] * h)
    x2 = int(roi[2] * w)
    y2 = int(roi[3] * h)

    # Ensure valid bounds
    x1 = max(0, min(x1, w - 1))
    y1 = max(0, min(y1, h - 1))
    x2 = max(x1 + 1, min(x2, w))
    y2 = max(y1 + 1, min(y2, h))

    return image[y1:y2, x1:x2]
