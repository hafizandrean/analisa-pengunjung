import os
import time
from datetime import datetime, timezone
from typing import Dict, Any, Optional
import cv2
import numpy as np

from vision.config import VisionWorkerConfig
from vision.tracker import ByteTrackTracker
from vision.line_crossing import VirtualCountingLine
from vision.event_dispatcher import BackendEventDispatcher


class VisionWorker:
    """
    Worker pipeline pemrosesan video computer vision untuk sistem Ayam Bang Jungko:
    - Input: File video rekaman atau stream RTSP
    - Deteksi & Tracking: YOLOv8n + ByteTrack
    - Virtual Line Crossing: Evaluasi perpotongan garis & arah IN/OUT
    - Dispatcher: Pengiriman event asinkron ke REST API backend
    """

    def __init__(self, config: VisionWorkerConfig):
        self.config = config
        self.tracker = ByteTrackTracker(
            model_path=config.model_path,
            tracker_config=config.tracker_type,
            conf=config.confidence_threshold,
            iou=config.iou_threshold
        )

        lc = config.line_coords
        self.line = VirtualCountingLine(
            pt1=(lc.get("x1", 40), lc.get("y1", 184)),
            pt2=(lc.get("x2", 440), lc.get("y2", 184)),
            direction_in=lc.get("direction_in", "down"),
            offset=lc.get("offset", 25)
        )

        self.dispatcher = BackendEventDispatcher(api_base_url=config.backend_api_url)

        # Metrik lokal sesi
        self.total_in = 0
        self.total_out = 0
        self.frames_processed = 0
        self.start_time: Optional[float] = None
        self.is_running = False

    def run(self) -> Dict[str, Any]:
        """Menjalankan loop pemrosesan video live stream atau kamera webcam."""
        source_input = self.config.video_source
        is_device_index = source_input.isdigit() if isinstance(source_input, str) else isinstance(source_input, int)
        is_stream = isinstance(source_input, str) and (source_input.startswith("rtsp://") or source_input.startswith("http://") or source_input.startswith("https://"))

        if is_device_index:
            dev_idx = int(source_input)
            backend = cv2.CAP_DSHOW if os.name == "nt" else cv2.CAP_ANY
            cap = cv2.VideoCapture(dev_idx, backend)
        elif is_stream:
            cap = cv2.VideoCapture(source_input, cv2.CAP_FFMPEG)
        else:
            if not os.path.exists(source_input):
                raise FileNotFoundError(f"Sumber video tidak ditemukan: {source_input}")
            cap = cv2.VideoCapture(source_input)

        if not cap.isOpened():
            raise RuntimeError(f"Gagal membuka sumber video: {self.config.video_source}")

        width = int(cap.get(cv2.CAP_PROP_FRAME_WIDTH))
        height = int(cap.get(cv2.CAP_PROP_FRAME_HEIGHT))
        fps = cap.get(cv2.CAP_PROP_FPS) or 25.0
        total_video_frames = int(cap.get(cv2.CAP_PROP_FRAME_COUNT))

        print(f"[VisionWorker] Memulai pemrosesan video: {width}x{height} @ {fps:.1f} FPS, Total: {total_video_frames} frames")
        print(f"[VisionWorker] Garis Virtual: ({self.line.x1}, {self.line.y1}) -> ({self.line.x2}, {self.line.y2}), Arah Masuk: {self.line.direction_in}")

        self.is_running = True
        self.start_time = time.time()
        flash_counter = 0

        if self.config.start_frame > 0:
            cap.set(cv2.CAP_PROP_POS_FRAMES, self.config.start_frame)
            self.frames_processed = self.config.start_frame
            print(f"[VisionWorker] Melompat ke frame awal: {self.config.start_frame}")

        processed_in_this_run = 0

        try:
            while self.is_running:
                ret, frame = cap.read()
                if not ret or frame is None:
                    if self.config.loop_video:
                        cap.set(cv2.CAP_PROP_POS_FRAMES, 0)
                        continue
                    else:
                        print("[VisionWorker] Akhir berkas video tercapai (EOF).")
                        break

                self.frames_processed += 1
                processed_in_this_run += 1

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
                        flash_counter = 10
                        now_iso = datetime.now(timezone.utc).isoformat()

                        if crossing_dir == "IN":
                            self.total_in += 1
                        elif crossing_dir == "OUT":
                            self.total_out += 1

                        print(f"[VisionWorker] DETEKSI: Person #{track_id} melintas {crossing_dir} pada frame {self.frames_processed}")

                        # Kirim event ke backend REST API
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
                                "fps": round(fps, 2)
                            }
                        )

                # Pembersihan memori jejak lama
                if self.frames_processed % 30 == 0:
                    self.line.cleanup_old_tracks(active_ids)

                # 3. Visual Annotations (jika preview diaktifkan)
                if self.config.show_preview:
                    self._draw_annotations(frame, tracks, flash_counter > 0)
                    if flash_counter > 0:
                        flash_counter -= 1

                    cv2.imshow("Ayam Bang Jungko - CCTV Analytics", frame)
                    key = cv2.waitKey(1) & 0xFF
                    if key in [ord("q"), 27]:  # 'q' atau ESC
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

    def _draw_annotations(self, frame: np.ndarray, tracks: list, is_crossing: bool):
        """Menggambar visualisasi deteksi, garis, dan metrik pada frame."""
        # Garis virtual (Merah/Kuning saat crossing)
        line_color = (0, 255, 255) if is_crossing else (0, 0, 255)
        cv2.line(
            frame,
            (self.line.x1, self.line.y1),
            (self.line.x2, self.line.y2),
            line_color,
            3
        )

        # Toleransi buffer zone (garis tipis)
        cv2.line(
            frame,
            (self.line.x1, max(0, self.line.y1 - self.line.offset)),
            (self.line.x2, max(0, self.line.y2 - self.line.offset)),
            (200, 200, 200),
            1
        )
        cv2.line(
            frame,
            (self.line.x1, self.line.y1 + self.line.offset),
            (self.line.x2, self.line.y2 + self.line.offset),
            (200, 200, 200),
            1
        )

        # Bounding box dan track ID
        for t in tracks:
            x1, y1, x2, y2 = t["bbox"]
            tid = t["track_id"]
            cx, cy = t["centroid"]

            cv2.rectangle(frame, (x1, y1), (x2, y2), (0, 255, 0), 2)
            cv2.circle(frame, (cx, cy), 4, (0, 0, 255), -1)

            # Label Track ID
            cv2.putText(
                frame,
                f"ID #{tid}",
                (x1, max(20, y1 - 8)),
                cv2.FONT_HERSHEY_SIMPLEX,
                0.5,
                (0, 255, 0),
                2
            )

        # Overlay HUD Panel
        cv2.rectangle(frame, (10, 10), (220, 80), (15, 23, 42), -1)
        cv2.rectangle(frame, (10, 10), (220, 80), (51, 65, 85), 1)

        cv2.putText(frame, f"MASUK  : {self.total_in}", (20, 35), cv2.FONT_HERSHEY_SIMPLEX, 0.6, (16, 185, 129), 2)
        cv2.putText(frame, f"KELUAR : {self.total_out}", (20, 65), cv2.FONT_HERSHEY_SIMPLEX, 0.6, (59, 130, 246), 2)
