from typing import Optional, List, Dict, Any
from uuid import UUID
from datetime import datetime
from pydantic import BaseModel, ConfigDict


class HealthResponse(BaseModel):
    status: str
    service: str
    version: str
    timestamp: datetime
    timezone: str


class WorkerStatus(BaseModel):
    status: str  # 'RUNNING', 'IDLE', 'OFFLINE'
    current_mode: str  # 'DEMO', 'LIVE_CCTV', 'WEBCAM'
    active_stream: Optional[str] = None
    processed_fps: Optional[float] = None
    last_frame_processed_at: Optional[datetime] = None


class SystemStatusResponse(BaseModel):
    backend_status: str  # 'HEALTHY', 'DEGRADED', 'DOWN'
    database_connected: bool
    cv_worker: WorkerStatus
    last_event_recorded_at: Optional[datetime] = None
    server_time_wib: str
    active_sessions_count: int


class SessionResponse(BaseModel):
    id: UUID
    camera_id: UUID
    camera_name: Optional[str] = None
    mode: str
    status: str
    started_at: datetime
    ended_at: Optional[datetime] = None
    total_in: int
    total_out: int
    notes: Optional[str] = None

    model_config = ConfigDict(from_attributes=True)


class SystemLogResponse(BaseModel):
    id: UUID
    module: str
    level: str
    message: str
    details: Optional[Dict[str, Any]] = None
    created_at: datetime

    model_config = ConfigDict(from_attributes=True)
