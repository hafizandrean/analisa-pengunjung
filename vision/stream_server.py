"""
HTTP MJPEG Video Streaming Server untuk Ayam Bang Jungko
Menyajikan live video feed kamera CCTV beranotasi ke browser (cameras.php & index.php)
secara real-time melalui protokol standar multipart/x-mixed-replace (MJPEG).
"""

import threading
import time
import json
from http.server import ThreadingHTTPServer, BaseHTTPRequestHandler
from typing import Optional
import cv2
import numpy as np


class FrameBuffer:
    """Buffer thread-safe untuk menyimpan frame video JPEG terakhir."""
    def __init__(self):
        self.lock = threading.Lock()
        self.jpeg_bytes: Optional[bytes] = None
        self.stats = {
            "status": "idle",
            "camera_id": "cam-1",
            "total_in": 0,
            "total_out": 0,
            "fps": 0.0,
            "updated_at": 0
        }

    def update(self, frame: np.ndarray, stats: dict = None, quality: int = 70):
        try:
            # Encode frame ke JPEG
            ret, buf = cv2.imencode('.jpg', frame, [cv2.IMWRITE_JPEG_QUALITY, quality])
            if ret:
                with self.lock:
                    self.jpeg_bytes = buf.tobytes()
                    if stats:
                        self.stats.update(stats)
                    self.stats["updated_at"] = time.time()
        except Exception as e:
            pass

    def get_jpeg(self) -> Optional[bytes]:
        with self.lock:
            return self.jpeg_bytes

    def get_stats(self) -> dict:
        with self.lock:
            return dict(self.stats)

    def reset_counters(self):
        with self.lock:
            self.stats["total_in"] = 0
            self.stats["total_out"] = 0
            self.stats["reset_requested"] = True

    def set_store_status(self, is_open: bool):
        with self.lock:
            self.stats["is_open"] = is_open
            self.stats["manual_store_status"] = "open" if is_open else "closed"
            self.stats["status_override_time"] = time.time()


GLOBAL_FRAME_BUFFER = FrameBuffer()


class StreamHandler(BaseHTTPRequestHandler):
    """Handler HTTP untuk rute streaming video & status."""

    def log_message(self, format, *args):
        # Meredam logging console HTTP agar terminal tidak banjir request
        return

    def do_OPTIONS(self):
        self.send_response(200)
        self.send_header('Access-Control-Allow-Origin', '*')
        self.send_header('Access-Control-Allow-Methods', 'GET, OPTIONS')
        self.send_header('Access-Control-Allow-Headers', 'Content-Type')
        self.end_headers()

    def do_GET(self):
        if self.path.startswith('/video_feed'):
            self.send_response(200)
            self.send_header('Access-Control-Allow-Origin', '*')
            self.send_header('Content-Type', 'multipart/x-mixed-replace; boundary=frame')
            self.send_header('Cache-Control', 'no-cache, no-store, must-revalidate')
            self.send_header('Pragma', 'no-cache')
            self.send_header('Expires', '0')
            self.end_headers()

            try:
                while True:
                    frame_bytes = GLOBAL_FRAME_BUFFER.get_jpeg()
                    if frame_bytes:
                        self.wfile.write(b'--frame\r\n')
                        self.wfile.write(b'Content-Type: image/jpeg\r\n')
                        self.wfile.write(f'Content-Length: {len(frame_bytes)}\r\n\r\n'.encode('utf-8'))
                        self.wfile.write(frame_bytes)
                        self.wfile.write(b'\r\n')
                    time.sleep(0.04)  # ~25 FPS
            except (ConnectionResetError, BrokenPipeError):
                pass
            except Exception:
                pass

        elif self.path.startswith('/snapshot'):
            frame_bytes = GLOBAL_FRAME_BUFFER.get_jpeg()
            if frame_bytes:
                self.send_response(200)
                self.send_header('Access-Control-Allow-Origin', '*')
                self.send_header('Content-Type', 'image/jpeg')
                self.send_header('Content-Length', str(len(frame_bytes)))
                self.end_headers()
                self.wfile.write(frame_bytes)
            else:
                self.send_response(503)
                self.send_header('Access-Control-Allow-Origin', '*')
                self.send_header('Content-Type', 'text/plain')
                self.end_headers()
                self.wfile.write(b'Kamera belum siap')

        elif self.path.startswith('/set_store_status'):
            from urllib.parse import urlparse, parse_qs
            parsed = urlparse(self.path)
            params = parse_qs(parsed.query)
            status_param = params.get('status', ['open'])[0]
            is_open = (status_param.lower() in ['open', '1', 'true', 'buka'])
            GLOBAL_FRAME_BUFFER.set_store_status(is_open)

            self.send_response(200)
            self.send_header('Access-Control-Allow-Origin', '*')
            self.send_header('Content-Type', 'application/json')
            self.end_headers()
            self.wfile.write(json.dumps({"success": True, "is_open": is_open, "store_status": "open" if is_open else "closed"}).encode('utf-8'))

        elif self.path.startswith('/reset'):
            GLOBAL_FRAME_BUFFER.reset_counters()
            self.send_response(200)
            self.send_header('Access-Control-Allow-Origin', '*')
            self.send_header('Content-Type', 'application/json')
            self.end_headers()
            self.wfile.write(b'{"success": true, "message": "Counters reset to 0"}')

        elif self.path.startswith('/shutdown') or self.path.startswith('/stop'):
            self.send_response(200)
            self.send_header('Access-Control-Allow-Origin', '*')
            self.send_header('Content-Type', 'application/json')
            self.end_headers()
            self.wfile.write(b'{"success": true, "message": "Worker stream stopping"}')
            
            # Matikan proses python di thread terpisah agar response HTTP sempat terkirim
            import os
            threading.Thread(target=lambda: (time.sleep(0.3), os._exit(0)), daemon=True).start()

        elif self.path.startswith('/status'):
            stats = GLOBAL_FRAME_BUFFER.get_stats()
            # Cek apakah feed masih aktif dalam 3 detik terakhir
            is_active = (time.time() - stats.get("updated_at", 0)) < 3.0
            stats["is_active"] = is_active
            payload = json.dumps(stats).encode('utf-8')

            self.send_response(200)
            self.send_header('Access-Control-Allow-Origin', '*')
            self.send_header('Content-Type', 'application/json')
            self.send_header('Content-Length', str(len(payload)))
            self.end_headers()
            self.wfile.write(payload)

        else:
            self.send_response(404)
            self.end_headers()


class VideoStreamServer:
    """Server MJPEG yang berjalan di thread terpisah."""
    def __init__(self, host: str = "0.0.0.0", port: int = 8080):
        self.host = host
        self.port = port
        self.server: Optional[ThreadingHTTPServer] = None
        self.thread: Optional[threading.Thread] = None

    def start(self):
        try:
            self.server = ThreadingHTTPServer((self.host, self.port), StreamHandler)
            self.thread = threading.Thread(target=self.server.serve_forever, daemon=True)
            self.thread.start()
            print(f"[StreamServer] Server streaming CCTV berjalan di http://127.0.0.1:{self.port}/video_feed")
        except Exception as e:
            print(f"[StreamServer] Peringatan: Gagal menjalankan server streaming: {e}")

    def stop(self):
        if self.server:
            try:
                self.server.shutdown()
                self.server.server_close()
            except Exception:
                pass
