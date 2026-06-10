"""Application settings loaded from environment variables."""

from pydantic_settings import BaseSettings


class Settings(BaseSettings):
    api_key: str = "change-me-in-production"
    tesseract_cmd: str = "tesseract"
    max_image_size_mb: int = 5
    time_budget_ms: int = 9500
    max_retries: int = 2
    host: str = "0.0.0.0"
    port: int = 8001
    log_level: str = "info"

    model_config = {"env_file": ".env", "env_file_encoding": "utf-8"}


settings = Settings()
