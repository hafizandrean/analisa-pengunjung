from typing import Optional, List, Dict, Any
from uuid import UUID
from datetime import datetime
from pydantic import BaseModel, Field, ConfigDict


class CountingEventCreate(BaseModel):
    camera_id: UUID
    session_id: Optional[UUID] = None
    event_timestamp: datetime
    direction: str = Field(description="'IN' or 'OUT'")
    count_value: int = Field(default=1, ge=1)
    track_id: Optional[int] = None
    confidence: Optional[float] = None
    source_mode: str = Field(default="DEMO", description="'DEMO', 'VIDEO', 'LIVE'")
    metadata_json: Optional[Dict[str, Any]] = None


class CountingEventResponse(BaseModel):
    id: UUID
    camera_id: UUID
    camera_name: Optional[str] = None
    session_id: Optional[UUID] = None
    event_timestamp: datetime
    direction: str
    count_value: int
    track_id: Optional[int] = None
    confidence: Optional[float] = None
    source_mode: str
    metadata_json: Optional[Dict[str, Any]] = None
    created_at: datetime

    model_config = ConfigDict(from_attributes=True)


class CountingEventListResponse(BaseModel):
    total: int
    page: int
    page_size: int
    items: List[CountingEventResponse]
