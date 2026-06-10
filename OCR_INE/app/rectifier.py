"""Geometric rectification — normalise card to a flat rectangle."""

from __future__ import annotations

import cv2
import cv2
import numpy as np
import pytesseract
from pytesseract import Output


def rectify(image: np.ndarray) -> tuple[np.ndarray, bool]:
    """Rectify the image to remove perspective distortion."""
    # Attempt A: Fix orientation (0, 90, 180, 270)
    image = _fix_orientation(image)

    # Attempt B: detect 4 card corners → warpPerspective
    result = _warp_by_card_contour(image)
    if result is not None:
        return result, True

    # Fallback: deskew via Hough lines + crop
    deskewed = _deskew(image)
    return deskewed, False


def _warp_by_card_contour(image: np.ndarray) -> np.ndarray | None:
    """Find the card contour and apply perspective warp."""
    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY) if len(image.shape) == 3 else image
    blurred = cv2.GaussianBlur(gray, (5, 5), 0)
    edged = cv2.Canny(blurred, 50, 150)

    # Dilate to connect edges
    kernel = cv2.getStructuringElement(cv2.MORPH_RECT, (3, 3))
    edged = cv2.dilate(edged, kernel, iterations=2)

    contours, _ = cv2.findContours(edged, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
    if not contours:
        return None

    # Find the largest quadrilateral
    contours = sorted(contours, key=cv2.contourArea, reverse=True)

    for contour in contours[:5]:
        peri = cv2.arcLength(contour, True)
        approx = cv2.approxPolyDP(contour, 0.02 * peri, True)

        if len(approx) == 4:
            pts = approx.reshape(4, 2).astype(np.float32)
            
            # Check if area is large enough (at least 50% of image)
            # This prevents warping internal features like photos or text blocks
            # when the image is already cropped to the card.
            h, w = image.shape[:2]
            image_area = w * h
            contour_area = cv2.contourArea(contour)
            
            if contour_area < (image_area * 0.50):
                continue

            return _four_point_transform(image, pts)

    return None


def _four_point_transform(image: np.ndarray, pts: np.ndarray) -> np.ndarray:
    """Apply perspective transform given 4 corner points."""
    # Order points: top-left, top-right, bottom-right, bottom-left
    rect = _order_points(pts)
    tl, tr, br, bl = rect

    # Compute output dimensions
    width_a = np.linalg.norm(br - bl)
    width_b = np.linalg.norm(tr - tl)
    max_width = int(max(width_a, width_b))

    height_a = np.linalg.norm(tr - br)
    height_b = np.linalg.norm(tl - bl)
    max_height = int(max(height_a, height_b))

    # Standard INE aspect ratio ~85.6mm x 54mm ≈ 1.585
    if max_width > 0 and max_height > 0:
        aspect = max_width / max_height
        if aspect < 1.0:  # portrait → swap
            max_width, max_height = max_height, max_width

    dst = np.array([
        [0, 0],
        [max_width - 1, 0],
        [max_width - 1, max_height - 1],
        [0, max_height - 1],
    ], dtype=np.float32)

    matrix = cv2.getPerspectiveTransform(rect, dst)
    return cv2.warpPerspective(image, matrix, (max_width, max_height))


def _order_points(pts: np.ndarray) -> np.ndarray:
    """Order 4 points as: top-left, top-right, bottom-right, bottom-left."""
    rect = np.zeros((4, 2), dtype=np.float32)

    s = pts.sum(axis=1)
    rect[0] = pts[np.argmin(s)]   # top-left has smallest sum
    rect[2] = pts[np.argmax(s)]   # bottom-right has largest sum

    d = np.diff(pts, axis=1)
    rect[1] = pts[np.argmin(d)]   # top-right has smallest difference
    rect[3] = pts[np.argmax(d)]   # bottom-left has largest difference

    return rect


def _deskew(image: np.ndarray) -> np.ndarray:
    """Deskew image using Hough line angle detection."""
    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY) if len(image.shape) == 3 else image
    edges = cv2.Canny(gray, 50, 150, apertureSize=3)

    lines = cv2.HoughLinesP(edges, 1, np.pi / 180, threshold=80,
                            minLineLength=100, maxLineGap=10)

    if lines is None or len(lines) == 0:
        return image

    # Compute median angle
    angles = []
    for line in lines:
        x1, y1, x2, y2 = line[0]
        angle = np.degrees(np.arctan2(y2 - y1, x2 - x1))
        if abs(angle) < 45:  # only near-horizontal lines
            angles.append(angle)

    if not angles:
        return image

    median_angle = float(np.median(angles))
    if abs(median_angle) < 0.5:
        return image  # already straight

    h, w = image.shape[:2]
    center = (w // 2, h // 2)
    matrix = cv2.getRotationMatrix2D(center, median_angle, 1.0)
    return cv2.warpAffine(image, matrix, (w, h),
                          flags=cv2.INTER_LINEAR,
                          borderMode=cv2.BORDER_REPLICATE)


def _fix_orientation(image: np.ndarray) -> np.ndarray:
    """Fix image orientation using Tesseract OSD."""
    try:
        rgb = cv2.cvtColor(image, cv2.COLOR_BGR2RGB)
        results = pytesseract.image_to_osd(rgb, output_type=Output.DICT)
        rotate_angle = results["rotate"]

        if rotate_angle == 90:
            return cv2.rotate(image, cv2.ROTATE_90_CLOCKWISE)
        elif rotate_angle == 180:
            return cv2.rotate(image, cv2.ROTATE_180)
        elif rotate_angle == 270:
            return cv2.rotate(image, cv2.ROTATE_90_COUNTERCLOCKWISE)

    except Exception:
        pass  # OSD failed, assume correct orientation

    return image
