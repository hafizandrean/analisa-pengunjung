import os
import uuid
import time
import asyncio
from typing import List, Generator, Optional
from datetime import datetime, timezone
import cv2
import numpy as np
from fastapi import APIRouter, HTTPException, Depends, status, Request
from fastapi.responses import StreamingResponse, Response
from sqlalchemy.orm import Session
from sqlalchemy.sql import func

from app.db.session import get_db
from app.models.camera import Camera
from app.schemas.camera import (
    CameraResponse,
    CameraCreate,
    CameraUpdate,
    CameraTestResult,
    CameraProcessingStatusResponse,
    DiscoveredCCTVDevice,
    DiscoveryResponse,
    DeviceValidationRequest,
    DeviceValidationResponse
)
from app.core.security import encrypt_credential
from app.services.video_source_manager import LiveVideoSourceManager
from app.services.onvif_discovery import ONVIFWSDiscovery

router = APIRouter()


def _build_placeholder_frame(status: str = 'OFFLINE') -> bytes:
    """Membuat frame JPEG placeholder dengan pesan status sesuai kondisi kamera."""
    img = np.zeros((360, 640, 3), dtype=np.uint8)
    cv2.rectangle(img, (0, 0), (640, 360), (30, 41, 59), -1)
    if status == 'ONLINE':
        cv2.putText(img, "Menghubungkan ke kamera...", (145, 165),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.7, (148, 163, 184), 2)
        cv2.putText(img, "Kamera ONLINE. Memuat frame pertama...", (110, 200),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.42, (100, 200, 100), 1)
    elif status == 'ERROR':
        cv2.putText(img, "Koneksi RTSP Gagal / Error", (150, 165),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.7, (200, 80, 80), 2)
        cv2.putText(img, "Periksa URL RTSP, username, dan password.", (110, 200),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.42, (148, 163, 184), 1)
    else:
        cv2.putText(img, "KAMERA OFFLINE / BELUM TERHUBUNG", (80, 165),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.65, (226, 232, 240), 2)
        cv2.putText(img, "Klik 'Uji Koneksi' untuk memeriksa kamera.", (110, 200),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.42, (148, 163, 184), 1)
    _, enc = cv2.imencode('.jpg', img, [cv2.IMWRITE_JPEG_QUALITY, 75])
    return enc.tobytes()


@router.get("", response_model=List[CameraResponse])
def get_cameras(db: Session = Depends(get_db)):
    """Mengambil daftar seluruh kamera CCTV live dengan penyembunyian kredensial sensitif."""
    cameras = db.query(Camera).order_by(Camera.created_at.asc()).all()
    results = []
    for cam in cameras:
        results.append(
            CameraResponse(
                id=cam.id,
                name=cam.name,
                location=cam.location,
                source_type=cam.source_type,
                source_uri_masked=cam.source_uri_masked,
                resolution_width=cam.resolution_width,
                resolution_height=cam.resolution_height,
                fps=cam.fps,
                line_coords=cam.line_coords,
                is_active=cam.is_active,
                connection_status=cam.connection_status or "DISCONNECTED",
                processing_status=cam.processing_status or "STOPPED",
                last_connected_at=cam.last_connected_at,
                last_frame_at=cam.last_frame_at,
                connection_error=cam.connection_error,
                username=cam.username,
                notes=cam.notes,
                created_at=cam.created_at,
                updated_at=cam.updated_at
            )
        )
    return results


@router.get("/webcams/detect")
def detect_local_webcams():
    """Mendeteksi index kamera webcam / laptop yang tersedia di host lokal untuk pengujian live."""
    webcams = LiveVideoSourceManager.enumerate_webcams()
    return {
        "count": len(webcams),
        "devices": webcams,
        "message": f"Ditemukan {len(webcams)} perangkat kamera live pada mesin ini." if webcams else "Tidak ada perangkat webcam lokal yang terdeteksi."
    }


@router.post("/discovery/scan", response_model=DiscoveryResponse)
async def scan_network_cameras(
    timeout: float = 3.0,
    simulate: bool = False
):
    """Memindai perangkat CCTV dan NVR yang kompatibel dengan protokol ONVIF WS-Discovery di jaringan lokal.

    Pemindaian dilakukan secara asinkron di thread pool sehingga tidak memblokir request API lainnya.
    """
    if simulate:
        mock_devices = ONVIFWSDiscovery.get_mock_discovery_devices()
        return DiscoveryResponse(
            success=True,
            device_count=len(mock_devices),
            devices=mock_devices,
            duration_seconds=0.25,
            message=f"Mode Simulasi: Ditemukan {len(mock_devices)} profil perangkat ONVIF.",
            network_scope="Simulasi Jaringan (Test Environment)"
        )

    # Eksekusi WS-Discovery non-blocking di worker thread
    result = await asyncio.to_thread(ONVIFWSDiscovery.discover, timeout=timeout)
    return result


@router.post("/discovery/validate", response_model=DeviceValidationResponse)
async def validate_discovered_device(payload: DeviceValidationRequest):
    """Menguji koneksi langsung ke kamera CCTV yang ditemukan sebelum didaftarkan ke sistem.

    Membuka stream RTSP dan mengambil 1 frame valid untuk membuktikan keterjangkauan live.
    """
    result = await asyncio.to_thread(ONVIFWSDiscovery.validate_device_connection, payload)
    return result



@router.post("", response_model=CameraResponse, status_code=status.HTTP_201_CREATED)
def create_camera(payload: CameraCreate, db: Session = Depends(get_db)):
    """Mendaftarkan sumber kamera live baru (IP RTSP, NVR Channel, Laptop Webcam, HTTP Stream)."""
    encrypted_pw = encrypt_credential(payload.password) if payload.password else None

    # Validasi tipe sumber live yang diizinkan (Tidak mengizinkan prerecorded video)
    source_type = payload.source_type.upper()
    if source_type not in ["RTSP", "NVR_CHANNEL", "USB_WEBCAM", "HTTP_STREAM"]:
        raise HTTPException(
            status_code=400,
            detail="Tipe sumber tidak valid. Hanya sumber live stream yang didukung (RTSP, NVR_CHANNEL, USB_WEBCAM, HTTP_STREAM)."
        )

    new_cam = Camera(
        id=uuid.uuid4(),
        name=payload.name,
        location=payload.location,
        source_type=source_type,
        source_uri=payload.source_uri,
        username=payload.username,
        password_encrypted=encrypted_pw,
        resolution_width=payload.resolution_width,
        resolution_height=payload.resolution_height,
        fps=payload.fps,
        line_coords=payload.line_coords.model_dump(),
        is_active=payload.is_active,
        connection_status="DISCONNECTED",  # Default DISCONNECTED sampai diverifikasi
        processing_status="STOPPED",
        notes=payload.notes
    )
    db.add(new_cam)
    db.commit()
    db.refresh(new_cam)

    return CameraResponse(
        id=new_cam.id,
        name=new_cam.name,
        location=new_cam.location,
        source_type=new_cam.source_type,
        source_uri_masked=new_cam.source_uri_masked,
        resolution_width=new_cam.resolution_width,
        resolution_height=new_cam.resolution_height,
        fps=new_cam.fps,
        line_coords=new_cam.line_coords,
        is_active=new_cam.is_active,
        connection_status=new_cam.connection_status,
        processing_status=new_cam.processing_status,
        last_connected_at=new_cam.last_connected_at,
        last_frame_at=new_cam.last_frame_at,
        connection_error=new_cam.connection_error,
        username=new_cam.username,
        notes=new_cam.notes,
        created_at=new_cam.created_at,
        updated_at=new_cam.updated_at
    )


@router.get("/{camera_id}", response_model=CameraResponse)
def get_camera(camera_id: uuid.UUID, db: Session = Depends(get_db)):
    """Mengambil detail konfigurasi satu kamera CCTV berdasarkan ID."""
    cam = db.query(Camera).filter(Camera.id == camera_id).first()
    if not cam:
        raise HTTPException(status_code=404, detail="Kamera tidak ditemukan.")
    return CameraResponse(
        id=cam.id,
        name=cam.name,
        location=cam.location,
        source_type=cam.source_type,
        source_uri_masked=cam.source_uri_masked,
        resolution_width=cam.resolution_width,
        resolution_height=cam.resolution_height,
        fps=cam.fps,
        line_coords=cam.line_coords,
        is_active=cam.is_active,
        connection_status=cam.connection_status or "DISCONNECTED",
        processing_status=cam.processing_status or "STOPPED",
        last_connected_at=cam.last_connected_at,
        last_frame_at=cam.last_frame_at,
        connection_error=cam.connection_error,
        username=cam.username,
        notes=cam.notes,
        created_at=cam.created_at,
        updated_at=cam.updated_at
    )


@router.patch("/{camera_id}", response_model=CameraResponse)
def update_camera(camera_id: uuid.UUID, payload: CameraUpdate, db: Session = Depends(get_db)):
    """Memperbarui pengaturan kamera termasuk koordinat garis virtual crossing."""
    cam = db.query(Camera).filter(Camera.id == camera_id).first()
    if not cam:
        raise HTTPException(status_code=404, detail="Kamera tidak ditemukan.")

    update_data = payload.model_dump(exclude_unset=True)

    # Perbarui koordinat garis virtual jika disertakan
    if "line_coords" in update_data and update_data["line_coords"]:
        cam.line_coords = update_data["line_coords"]

    # Enkripsi kata sandi baru jika dikirim
    if "password" in update_data:
        pw = update_data.pop("password")
        if pw:
            cam.password_encrypted = encrypt_credential(pw)

    if "source_type" in update_data and update_data["source_type"]:
        st = update_data["source_type"].upper()
        if st not in ["RTSP", "NVR_CHANNEL", "USB_WEBCAM", "HTTP_STREAM"]:
            raise HTTPException(status_code=400, detail="Tipe sumber harus berupa stream live.")
        cam.source_type = st

    for field, val in update_data.items():
        if field not in ["line_coords", "source_type"] and hasattr(cam, field):
            setattr(cam, field, val)

    db.commit()
    db.refresh(cam)

    return CameraResponse(
        id=cam.id,
        name=cam.name,
        location=cam.location,
        source_type=cam.source_type,
        source_uri_masked=cam.source_uri_masked,
        resolution_width=cam.resolution_width,
        resolution_height=cam.resolution_height,
        fps=cam.fps,
        line_coords=cam.line_coords,
        is_active=cam.is_active,
        connection_status=cam.connection_status or "DISCONNECTED",
        processing_status=cam.processing_status or "STOPPED",
        last_connected_at=cam.last_connected_at,
        last_frame_at=cam.last_frame_at,
        connection_error=cam.connection_error,
        username=cam.username,
        notes=cam.notes,
        created_at=cam.created_at,
        updated_at=cam.updated_at
    )


@router.delete("/{camera_id}", status_code=status.HTTP_200_OK)
def delete_camera(camera_id: uuid.UUID, db: Session = Depends(get_db)):
    """Menghapus konfigurasi kamera CCTV beserta sesi penhitungannya."""
    cam = db.query(Camera).filter(Camera.id == camera_id).first()
    if not cam:
        raise HTTPException(status_code=404, detail="Kamera tidak ditemukan.")

    # Hentikan pemrosesan AI jika sedang berjalan
    LiveVideoSourceManager.stop_processing(cam, db)

    camera_name = cam.name
    db.delete(cam)
    db.commit()

    return {"message": f"Kamera '{camera_name}' berhasil dihapus dari sistem.", "camera_id": str(camera_id)}


@router.post("/{camera_id}/test", response_model=CameraTestResult)
def test_camera(camera_id: uuid.UUID, db: Session = Depends(get_db)):
    """Menguji konektivitas riil ke sumber live (RTSP/NVR/Webcam/HTTP) dan mengambil frame valid."""
    cam = db.query(Camera).filter(Camera.id == camera_id).first()
    if not cam:
        raise HTTPException(status_code=404, detail="Kamera tidak ditemukan.")

    # Jalankan uji konektivitas langsung via LiveVideoSourceManager
    test_result = LiveVideoSourceManager.test_camera_connection(cam)

    # Perbarui status di database
    cam.connection_status = test_result.connection_status
    if test_result.is_accessible:
        cam.last_connected_at = datetime.now(timezone.utc)
        cam.connection_error = None
        if test_result.resolution_width:
            cam.resolution_width = test_result.resolution_width
        if test_result.resolution_height:
            cam.resolution_height = test_result.resolution_height
        if test_result.fps:
            cam.fps = test_result.fps
    else:
        cam.connection_error = test_result.error_detail or test_result.message

    db.commit()
    return test_result


@router.post("/{camera_id}/start-processing", response_model=CameraProcessingStatusResponse)
def start_camera_processing(camera_id: uuid.UUID, db: Session = Depends(get_db)):
    """Memulai sesi pemrosesan AI computer vision langsung pada stream live kamera."""
    cam = db.query(Camera).filter(Camera.id == camera_id).first()
    if not cam:
        raise HTTPException(status_code=404, detail="Kamera tidak ditemukan.")

    if not cam.is_active:
        raise HTTPException(status_code=400, detail="Kamera dalam kondisi nonaktif. Aktifkan kamera terlebih dahulu.")

    success, msg, session_id = LiveVideoSourceManager.start_processing(cam, db)
    return CameraProcessingStatusResponse(
        camera_id=cam.id,
        processing_status=cam.processing_status,
        connection_status=cam.connection_status,
        session_id=session_id,
        message=msg
    )


@router.post("/{camera_id}/stop-processing", response_model=CameraProcessingStatusResponse)
def stop_camera_processing(camera_id: uuid.UUID, db: Session = Depends(get_db)):
    """Menghentikan sesi pemrosesan AI computer vision kamera."""
    cam = db.query(Camera).filter(Camera.id == camera_id).first()
    if not cam:
        raise HTTPException(status_code=404, detail="Kamera tidak ditemukan.")

    success, msg = LiveVideoSourceManager.stop_processing(cam, db)
    return CameraProcessingStatusResponse(
        camera_id=cam.id,
        processing_status=cam.processing_status,
        connection_status=cam.connection_status,
        session_id=None,
        message=msg
    )


@router.get("/{camera_id}/status", response_model=CameraProcessingStatusResponse)
def get_camera_status(camera_id: uuid.UUID, db: Session = Depends(get_db)):
    """Mengambil status koneksi dan pemrosesan terbaru kamera."""
    cam = db.query(Camera).filter(Camera.id == camera_id).first()
    if not cam:
        raise HTTPException(status_code=404, detail="Kamera tidak ditemukan.")

    return CameraProcessingStatusResponse(
        camera_id=cam.id,
        processing_status=cam.processing_status or "STOPPED",
        connection_status=cam.connection_status or "DISCONNECTED",
        session_id=None,
        message=f"Status: {cam.processing_status} / {cam.connection_status}"
    )


@router.get("/{camera_id}/preview")
async def get_camera_preview(
    camera_id: uuid.UUID,
    max_frames: Optional[int] = None,
    db: Session = Depends(get_db)
):
    """
    Menyediakan stream MJPEG langsung (multipart/x-mixed-replace) dari kamera live
    agar peramban (browser) dapat menampilkan live video tanpa memerlukan player RTSP eksternal.
    Dukungan parameter 'max_frames' opsional untuk pengujian deterministik dan verifikasi status.
    """
    cam = db.query(Camera).filter(Camera.id == camera_id).first()
    if not cam:
        raise HTTPException(status_code=404, detail="Kamera tidak ditemukan.")

    cam_id_str = str(cam.id)

    async def mjpeg_stream_generator():
        # Generator async yang mengalirkan frame JPEG live — atau placeholder status jika kamera offline
        frames_sent = 0

        try:
            while True:
                # 1. Coba ambil frame dari buffer AI worker (jika AI sedang berjalan)
                frame_bytes = LiveVideoSourceManager.get_latest_preview_frame(cam_id_str)

                if frame_bytes is None:
                    # 2. Refresh status kamera dari DB
                    db.expire(cam)
                    db.refresh(cam)

                    if cam.connection_status == 'ONLINE':
                        # 3. Coba baca frame langsung dari RTSP (persistent connection, tanpa AI)
                        frame_bytes = await asyncio.to_thread(
                            LiveVideoSourceManager.get_or_read_preview_frame, cam
                        )

                if frame_bytes is not None:
                    yield (
                        b"--frame\r\n"
                        b"Content-Type: image/jpeg\r\n\r\n" + frame_bytes + b"\r\n"
                    )
                    frames_sent += 1
                    if max_frames and frames_sent >= max_frames:
                        break
                    await asyncio.sleep(0.07)  # ~14 FPS
                else:
                    # 4. Kamera offline / gagal — tampilkan placeholder informatif
                    db.expire(cam)
                    db.refresh(cam)
                    placeholder = _build_placeholder_frame(cam.connection_status or 'OFFLINE')
                    yield (
                        b"--frame\r\n"
                        b"Content-Type: image/jpeg\r\n\r\n" + placeholder + b"\r\n"
                    )
                    frames_sent += 1
                    if max_frames and frames_sent >= max_frames:
                        break
                    await asyncio.sleep(1.5)
        except (asyncio.CancelledError, GeneratorExit):
            pass

    return StreamingResponse(
        mjpeg_stream_generator(),
        media_type="multipart/x-mixed-replace; boundary=frame"
    )
