"""Tests for the FastAPI endpoint."""

from __future__ import annotations

import io

import pytest
from httpx import ASGITransport, AsyncClient
from PIL import Image

from app.main import app


def _make_jpeg(width: int = 856, height: int = 540) -> bytes:
    img = Image.new("RGB", (width, height), (200, 200, 200))
    buf = io.BytesIO()
    img.save(buf, format="JPEG", quality=85)
    buf.seek(0)
    return buf.read()


@pytest.mark.asyncio
async def test_health():
    transport = ASGITransport(app=app)
    async with AsyncClient(transport=transport, base_url="http://test") as c:
        r = await c.get("/health")
    assert r.status_code == 200
    assert r.json()["status"] == "ok"


@pytest.mark.asyncio
async def test_extract_returns_200_with_valid_images():
    front = _make_jpeg()
    back = _make_jpeg()

    transport = ASGITransport(app=app)
    async with AsyncClient(transport=transport, base_url="http://test") as c:
        r = await c.post(
            "/v1/ine/extract",
            files={
                "front_image": ("front.jpg", front, "image/jpeg"),
                "back_image": ("back.jpg", back, "image/jpeg"),
            },
        )

    assert r.status_code == 200
    data = r.json()
    assert "beneficiarios" in data
    assert "domicilio" in data
    assert "model_id" in data
    assert "processing_ms" in data


@pytest.mark.asyncio
async def test_extract_rejects_missing_files():
    transport = ASGITransport(app=app)
    async with AsyncClient(transport=transport, base_url="http://test") as c:
        r = await c.post("/v1/ine/extract")
    assert r.status_code == 422  # validation error


@pytest.mark.asyncio
async def test_extract_rejects_too_small_image():
    tiny = b"tiny"  # < 1000 bytes

    transport = ASGITransport(app=app)
    async with AsyncClient(transport=transport, base_url="http://test") as c:
        r = await c.post(
            "/v1/ine/extract",
            files={
                "front_image": ("front.jpg", tiny, "image/jpeg"),
                "back_image": ("back.jpg", tiny, "image/jpeg"),
            },
        )
    assert r.status_code == 422


@pytest.mark.asyncio
async def test_extract_rejects_unsupported_type():
    pdf = _make_jpeg()  # content doesn't matter, type does
    transport = ASGITransport(app=app)
    async with AsyncClient(transport=transport, base_url="http://test") as c:
        r = await c.post(
            "/v1/ine/extract",
            files={
                "front_image": ("front.pdf", pdf, "application/pdf"),
                "back_image": ("back.jpg", pdf, "image/jpeg"),
            },
        )
    assert r.status_code == 415
