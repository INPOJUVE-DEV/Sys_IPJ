"""FastAPI application — OCR INE Service."""

from __future__ import annotations

import logging

from fastapi import FastAPI, File, Header, HTTPException, UploadFile
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse

from .config import settings
from .models import ErrorResponse, OcrResponse
from .pipeline import process_ine

# ── Logging ──────────────────────────────────────────────────────────────────
logging.basicConfig(
    level=getattr(logging, settings.log_level.upper(), logging.INFO),
    format="%(asctime)s [%(levelname)s] %(name)s: %(message)s",
)
logger = logging.getLogger(__name__)

# ── App ──────────────────────────────────────────────────────────────────────
app = FastAPI(
    title="OCR INE Service",
    version="1.0.0",
    description="Extracts data from Mexican INE cards using OCR.",
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_methods=["POST", "GET"],
    allow_headers=["*"],
)

MAX_SIZE = settings.max_image_size_mb * 1024 * 1024  # bytes


# ── Health ───────────────────────────────────────────────────────────────────
@app.get("/health")
async def health():
    return {"status": "ok", "service": "ocr-ine"}


# ── Extract ──────────────────────────────────────────────────────────────────
@app.post("/v1/ine/extract", response_model=OcrResponse)
async def extract_ine(
    front_image: UploadFile = File(..., description="Front side of the INE card"),
    back_image: UploadFile = File(..., description="Back side of the INE card"),
    x_api_key: str | None = Header(None, alias="X-Api-Key"),
):
    """Extract data from INE images (front + back)."""

    # ── Auth ─────────────────────────────────────────────────────────────
    if settings.api_key != "change-me-in-production":
        if x_api_key != settings.api_key:
            raise HTTPException(status_code=401, detail="Invalid API key")

    # ── Validate content types ───────────────────────────────────────────
    allowed_types = {"image/jpeg", "image/png", "image/jpg"}

    if front_image.content_type and front_image.content_type not in allowed_types:
        return JSONResponse(
            status_code=415,
            content=ErrorResponse(
                error_code="UNSUPPORTED_MEDIA_TYPE",
                message=f"front_image type '{front_image.content_type}' not supported",
            ).model_dump(),
        )

    if back_image.content_type and back_image.content_type not in allowed_types:
        return JSONResponse(
            status_code=415,
            content=ErrorResponse(
                error_code="UNSUPPORTED_MEDIA_TYPE",
                message=f"back_image type '{back_image.content_type}' not supported",
            ).model_dump(),
        )

    # ── Read files ───────────────────────────────────────────────────────
    try:
        front_bytes = await front_image.read()
        back_bytes = await back_image.read()
    except Exception as e:
        logger.error("Failed to read uploaded files: %s", e)
        return JSONResponse(
            status_code=422,
            content=ErrorResponse(
                error_code="IMAGE_DECODE_FAILED",
                message="Failed to read uploaded images",
            ).model_dump(),
        )

    # ── Size validation ──────────────────────────────────────────────────
    if len(front_bytes) > MAX_SIZE:
        return JSONResponse(
            status_code=422,
            content=ErrorResponse(
                error_code="IMAGE_TOO_LARGE",
                message=f"front_image exceeds {settings.max_image_size_mb}MB limit",
                details={"which": "front_image"},
            ).model_dump(),
        )

    if len(back_bytes) > MAX_SIZE:
        return JSONResponse(
            status_code=422,
            content=ErrorResponse(
                error_code="IMAGE_TOO_LARGE",
                message=f"back_image exceeds {settings.max_image_size_mb}MB limit",
                details={"which": "back_image"},
            ).model_dump(),
        )

    if len(front_bytes) < 1000:
        return JSONResponse(
            status_code=422,
            content=ErrorResponse(
                error_code="IMAGE_TOO_SMALL",
                message="front_image is too small — likely not a valid image",
                details={"which": "front_image"},
            ).model_dump(),
        )

    if len(back_bytes) < 1000:
        return JSONResponse(
            status_code=422,
            content=ErrorResponse(
                error_code="IMAGE_TOO_SMALL",
                message="back_image is too small — likely not a valid image",
                details={"which": "back_image"},
            ).model_dump(),
        )

    # ── Process ──────────────────────────────────────────────────────────
    try:
        result = process_ine(front_bytes, back_bytes)
        logger.info(
            "OCR completed: model=%s, attempts=%d, ms=%d, warnings=%s",
            result.model_id,
            result.attempts,
            result.processing_ms,
            result.warnings,
        )
        return result
    except Exception as e:
        logger.exception("OCR pipeline error: %s", e)
        return JSONResponse(
            status_code=500,
            content=ErrorResponse(
                error_code="OCR_INTERNAL_ERROR",
                message="Internal OCR processing error",
            ).model_dump(),
        )
