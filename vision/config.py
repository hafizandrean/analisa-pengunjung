import os
from typing import Dict, Any, Optional
from pydantic import BaseModel, Field


class VisionWorkerConfig(BaseModel):
    video_source: str = Field(default="0", description="Index kamera lokal (misal '0' untuk webcam laptop) atau URL stream live RTSP")
    camera_id: str = Field(default="c0000000-0000-0000-0000-000000000001", description="ID Kamera UUID")
    session_id: Optional[str] = Field(default="e0000000-0000-0000-0000-000000000001", description="ID Sesi aktif")
    model_path: str = Field(default="vision/models/yolov8n.pt", description="Path bobot model YOLOv8n")
    confidence_threshold: float = Field(default=0.30, ge=0.05, le=0.95)
    iou_threshold: float = Field(default=0.45, ge=0.1, le=0.9)
    tracker_type: str = Field(default="vision/bytetrack_custom.yaml")
    
    # Koordinat garis virtual crossing (default sesuai dimensi video sample 480x368)
    line_coords: Dict[str, Any] = Field(
        default_factory=lambda: {
            "x1": 40,
            "y1": 184,
            "x2": 440,
            "y2": 184,
            "direction_in": "down",
            "offset": 25
        }
    )
    
    backend_api_url: str = Field(default="http://127.0.0.1:8000/api/v1")
    source_mode: str = Field(default="LIVE", description="Mode sumber data ('LIVE')")
    show_preview: bool = Field(default=False, description="Tampilkan jendela visual OpenCV")
    save_output_video: bool = Field(default=False, description="Simpan rekaman video beranotasi")
    start_frame: int = Field(default=0, description="Nomor frame awal pemrosesan")
    max_frames: Optional[int] = Field(default=None, description="Batasan jumlah frame uji coba")
    loop_video: bool = Field(default=False, description="Looping video jika mencapai EOF")
