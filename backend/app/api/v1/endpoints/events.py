from typing import Optional
from datetime import datetime, timezone
import uuid
from fastapi import APIRouter, Depends, Query, HTTPException, status
from sqlalchemy.orm import Session
from sqlalchemy import or_, and_
from app.db.session import get_db
from app.models.counting_event import CountingEvent
from app.models.camera import Camera
from app.models.counting_session import CountingSession
from app.models.system_log import SystemLog
from app.schemas.events import CountingEventListResponse, CountingEventResponse, CountingEventCreate

router = APIRouter()


@router.get("", response_model=CountingEventListResponse)
def get_counting_events(
    page: int = Query(1, ge=1),
    page_size: int = Query(15, ge=1, le=100),
    direction: Optional[str] = Query(None, description="'IN' or 'OUT'"),
    camera_id: Optional[str] = Query(None),
    source_mode: Optional[str] = Query(None, description="'DEMO', 'VIDEO', 'LIVE'"),
    search: Optional[str] = Query(None),
    db: Session = Depends(get_db)
):
    """Mengambil riwayat penyeberangan garis virtual dengan filter dinamis dan paginasi dari basis data."""
    query = db.query(CountingEvent, Camera.name.label("camera_name")).outerjoin(
        Camera, CountingEvent.camera_id == Camera.id
    )

    filters = []
    if direction:
        filters.append(CountingEvent.direction == direction.upper())
    if camera_id and camera_id != "all":
        try:
            filters.append(CountingEvent.camera_id == uuid.UUID(camera_id))
        except ValueError:
            pass
    if source_mode:
        filters.append(CountingEvent.source_mode == source_mode.upper())
    if search:
        search_term = f"%{search.lower()}%"
        try:
            # If search is an integer track_id
            track_int = int(search)
            filters.append(
                or_(
                    CountingEvent.track_id == track_int,
                    CountingEvent.direction.ilike(search_term),
                    Camera.name.ilike(search_term)
                )
            )
        except ValueError:
            filters.append(
                or_(
                    CountingEvent.direction.ilike(search_term),
                    Camera.name.ilike(search_term)
                )
            )

    if filters:
        query = query.filter(and_(*filters))

    total = query.count()
    offset = (page - 1) * page_size
    records = query.order_by(CountingEvent.event_timestamp.desc()).offset(offset).limit(page_size).all()

    items = []
    for evt, cam_name in records:
        items.append(
            CountingEventResponse(
                id=evt.id,
                camera_id=evt.camera_id,
                camera_name=cam_name or "CCTV Pintu Masuk Utama",
                session_id=evt.session_id,
                event_timestamp=evt.event_timestamp,
                direction=evt.direction,
                count_value=evt.count_value,
                track_id=evt.track_id,
                confidence=evt.confidence,
                source_mode=evt.source_mode,
                metadata_json=evt.metadata_json,
                created_at=evt.created_at
            )
        )

    return CountingEventListResponse(
        total=total,
        page=page,
        page_size=page_size,
        items=items
    )


@router.post("", response_model=CountingEventResponse, status_code=status.HTTP_201_CREATED)
def record_counting_event(event_in: CountingEventCreate, db: Session = Depends(get_db)):
    """Mencatat kejadian penyeberangan garis virtual baru ke PostgreSQL dan memperbarui total sesi."""
    # Validasi camera_id
    cam = db.query(Camera).filter(Camera.id == event_in.camera_id).first()
    if not cam:
        raise HTTPException(status_code=404, detail="Kamera tidak ditemukan dalam sistem.")

    new_event = CountingEvent(
        id=uuid.uuid4(),
        camera_id=event_in.camera_id,
        session_id=event_in.session_id,
        event_timestamp=event_in.event_timestamp,
        direction=event_in.direction.upper(),
        count_value=event_in.count_value,
        track_id=event_in.track_id,
        confidence=event_in.confidence,
        source_mode=event_in.source_mode.upper(),
        metadata_json=event_in.metadata_json
    )
    db.add(new_event)

    # Perbarui akumulasi pada counting_sessions jika ada session_id
    if event_in.session_id:
        sess = db.query(CountingSession).filter(CountingSession.id == event_in.session_id).first()
        if sess:
            if event_in.direction.upper() == "IN":
                sess.total_in += event_in.count_value
            elif event_in.direction.upper() == "OUT":
                sess.total_out += event_in.count_value

    db.commit()
    db.refresh(new_event)

    return CountingEventResponse(
        id=new_event.id,
        camera_id=new_event.camera_id,
        camera_name=cam.name,
        session_id=new_event.session_id,
        event_timestamp=new_event.event_timestamp,
        direction=new_event.direction,
        count_value=new_event.count_value,
        track_id=new_event.track_id,
        confidence=new_event.confidence,
        source_mode=new_event.source_mode,
        metadata_json=new_event.metadata_json,
        created_at=new_event.created_at
    )
