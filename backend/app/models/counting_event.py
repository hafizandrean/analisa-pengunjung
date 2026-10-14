import uuid
from sqlalchemy import Column, String, Integer, Float, DateTime, ForeignKey, Index
from sqlalchemy.dialects.postgresql import UUID, JSONB
from sqlalchemy.orm import relationship
from sqlalchemy.sql import func
from app.db.base_class import Base


class CountingEvent(Base):
    __tablename__ = "counting_events"

    id = Column(UUID(as_uuid=True), primary_key=True, default=uuid.uuid4)
    camera_id = Column(UUID(as_uuid=True), ForeignKey("cameras.id", ondelete="CASCADE"), nullable=False)
    session_id = Column(UUID(as_uuid=True), ForeignKey("counting_sessions.id", ondelete="SET NULL"), nullable=True)
    event_timestamp = Column(DateTime(timezone=True), nullable=False, server_default=func.now(), index=True)
    direction = Column(String(10), nullable=False, index=True)  # 'IN' or 'OUT'
    count_value = Column(Integer, nullable=False, default=1)
    track_id = Column(Integer, nullable=True)
    confidence = Column(Float, nullable=True)
    source_mode = Column(String(20), nullable=False, default="DEMO", index=True)  # 'DEMO', 'VIDEO', 'LIVE'
    metadata_json = Column(JSONB, nullable=True)
    created_at = Column(DateTime(timezone=True), nullable=False, server_default=func.now())

    camera = relationship("Camera", back_populates="events")
    session = relationship("CountingSession", back_populates="events")

    __table_args__ = (
        Index("idx_counting_events_cam_ts", "camera_id", event_timestamp.desc()),
    )
