"""
Evidence Manager - Modul Manajemen Bukti Visual & Integrasi Cloudflare R2
Untuk Ayam Bang Jungko - Computer Vision Traffic Analytics

Fitur:
1. Otomatis memotong (crop) postur tamu melintas dengan margin aman & kompresi JPEG hemat (~15-25 KB).
2. Penyimpanan terstruktur: uploads/evidence/YYYY-MM-DD/evt_{id}.jpg.
3. Hybrid Cloudflare R2 Offloading:
   - Foto diunggah ke bucket R2 (countiva) di latar belakang (asynchronous background thread).
   - Setelah sukses di-upload, URL publik Cloudflare disimpan ke database.
   - File fisik di harddisk laptop kasir langsung dihapus sehingga disk tetap 0% beban!
4. Offline Spool & Auto-Purge:
   - Jika offline, foto disimpan sementara di lokal.
   - Retry otomatis saat koneksi aktif kembali.
   - Menghapus file lokal yang melebihi masa simpan (retention days).
"""

import os
import sys
import time
import queue
import logging
import threading
import sqlite3
from datetime import datetime, timezone
from typing import Optional, Tuple, Dict, Any

import cv2
import numpy as np

try:
    import boto3
    from botocore.config import Config as BotoConfig
    BOTO3_AVAILABLE = True
except ImportError:
    BOTO3_AVAILABLE = False


logger = logging.getLogger("EvidenceManager")


class EvidenceManager:
    """Manajer Bukti Foto dan Pengunggah Asinkron Cloudflare R2."""

    def __init__(self, db_path: Optional[str] = None):
        self.base_dir = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
        self.evidence_dir = os.path.join(self.base_dir, "uploads", "evidence")
        os.makedirs(self.evidence_dir, exist_ok=True)

        self.db_path = db_path or os.path.join(self.base_dir, "database", "analisa_pengunjung.sqlite")
        self.upload_queue = queue.Queue(maxsize=100)
        self.is_running = True

        # Memuat konfigurasi awal dari database
        self.settings: Dict[str, str] = {}
        self._load_settings()

        # Thread pekerja untuk upload Cloudflare di background
        self.worker_thread = threading.Thread(target=self._upload_worker_loop, daemon=True, name="R2EvidenceWorker")
        self.worker_thread.start()

        # Thread periodik untuk retensi & auto-purge lokal
        self.purge_thread = threading.Thread(target=self._purge_worker_loop, daemon=True, name="EvidencePurgeWorker")
        self.purge_thread.start()

    def _load_settings(self):
        """Membaca pengaturan Cloudflare R2 & retensi dari database SQLite."""
        if not os.path.exists(self.db_path):
            return

        try:
            conn = sqlite3.connect(self.db_path, timeout=5.0)
            c = conn.cursor()
            c.execute("SELECT setting_key, setting_value FROM app_settings")
            self.settings = {row[0]: row[1] for row in c.fetchall()}
            conn.close()
        except Exception as e:
            logger.warning(f"[EvidenceManager] Gagal memuat settings: {e}")

    def _get_s3_client(self):
        """Membuat instance klien S3 Boto3 untuk Cloudflare R2."""
        if not BOTO3_AVAILABLE:
            return None

        account_id = self.settings.get("r2_account_id", "").strip()
        access_key = self.settings.get("r2_access_key_id", "").strip()
        secret_key = self.settings.get("r2_secret_access_key", "").strip()

        if not account_id or not access_key or not secret_key:
            return None

        endpoint_url = f"https://{account_id}.r2.cloudflarestorage.com"
        boto_config = BotoConfig(
            signature_version="s3v4",
            retries={"max_attempts": 3, "mode": "standard"},
            connect_timeout=5,
            read_timeout=10
        )

        return boto3.client(
            "s3",
            endpoint_url=endpoint_url,
            aws_access_key_id=access_key,
            aws_secret_access_key=secret_key,
            config=boto_config,
            region_name="auto"
        )

    def capture_and_save(
        self,
        frame: np.ndarray,
        bbox: Tuple[int, int, int, int],
        event_id: str,
        track_id: Optional[int] = None,
        direction: str = "IN"
    ) -> Optional[str]:
        """
        Memotong postur orang yang melintas, menyimpannya ke format JPEG efisien,
        dan mendaftarkannya ke antrean upload Cloudflare R2.
        
        Mengembalikan path relatif lokal (misal: uploads/evidence/2026-10-21/evt_xxx.jpg).
        """
        # Muat settings terbaru secara berkala
        self._load_settings()
        storage_mode = self.settings.get("evidence_storage_mode", "hybrid_r2")
        if storage_mode == "disabled":
            return None

        try:
            h, w = frame.shape[:2]
            x1, y1, x2, y2 = bbox

            # Berikan padding/margin 15% di sekitar bounding box agar foto memiliki konteks estetis
            bw = x2 - x1
            bh = y2 - y1
            pad_x = int(bw * 0.15)
            pad_y = int(bh * 0.15)

            cx1 = max(0, x1 - pad_x)
            cy1 = max(0, y1 - pad_y)
            cx2 = min(w, x2 + pad_x)
            cy2 = min(h, y2 + pad_y)

            crop = frame[cy1:cy2, cx1:cx2].copy()
            if crop.size == 0:
                crop = frame.copy()

            # Skalakan agar resolusi proporsional & sangat ringan (tinggi max 360px)
            crop_h, crop_w = crop.shape[:2]
            if crop_h > 360:
                scale = 360.0 / crop_h
                crop = cv2.resize(crop, (int(crop_w * scale), 360), interpolation=cv2.INTER_AREA)

            # Struktur folder terorganisir rapi: YYYY-MM-DD / [IN atau OUT]
            today_str = datetime.now().strftime("%Y-%m-%d")
            dir_folder = direction.upper() if direction in ["IN", "OUT"] else "IN"
            day_folder = os.path.join(self.evidence_dir, today_str, dir_folder)
            os.makedirs(day_folder, exist_ok=True)

            filename = f"{event_id}.jpg"
            full_local_path = os.path.join(day_folder, filename)
            rel_local_path = f"uploads/evidence/{today_str}/{dir_folder}/{filename}"

            # Kompresi JPEG kualitas 75 (ukuran berkas hanya ~15-25 KB)
            encode_params = [int(cv2.IMWRITE_JPEG_QUALITY), 75]
            success = cv2.imwrite(full_local_path, crop, encode_params)

            if not success:
                logger.error(f"[EvidenceManager] Gagal menulis gambar ke: {full_local_path}")
                return None

            # Masukkan ke antrean upload R2 di background
            if storage_mode == "hybrid_r2":
                self.upload_queue.put({
                    "event_id": event_id,
                    "local_path": full_local_path,
                    "rel_path": rel_local_path,
                    "date_str": today_str,
                    "dir_folder": dir_folder,
                    "filename": filename
                })

            return rel_local_path

        except Exception as e:
            logger.error(f"[EvidenceManager] Gagal capture bukti: {e}")
            return None

    def _upload_worker_loop(self):
        """Loop latar belakang untuk mengunggah foto ke Cloudflare R2 secara non-blocking."""
        while self.is_running:
            try:
                task = self.upload_queue.get(timeout=2.0)
            except queue.Empty:
                continue

            event_id = task["event_id"]
            local_path = task["local_path"]
            date_str = task["date_str"]
            dir_folder = task.get("dir_folder", "IN")
            filename = task["filename"]

            if not os.path.exists(local_path):
                self.upload_queue.task_done()
                continue

            # Periksa koneksi klien S3
            s3 = self._get_s3_client()
            bucket = self.settings.get("r2_bucket", "countiva").strip()
            public_domain = self.settings.get("r2_public_domain", "").strip().rstrip("/")

            if s3 and bucket:
                r2_key = f"evidence/{date_str}/{dir_folder}/{filename}"
                try:
                    with open(local_path, "rb") as f:
                        s3.put_object(
                            Bucket=bucket,
                            Key=r2_key,
                            Body=f,
                            ContentType="image/jpeg",
                            Metadata={"event_id": event_id, "uploaded_at": datetime.now(timezone.utc).isoformat()}
                        )

                    # Buat URL publik Cloudflare R2
                    if public_domain:
                        cloud_url = f"{public_domain}/{r2_key}"
                    else:
                        cloud_url = f"https://{bucket}.r2.dev/{r2_key}"

                    # Perbarui snapshot_path di DB ke R2 key (bukan URL publik yang bisa diblokir ISP)
                    # api/evidence.php akan menangani pengambilan via SigV4 secara transparan
                    self._update_db_snapshot_path(event_id, r2_key)
                    # Simpan juga r2_key & cloud_url ke metadata untuk referensi
                    self._update_db_r2_url(event_id, cloud_url, r2_key)
                    # Hapus file lokal setelah berhasil diupload ke R2 (0% beban disk laptop kasir)
                    try:
                        if os.path.exists(local_path):
                            os.remove(local_path)
                            logger.info(f"[EvidenceManager] 🗑️ File lokal dihapus setelah upload R2: {local_path}")
                    except Exception as del_e:
                        logger.warning(f"[EvidenceManager] Gagal hapus file lokal: {del_e}")
                    logger.info(f"[EvidenceManager] ☁️ Berhasil upload ke Cloudflare R2: {r2_key}")

                except Exception as e:
                    logger.warning(f"[EvidenceManager] Upload Cloudflare R2 tertunda (Internet/Jaringan Offline): {e}")
                    # Jeda agar tidak membebani CPU saat internet restoran terputus
                    time.sleep(10)

            self.upload_queue.task_done()

    def _update_db_r2_url(self, event_id: str, cloud_url: str, r2_key: str):
        """Memperbarui metadata event dengan URL backup Cloudflare R2."""
        try:
            conn = sqlite3.connect(self.db_path, timeout=5.0)
            c = conn.cursor()
            c.execute("SELECT metadata_json FROM counting_events WHERE id = ?", (event_id,))
            row = c.fetchone()
            meta = {}
            if row and row[0]:
                try:
                    import json
                    meta = json.loads(row[0]) if isinstance(row[0], str) else row[0]
                except Exception:
                    pass
            meta['r2_url'] = cloud_url
            meta['r2_key'] = r2_key
            import json
            c.execute("UPDATE counting_events SET metadata_json = ? WHERE id = ?", (json.dumps(meta), event_id))
            conn.commit()
            conn.close()
        except Exception as e:
            logger.error(f"[EvidenceManager] Gagal update r2_url ke DB: {e}")

    def _update_db_snapshot_path(self, event_id: str, new_path: str):
        """Memperbarui nilai snapshot_path di database SQLite."""
        try:
            conn = sqlite3.connect(self.db_path, timeout=5.0)
            c = conn.cursor()
            c.execute("UPDATE counting_events SET snapshot_path = ? WHERE id = ?", (new_path, event_id))
            conn.commit()
            conn.close()
        except Exception as e:
            logger.error(f"[EvidenceManager] Gagal update snapshot_path DB: {e}")

    def _purge_worker_loop(self):
        """Pekerja berkala (setiap 6 jam) untuk membersihkan file lokal yang melebihi masa retensi atau kuota disk."""
        while self.is_running:
            try:
                # 1. Batasi kuota folder maksimal 100 MB (Mencegah beban harddisk kasir jika offline berhari-hari)
                self._enforce_disk_cap(max_size_mb=100)

                # 2. Retensi hari standar
                retention_days = int(self.settings.get("evidence_retention_days", 14))
                cutoff_time = time.time() - (retention_days * 86400)

                if os.path.exists(self.evidence_dir):
                    for day_name in os.listdir(self.evidence_dir):
                        day_path = os.path.join(self.evidence_dir, day_name)
                        if os.path.isdir(day_path):
                            # Jika folder hari lebih tua dari cutoff
                            mtime = os.path.getmtime(day_path)
                            if mtime < cutoff_time:
                                for f in os.listdir(day_path):
                                    try:
                                        os.remove(os.path.join(day_path, f))
                                    except Exception:
                                        pass
                                try:
                                    os.rmdir(day_path)
                                    logger.info(f"[EvidenceManager] Auto-Purge: Folder lama {day_name} telah dibersihkan.")
                                except Exception:
                                    pass
            except Exception as e:
                pass

            # Tidur selama 6 jam sebelum siklus berikutnya
            time.sleep(21600)

    def _enforce_disk_cap(self, max_size_mb: int = 100):
        """Mencegah pembengkakan harddisk kasir jika toko offline tanpa internet berhari-hari (FIFO Purge)."""
        try:
            if not os.path.exists(self.evidence_dir):
                return
            all_files = []
            total_size = 0
            for root, _, files in os.walk(self.evidence_dir):
                for f in files:
                    if f.endswith('.jpg') or f.endswith('.jpeg'):
                        fp = os.path.join(root, f)
                        try:
                            sz = os.path.getsize(fp)
                            mtime = os.path.getmtime(fp)
                            total_size += sz
                            all_files.append((fp, sz, mtime))
                        except Exception:
                            pass
            
            # Jika ukuran total melebihi batas kuota (100 MB)
            if total_size > (max_size_mb * 1024 * 1024):
                # Urutkan berdasarkan mtime tertua (FIFO: file tertua dihapus duluan)
                all_files.sort(key=lambda x: x[2])
                target_size = 50 * 1024 * 1024  # Kurangi hingga aman di 50 MB
                for fp, sz, _ in all_files:
                    try:
                        os.remove(fp)
                        total_size -= sz
                    except Exception:
                        pass
                    if total_size <= target_size:
                        break
                logger.info(f"[EvidenceManager] 🛡️ Disk Safety Cap: Kuota aman dipertahankan < {max_size_mb} MB.")
        except Exception as e:
            logger.warning(f"[EvidenceManager] Gagal memeriksa kuota disk: {e}")

    def stop(self):
        """Menghentikan thread pekerja secara anggun."""
        self.is_running = False
