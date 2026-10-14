from fastapi import APIRouter
from app.api.v1.endpoints import (
    health,
    auth,
    dashboard,
    analytics,
    events,
    cameras,
    sessions,
    system
)

api_router = APIRouter()

api_router.include_router(health.router, tags=["Health"])
api_router.include_router(auth.router, prefix="/auth", tags=["Authentication"])
api_router.include_router(dashboard.router, prefix="/dashboard", tags=["Dashboard"])
api_router.include_router(analytics.router, prefix="/analytics", tags=["Crowd Analytics"])
api_router.include_router(events.router, prefix="/events", tags=["Counting Events"])
api_router.include_router(cameras.router, prefix="/cameras", tags=["Cameras"])
api_router.include_router(sessions.router, prefix="/sessions", tags=["Counting Sessions"])
api_router.include_router(system.router, prefix="/system", tags=["System Status"])
