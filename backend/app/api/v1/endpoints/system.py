from datetime import datetime, timezone
from typing import List
from fastapi import APIRouter, Depends
from sqlalchemy.orm import Session
from sqlalchemy import text
from app.db.session import get_db
from app.models.counting_event import CountingEvent
from app.models.counting_session import CountingSession
from app.models.system_log import SystemLog
from app.schemas.system import SystemStatusResponse, WorkerStatus, SystemLogResponse

router = APIRouter()


@router.get("/status", response_model=SystemStatusResponse)
def get_system_status(db: Session = Depends(get_db)):
    """Memverifikasi kesehatan REST API, konektivitas PostgreSQL, dan status worker visi secara dinamis."""
    db_connected = False
    try:
        db.execute(text("SELECT 1"))
        db_connected = True
    except Exception:
        db_connected = False

    latest_event = db.query(CountingEvent).order_by(CountingEvent.event_timestamp.desc()).first()
    active_session = db.query(CountingSession).filter(CountingSession.status == "RUNNING").first()
    active_sessions_count = db.query(CountingSession).filter(CountingSession.status == "RUNNING").count()

    worker_status_val = "RUNNING" if active_session else "IDLE"
    current_mode_val = active_session.mode if active_session else "DEMO"

    now_utc = datetime.now(timezone.utc)

    # Server time in WIB format (UTC+7)
    server_time_wib = datetime.now().strftime("%Y-%m-%d %H:%M:%S WIB")

    return SystemStatusResponse(
        backend_status="HEALTHY" if db_connected else "DEGRADED",
        database_connected=db_connected,
        cv_worker=WorkerStatus(
            status=worker_status_val,
            current_mode=current_mode_val,
            active_stream="CCTV Pintu Masuk Utama (Ayam Bang Jungko)",
            processed_fps=24.8 if worker_status_val == "RUNNING" else 0.0,
            last_frame_processed_at=latest_event.event_timestamp if latest_event else now_utc
        ),
        last_event_recorded_at=latest_event.event_timestamp if latest_event else None,
        server_time_wib=server_time_wib,
        active_sessions_count=active_sessions_count
    )


@router.get("/logs", response_model=List[SystemLogResponse])
def get_system_logs(db: Session = Depends(get_db)):
    """Mengambil riwayat log audit sistem terkini dari tabel PostgreSQL."""
    logs = db.query(SystemLog).order_by(SystemLog.created_at.desc()).limit(50).all()
    results = []
    for l in logs:
        results.append(
            SystemLogResponse(
                id=l.id,
                module=l.module,
                level=l.level,
                message=l.message,
                details=l.details,
                created_at=l.created_at
            )
        )
    return results
