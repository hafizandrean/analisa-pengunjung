from datetime import datetime, timezone, timedelta
from typing import Optional
import uuid
from fastapi import APIRouter, Depends, Query
from sqlalchemy import func, and_
from sqlalchemy.orm import Session
from app.db.session import get_db
from app.models.counting_event import CountingEvent
from app.models.camera import Camera
from app.models.counting_session import CountingSession
from app.schemas.analytics import DashboardSummaryResponse

router = APIRouter()


@router.get("/summary", response_model=DashboardSummaryResponse)
def get_dashboard_summary(
    camera_id: Optional[str] = Query(None, description="Filter berdasarkan ID kamera"),
    source_mode: Optional[str] = Query(None, description="Filter mode data: 'DEMO', 'VIDEO', 'LIVE'"),
    db: Session = Depends(get_db)
):
    """Menghitung metrik ringkasan dashboard operasional langsung dari basis data PostgreSQL."""
    now = datetime.now(timezone.utc)
    today_start = now.replace(hour=0, minute=0, second=0, microsecond=0)
    week_start = now - timedelta(days=7)
    yesterday_start = today_start - timedelta(days=1)

    # Base query filter
    filters = []
    if camera_id and camera_id != "all":
        try:
            filters.append(CountingEvent.camera_id == uuid.UUID(camera_id))
        except ValueError:
            pass
    if source_mode:
        filters.append(CountingEvent.source_mode == source_mode.upper())

    # 1. Total Pengunjung Hari Ini (In & Out)
    today_in = db.query(func.coalesce(func.sum(CountingEvent.count_value), 0)).filter(
        and_(
            *filters,
            CountingEvent.event_timestamp >= today_start,
            CountingEvent.direction == "IN"
        )
    ).scalar() or 0

    today_out = db.query(func.coalesce(func.sum(CountingEvent.count_value), 0)).filter(
        and_(
            *filters,
            CountingEvent.event_timestamp >= today_start,
            CountingEvent.direction == "OUT"
        )
    ).scalar() or 0

    total_today = today_in + today_out

    # 2. Total Pengunjung Kemarin (untuk perbandingan tren)
    yesterday_total = db.query(func.coalesce(func.sum(CountingEvent.count_value), 0)).filter(
        and_(
            *filters,
            CountingEvent.event_timestamp >= yesterday_start,
            CountingEvent.event_timestamp < today_start
        )
    ).scalar() or 0

    trend_today = 0.0
    if yesterday_total > 0:
        trend_today = round(((total_today - yesterday_total) / yesterday_total) * 100, 1)

    # 3. Total Pengunjung Minggu Ini (7 Hari Terakhir)
    total_week = db.query(func.coalesce(func.sum(CountingEvent.count_value), 0)).filter(
        and_(
            *filters,
            CountingEvent.event_timestamp >= week_start
        )
    ).scalar() or 0

    # Total Minggu Sebelumnya (Hari 8-14 lalu)
    two_weeks_ago = week_start - timedelta(days=7)
    last_week_total = db.query(func.coalesce(func.sum(CountingEvent.count_value), 0)).filter(
        and_(
            *filters,
            CountingEvent.event_timestamp >= two_weeks_ago,
            CountingEvent.event_timestamp < week_start
        )
    ).scalar() or 0

    trend_week = 0.0
    if last_week_total > 0:
        trend_week = round(((total_week - last_week_total) / last_week_total) * 100, 1)

    average_daily = round(total_week / 7.0, 1) if total_week > 0 else 0.0

    # 4. Jam Paling Sibuk (Busiest Hour) Hari Ini
    busiest_hour_query = db.query(
        func.extract("hour", CountingEvent.event_timestamp).label("hour_val"),
        func.sum(CountingEvent.count_value).label("cnt")
    ).filter(
        and_(*filters, CountingEvent.event_timestamp >= today_start)
    ).group_by("hour_val").order_by(func.sum(CountingEvent.count_value).desc()).first()

    busiest_hour_str = "18:00 - 19:00"
    busiest_hour_cnt = 0
    if busiest_hour_query and busiest_hour_query[0] is not None:
        h = int(busiest_hour_query[0])
        busiest_hour_str = f"{h:02d}:00 - {h+1:02d}:00"
        busiest_hour_cnt = int(busiest_hour_query[1])

    # 5. Status Kamera Aktif & Sesi Worker
    active_cameras = db.query(Camera).filter(Camera.is_active == True).count()
    running_session = db.query(CountingSession).filter(CountingSession.status == "RUNNING").first()
    worker_status = "RUNNING" if running_session else "IDLE"

    return DashboardSummaryResponse(
        total_today=int(total_today),
        total_today_in=int(today_in),
        total_today_out=int(today_out),
        total_week=int(total_week),
        average_daily=float(average_daily),
        busiest_hour=busiest_hour_str,
        busiest_hour_count=int(busiest_hour_cnt),
        active_cameras=active_cameras,
        worker_status=worker_status,
        source_mode=source_mode.upper() if source_mode else "DEMO",
        trend_today_vs_yesterday=trend_today,
        trend_week_vs_last_week=trend_week
    )
