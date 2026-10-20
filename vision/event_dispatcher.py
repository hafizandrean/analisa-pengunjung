import queue
import threading
import time
from typing import Dict, Any, Optional
import requests


class BackendEventDispatcher:
    """
    Dispatcher pengirim event penyeberangan ke REST API backend.
    Menggunakan antrian latar belakang (background queue thread)
    agar inferensi computer vision tidak terblokir oleh latency jaringan.
    """

    def __init__(self, api_base_url: str = "http://127.0.0.1:8000/api/v1", max_queue_size: int = 500):
        url = api_base_url.strip()
        if url.endswith(".php") or url.endswith("/events"):
            self.api_url = url
        else:
            self.api_url = f"{url.rstrip('/')}/events"
        self.event_queue = queue.Queue(maxsize=max_queue_size)
        self.is_running = True
        self.delivered_count = 0
        self.failed_count = 0

        # Jalankan worker thread pengirim
        self.worker_thread = threading.Thread(target=self._dispatch_loop, daemon=True)
        self.worker_thread.start()

    def dispatch(
        self,
        camera_id: str,
        session_id: Optional[str],
        timestamp_iso: str,
        direction: str,
        count_value: int,
        track_id: int,
        confidence: float,
        source_mode: str = "VIDEO",
        metadata: Optional[Dict[str, Any]] = None
    ) -> bool:
        """Menambahkan event ke antrian pengiriman."""
        payload = {
            "camera_id": camera_id,
            "session_id": session_id,
            "event_timestamp": timestamp_iso,
            "direction": direction,
            "count_value": count_value,
            "track_id": track_id,
            "confidence": round(confidence, 3),
            "source_mode": source_mode,
            "metadata_json": metadata or {}
        }
        try:
            self.event_queue.put_nowait(payload)
            return True
        except queue.Full:
            print("[Dispatcher] Antrian event penuh, event dilewati.")
            self.failed_count += 1
            return False

    def _dispatch_loop(self):
        """Loop pengiriman HTTP POST di latar belakang."""
        session = requests.Session()
        session.headers.update({"Content-Type": "application/json"})

        while self.is_running:
            try:
                payload = self.event_queue.get(timeout=1.0)
            except queue.Empty:
                continue

            try:
                response = session.post(self.api_url, json=payload, timeout=3.0)
                if response.status_code in [200, 201]:
                    self.delivered_count += 1
                else:
                    self.failed_count += 1
                    print(f"[Dispatcher] Gagal kirim event (HTTP {response.status_code}): {response.text}")
            except Exception as e:
                self.failed_count += 1
                # Jangan hentikan worker jika backend offline sementara
                # print(f"[Dispatcher] Error koneksi ke {self.api_url}: {e}")

            self.event_queue.task_done()

    def stop(self, wait_completion: bool = True):
        """Menghentikan worker thread pengirim."""
        if wait_completion:
            # Tunggu sisa event terkirim dengan batas waktu
            try:
                self.event_queue.join()
            except Exception:
                pass
        self.is_running = False
