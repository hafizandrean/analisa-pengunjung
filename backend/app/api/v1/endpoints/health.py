from datetime import datetime, timezone
from fastapi import APIRouter
from app.core.config import settings
from app.schemas.system import HealthResponse

router = APIRouter()


@router.get("/health", response_model=HealthResponse, tags=["Health"])
def get_health():
    return HealthResponse(
        status="ok",
        service=settings.PROJECT_NAME,
        version="1.0.0",
        timestamp=datetime.now(timezone.utc),
        timezone=settings.TIMEZONE
    )
