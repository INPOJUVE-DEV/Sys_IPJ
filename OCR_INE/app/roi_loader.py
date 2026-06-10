"""ROI templates for INE card regions (normalised 0..1 coordinates: [x1, y1, x2, y2])."""

import json
import pathlib

_TEMPLATES_PATH = pathlib.Path(__file__).parent / "templates" / "roi_templates.json"


def _load() -> dict:
    with open(_TEMPLATES_PATH, encoding="utf-8") as f:
        return json.load(f)


TEMPLATES: dict = _load()


def get_front_rois() -> dict[str, list[float]]:
    """Return ROIs for the front side of the INE (same for all models)."""
    return TEMPLATES.get("front", {})


def get_back_rois(model_id: str) -> dict[str, list[float]]:
    """Return ROIs for the back side based on the detected model."""
    return TEMPLATES.get(model_id, TEMPLATES.get("MODEL_UNKNOWN", {}))


def expand_roi(roi: list[float], expand_x: float = 0.03, expand_y: float = 0.02) -> list[float]:
    """Expand a ROI for retry attempts, clamping to 0..1."""
    x1, y1, x2, y2 = roi
    return [
        max(0.0, x1 - expand_x),
        max(0.0, y1 - expand_y),
        min(1.0, x2 + expand_x),
        min(1.0, y2 + expand_y),
    ]
