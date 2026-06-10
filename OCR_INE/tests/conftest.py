"""Shared fixtures for OCR INE tests."""

from __future__ import annotations

import io

import numpy as np
import pytest
from PIL import Image


@pytest.fixture
def white_card_front() -> bytes:
    """Create a synthetic white 856x540 JPEG (INE front-size)."""
    return _make_jpeg(856, 540)


@pytest.fixture
def white_card_back() -> bytes:
    """Create a synthetic white 856x540 JPEG (INE back-size)."""
    return _make_jpeg(856, 540)


@pytest.fixture
def tiny_image() -> bytes:
    """A very small JPEG (10x10)."""
    return _make_jpeg(10, 10)


@pytest.fixture
def sample_front_array() -> np.ndarray:
    """A BGR numpy array simulating a white INE front."""
    return np.ones((540, 856, 3), dtype=np.uint8) * 255


@pytest.fixture
def sample_back_array() -> np.ndarray:
    """A BGR numpy array simulating a white INE back."""
    return np.ones((540, 856, 3), dtype=np.uint8) * 255


def _make_jpeg(width: int, height: int, color: tuple = (255, 255, 255)) -> bytes:
    """Generate a JPEG image as bytes."""
    img = Image.new("RGB", (width, height), color)
    buf = io.BytesIO()
    img.save(buf, format="JPEG", quality=85)
    buf.seek(0)
    return buf.read()
