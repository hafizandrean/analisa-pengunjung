from typing import Optional, Dict, Any, Literal, List
from uuid import UUID
from datetime import datetime
from pydantic import BaseModel, Field, ConfigDict


class VirtualLineCoords(BaseModel):
    x1: int = Field(default=100, description="Titik awal X garis virtual")
    y1: int = Field(default=360, description="Titik awal Y garis virtual")
    x2: int = Field(default=1180, description="Titik akhir X garis virtual")
    y2: int = Field(default=360, description="Titik akhir Y garis virtual")
    direction_in: str = Field(default="down", description="'down', 'up', 'left', or 'right'")
    offset: int = Field(default=30, description="Toleransi deteksi crossing dalam pixel")
    reference_width: Optional[int] = Field(default=1280, description="Lebar acuan frame video")
    reference_height: Optional[int] = Field(default=720, description="Tinggi acuan frame video")
    is_enabled: Optional[bool] = Field(default=True, description="Status aktif garis virtual")


class CameraBase(BaseModel):
    name: str = Field(..., min_length=2, max_length=100, description="Nama kamera CCTV")
    location: str = Field(..., min_length=2, max_length=100, description="Lokasi penempatan")
    source_type: str = Field(
        default="RTSP",
        description="Tipe sumber live: 'RTSP', 'NVR_CHANNEL', 'USB_WEBCAM', 'HTTP_STREAM'"
    )
    resolution_width: int = Field(default=1280, ge=160, le=7680)
    resolution_height: int = Field(default=720, ge=120, le=4320)
    fps: int = Field(default=25, ge=1, le=120)
    line_coords: VirtualLineCoords
    is_active: bool = Field(default=True, description="Status aktif konfigurasi kamera")
    notes: Optional[str] = Field(default=None, max_length=500, description="Catatan operasional kamera")


class CameraCreate(CameraBase):
    source_uri: str = Field(..., min_length=1, max_length=500, description="URL RTSP stream IP Camera/NVR, identifier webcam, atau URL HTTP stream")
    username: Optional[str] = Field(default=None, max_length=100, description="Nama pengguna kamera IP terproteksi")
    password: Optional[str] = Field(default=None, max_length=255, description="Kata sandi kamera IP terproteksi")


class CameraUpdate(BaseModel):
    name: Optional[str] = Field(default=None, min_length=2, max_length=100)
    location: Optional[str] = Field(default=None, min_length=2, max_length=100)
    source_type: Optional[str] = None
    source_uri: Optional[str] = Field(default=None, min_length=1, max_length=500)
    username: Optional[str] = None
    password: Optional[str] = None
    resolution_width: Optional[int] = None
    resolution_height: Optional[int] = None
    fps: Optional[int] = None
    line_coords: Optional[VirtualLineCoords] = None
    is_active: Optional[bool] = None
    connection_status: Optional[str] = None
    processing_status: Optional[str] = None
    notes: Optional[str] = None


class CameraResponse(CameraBase):
    id: UUID
    source_uri_masked: str  # Safe string masked for frontend display without RTSP credentials
    connection_status: str = "DISCONNECTED"  # DISCONNECTED, CONNECTING, ONLINE, RECONNECTING, ERROR
    processing_status: str = "STOPPED"  # STOPPED, STARTING, RUNNING, ERROR
    last_connected_at: Optional[datetime] = None
    last_frame_at: Optional[datetime] = None
    connection_error: Optional[str] = None
    username: Optional[str] = None
    created_at: datetime
    updated_at: datetime

    model_config = ConfigDict(from_attributes=True)


class CameraTestResult(BaseModel):
    camera_id: UUID
    is_accessible: bool
    connection_status: str  # 'ONLINE', 'DISCONNECTED', 'ERROR'
    resolution_width: Optional[int] = None
    resolution_height: Optional[int] = None
    fps: Optional[int] = None
    message: str
    error_detail: Optional[str] = None
    checked_at: datetime


class CameraProcessingStatusResponse(BaseModel):
    camera_id: UUID
    processing_status: str  # 'STOPPED', 'STARTING', 'RUNNING', 'ERROR'
    connection_status: str  # 'DISCONNECTED', 'CONNECTING', 'ONLINE', 'RECONNECTING', 'ERROR'
    session_id: Optional[UUID] = None
    message: str


class DiscoveredCCTVDevice(BaseModel):
    device_id: str = Field(..., description="URN atau identifier unik perangkat")
    name: str = Field(..., description="Nama perangkat / model yang teridentifikasi")
    ip_address: str = Field(..., description="Alamat IP lokal perangkat")
    port: int = Field(default=80, description="Port HTTP/ONVIF service")
    onvif_xaddr: str = Field(..., description="ONVIF Service Endpoint (XAddrs)")
    manufacturer: Optional[str] = Field(default=None, description="Produsen perangkat")
    model: Optional[str] = Field(default=None, description="Tipe/model perangkat")
    location: Optional[str] = Field(default=None, description="Lokasi dari metadata ONVIF scope")
    types: List[str] = Field(default_factory=list, description="Daftar ONVIF WS-Discovery Types")
    scopes: List[str] = Field(default_factory=list, description="Daftar ONVIF Scopes")
    suggested_rtsp_uri: str = Field(..., description="Rekomendasi URL RTSP stream untuk kamera ini")
    capabilities: Dict[str, Any] = Field(default_factory=dict, description="Kapabilitas ONVIF yang terdeteksi")


class DiscoveryResponse(BaseModel):
    success: bool
    device_count: int
    devices: List[DiscoveredCCTVDevice]
    duration_seconds: float
    message: str
    network_scope: str = "Jaringan Lokal (UDP Multicast WS-Discovery 239.255.255.250:3702)"
    error_detail: Optional[str] = None


class DeviceValidationRequest(BaseModel):
    ip_address: str
    port: int = 554
    source_type: str = "RTSP"  # RTSP atau NVR_CHANNEL
    rtsp_uri: str
    username: Optional[str] = None
    password: Optional[str] = None
    timeout_seconds: float = 5.0
    # Opsional: diisi saat validasi dari ONVIF Discovery agar sistem dapat
    # memanggil GetStreamUri SOAP untuk mendapatkan URL RTSP yang benar
    onvif_xaddr: Optional[str] = None
    manufacturer: Optional[str] = None


class DeviceValidationResponse(BaseModel):
    is_accessible: bool
    connection_status: str  # ONLINE, ERROR, TIMEOUT
    resolution_width: Optional[int] = None
    resolution_height: Optional[int] = None
    fps: Optional[int] = None
    message: str
    error_detail: Optional[str] = None
    # URL RTSP yang berhasil terhubung (mungkin berbeda dari URL yang diminta)
    # Diisi saat validasi berhasil agar UI dapat menampilkan dan auto-fill URL yang tepat
    working_rtsp_uri: Optional[str] = None
    checked_at: datetime

