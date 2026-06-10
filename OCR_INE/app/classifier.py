"""Back-side classifier — detect QR codes or PDF417 barcode to determine INE model."""

from __future__ import annotations

import cv2
import numpy as np


# ── Model IDs ────────────────────────────────────────────────────────────────
MODEL_QR = "MODEL_QRHD_2019_PRESENT"
MODEL_PDF417 = "MODEL_PDF417_2017_2018"
MODEL_UNKNOWN = "MODEL_UNKNOWN"


def classify(back_image: np.ndarray) -> tuple[str, list[np.ndarray]]:
    """Classify the back of the INE and return (model_id, feature_bboxes).

    Returns:
        (model_id, feature_bboxes) where feature_bboxes is a list of
        bounding-box arrays that can be used for alignment.
    """
    # Try QR detection first (2019+ model has 2 large QRs)
    model_id, bboxes = _detect_qr(back_image)
    if model_id == MODEL_QR:
        return model_id, bboxes

    # Try PDF417 detection (2017-2018 model)
    model_id, bboxes = _detect_pdf417(back_image)
    if model_id == MODEL_PDF417:
        return model_id, bboxes

    return MODEL_UNKNOWN, []


def _detect_qr(image: np.ndarray) -> tuple[str, list[np.ndarray]]:
    """Detect QR codes using OpenCV's QRCodeDetector."""
    resized, scale = _resize_if_needed(image)
    detector = cv2.QRCodeDetector()

    try:
        retval, points = detector.detectMulti(resized)
    except cv2.error:
        retval = False
        points = None

    if retval and points is not None and len(points) >= 1:
        # Scale points back to original image coordinates
        bboxes = []
        for pts in points:
            scaled_pts = (pts * (1 / scale)).astype(np.int32)
            bboxes.append(scaled_pts)
        return MODEL_QR, bboxes

    return MODEL_UNKNOWN, []


def _detect_pdf417(image: np.ndarray) -> tuple[str, list[np.ndarray]]:
    """Detect PDF417 barcode using morphological operations."""
    resized, scale = _resize_if_needed(image, max_dim=1200)
    gray = cv2.cvtColor(resized, cv2.COLOR_BGR2GRAY) if len(resized.shape) == 3 else resized
    h, w = gray.shape[:2]

    # Compute horizontal gradient (Scharr)
    grad_x = cv2.Scharr(gray, cv2.CV_32F, 1, 0)
    grad_y = cv2.Scharr(gray, cv2.CV_32F, 0, 1)
    gradient = cv2.subtract(cv2.convertScaleAbs(grad_x), cv2.convertScaleAbs(grad_y))

    # Blur and threshold
    blurred = cv2.GaussianBlur(gradient, (9, 9), 0)
    _, thresh = cv2.threshold(blurred, 0, 255, cv2.THRESH_BINARY | cv2.THRESH_OTSU)

    # Close gaps
    kernel = cv2.getStructuringElement(cv2.MORPH_RECT, (21, 7))
    closed = cv2.morphologyEx(thresh, cv2.MORPH_CLOSE, kernel)

    # Erode + dilate
    closed = cv2.erode(closed, None, iterations=4)
    closed = cv2.dilate(closed, None, iterations=4)

    # Find contours
    contours, _ = cv2.findContours(closed, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)

    for contour in sorted(contours, key=cv2.contourArea, reverse=True):
        rect = cv2.minAreaRect(contour)
        box_w, box_h = rect[1]
        if box_w < box_h:
            box_w, box_h = box_h, box_w

        # PDF417 is typically wide and relatively short
        aspect = box_w / box_h if box_h > 0 else 0
        area_ratio = (box_w * box_h) / (w * h) if (w * h) > 0 else 0

        # Accept if wide rectangle with reasonable area
        if aspect > 2.5 and area_ratio > 0.03:
            box_points = cv2.boxPoints(rect)
            # Scale back
            scaled_points = (box_points * (1 / scale)).astype(np.int32)
            return MODEL_PDF417, [scaled_points]

    return MODEL_UNKNOWN, []


def _resize_if_needed(image: np.ndarray, max_dim: int = 1000) -> tuple[np.ndarray, float]:
    """Resize image if larger than max_dim, preserving aspect ratio."""
    h, w = image.shape[:2]
    if max(h, w) <= max_dim:
        return image, 1.0

    scale = max_dim / max(h, w)
    new_w = int(w * scale)
    new_h = int(h * scale)
    resized = cv2.resize(image, (new_w, new_h), interpolation=cv2.INTER_AREA)
    return resized, scale
