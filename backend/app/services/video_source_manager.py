import os
import sys
import time
import uuid
import threading
import concurrent.futures
from datetime import datetime, timezone
from typing import Tuple, Optional, Dict, Any, List, Set
from abc import ABC, abstractmethod

import cv2
import numpy as np
from sqlalchemy.orm import Session
from sqlalchemy.sql import func

from app.models.camera import Camera
from app.models.counting_session import CountingSession
from app.models.counting_event import CountingEvent
from app.schemas.camera import CameraTestResult
from app.core.security import decrypt_credential


class BaseLiveVideoSource(ABC):
    """Antarmuka umum untuk seluruh sumber kamera video langsung (Live Streams)."""

    def __init__(self, camera_id: str, uri: str, name: str):
        self.camera_id = camera_id
        self.uri = uri
        self.name = name
        self.status = "DISCONNECTED"  # DISCONNECTED, CONNECTING, ONLINE, RECONNECTING, ERROR
        self.error_message: Optional[str] = None
        self.width = 0
        self.height = 0
        self.fps = 0
        self.last_frame_time: Optional[float] = None
        self.reconnect_attempts = 0
        self.max_reconnect_attempts = 5
        self._lock = threading.Lock()

    @abstractmethod
    def connect(self) -> Tuple[bool, str]:
        """Membuka koneksi ke sumber kamera dan mengambil frame pertama untuk validasi."""
        pass

    @abstractmethod
    def read_frame(self) -> Tuple[bool, Optional[np.ndarray]]:
        """Membaca frame terbaru dari stream langsung."""
        pass

    @abstractmethod
    def disconnect(self) -> None:
        """Menutup koneksi dan melepaskan sumber daya perangkat."""
        pass

    def get_status(self) -> str:
        with self._lock:
            return self.status

    def get_metadata(self) -> Dict[str, Any]:
        with self._lock:
            return {
                "camera_id": self.camera_id,
                "name": self.name,
                "status": self.status,
                "error_message": self.error_message,
                "width": self.width,
                "height": self.height,
                "fps": self.fps,
                "last_frame_time": self.last_frame_time,
                "reconnect_attempts": self.reconnect_attempts
            }


class RTSPLiveVideoSource(BaseLiveVideoSource):
    """Adapter untuk kamera IP RTSP dan channel NVR/DVR."""

    def __init__(self, camera_id: str, uri: str, name: str, username: Optional[str] = None, password_encrypted: Optional[str] = None):
        super().__init__(camera_id, uri, name)
        self.username = username
        self.password_encrypted = password_encrypted
        self.cap: Optional[cv2.VideoCapture] = None

    def _get_authenticated_uri(self) -> str:
        raw_uri = self.uri or ""
        if self.username and self.password_encrypted:
            plain_pass = decrypt_credential(self.password_encrypted)
            if "@" not in raw_uri and "://" in raw_uri:
                proto, rest = raw_uri.split("://", 1)
                return f"{proto}://{self.username}:{plain_pass}@{rest}"
        return raw_uri

    def connect(self) -> Tuple[bool, str]:
        with self._lock:
            self.status = "CONNECTING"
            self.error_message = None

        auth_uri = self._get_authenticated_uri()
        if not auth_uri.lower().startswith("rtsp://"):
            with self._lock:
                self.status = "ERROR"
                self.error_message = "URL harus diawali dengan 'rtsp://'."
            return False, self.error_message

        # Keamanan: blokir SSRF port sensitif lokal
        if "localhost:22" in auth_uri or "127.0.0.1:22" in auth_uri:
            with self._lock:
                self.status = "ERROR"
                self.error_message = "Port loopback dilarang."
            return False, self.error_message

        def _open():
            os.environ["OPENCV_FFMPEG_CAPTURE_OPTIONS"] = "timeout;5000000"
            cap = cv2.VideoCapture(auth_uri, cv2.CAP_FFMPEG)
            if not cap.isOpened():
                return None, 0, 0, 0
            ret, frame = cap.read()
            if not ret or frame is None or frame.size == 0:
                cap.release()
                return None, 0, 0, 0
            w = int(cap.get(cv2.CAP_PROP_FRAME_WIDTH))
            h = int(cap.get(cv2.CAP_PROP_FRAME_HEIGHT))
            fps = int(cap.get(cv2.CAP_PROP_FPS) or 25)
            return cap, w, h, fps

        executor = concurrent.futures.ThreadPoolExecutor(max_workers=1)
        try:
            future = executor.submit(_open)
            cap, w, h, fps = future.result(timeout=4.0)

            if cap is not None:
                with self._lock:
                    self.cap = cap
                    self.width = w
                    self.height = h
                    self.fps = fps
                    self.status = "ONLINE"
                    self.last_frame_time = time.time()
                    self.reconnect_attempts = 0
                return True, f"Kamera RTSP terhubung ({w}x{h} @ {fps} FPS)."
            else:
                with self._lock:
                    self.status = "ERROR"
                    self.error_message = "Gagal membuka stream RTSP atau frame tidak valid."
                return False, self.error_message
        except concurrent.futures.TimeoutError:
            with self._lock:
                self.status = "ERROR"
                self.error_message = "Batas waktu koneksi stream RTSP terlampaui (Timeout 4s)."
            return False, self.error_message
        except Exception as e:
            with self._lock:
                self.status = "ERROR"
                self.error_message = f"Kesalahan koneksi RTSP: {str(e)}"
            return False, self.error_message
        finally:
            executor.shutdown(wait=False, cancel_futures=True)

    def read_frame(self) -> Tuple[bool, Optional[np.ndarray]]:
        with self._lock:
            if not self.cap or not self.cap.isOpened():
                if self.reconnect_attempts < self.max_reconnect_attempts:
                    self.status = "RECONNECTING"
                else:
                    self.status = "ERROR"
                return False, None

        ret, frame = self.cap.read()
        if ret and frame is not None and frame.size > 0:
            with self._lock:
                self.status = "ONLINE"
                self.last_frame_time = time.time()
                self.reconnect_attempts = 0
            return True, frame
        else:
            # Stream terputus, picu reconnection
            self._handle_reconnect()
            return False, None

    def _handle_reconnect(self):
        with self._lock:
            self.reconnect_attempts += 1
            if self.reconnect_attempts <= self.max_reconnect_attempts:
                self.status = "RECONNECTING"
            else:
                self.status = "ERROR"
                self.error_message = "Koneksi terputus dan melebihi batas percobaan reconnect."
                if self.cap:
                    self.cap.release()
                    self.cap = None
                return

        # Bounded exponential backoff sleep
        backoff = min(2 ** (self.reconnect_attempts - 1), 10)
        time.sleep(backoff)

        auth_uri = self._get_authenticated_uri()
        if self.cap:
            self.cap.release()
        self.cap = cv2.VideoCapture(auth_uri, cv2.CAP_FFMPEG)
        if self.cap.isOpened():
            ret, frame = self.cap.read()
            if ret and frame is not None:
                with self._lock:
                    self.status = "ONLINE"
                    self.last_frame_time = time.time()
                    self.reconnect_attempts = 0

    def disconnect(self) -> None:
        with self._lock:
            if self.cap:
                self.cap.release()
                self.cap = None
            self.status = "DISCONNECTED"


class WebcamLiveVideoSource(BaseLiveVideoSource):
    """Adapter untuk Laptop Built-in Camera dan USB Webcam."""

    # Set kelas untuk melacak device index yang sedang terbuka agar tidak terjadi multiple lock
    _active_device_indices: Set[int] = set()
    _device_lock = threading.Lock()

    def __init__(self, camera_id: str, uri: str, name: str):
        super().__init__(camera_id, uri, name)
        try:
            clean = "".join(filter(str.isdigit, uri))
            self.device_id = int(clean) if clean else 0
        except ValueError:
            self.device_id = 0
        self.cap: Optional[cv2.VideoCapture] = None

    def connect(self) -> Tuple[bool, str]:
        with WebcamLiveVideoSource._device_lock:
            if self.device_id in WebcamLiveVideoSource._active_device_indices:
                with self._lock:
                    self.status = "ERROR"
                    self.error_message = f"Perangkat kamera #{self.device_id} sedang digunakan oleh sesi capture aktif lainnya."
                return False, self.error_message

        with self._lock:
            self.status = "CONNECTING"
            self.error_message = None

        try:
            backend = cv2.CAP_DSHOW if os.name == "nt" else cv2.CAP_ANY
            cap = cv2.VideoCapture(self.device_id, backend)
            if not cap.isOpened():
                with self._lock:
                    self.status = "ERROR"
                    self.error_message = f"Perangkat webcam #{self.device_id} tidak dapat dibuka."
                return False, self.error_message

            ret, frame = cap.read()
            if not ret or frame is None or frame.size == 0:
                cap.release()
                with self._lock:
                    self.status = "ERROR"
                    self.error_message = f"Webcam #{self.device_id} tidak mengembalikan frame valid."
                return False, self.error_message

            w = int(cap.get(cv2.CAP_PROP_FRAME_WIDTH))
            h = int(cap.get(cv2.CAP_PROP_FRAME_HEIGHT))
            fps = int(cap.get(cv2.CAP_PROP_FPS) or 30)

            with WebcamLiveVideoSource._device_lock:
                WebcamLiveVideoSource._active_device_indices.add(self.device_id)

            with self._lock:
                self.cap = cap
                self.width = w
                self.height = h
                self.fps = fps
                self.status = "ONLINE"
                self.last_frame_time = time.time()
                self.reconnect_attempts = 0
            return True, f"Kamera Laptop/Webcam #{self.device_id} aktif ({w}x{h} @ {fps} FPS)."
        except Exception as e:
            with self._lock:
                self.status = "ERROR"
                self.error_message = f"Gagal mengakses webcam: {str(e)}"
            return False, self.error_message

    def read_frame(self) -> Tuple[bool, Optional[np.ndarray]]:
        with self._lock:
            if not self.cap or not self.cap.isOpened():
                self.status = "ERROR"
                return False, None

        ret, frame = self.cap.read()
        if ret and frame is not None and frame.size > 0:
            with self._lock:
                self.status = "ONLINE"
                self.last_frame_time = time.time()
            return True, frame
        else:
            with self._lock:
                self.status = "ERROR"
                self.error_message = "Webcam terputus atau tidak lagi mengirimkan frame."
            return False, None

    def disconnect(self) -> None:
        with self._lock:
            if self.cap:
                self.cap.release()
                self.cap = None
            self.status = "DISCONNECTED"

        with WebcamLiveVideoSource._device_lock:
            self._active_device_indices.discard(self.device_id)


class HTTPLiveVideoSource(BaseLiveVideoSource):
    """Adapter untuk stream HTTP/HTTPS atau HLS langsung."""

    def __init__(self, camera_id: str, uri: str, name: str):
        super().__init__(camera_id, uri, name)
        self.cap: Optional[cv2.VideoCapture] = None

    def connect(self) -> Tuple[bool, str]:
        with self._lock:
            self.status = "CONNECTING"
            self.error_message = None

        if not (self.uri.lower().startswith("http://") or self.uri.lower().startswith("https://")):
            with self._lock:
                self.status = "ERROR"
                self.error_message = "URL stream harus diawali dengan 'http://' atau 'https://'."
            return False, self.error_message

        def _open():
            cap = cv2.VideoCapture(self.uri)
            if not cap.isOpened():
                return None, 0, 0, 0
            ret, frame = cap.read()
            if not ret or frame is None:
                cap.release()
                return None, 0, 0, 0
            w = int(cap.get(cv2.CAP_PROP_FRAME_WIDTH))
            h = int(cap.get(cv2.CAP_PROP_FRAME_HEIGHT))
            fps = int(cap.get(cv2.CAP_PROP_FPS) or 25)
            return cap, w, h, fps

        try:
            with concurrent.futures.ThreadPoolExecutor(max_workers=1) as executor:
                future = executor.submit(_open)
                cap, w, h, fps = future.result(timeout=6.0)

            if cap is not None:
                with self._lock:
                    self.cap = cap
                    self.width = w
                    self.height = h
                    self.fps = fps
                    self.status = "ONLINE"
                    self.last_frame_time = time.time()
                return True, f"Stream HTTP/HTTPS terhubung ({w}x{h} @ {fps} FPS)."
            else:
                with self._lock:
                    self.status = "ERROR"
                    self.error_message = "URL HTTP tidak mengembalikan video stream yang valid."
                return False, self.error_message
        except Exception as e:
            with self._lock:
                self.status = "ERROR"
                self.error_message = str(e)
            return False, self.error_message

    def read_frame(self) -> Tuple[bool, Optional[np.ndarray]]:
        with self._lock:
            if not self.cap or not self.cap.isOpened():
                self.status = "ERROR"
                return False, None

        ret, frame = self.cap.read()
        if ret and frame is not None:
            with self._lock:
                self.status = "ONLINE"
                self.last_frame_time = time.time()
            return True, frame
        else:
            with self._lock:
                self.status = "ERROR"
            return False, None

    def disconnect(self) -> None:
        with self._lock:
            if self.cap:
                self.cap.release()
                self.cap = None
            self.status = "DISCONNECTED"


class LiveVideoSourceManager:
    """Manajer sentral untuk pendaftaran, pengujian, pemrosesan AI, dan live preview frame buffer."""

    _active_sources: Dict[str, BaseLiveVideoSource] = {}
    _latest_preview_jpegs: Dict[str, bytes] = {}
    _active_ai_threads: Dict[str, threading.Thread] = {}
    _ai_running_flags: Dict[str, bool] = {}
    _registry_lock = threading.Lock()

    # Lightweight preview sources — persistent RTSP connections untuk preview tanpa AI
    _preview_only_sources: Dict[str, BaseLiveVideoSource] = {}
    _preview_only_last_used: Dict[str, float] = {}
    _preview_only_lock = threading.Lock()
    _PREVIEW_SOURCE_TTL_SECONDS = 30

    @classmethod
    def create_source(cls, camera: Camera) -> BaseLiveVideoSource:
        cam_id = str(camera.id)
        source_type = (camera.source_type or "RTSP").upper()

        if source_type in ["RTSP", "NVR_CHANNEL"]:
            return RTSPLiveVideoSource(
                camera_id=cam_id,
                uri=camera.source_uri,
                name=camera.name,
                username=camera.username,
                password_encrypted=camera.password_encrypted
            )
        elif source_type in ["USB_WEBCAM", "WEBCAM", "LAPTOP_CAMERA"]:
            return WebcamLiveVideoSource(
                camera_id=cam_id,
                uri=camera.source_uri,
                name=camera.name
            )
        elif source_type in ["HTTP_STREAM", "HTTP", "HTTPS"]:
            return HTTPLiveVideoSource(
                camera_id=cam_id,
                uri=camera.source_uri,
                name=camera.name
            )
        else:
            # Default fallback ke RTSP
            return RTSPLiveVideoSource(
                camera_id=cam_id,
                uri=camera.source_uri,
                name=camera.name
            )

    @classmethod
    def test_camera_connection(cls, camera: Camera) -> CameraTestResult:
        """Menguji koneksi riil ke sumber kamera langsung dengan pembacaan frame valid."""
        source = cls.create_source(camera)
        success, msg = source.connect()
        meta = source.get_metadata()
        now_dt = datetime.now(timezone.utc)

        result = CameraTestResult(
            camera_id=camera.id,
            is_accessible=success,
            connection_status="ONLINE" if success else "DISCONNECTED",
            resolution_width=meta.get("width") if success else None,
            resolution_height=meta.get("height") if success else None,
            fps=meta.get("fps") if success else None,
            message=msg,
            error_detail=meta.get("error_message"),
            checked_at=now_dt
        )

        # Lepaskan sumber setelah pengujian selesai agar tidak terkunci
        source.disconnect()
        return result

    @classmethod
    def enumerate_webcams(cls, max_devices: int = 3) -> List[Dict[str, Any]]:
        """Mendeteksi index kamera webcam/laptop yang tersedia di mesin lokal."""
        found = []
        backend = cv2.CAP_DSHOW if os.name == "nt" else cv2.CAP_ANY
        for i in range(max_devices):
            try:
                cap = cv2.VideoCapture(i, backend)
                if cap.isOpened():
                    ret, frame = cap.read()
                    w = int(cap.get(cv2.CAP_PROP_FRAME_WIDTH))
                    h = int(cap.get(cv2.CAP_PROP_FRAME_HEIGHT))
                    fps = int(cap.get(cv2.CAP_PROP_FPS) or 30)
                    cap.release()
                    if ret and frame is not None:
                        found.append({
                            "index": i,
                            "name": f"Kamera #{i} (Laptop / USB)",
                            "resolution": f"{w}x{h}",
                            "fps": fps
                        })
            except Exception:
                pass
        return found

    @classmethod
    def start_processing(cls, camera: Camera, db: Session) -> Tuple[bool, str, Optional[uuid.UUID]]:
        """Memulai background thread inferensi AI live YOLOv8 + ByteTrack + virtual line counting."""
        cam_id = str(camera.id)

        with cls._registry_lock:
            if cam_id in cls._ai_running_flags and cls._ai_running_flags[cam_id]:
                return True, "Pemrosesan AI sudah berjalan untuk kamera ini.", None

        # 0. Lepaskan preview source yang mungkin masih memegang perangkat kamera
        #    Ini penting untuk USB_WEBCAM yang tidak dapat dibuka oleh dua proses sekaligus
        cls.release_preview_source(cam_id)

        # 1. Buka sumber video live
        source = cls.create_source(camera)
        connected, msg = source.connect()
        if not connected:
            camera.connection_status = "ERROR"
            camera.connection_error = msg
            camera.processing_status = "ERROR"
            db.commit()
            return False, f"Gagal memulai pemrosesan: {msg}", None

        # 2. Buat sesi baru di PostgreSQL
        new_session = CountingSession(
            id=uuid.uuid4(),
            camera_id=camera.id,
            mode="LIVE_CCTV",
            status="RUNNING",
            started_at=datetime.now(timezone.utc),
            total_in=0,
            total_out=0,
            notes=f"Sesi analisis live untuk {camera.name}"
        )
        db.add(new_session)

        camera.connection_status = "ONLINE"
        camera.processing_status = "RUNNING"
        camera.last_connected_at = datetime.now(timezone.utc)
        camera.last_frame_at = datetime.now(timezone.utc)
        db.commit()

        session_id = new_session.id

        # 3. Jalankan pipeline AI di worker thread latar belakang
        with cls._registry_lock:
            cls._active_sources[cam_id] = source
            cls._ai_running_flags[cam_id] = True

            worker_thread = threading.Thread(
                target=cls._run_live_inference_loop,
                args=(cam_id, session_id, camera.line_coords),
                daemon=True,
                name=f"ai_worker_{cam_id[:8]}"
            )
            cls._active_ai_threads[cam_id] = worker_thread
            worker_thread.start()

        return True, f"Pemrosesan AI berhasil dimulai untuk {camera.name}.", session_id

    @classmethod
    def stop_processing(cls, camera: Camera, db: Session) -> Tuple[bool, str]:
        """Menghentikan worker thread inferensi dan melepaskan koneksi kamera."""
        cam_id = str(camera.id)

        with cls._registry_lock:
            if cam_id in cls._ai_running_flags:
                cls._ai_running_flags[cam_id] = False
            source = cls._active_sources.pop(cam_id, None)

        if source:
            source.disconnect()

        # Update sesi di database
        sess = db.query(CountingSession).filter(
            CountingSession.camera_id == camera.id,
            CountingSession.status == "RUNNING"
        ).order_by(CountingSession.started_at.desc()).first()

        if sess:
            sess.status = "COMPLETED"
            sess.ended_at = datetime.now(timezone.utc)

        camera.processing_status = "STOPPED"
        db.commit()

        return True, f"Pemrosesan AI dihentikan untuk {camera.name}."

    @classmethod
    def _run_live_inference_loop(cls, cam_id: str, session_id: uuid.UUID, line_coords: Dict[str, Any]):
        """Loop eksekusi inferensi AI live: YOLOv8 deteksi -> ByteTrack -> Line Crossing -> Event DB."""
        # Import komponen vision lokal
        import sys
        project_root = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", "..", ".."))
        if project_root not in sys.path:
            sys.path.insert(0, project_root)

        from vision.tracker import ByteTrackTracker
        from vision.line_crossing import VirtualCountingLine
        from app.db.session import SessionLocal

        source = cls._active_sources.get(cam_id)
        if not source:
            return

        tracker = ByteTrackTracker(
            model_path="vision/models/yolov8n.pt",
            tracker_config="vision/bytetrack_custom.yaml",
            conf=0.30,
            iou=0.45
        )

        lc = line_coords or {}
        line = VirtualCountingLine(
            pt1=(lc.get("x1", 40), lc.get("y1", 184)),
            pt2=(lc.get("x2", 440), lc.get("y2", 184)),
            direction_in=lc.get("direction_in", "down"),
            offset=lc.get("offset", 25)
        )

        frame_counter = 0
        cnt_in = 0
        cnt_out = 0

        # --- Lightweight extras (referensi YOLO_Projects) ---
        heatmap = None          # Accumulator kepadatan — dibuat saat frame pertama
        HEATMAP_DECAY = 0.97    # Per-frame decay (panas lama perlahan hilang)
        HEATMAP_RADIUS = 25     # Radius "panas" setiap deteksi orang (px)
        dwell_start: dict = {}  # {track_id: timestamp} untuk dwell time
        ai_start_time = time.time()

        while cls._ai_running_flags.get(cam_id, False):
            ret, frame = source.read_frame()
            if not ret or frame is None:
                time.sleep(0.1)
                continue

            frame_counter += 1
            fh, fw = frame.shape[:2]

            # Inisialisasi heatmap sekali saat tahu ukuran frame
            if heatmap is None:
                heatmap = np.zeros((fh, fw), dtype=np.float32)

            # 1. Tracking Objek (YOLOv8 + ByteTrack)
            tracks = tracker.track(frame)
            active_ids = {t["track_id"] for t in tracks}

            # Update dwell time — catat kapan setiap track pertama terlihat
            now_ts = time.time()
            for t in tracks:
                dwell_start.setdefault(t["track_id"], now_ts)
            # Bersihkan track yang sudah tidak aktif
            for tid in list(dwell_start):
                if tid not in active_ids:
                    dwell_start.pop(tid, None)

            # Update heatmap — decay dulu, lalu tambah panas di centroid
            heatmap *= HEATMAP_DECAY
            for t in tracks:
                cx, cy = t["centroid"]
                if 0 <= cx < fw and 0 <= cy < fh:
                    cv2.circle(heatmap, (cx, cy), HEATMAP_RADIUS, 1.0, -1)

            # 2. Evaluasi Penyeberangan Garis Virtual & simpan event ke DB
            for t in tracks:
                track_id = t["track_id"]
                curr_c = t["centroid"]
                prev_c = t["prev_centroid"]
                conf = t["confidence"]

                crossing_dir = line.evaluate_crossing(
                    track_id=track_id,
                    curr_centroid=curr_c,
                    prev_centroid=prev_c,
                    current_frame=frame_counter
                )

                if crossing_dir:
                    if crossing_dir == "IN":
                        cnt_in += 1
                    elif crossing_dir == "OUT":
                        cnt_out += 1

                    now_dt = datetime.now(timezone.utc)
                    db = SessionLocal()
                    try:
                        new_evt = CountingEvent(
                            id=uuid.uuid4(),
                            camera_id=uuid.UUID(cam_id),
                            session_id=session_id,
                            event_timestamp=now_dt,
                            direction=crossing_dir,
                            count_value=1,
                            track_id=track_id,
                            confidence=round(conf, 3),
                            source_mode="LIVE",
                            metadata_json={
                                "frame_number": frame_counter,
                                "bbox": t["bbox"],
                                "source_type": type(source).__name__
                            }
                        )
                        db.add(new_evt)

                        sess = db.query(CountingSession).filter(CountingSession.id == session_id).first()
                        if sess:
                            if crossing_dir == "IN":
                                sess.total_in += 1
                            elif crossing_dir == "OUT":
                                sess.total_out += 1

                        cam_db = db.query(Camera).filter(Camera.id == uuid.UUID(cam_id)).first()
                        if cam_db:
                            cam_db.last_frame_at = now_dt

                        db.commit()
                        print(f"[LiveAI] Person #{track_id} melintas {crossing_dir} - Kamera {cam_id[:8]}")
                    except Exception as e:
                        db.rollback()
                        print(f"[LiveAI] Gagal simpan event: {e}")
                    finally:
                        db.close()

            # 3. Gambar anotasi ke frame (setiap 2 frame)
            if frame_counter % 2 == 0:
                annotated = frame.copy()

                # -- Heatmap overlay (setiap 10 frame agar tidak boros CPU) --
                if frame_counter % 10 == 0:
                    heat_blur = cv2.GaussianBlur(heatmap, (0, 0), 15)
                    heat_norm = cv2.normalize(heat_blur, None, 0, 255, cv2.NORM_MINMAX).astype(np.uint8)
                    heat_color = cv2.applyColorMap(heat_norm, cv2.COLORMAP_JET)
                    # Simpan agar frame-frame berikutnya bisa pakai tanpa hitung ulang
                    cls._heatmap_cache = (cam_id, heat_color)

                # Terapkan heatmap yang sudah di-cache (ringan — hanya addWeighted)
                cached = getattr(cls, '_heatmap_cache', None)
                if cached and cached[0] == cam_id:
                    annotated = cv2.addWeighted(annotated, 0.75, cached[1], 0.25, 0)

                # -- Bounding box + centroid + label per orang --
                for t in tracks:
                    x1, y1, x2, y2 = t["bbox"]
                    cx, cy = t["centroid"]
                    tid = t["track_id"]

                    # Dwell time — berapa lama orang ini sudah di frame
                    dwell_s = int(now_ts - dwell_start.get(tid, now_ts))
                    if dwell_s >= 60:
                        dwell_str = f"{dwell_s // 60}m{dwell_s % 60:02d}s"
                    else:
                        dwell_str = f"{dwell_s}s"

                    # Bbox hijau terang
                    cv2.rectangle(annotated, (x1, y1), (x2, y2), (0, 255, 0), 2)
                    # Centroid merah
                    cv2.circle(annotated, (cx, cy), 5, (0, 0, 255), -1)
                    # Label: #ID conf | dwell
                    label = f"#{tid}  {t['confidence']:.2f} | {dwell_str}"
                    cv2.putText(annotated, label, (x1, max(y1 - 6, 10)),
                                cv2.FONT_HERSHEY_SIMPLEX, 0.42, (0, 0, 0), 3, cv2.LINE_AA)
                    cv2.putText(annotated, label, (x1, max(y1 - 6, 10)),
                                cv2.FONT_HERSHEY_SIMPLEX, 0.42, (0, 255, 0), 1, cv2.LINE_AA)

                # -- Garis virtual (cyan) + offset (abu-abu) --
                lx1, ly1 = line.x1, line.y1
                lx2, ly2 = line.x2, line.y2
                cv2.line(annotated, (lx1, ly1), (lx2, ly2), (0, 255, 255), 2)
                off = line.offset
                dy_n, dx_n = ly2 - ly1, lx2 - lx1
                seg_len = max(1, int((dx_n ** 2 + dy_n ** 2) ** 0.5))
                nx = int(-dy_n / seg_len * off)
                ny = int(dx_n / seg_len * off)
                cv2.line(annotated, (lx1 + nx, ly1 + ny), (lx2 + nx, ly2 + ny), (80, 80, 80), 1)
                cv2.line(annotated, (lx1 - nx, ly1 - ny), (lx2 - nx, ly2 - ny), (80, 80, 80), 1)

                # -- Info panel pojok kiri atas (semi-transparan) --
                font = cv2.FONT_HERSHEY_SIMPLEX
                people_now = len(tracks)
                fps_live = frame_counter / max(1.0, now_ts - ai_start_time)

                panel_lines = [
                    (f"Masuk : {cnt_in}",   (80, 220, 80)),
                    (f"Keluar: {cnt_out}",   (80, 80, 220)),
                    (f"Di area: {people_now} org", (220, 220, 80)),
                    (f"FPS: {fps_live:.1f}", (160, 160, 160)),
                ]
                # Background kotak gelap semi-transparan di belakang teks
                overlay = annotated.copy()
                cv2.rectangle(overlay, (6, 6), (200, 18 + len(panel_lines) * 28), (0, 0, 0), -1)
                annotated = cv2.addWeighted(overlay, 0.45, annotated, 0.55, 0)

                for i, (txt, color) in enumerate(panel_lines):
                    y_pos = 30 + i * 28
                    cv2.putText(annotated, txt, (10, y_pos), font, 0.6, (0, 0, 0), 3, cv2.LINE_AA)
                    cv2.putText(annotated, txt, (10, y_pos), font, 0.6, color, 1, cv2.LINE_AA)

                # Encode ke JPEG dan simpan ke buffer preview
                ret_jpg, jpeg_bytes = cv2.imencode(".jpg", annotated, [cv2.IMWRITE_JPEG_QUALITY, 75])
                if ret_jpg:
                    with cls._registry_lock:
                        cls._latest_preview_jpegs[cam_id] = jpeg_bytes.tobytes()

            if frame_counter % 30 == 0:
                line.cleanup_old_tracks(active_ids)

            time.sleep(0.01)

        # Cleanup di akhir thread
        if source:
            source.disconnect()
        with cls._registry_lock:
            cls._latest_preview_jpegs.pop(cam_id, None)
        # Bersihkan heatmap cache kamera ini
        if getattr(cls, '_heatmap_cache', None) and cls._heatmap_cache[0] == cam_id:
            cls._heatmap_cache = None

    @classmethod
    def get_latest_preview_frame(cls, camera_id: str) -> Optional[bytes]:
        """Mengambil frame JPEG terbaru dari memory buffer untuk streaming preview HTTP."""
        with cls._registry_lock:
            return cls._latest_preview_jpegs.get(camera_id)

    @classmethod
    def get_or_read_preview_frame(cls, camera: Camera) -> Optional[bytes]:
        """Mengambil frame preview dari persistent lightweight source (tanpa AI/YOLO).

        Berbeda dengan capture_single_preview_frame, method ini MEMPERTAHANKAN koneksi RTSP
        antar pemanggilan sehingga tidak perlu handshake ulang setiap frame — jauh lebih cepat.
        Koneksi ditutup otomatis jika tidak dipakai lebih dari TTL.
        """
        cam_id = str(camera.id)

        # Jika AI worker sedang berjalan, pakai frame dari AI buffer langsung
        with cls._registry_lock:
            if cls._ai_running_flags.get(cam_id):
                return cls._latest_preview_jpegs.get(cam_id)

        now = time.time()

        with cls._preview_only_lock:
            # Cleanup sumber yang sudah expired (TTL)
            expired = [
                cid for cid, last_used in cls._preview_only_last_used.items()
                if now - last_used > cls._PREVIEW_SOURCE_TTL_SECONDS
                and cid != cam_id
            ]
            for cid in expired:
                src = cls._preview_only_sources.pop(cid, None)
                cls._preview_only_last_used.pop(cid, None)
                if src:
                    try:
                        src.disconnect()
                    except Exception:
                        pass

            # Dapatkan atau buat source untuk kamera ini
            source = cls._preview_only_sources.get(cam_id)

            # Validasi source masih bisa dipakai
            source_ok = False
            if source is not None:
                try:
                    source_ok = (source.get_status() in ("ONLINE", "CONNECTING"))
                except Exception:
                    source_ok = False

            if not source_ok:
                # Buat source baru
                if source is not None:
                    try:
                        source.disconnect()
                    except Exception:
                        pass

                new_source = cls.create_source(camera)
                connected, _ = new_source.connect()
                if not connected:
                    try:
                        new_source.disconnect()
                    except Exception:
                        pass
                    return None

                cls._preview_only_sources[cam_id] = new_source
                source = new_source

            # Baca frame dari source yang sudah terhubung
            try:
                ret, frame = source.read_frame()
            except Exception:
                # Source rusak, hapus agar dibuat ulang saat request berikutnya
                cls._preview_only_sources.pop(cam_id, None)
                cls._preview_only_last_used.pop(cam_id, None)
                return None

            cls._preview_only_last_used[cam_id] = now

        if ret and frame is not None and frame.size > 0:
            ret_jpg, jpeg = cv2.imencode(".jpg", frame, [cv2.IMWRITE_JPEG_QUALITY, 70])
            if ret_jpg:
                return jpeg.tobytes()

        return None

    @classmethod
    def release_preview_source(cls, cam_id: str) -> None:
        """Menutup dan menghapus lightweight preview source untuk kamera tertentu."""
        with cls._preview_only_lock:
            source = cls._preview_only_sources.pop(cam_id, None)
            cls._preview_only_last_used.pop(cam_id, None)
        if source:
            try:
                source.disconnect()
            except Exception:
                pass

