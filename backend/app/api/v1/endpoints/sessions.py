from typing import List
import uuid
from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session
from app.db.session import get_db
from app.models.counting_session import CountingSession
from app.models.camera import Camera
from app.schemas.system import SessionResponse

router = APIRouter()


@router.get("", response_model=List[SessionResponse])
def get_sessions(db: Session = Depends(get_db)):
    """Mengambil riwayat sesi pemrosesan video analitik dari basis data PostgreSQL."""
    sessions = db.query(CountingSession, Camera.name.label("camera_name")).outerjoin(
        Camera, CountingSession.camera_id == Camera.id
    ).order_by(CountingSession.started_at.desc()).all()

    results = []
    for sess, cam_name in sessions:
        results.append(
            SessionResponse(
                id=sess.id,
                camera_id=sess.camera_id,
                camera_name=cam_name or "CCTV Pintu Masuk Utama",
                mode=sess.mode,
                status=sess.status,
                started_at=sess.started_at,
                ended_at=sess.ended_at,
                total_in=sess.total_in,
                total_out=sess.total_out,
                notes=sess.notes
            )
        )
    return results
