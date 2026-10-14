from typing import List, Optional
from datetime import date, datetime
from pydantic import BaseModel


class MetricTrend(BaseModel):
    value: int
    change_percentage: float
    direction: str  # 'up', 'down', 'neutral'


class DashboardSummaryResponse(BaseModel):
    total_today: int
    total_today_in: int
    total_today_out: int
    total_week: int
    average_daily: float
    busiest_hour: str  # e.g., "12:00 - 13:00"
    busiest_hour_count: int
    active_cameras: int
    worker_status: str  # 'RUNNING', 'IDLE', 'ERROR'
    source_mode: str  # 'DEMO', 'VIDEO', 'LIVE'
    trend_today_vs_yesterday: float
    trend_week_vs_last_week: float


class HourlyTrafficItem(BaseModel):
    hour: str  # e.g., "08:00", "09:00"
    count_in: int
    count_out: int
    total: int


class DailyTrafficItem(BaseModel):
    date: str  # e.g., "2026-10-12"
    day_name: str  # e.g., "Senin", "Selasa"
    count_in: int
    count_out: int
    total: int


class PeakHourItem(BaseModel):
    time_window: str  # e.g., "12:00 - 13:00 (Makan Siang)"
    count: int
    occupancy_status: str  # 'PADAT', 'SEDANG', 'LENGANG'
    percentage_of_daily: float


class PeriodComparisonItem(BaseModel):
    period_label: str
    current_period_count: int
    previous_period_count: int
    change_percentage: float
