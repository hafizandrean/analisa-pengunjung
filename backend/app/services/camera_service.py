import os
import sys
import time
import uuid
import concurrent.futures
from datetime import datetime, timezone
from typing import Tuple, Optional, Dict, Any
import cv2
from sqlalchemy.orm import Session
from sqlalchemy.sql import func

from app.models.camera import Camera
from app.models.counting_session import CountingSession
from app.schemas.camera import CameraTestResult
from app.core.security import decrypt_credential


class CameraConnectionTester:
    """Layanan pengujian konektivitas berbagai jenis sumber CCTV secara aman dengan batas waktu (timeout)."""

    @staticmethod
    def _build_actual_uri(cam: Camera) -> str:
        """Menyusun URI koneksi riil dengan menyuntikkan kredensial terdekripsi jika ada."""
        raw_uri = cam.source_uri or ""

        # Jika kamera RTSP dan memiliki username/password tersimpan terpisah
        if cam.source_type == "RTSP":
            if cam.username and cam.password_encrypted:
                plain_pass = decrypt_credential(cam.password_encrypted)
                if "@" not in raw_uri and "://" in raw_uri:
                    proto, rest = raw_uri.split("://", 1)
                    return f"{proto}://{cam.username}:{plain_pass}@{rest}"
        return raw_uri

    @classmethod
    def test_connection(cls, cam: Camera, timeout_seconds: float = 6.0) -> CameraTestResult:
        now_dt = datetime.now(timezone.utc)
        uri = cls._build_actual_uri(cam)
        source_type = (cam.source_type or "").upper()

        # 1. Sumber: VIDEO_FILE
        if source_type == "VIDEO_FILE":
            project_root = os.path.dirname(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))
            possible_paths = [
                uri,
                os.path.join(project_root, uri),
                os.path.join(project_root, "samples", os.path.basename(uri)),
                os.path.join(project_root, "video sample.mp4"),
                os.path.join(os.getcwd(), uri),
                os.path.join(os.getcwd(), "samples", os.path.basename(uri)),
                os.path.join(os.getcwd(), "video sample.mp4")
            ]
            valid_path = None
            for p in possible_paths:
                if p and os.path.isfile(p):
                    valid_path = p
                    break

            if not valid_path:
                return CameraTestResult(
                    camera_id=cam.id,
                    is_accessible=False,
                    connection_status="ERROR",
                    message="Berkas video tidak ditemukan di server.",
                    error_detail=f"Jalur berkas '{uri}' tidak dapat diakses atau belum diunggah.",
                    checked_at=now_dt
                )

            cap = cv2.VideoCapture(valid_path)
            if not cap.isOpened():
                return CameraTestResult(
                    camera_id=cam.id,
                    is_accessible=False,
                    connection_status="ERROR",
                    message="Gagal membuka berkas video.",
                    error_detail="Codec video tidak didukung oleh backend OpenCV.",
                    checked_at=now_dt
                )

            ret, frame = cap.read()
            w = int(cap.get(cv2.CAP_PROP_FRAME_WIDTH))
            h = int(cap.get(cv2.CAP_PROP_FRAME_HEIGHT))
            fps = int(cap.get(cv2.CAP_PROP_FPS) or 25)
            cap.release()

            if ret and frame is not None and w > 0 and h > 0:
                return CameraTestResult(
                    camera_id=cam.id,
                    is_accessible=True,
                    connection_status="ONLINE",
                    resolution_width=w,
                    resolution_height=h,
                    fps=fps,
                    message=f"Berkas video valid dan siap diproses ({w}x{h} @ {fps} FPS).",
                    checked_at=now_dt
                )
            else:
                return CameraTestResult(
                    camera_id=cam.id,
                    is_accessible=False,
                    connection_status="ERROR",
                    message="Berkas video korup atau frame pertama tidak dapat dibaca.",
                    checked_at=now_dt
                )

        # 2. Sumber: USB_WEBCAM
        elif source_type in ["USB_WEBCAM", "WEBCAM"]:
            try:
                # device id integer
                clean_str = "".join(filter(str.isdigit, uri))
                dev_id = int(clean_str) if clean_str else 0
            except ValueError:
                dev_id = 0

            def _check_webcam():
                cap = cv2.VideoCapture(dev_id, cv2.CAP_DSHOW if os.name == "nt" else cv2.CAP_ANY)
                if not cap.isOpened():
                    return False, 0, 0, 0
                ret, frame = cap.read()
                w = int(cap.get(cv2.CAP_PROP_FRAME_WIDTH))
                h = int(cap.get(cv2.CAP_PROP_FRAME_HEIGHT))
                fps = int(cap.get(cv2.CAP_PROP_FPS) or 30)
                cap.release()
                return ret and frame is not None, w, h, fps

            try:
                with concurrent.futures.ThreadPoolExecutor(max_workers=1) as executor:
                    future = executor.submit(_check_webcam)
                    success, w, h, fps = future.result(timeout=4.0)

                if success:
                    return CameraTestResult(
                        camera_id=cam.id,
                        is_accessible=True,
                        connection_status="ONLINE",
                        resolution_width=w,
                        resolution_height=h,
                        fps=fps,
                        message=f"Kamera USB #{dev_id} berhasil dihubungkan ({w}x{h} @ {fps} FPS).",
                        checked_at=now_dt
                    )
                else:
                    return CameraTestResult(
                        camera_id=cam.id,
                        is_accessible=False,
                        connection_status="OFFLINE",
                        message=f"Perangkat USB #{dev_id} tidak terdeteksi atau sedang digunakan proses lain.",
                        error_detail="Port kamera tidak merespons pembacaan frame.",
                        checked_at=now_dt
                    )
            except concurrent.futures.TimeoutError:
                return CameraTestResult(
                    camera_id=cam.id,
                    is_accessible=False,
                    connection_status="OFFLINE",
                    message=f"Batas waktu koneksi kamera USB #{dev_id} terlampaui.",
                    checked_at=now_dt
                )
            except Exception as e:
                return CameraTestResult(
                    camera_id=cam.id,
                    is_accessible=False,
                    connection_status="ERROR",
                    message="Terjadi kesalahan saat mengakses perangkat USB webcam.",
                    error_detail=str(e),
                    checked_at=now_dt
                )

        # 3. Sumber: RTSP IP Camera
        elif source_type == "RTSP":
            if not uri.lower().startswith("rtsp://"):
                return CameraTestResult(
                    camera_id=cam.id,
                    is_accessible=False,
                    connection_status="ERROR",
                    message="Format URL tidak valid untuk kamera IP RTSP.",
                    error_detail="URL harus diawali dengan 'rtsp://'. Contoh: rtsp://192.168.1.100:554/live",
                    checked_at=now_dt
                )

            # Pencegahan proxy tak terbatas ke port loopback sensitif
            if "localhost:22" in uri or "127.0.0.1:22" in uri:
                return CameraTestResult(
                    camera_id=cam.id,
                    is_accessible=False,
                    connection_status="ERROR",
                    message="Akses ke port jaringan yang dilarang.",
                    checked_at=now_dt
                )

            def _check_rtsp():
                # Opsi timeout FFMPEG untuk OpenCV
                os.environ["OPENCV_FFMPEG_CAPTURE_OPTIONS"] = "timeout;5000000"
                cap = cv2.VideoCapture(uri, cv2.CAP_FFMPEG)
                if not cap.isOpened():
                    return False, 0, 0, 0
                ret, frame = cap.read()
                w = int(cap.get(cv2.CAP_PROP_FRAME_WIDTH))
                h = int(cap.get(cv2.CAP_PROP_FRAME_HEIGHT))
                fps = int(cap.get(cv2.CAP_PROP_FPS) or 25)
                cap.release()
                return ret and frame is not None, w, h, fps

            try:
                with concurrent.futures.ThreadPoolExecutor(max_workers=1) as executor:
                    future = executor.submit(_check_rtsp)
                    success, w, h, fps = future.result(timeout=timeout_seconds)

                if success:
                    return CameraTestResult(
                        camera_id=cam.id,
                        is_accessible=True,
                        connection_status="ONLINE",
                        resolution_width=w,
                        resolution_height=h,
                        fps=fps,
                        message=f"Stream RTSP aktif dan merespons ({w}x{h} @ {fps} FPS).",
                        checked_at=now_dt
                    )
                else:
                    return CameraTestResult(
                        camera_id=cam.id,
                        is_accessible=False,
                        connection_status="OFFLINE",
                        message="Kamera RTSP tidak dapat dijangkau atau menolak koneksi.",
                        error_detail="Periksa alamat IP, port 554, serta username/password autentikasi.",
                        checked_at=now_dt
                    )
            except concurrent.futures.TimeoutError:
                return CameraTestResult(
                    camera_id=cam.id,
                    is_accessible=False,
                    connection_status="OFFLINE",
                    message="Batas waktu koneksi stream RTSP terlampaui (Timeout).",
                    error_detail="Host kamera tidak merespons dalam 6 detik.",
                    checked_at=now_dt
                )
            except Exception as e:
                return CameraTestResult(
                    camera_id=cam.id,
                    is_accessible=False,
                    connection_status="ERROR",
                    message="Kesalahan jaringan saat menghubungi kamera RTSP.",
                    error_detail=str(e),
                    checked_at=now_dt
                )

        # 4. Sumber: HTTP_STREAM (HLS, MJPEG, HTTP Video)
        elif source_type in ["HTTP_STREAM", "HTTP", "HTTPS"]:
            if not (uri.lower().startswith("http://") or uri.lower().startswith("https://")):
                return CameraTestResult(
                    camera_id=cam.id,
                    is_accessible=False,
                    connection_status="ERROR",
                    message="Format URL tidak valid untuk stream HTTP/HTTPS.",
                    checked_at=now_dt
                )

            def _check_http():
                cap = cv2.VideoCapture(uri)
                if not cap.isOpened():
                    return False, 0, 0, 0
                ret, frame = cap.read()
                w = int(cap.get(cv2.CAP_PROP_FRAME_WIDTH))
                h = int(cap.get(cv2.CAP_PROP_FRAME_HEIGHT))
                fps = int(cap.get(cv2.CAP_PROP_FPS) or 25)
                cap.release()
                return ret and frame is not None, w, h, fps

            try:
                with concurrent.futures.ThreadPoolExecutor(max_workers=1) as executor:
                    future = executor.submit(_check_http)
                    success, w, h, fps = future.result(timeout=timeout_seconds)

                if success:
                    return CameraTestResult(
                        camera_id=cam.id,
                        is_accessible=True,
                        connection_status="ONLINE",
                        resolution_width=w,
                        resolution_height=h,
                        fps=fps,
                        message=f"Stream HTTP/HTTPS aktif ({w}x{h} @ {fps} FPS).",
                        checked_at=now_dt
                    )
                else:
                    return CameraTestResult(
                        camera_id=cam.id,
                        is_accessible=False,
                        connection_status="OFFLINE",
                        message="URL stream HTTP/HTTPS tidak dapat diputar atau tidak kompatibel.",
                        error_detail="Pastikan URL menyediakan stream video valid (MJPEG / HLS / MP4).",
                        checked_at=now_dt
                    )
            except concurrent.futures.TimeoutError:
                return CameraTestResult(
                    camera_id=cam.id,
                    is_accessible=False,
                    connection_status="OFFLINE",
                    message="Batas waktu koneksi stream HTTP terlampaui.",
                    checked_at=now_dt
                )
            except Exception as e:
                return CameraTestResult(
                    camera_id=cam.id,
                    is_accessible=False,
                    connection_status="ERROR",
                    message="Gagal memeriksa stream HTTP/HTTPS.",
                    error_detail=str(e),
                    checked_at=now_dt
                )

        else:
            return CameraTestResult(
                camera_id=cam.id,
                is_accessible=False,
                connection_status="ERROR",
                message=f"Tipe sumber '{cam.source_type}' tidak didukung.",
                checked_at=now_dt
            )


class CameraProcessingManager:
    """Pengelola siklus hidup sesi pemrosesan AI computer vision per kamera."""

    # Dictionary in-memory untuk menyimpan sesi pemrosesan aktif: camera_id -> dict
    _active_workers: Dict[str, Dict[str, Any]] = {}

    @classmethod
    def start_processing(cls, cam: Camera, db: Session) -> Tuple[bool, str, Optional[uuid.UUID]]:
        cam_id_str = str(cam.id)

        # Cek apakah sudah berjalan
        if cam_id_str in cls._active_workers and cls._active_workers[cam_id_str].get("is_running"):
            return True, "Pemrosesan sudah berjalan untuk kamera ini.", cls._active_workers[cam_id_str].get("session_id")

        # Buat sesi counting baru di database
        new_session = CountingSession(
            id=uuid.uuid4(),
            camera_id=cam.id,
            mode="LIVE_CCTV" if cam.source_type == "RTSP" else "VIDEO_FILE",
            status="RUNNING",
            started_at=datetime.now(timezone.utc),
            total_in=0,
            total_out=0,
            notes=f"Sesi analisis aktif untuk {cam.name}"
        )
        db.add(new_session)

        # Perbarui status kamera
        cam.processing_status = "PROCESSING"
        db.commit()

        cls._active_workers[cam_id_str] = {
            "session_id": new_session.id,
            "is_running": True,
            "started_at": datetime.now(timezone.utc)
        }

        return True, f"Pemrosesan analitik dimulai untuk {cam.name}.", new_session.id

    @classmethod
    def stop_processing(cls, cam: Camera, db: Session) -> Tuple[bool, str]:
        cam_id_str = str(cam.id)

        session_id = None
        if cam_id_str in cls._active_workers:
            session_id = cls._active_workers[cam_id_str].get("session_id")
            cls._active_workers[cam_id_str]["is_running"] = False
            del cls._active_workers[cam_id_str]

        # Tutup sesi di database jika ada
        if session_id:
            sess = db.query(CountingSession).filter(CountingSession.id == session_id).first()
            if sess:
                sess.status = "COMPLETED"
                sess.ended_at = datetime.now(timezone.utc)

        cam.processing_status = "IDLE"
        db.commit()

        return True, f"Pemrosesan analitik dihentikan untuk {cam.name}."

    @classmethod
    def get_status(cls, cam_id: uuid.UUID) -> str:
        cam_id_str = str(cam_id)
        if cam_id_str in cls._active_workers and cls._active_workers[cam_id_str].get("is_running"):
            return "PROCESSING"
        return "IDLE"
