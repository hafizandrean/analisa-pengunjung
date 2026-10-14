import uuid
from sqlalchemy import Column, String, Integer, Boolean, DateTime
from sqlalchemy.dialects.postgresql import UUID, JSONB
from sqlalchemy.orm import relationship
from sqlalchemy.sql import func
from app.db.base_class import Base


class Camera(Base):
    __tablename__ = "cameras"

    id = Column(UUID(as_uuid=True), primary_key=True, default=uuid.uuid4)
    name = Column(String(100), nullable=False)
    location = Column(String(100), nullable=False)
    source_type = Column(String(50), nullable=False, default="RTSP")
    source_uri = Column(String(500), nullable=False)
    resolution_width = Column(Integer, nullable=False, default=1280)
    resolution_height = Column(Integer, nullable=False, default=720)
    fps = Column(Integer, nullable=False, default=25)
    line_coords = Column(JSONB, nullable=False)
    is_active = Column(Boolean, nullable=False, default=True)
    connection_status = Column(String(50), nullable=False, default="DISCONNECTED")
    processing_status = Column(String(50), nullable=False, default="STOPPED")
    last_connected_at = Column(DateTime(timezone=True), nullable=True)
    last_frame_at = Column(DateTime(timezone=True), nullable=True)
    notes = Column(String(500), nullable=True)
    connection_error = Column(String(500), nullable=True)
    username = Column(String(100), nullable=True)
    password_encrypted = Column(String(255), nullable=True)
    created_at = Column(DateTime(timezone=True), nullable=False, server_default=func.now())
    updated_at = Column(DateTime(timezone=True), nullable=False, server_default=func.now(), onupdate=func.now())

    sessions = relationship("CountingSession", back_populates="camera", cascade="all, delete-orphan")
    events = relationship("CountingEvent", back_populates="camera", cascade="all, delete-orphan")

    @property
    def source_uri_masked(self) -> str:
        """Penyembunyian kredensial RTSP agar tidak terekspos ke frontend atau log."""
        raw = self.source_uri or ""
        if "@" in raw and "://" in raw:
            protocol, rest = raw.split("://", 1)
            creds, host = rest.split("@", 1)
            if ":" in creds:
                u, _ = creds.split(":", 1)
                return f"{protocol}://{u}:***@{host}"
            return f"{protocol}://***:***@{host}"
        elif self.username and self.password_encrypted and "://" in raw:
            protocol, rest = raw.split("://", 1)
            return f"{protocol}://{self.username}:***@{rest}"
        return raw
