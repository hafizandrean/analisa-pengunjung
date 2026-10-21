import os
import time
import json
import sqlite3
from datetime import datetime, timezone
try:
    from zoneinfo import ZoneInfo
except ImportError:
    from backports.zoneinfo import ZoneInfo
from typing import Dict, Any, Optional
import cv2
import numpy as np

from vision.config import VisionWorkerConfig
from vision.tracker import ByteTrackTracker
from vision.line_crossing import VirtualCountingLine
from vision.event_dispatcher import BackendEventDispatcher
from vision.stream_server import VideoStreamServer, GLOBAL_FRAME_BUFFER


class VisionWorker:
    """
    Worker pipeline pemrosesan video computer vision untuk sistem Ayam Bang Jungko:
    - Input: File video rekaman, webcam lokal, atau stream RTSP
    - Deteksi & Tracking: YOLOv8n + ByteTrack
    - Virtual Line Crossing: Evaluasi perpotongan garis & arah IN/OUT dengan cooldown
    - Stream Server: HTTP MJPEG server untuk live feed video di dashboard web
    - Dispatcher: Pengiriman event asinkron ke REST API backend PHP Native
    - Sinkronisasi Dinamis: Membaca perubahan koordinat garis dari database secara real-time
    """

    def __init__(self, config: VisionWorkerConfig):
        self.config = config
        self.db_path = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", "database", "analisa_pengunjung.sqlite"))

        self.tracker = ByteTrackTracker(
            model_path=config.model_path,
            tracker_config=config.tracker_type,
            conf=config.confidence_threshold,
            iou=config.iou_threshold
        )

        lc = config.line_coords
        self.line = VirtualCountingLine(
            pt1=(lc.get("x1", 120), lc.get("y1", 380)),
            pt2=(lc.get("x2", 1160), lc.get("y2", 380)),
            direction_in=lc.get("direction_in", "down"),
            offset=lc.get("offset", 35)
        )

        # Muat koordinat terbaru dari database jika tersedia
        self._load_line_coords_from_db()

        self.dispatcher = BackendEventDispatcher(api_base_url=config.backend_api_url)

        # Server HTTP MJPEG Streaming untuk Browser
        self.stream_server = None
        if config.enable_stream:
            self.stream_server = VideoStreamServer(port=config.stream_port)
            self.stream_server.start()

        # Metrik lokal sesi
        self.total_in = 0
        self.total_out = 0
        self.frames_processed = 0
        self.start_time: Optional[float] = None
        self.is_running = False

        # Pengaturan jam operasional & status harian
        self.settings: Dict[str, str] = {}
        self.last_reset_day: Optional[str] = None
        self.manual_override: Optional[bool] = None
        self.is_open: bool = False
        self.operating_status_text: str = "Tutup"

        # Muat hitungan hari ini dari database dan status operasional
        self._load_settings_and_today_counts()

    def _get_tz(self):
        tz_name = self.settings.get("timezone", "Asia/Jakarta")
        try:
            return ZoneInfo(tz_name)
        except Exception:
            return timezone.utc

    def _load_settings_and_today_counts(self):
        """Memuat pengaturan jam operasional dan mensinkronkan hitungan hari ini dari database SQLite."""
        if not os.path.exists(self.db_path):
            return

        try:
            conn = sqlite3.connect(self.db_path, timeout=5.0)
            cursor = conn.cursor()

            # 1. Ambil pengaturan sistem
            cursor.execute("SELECT setting_key, setting_value FROM app_settings")
            rows = cursor.fetchall()
            self.settings = {k: v for k, v in rows}

            # 2. Ambil akumulasi hitungan hari ini (sesuai tanggal zona waktu lokal)
            tz = self._get_tz()
            now_dt = datetime.now(tz)
            today_str = now_dt.strftime("%Y-%m-%d")
            self.last_reset_day = today_str

            cursor.execute("""
                SELECT 
                    COALESCE(SUM(CASE WHEN direction = 'IN' THEN count_value ELSE 0 END), 0),
                    COALESCE(SUM(CASE WHEN direction = 'OUT' THEN count_value ELSE 0 END), 0)
                FROM counting_events 
                WHERE camera_id = ? AND DATE(event_timestamp) = ?
            """, (self.config.camera_id, today_str))
            count_row = cursor.fetchone()
            conn.close()

            if count_row:
                self.total_in = int(count_row[0])
                self.total_out = int(count_row[1])
                print(f"[VisionWorker] 📊 Sinkronisasi data awal hari ini ({today_str}): Masuk = {self.total_in}, Keluar = {self.total_out}")

            # Periksa status buka/tutup awal
            self._check_operating_hours_and_status()

        except Exception as e:
            print(f"[VisionWorker] Peringatan: Gagal memuat data awal hari ini: {e}")

    def _check_operating_hours_and_status(self):
        """Mengecek status buka/tutup toko berdasarkan otomatisasi jam operasional atau kontrol manual tombol Buka/Tutup."""
        try:
            if os.path.exists(self.db_path):
                conn = sqlite3.connect(self.db_path, timeout=3.0)
                cursor = conn.cursor()
                cursor.execute("SELECT setting_key, setting_value FROM app_settings")
                for k, v in cursor.fetchall():
                    self.settings[k] = v
                conn.close()
        except Exception:
            pass

        # Cek apakah ada perubahan status manual dari endpoint HTTP /set_store_status
        manual_status = GLOBAL_FRAME_BUFFER.stats.get("manual_store_status")
        if manual_status:
            with GLOBAL_FRAME_BUFFER.lock:
                GLOBAL_FRAME_BUFFER.stats["manual_store_status"] = None
            self.manual_override = (manual_status == "open")
            # Simpan juga ke DB
            try:
                conn = sqlite3.connect(self.db_path, timeout=3.0)
                conn.execute("UPDATE app_settings SET setting_value = ? WHERE setting_key = 'store_status'", (manual_status,))
                conn.commit()
                conn.close()
            except Exception:
                pass

        tz = self._get_tz()
        now_dt = datetime.now(tz)
        today_str = now_dt.strftime("%Y-%m-%d")
        current_hm = now_dt.strftime("%H:%M")

        open_hm = self.settings.get("opening_time", "10:00")
        close_hm = self.settings.get("closing_time", "22:00")
        auto_active = self.settings.get("auto_operating_hours", "1") == "1"

        if auto_active:
            # Evaluasi jam operasional otomatis
            if open_hm <= close_hm:
                is_within_hours = (open_hm <= current_hm < close_hm)
            else:
                is_within_hours = (current_hm >= open_hm or current_hm < close_hm)

            # Jika kasir/owner mengklik tombol manual Buka/Tutup, dahulukan perintah manual
            if self.manual_override is not None:
                self.is_open = self.manual_override
                if self.is_open:
                    if current_hm < open_hm:
                        self.operating_status_text = f"Buka Awal (Jadwal: {open_hm})"
                    elif current_hm >= close_hm:
                        self.operating_status_text = f"Buka Lembur (Lewat {close_hm})"
                    else:
                        self.operating_status_text = f"Buka ({open_hm} - {close_hm})"
                else:
                    self.operating_status_text = "Tutup (Manual)"
            else:
                self.is_open = is_within_hours
                if self.is_open:
                    self.operating_status_text = f"Buka ({open_hm} - {close_hm})"
                else:
                    self.operating_status_text = f"Tutup (Buka {open_hm})"
        else:
            # Mode manual: status murni mengikuti tombol Buka/Tutup kasir
            db_status = self.settings.get("store_status", "closed")
            if self.manual_override is not None:
                self.is_open = self.manual_override
            else:
                self.is_open = (db_status == "open")

            self.operating_status_text = "Buka (Mode Manual)" if self.is_open else "Tutup (Mode Manual)"

        # Saat berganti hari baru, segarkan tanggal dan sinkronisasi
        if self.last_reset_day != today_str:
            self.manual_override = None
            self.last_reset_day = today_str
            self._load_settings_and_today_counts()

    def _load_line_coords_from_db(self, actual_w: int = 1280, actual_h: int = 720):
        """Memuat atau memperbarui koordinat garis virtual dari database SQLite dan menyesuaikan ke resolusi video."""
        if not os.path.exists(self.db_path):
            return

        try:
            conn = sqlite3.connect(self.db_path, timeout=5.0)
            cursor = conn.cursor()
            cursor.execute("SELECT line_coords FROM cameras WHERE id = ?", (self.config.camera_id,))
            row = cursor.fetchone()
            conn.close()

            if row and row[0]:
                coords = json.loads(row[0]) if isinstance(row[0], str) else row[0]
                
                # Skala dari resolusi acuan web UI (1280x720) ke resolusi video aktual
                scale_x = actual_w / 1280.0
                scale_y = actual_h / 720.0
                
                pt1 = (int(coords.get("x1", 120) * scale_x), int(coords.get("y1", 380) * scale_y))
                pt2 = (int(coords.get("x2", 1160) * scale_x), int(coords.get("y2", 380) * scale_y))
                direction_in = coords.get("direction_in", "down")
                offset = max(15, int(coords.get("offset", 35) * scale_y))

                if (self.line.x1, self.line.y1, self.line.x2, self.line.y2, self.line.direction_in) != (pt1[0], pt1[1], pt2[0], pt2[1], direction_in):
                    self.line.update_coordinates(pt1=pt1, pt2=pt2, direction_in=direction_in, offset=offset)
                    print(f"[VisionWorker] Sinkronisasi koordinat garis ({actual_w}x{actual_h}): P1={pt1}, P2={pt2}, Arah={direction_in}")
        except Exception as e:
            pass

    def run(self) -> Dict[str, Any]:
        """Menjalankan loop pemrosesan video live stream atau kamera webcam."""
        source_input = self.config.video_source
        is_device_index = source_input.isdigit() if isinstance(source_input, str) else isinstance(source_input, int)
        is_stream = isinstance(source_input, str) and (source_input.startswith("rtsp://") or source_input.startswith("http://") or source_input.startswith("https://"))

        reconnect_delay = 2.0
        max_reconnect_delay = 30.0

        def open_capture():
            if is_device_index:
                backend = cv2.CAP_DSHOW if os.name == "nt" else cv2.CAP_ANY
                c = cv2.VideoCapture(int(source_input), backend)
                c.set(cv2.CAP_PROP_BUFFERSIZE, 1)
            elif is_stream:
                # Optimasi latensi rendah (Low Latency) & kestabilan TCP untuk CCTV IP / Wi-Fi
                os.environ["OPENCV_FFMPEG_CAPTURE_OPTIONS"] = "rtsp_transport;tcp|fflags;nobuffer|flags;low_delay"
                c = cv2.VideoCapture(source_input, cv2.CAP_FFMPEG)
                c.set(cv2.CAP_PROP_BUFFERSIZE, 1)
            else:
                if not os.path.exists(source_input):
                    raise FileNotFoundError(f"Sumber video tidak ditemukan: {source_input}")
                c = cv2.VideoCapture(source_input)
            return c

        cap = open_capture()
        if not cap.isOpened() and not is_stream:
            raise RuntimeError(f"Gagal membuka sumber video: {self.config.video_source}")

        width = int(cap.get(cv2.CAP_PROP_FRAME_WIDTH)) or 1280
        height = int(cap.get(cv2.CAP_PROP_FRAME_HEIGHT)) or 720
        fps = cap.get(cv2.CAP_PROP_FPS) or 25.0
        total_video_frames = int(cap.get(cv2.CAP_PROP_FRAME_COUNT))

        self._load_line_coords_from_db(width, height)
        print(f"[VisionWorker] Memulai pemrosesan video: {width}x{height} @ {fps:.1f} FPS, Total: {total_video_frames} frames")
        print(f"[VisionWorker] Garis Virtual Aktif: ({self.line.x1}, {self.line.y1}) -> ({self.line.x2}, {self.line.y2}), Arah Masuk: {self.line.direction_in}")

        self.is_running = True
        self.start_time = time.time()
        flash_counter = 0
        last_fps_calc = time.time()
        fps_frame_count = 0
        current_fps = fps

        if self.config.start_frame > 0:
            cap.set(cv2.CAP_PROP_POS_FRAMES, self.config.start_frame)
            self.frames_processed = self.config.start_frame

        processed_in_this_run = 0

        try:
            while self.is_running:
                ret, frame = cap.read()

                # Penanganan akhir video atau terputus
                if not ret or frame is None:
                    if is_stream and self.config.auto_reconnect:
                        print(f"[VisionWorker] Sambungan stream RTSP terputus. Mencoba reconnect dalam {reconnect_delay:.1f} detik...")
                        cap.release()
                        time.sleep(reconnect_delay)
                        reconnect_delay = min(reconnect_delay * 1.5, max_reconnect_delay)
                        cap = open_capture()
                        continue
                    elif self.config.loop_video:
                        cap.set(cv2.CAP_PROP_POS_FRAMES, 0)
                        continue
                    else:
                        print("[VisionWorker] Akhir berkas video tercapai (EOF).")
                        break

                reconnect_delay = 2.0
                self.frames_processed += 1
                processed_in_this_run += 1
                fps_frame_count += 1

                # Cek jika ada permintaan reset manual dari UI web
                if GLOBAL_FRAME_BUFFER.stats.get("reset_requested"):
                    self.total_in = 0
                    self.total_out = 0
                    with GLOBAL_FRAME_BUFFER.lock:
                        GLOBAL_FRAME_BUFFER.stats["reset_requested"] = False
                    print("[VisionWorker] 🔄 Permintaan reset manual diterima: Hitungan dikembalikan ke 0.")

                # Hitung estimasi real FPS
                now_time = time.time()
                if now_time - last_fps_calc >= 1.0:
                    current_fps = fps_frame_count / (now_time - last_fps_calc)
                    fps_frame_count = 0
                    last_fps_calc = now_time

                # 1. Jalankan tracking objek (YOLOv8 + ByteTrack)
                tracks = self.tracker.track(frame)
                active_ids = {t["track_id"] for t in tracks}

                # 2. Evaluasi setiap objek terhadap garis virtual
                new_crossing = False
                for t in tracks:
                    track_id = t["track_id"]
                    curr_c = t["centroid"]
                    prev_c = t["prev_centroid"]
                    conf = t["confidence"]

                    crossing_dir = self.line.evaluate_crossing(
                        track_id=track_id,
                        curr_centroid=curr_c,
                        prev_centroid=prev_c,
                        current_frame=self.frames_processed
                    )

                    if crossing_dir:
                        new_crossing = True
                        flash_counter = 12

                        # HANYA hitung dan simpan event jika berada dalam jam operasional restoran
                        if self.is_open:
                            now_iso = datetime.now(timezone.utc).isoformat()
                            if crossing_dir == "IN":
                                self.total_in += 1
                            elif crossing_dir == "OUT":
                                self.total_out += 1

                            print(f"[VisionWorker] DETEKSI: Pengunjung #{track_id} melintas {crossing_dir} (Total Masuk: {self.total_in}, Keluar: {self.total_out})")

                            # Kirim event ke backend REST API PHP Native
                            self.dispatcher.dispatch(
                                camera_id=self.config.camera_id,
                                session_id=self.config.session_id,
                                timestamp_iso=now_iso,
                                direction=crossing_dir,
                                count_value=1,
                                track_id=track_id,
                                confidence=conf,
                                source_mode=self.config.source_mode,
                                metadata={
                                    "frame_number": self.frames_processed,
                                    "bbox": t["bbox"],
                                    "fps": round(current_fps, 2)
                                }
                            )
                        else:
                            print(f"[VisionWorker] Tamu #{track_id} melintas {crossing_dir} saat restoran tutup ({self.operating_status_text}), gerakan diabaikan.")

                # Pembersihan memori jejak lama & sinkronisasi koordinat garis + status jam operasional berkala
                if self.frames_processed % 30 == 0:
                    self.line.cleanup_old_tracks(active_ids)
                    self._load_line_coords_from_db(width, height)
                    self._check_operating_hours_and_status()

                # 3. Anotasi visual untuk streaming browser dan preview
                annotated_frame = frame.copy()
                self._draw_annotations(annotated_frame, tracks, flash_counter > 0, current_fps)
                if flash_counter > 0:
                    flash_counter -= 1

                # Update live MJPEG frame buffer untuk browser web (cameras.php & index.php)
                if self.config.enable_stream:
                    GLOBAL_FRAME_BUFFER.update(
                        annotated_frame,
                        stats={
                            "status": "running",
                            "camera_id": self.config.camera_id,
                            "total_in": self.total_in,
                            "total_out": self.total_out,
                            "fps": round(current_fps, 1),
                            "frame_number": self.frames_processed,
                            "is_open": self.is_open,
                            "store_status": "open" if self.is_open else "closed",
                            "operating_status": self.operating_status_text,
                            "opening_time": self.settings.get("opening_time", "10:00"),
                            "closing_time": self.settings.get("closing_time", "22:00"),
                            "auto_operating_hours": self.settings.get("auto_operating_hours", "1") == "1",
                        },
                        quality=65
                    )

                # Pratinjau jendela OpenCV lokal jika diaktifkan
                if self.config.show_preview:
                    cv2.imshow("Ayam Bang Jungko - CCTV Analytics", annotated_frame)
                    key = cv2.waitKey(1) & 0xFF
                    if key in [ord("q"), 27]:
                        print("[VisionWorker] Pengguna menghentikan pemrosesan.")
                        break

                if self.config.max_frames and processed_in_this_run >= self.config.max_frames:
                    print(f"[VisionWorker] Batas frame uji coba ({self.config.max_frames}) tercapai.")
                    break

        finally:
            cap.release()
            if self.config.show_preview:
                cv2.destroyAllWindows()
            self.dispatcher.stop()
            if self.stream_server:
                self.stream_server.stop()

        elapsed = time.time() - (self.start_time or time.time())
        avg_fps = self.frames_processed / max(elapsed, 0.001)

        result_summary = {
            "total_in": self.total_in,
            "total_out": self.total_out,
            "total_count": self.total_in + self.total_out,
            "frames_processed": self.frames_processed,
            "elapsed_seconds": round(elapsed, 2),
            "average_fps": round(avg_fps, 2),
            "events_delivered": self.dispatcher.delivered_count,
            "events_failed": self.dispatcher.failed_count
        }

        print("\n=== Ringkasan Eksekusi Vision Worker ===")
        print(f"Total Masuk (IN) : {self.total_in}")
        print(f"Total Keluar (OUT): {self.total_out}")
        print(f"Frame Diproses    : {self.frames_processed} ({avg_fps:.1f} FPS)")
        print(f"Event Terkirim    : {self.dispatcher.delivered_count}")

        return result_summary

    def _draw_annotations(self, frame: np.ndarray, tracks: list, is_crossing: bool, current_fps: float):
        """Menggambar visualisasi deteksi, garis virtual, dan HUD metrik pada frame."""
        # Garis virtual hanya digambar pada desktop preview GUI (--show).
        # Untuk web stream (cameras.php), garis ditangani secara interaktif oleh HTML5 Canvas
        # agar tidak terjadi garis ganda (ghosting) dan posisi selalu presisi serta bisa digeser bebas.
        if self.config.show_preview:
            line_color = (0, 255, 255) if is_crossing else (22, 115, 249)  # BGR
            cv2.line(
                frame,
                (self.line.x1, self.line.y1),
                (self.line.x2, self.line.y2),
                line_color,
                3
            )

            # Handle titik ujung garis P1 & P2
            cv2.circle(frame, (self.line.x1, self.line.y1), 7, (16, 185, 129), -1)  # Emerald P1
            cv2.circle(frame, (self.line.x1, self.line.y1), 7, (255, 255, 255), 2)
            cv2.putText(frame, "P1", (self.line.x1 - 10, self.line.y1 - 12), cv2.FONT_HERSHEY_SIMPLEX, 0.45, (16, 185, 129), 2)

            cv2.circle(frame, (self.line.x2, self.line.y2), 7, (246, 130, 59), -1)  # Blue P2
            cv2.circle(frame, (self.line.x2, self.line.y2), 7, (255, 255, 255), 2)
            cv2.putText(frame, "P2", (self.line.x2 - 10, self.line.y2 - 12), cv2.FONT_HERSHEY_SIMPLEX, 0.45, (246, 130, 59), 2)

            # Buffer zone (garis tipis putus-putus acuan)
            cv2.line(
                frame,
                (self.line.x1, max(0, self.line.y1 - self.line.offset)),
                (self.line.x2, max(0, self.line.y2 - self.line.offset)),
                (148, 163, 184),
                1
            )
            cv2.line(
                frame,
                (self.line.x1, self.line.y1 + self.line.offset),
                (self.line.x2, self.line.y2 + self.line.offset),
                (148, 163, 184),
                1
            )

        # Bounding box dan Track ID orang
        for t in tracks:
            x1, y1, x2, y2 = t["bbox"]
            tid = t["track_id"]
            cx, cy = t["centroid"]
            conf = t.get("confidence", 0.0)
            traj = t.get("trajectory", [])

            # Gambar garis jejak pergerakan (trajectory trail) seperti pada ByteTrack & Temple Counter
            if len(traj) > 1:
                for i in range(1, len(traj)):
                    alpha = i / len(traj)
                    color = (int(16 * alpha), int(185 * alpha), int(129 * alpha))
                    cv2.line(frame, traj[i - 1], traj[i], color, 2)

            # Bounding box orang (Hijau Cerah)
            cv2.rectangle(frame, (x1, y1), (x2, y2), (16, 185, 129), 2)
            cv2.circle(frame, (cx, cy), 4, (0, 0, 255), -1)

            # Label Track ID & Tingkat Kepercayaan Deteksi (%)
            conf_pct = int(conf * 100)
            label = f"Tamu #{tid} {conf_pct}%"
            (tw, th), _ = cv2.getTextSize(label, cv2.FONT_HERSHEY_SIMPLEX, 0.42, 1)

            cv2.rectangle(frame, (x1, max(0, y1 - th - 8)), (x1 + tw + 10, max(th + 8, y1)), (15, 23, 42), -1)
            cv2.putText(
                frame,
                label,
                (x1 + 5, max(th + 2, y1 - 4)),
                cv2.FONT_HERSHEY_SIMPLEX,
                0.42,
                (255, 255, 255),
                1
            )

        # Overlay HUD Panel di desktop GUI (hanya jika mode --show aktif agar video web tetap bersih & jernih)
        if self.config.show_preview:
            h_panel = 125 if not self.is_open else 105
            overlay = frame.copy()
            cv2.rectangle(overlay, (15, 15), (285, h_panel), (15, 23, 42), -1)
            cv2.addWeighted(overlay, 0.75, frame, 0.25, 0, frame)
            cv2.rectangle(frame, (15, 15), (285, h_panel), (51, 65, 85), 1)

            cv2.putText(frame, f"MASUK   : {self.total_in}", (30, 42), cv2.FONT_HERSHEY_SIMPLEX, 0.58, (16, 185, 129), 2)
            cv2.putText(frame, f"KELUAR  : {self.total_out}", (30, 70), cv2.FONT_HERSHEY_SIMPLEX, 0.58, (59, 130, 246), 2)
            cv2.putText(frame, f"LIVE @ {current_fps:.1f} FPS", (30, 94), cv2.FONT_HERSHEY_SIMPLEX, 0.42, (148, 163, 184), 1)
            if not self.is_open:
                cv2.putText(frame, f"STATUS  : {self.operating_status_text}", (30, 116), cv2.FONT_HERSHEY_SIMPLEX, 0.38, (0, 165, 255), 1)
