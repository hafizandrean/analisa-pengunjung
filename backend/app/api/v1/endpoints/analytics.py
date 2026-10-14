from typing import List, Optional
from datetime import datetime, timezone, timedelta
import uuid
from fastapi import APIRouter, Depends, Query
from sqlalchemy import func, and_, case
from sqlalchemy.orm import Session
from app.db.session import get_db
from app.models.counting_event import CountingEvent
from app.schemas.analytics import (
    HourlyTrafficItem,
    DailyTrafficItem,
    PeakHourItem,
    PeriodComparisonItem
)

router = APIRouter()

INDONESIAN_DAYS = {
    0: "Senin",
    1: "Selasa",
    2: "Rabu",
    3: "Kamis",
    4: "Jumat",
    5: "Sabtu",
    6: "Minggu"
}


@router.get("/hourly", response_model=List[HourlyTrafficItem])
def get_hourly_analytics(
    date: Optional[str] = Query(None, description="Tanggal format YYYY-MM-DD (default: hari ini)"),
    camera_id: Optional[str] = Query(None),
    source_mode: Optional[str] = Query(None),
    db: Session = Depends(get_db)
):
    """Menghitung distribusi volume pengunjung per jam dari basis data PostgreSQL."""
    now = datetime.now(timezone.utc)
    if date:
        try:
            target_date = datetime.strptime(date, "%Y-%m-%d").date()
            start_time = datetime(target_date.year, target_date.month, target_date.day, 0, 0, 0, tzinfo=timezone.utc)
        except ValueError:
            start_time = now.replace(hour=0, minute=0, second=0, microsecond=0)
    else:
        start_time = now.replace(hour=0, minute=0, second=0, microsecond=0)

    end_time = start_time + timedelta(days=1)

    filters = [
        CountingEvent.event_timestamp >= start_time,
        CountingEvent.event_timestamp < end_time
    ]
    if camera_id and camera_id != "all":
        try:
            filters.append(CountingEvent.camera_id == uuid.UUID(camera_id))
        except ValueError:
            pass
    if source_mode:
        filters.append(CountingEvent.source_mode == source_mode.upper())

    # Agregasi per jam menggunakan SQL
    results = db.query(
        func.extract("hour", CountingEvent.event_timestamp).label("hour_int"),
        func.sum(case((CountingEvent.direction == "IN", CountingEvent.count_value), else_=0)).label("in_sum"),
        func.sum(case((CountingEvent.direction == "OUT", CountingEvent.count_value), else_=0)).label("out_sum")
    ).filter(
        and_(*filters)
    ).group_by("hour_int").order_by("hour_int").all()

    hour_map = {int(r[0]): (int(r[1] or 0), int(r[2] or 0)) for r in results if r[0] is not None}

    # Restoran buka jam 10:00 s/d 21:00
    hourly_items = []
    for h in range(10, 22):
        in_val, out_val = hour_map.get(h, (0, 0))
        hourly_items.append(
            HourlyTrafficItem(
                hour=f"{h:02d}:00",
                count_in=in_val,
                count_out=out_val,
                total=in_val + out_val
            )
        )

    return hourly_items


@router.get("/daily", response_model=List[DailyTrafficItem])
def get_daily_analytics(
    days: int = Query(7, ge=1, le=30),
    camera_id: Optional[str] = Query(None),
    source_mode: Optional[str] = Query(None),
    db: Session = Depends(get_db)
):
    """Menghitung total pengunjung harian untuk N hari terakhir dari basis data PostgreSQL."""
    now = datetime.now(timezone.utc)
    today = now.date()
    start_date = today - timedelta(days=days - 1)
    start_dt = datetime(start_date.year, start_date.month, start_date.day, 0, 0, 0, tzinfo=timezone.utc)

    filters = [CountingEvent.event_timestamp >= start_dt]
    if camera_id and camera_id != "all":
        try:
            filters.append(CountingEvent.camera_id == uuid.UUID(camera_id))
        except ValueError:
            pass
    if source_mode:
        filters.append(CountingEvent.source_mode == source_mode.upper())

    results = db.query(
        func.date(CountingEvent.event_timestamp).label("dt"),
        func.sum(case((CountingEvent.direction == "IN", CountingEvent.count_value), else_=0)).label("in_sum"),
        func.sum(case((CountingEvent.direction == "OUT", CountingEvent.count_value), else_=0)).label("out_sum")
    ).filter(
        and_(*filters)
    ).group_by("dt").order_by("dt").all()

    date_map = {r[0]: (int(r[1] or 0), int(r[2] or 0)) for r in results}

    daily_items = []
    for i in range(days):
        curr_d = start_date + timedelta(days=i)
        in_cnt, out_cnt = date_map.get(curr_d, (0, 0))
        day_name = INDONESIAN_DAYS[curr_d.weekday()]
        daily_items.append(
            DailyTrafficItem(
                date=curr_d.strftime("%Y-%m-%d"),
                day_name=day_name,
                count_in=in_cnt,
                count_out=out_cnt,
                total=in_cnt + out_cnt
            )
        )

    return daily_items


@router.get("/peak-hours", response_model=List[PeakHourItem])
def get_peak_hours_analytics(
    date: Optional[str] = Query(None),
    source_mode: Optional[str] = Query(None),
    db: Session = Depends(get_db)
):
    """Mengidentifikasi jam-jam sibuk utama restoran Ayam Bang Jungko berdasarkan volume crossing riil."""
    now = datetime.now(timezone.utc)
    if date:
        try:
            target_date = datetime.strptime(date, "%Y-%m-%d").date()
            start_time = datetime(target_date.year, target_date.month, target_date.day, 0, 0, 0, tzinfo=timezone.utc)
        except ValueError:
            start_time = now.replace(hour=0, minute=0, second=0, microsecond=0)
    else:
        start_time = now.replace(hour=0, minute=0, second=0, microsecond=0)

    end_time = start_time + timedelta(days=1)

    filters = [
        CountingEvent.event_timestamp >= start_time,
        CountingEvent.event_timestamp < end_time
    ]
    if source_mode:
        filters.append(CountingEvent.source_mode == source_mode.upper())

    # Total volume hari itu
    total_day = db.query(func.coalesce(func.sum(CountingEvent.count_value), 0)).filter(
        and_(*filters)
    ).scalar() or 1

    results = db.query(
        func.extract("hour", CountingEvent.event_timestamp).label("h_int"),
        func.sum(CountingEvent.count_value).label("cnt")
    ).filter(
        and_(*filters)
    ).group_by("h_int").order_by(func.sum(CountingEvent.count_value).desc()).limit(5).all()

    peak_items = []
    for r in results:
        if r[0] is None:
            continue
        h = int(r[0])
        cnt = int(r[1] or 0)
        pct = round((cnt / total_day) * 100, 1)

        # Penanda waktu shift Ayam Bang Jungko
        label = "Makan Siang" if 11 <= h <= 14 else "Makan Malam" if 17 <= h <= 20 else "Sore Hari"
        occupancy = "PADAT" if pct >= 20.0 else "SEDANG" if pct >= 10.0 else "LENGANG"

        peak_items.append(
            PeakHourItem(
                time_window=f"{h:02d}:00 - {h+1:02d}:00 ({label})",
                count=cnt,
                occupancy_status=occupancy,
                percentage_of_daily=pct
            )
        )

    return peak_items


@router.get("/comparison", response_model=List[PeriodComparisonItem])
def get_period_comparison(
    period_type: str = Query("weekly"),
    db: Session = Depends(get_db)
):
    """Membandingkan trafik hari demi hari antara 7 hari berjalan vs 7 hari sebelumnya."""
    now = datetime.now(timezone.utc)
    today = now.date()

    comparison_items = []
    for i in range(7):
        curr_d = today - timedelta(days=6 - i)
        prev_d = curr_d - timedelta(days=7)

        # Hitung volume hari ini
        c_start = datetime(curr_d.year, curr_d.month, curr_d.day, 0, 0, 0, tzinfo=timezone.utc)
        c_end = c_start + timedelta(days=1)
        curr_cnt = db.query(func.coalesce(func.sum(CountingEvent.count_value), 0)).filter(
            CountingEvent.event_timestamp >= c_start,
            CountingEvent.event_timestamp < c_end
        ).scalar() or 0

        # Hitung volume hari yang sama minggu lalu
        p_start = datetime(prev_d.year, prev_d.month, prev_d.day, 0, 0, 0, tzinfo=timezone.utc)
        p_end = p_start + timedelta(days=1)
        prev_cnt = db.query(func.coalesce(func.sum(CountingEvent.count_value), 0)).filter(
            CountingEvent.event_timestamp >= p_start,
            CountingEvent.event_timestamp < p_end
        ).scalar() or 0

        pct_change = 0.0
        if prev_cnt > 0:
            pct_change = round(((curr_cnt - prev_cnt) / prev_cnt) * 100, 1)

        day_label = INDONESIAN_DAYS[curr_d.weekday()]
        if curr_d.weekday() in [5, 6]:
            day_label += " (Weekend)"

        comparison_items.append(
            PeriodComparisonItem(
                period_label=day_label,
                current_period_count=int(curr_cnt),
                previous_period_count=int(prev_cnt),
                change_percentage=pct_change
            )
        )

    return comparison_items
