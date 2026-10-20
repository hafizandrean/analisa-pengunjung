"""
Runner Skrip Python AI Vision -> PHP Native Backend
Ayam Bang Jungko - Visitor Counting & Crowd Analytics

Skrip ini mendeteksi pengunjung melintasi garis virtual menggunakan model
YOLOv8 dan tracker ByteTrack, menyajikan MJPEG live streaming ke browser di port 8080,
dan mengirimkan event penyeberangan secara real-time ke API PHP Native:
http://127.0.0.1:8000/api/events.php
"""

import argparse
import sys
import os
import json
import sqlite3

# Tambahkan direktori root ke path Python
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from vision.config import VisionWorkerConfig
from vision.worker import VisionWorker


def get_camera_info_from_db(camera_id: str, db_path: str):
    """Mengambil konfigurasi kamera & koordinat garis langsung dari database."""
    if not os.path.exists(db_path):
        return None
    try:
        conn = sqlite3.connect(db_path, timeout=5.0)
        cursor = conn.cursor()
        cursor.execute("SELECT name, source_uri, line_coords FROM cameras WHERE id = ?", (camera_id,))
        row = cursor.fetchone()
        conn.close()
        if row:
            coords = json.loads(row[2]) if isinstance(row[2], str) else row[2]
            return {
                "name": row[0],
                "source_uri": row[1],
                "line_coords": coords
            }
    except Exception as e:
        print(f"[Warning] Gagal membaca DB kamera: {e}")
    return None


def main():
    db_default_path = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", "database", "analisa_pengunjung.sqlite"))

    parser = argparse.ArgumentParser(description="Ayam Bang Jungko AI Vision Worker -> PHP Native")
    parser.add_argument("--camera-id", type=str, default="cam-1", help="ID Kamera di database (misal: cam-1)")
    parser.add_argument("--video", type=str, default=None, help="Override path file video atau RTSP stream atau 0 untuk webcam")
    parser.add_argument("--api", type=str, default="http://127.0.0.1:8000/api/events.php", help="URL endpoint API PHP Native")
    parser.add_argument("--port", type=int, default=8080, help="Port HTTP server live video streaming (default: 8080)")
    parser.add_argument("--show", action="store_true", help="Tampilkan jendela visualisasi GUI OpenCV di desktop")
    parser.add_argument("--no-loop", action="store_true", help="Nonaktifkan looping video ketika mencapai akhir")
    parser.add_argument("--no-stream", action="store_true", help="Nonaktifkan HTTP streaming ke web dashboard")
    parser.add_argument("--max-frames", type=int, default=None, help="Batas maksimal frame yang diproses")

    args = parser.parse_args()

    # Ambil info kamera dari DB
    cam_info = get_camera_info_from_db(args.camera_id, db_default_path)
    video_source = args.video
    line_coords = {
        "x1": 120, "y1": 380,
        "x2": 1160, "y2": 380,
        "direction_in": "down",
        "offset": 35
    }

    if cam_info:
        if not video_source:
            video_source = cam_info["source_uri"]
        if cam_info.get("line_coords"):
            line_coords = cam_info["line_coords"]

    if not video_source:
        video_source = "samples/video_sample.mp4"

    # Jika video_source adalah '0' atau '1' (webcam)
    if video_source.isdigit():
        video_source = int(video_source)

    loop_video = not args.no_loop and not str(video_source).startswith("rtsp://") and not isinstance(video_source, int)

    print("=" * 70)
    print("🍗 Ayam Bang Jungko — AI Vision Worker & Live CCTV Streamer")
    print("=" * 70)
    print(f"Kamera ID        : {args.camera_id} ({cam_info.get('name', 'Kamera') if cam_info else 'Kamera'})")
    print(f"Sumber Video     : {video_source}")
    print(f"Garis Virtual    : ({line_coords.get('x1')},{line_coords.get('y1')}) -> ({line_coords.get('x2')},{line_coords.get('y2')}) [{line_coords.get('direction_in')}]")
    print(f"Target API PHP   : {args.api}")
    print(f"Live Stream Web  : {'Aktif di http://127.0.0.1:' + str(args.port) + '/video_feed' if not args.no_stream else 'Nonaktif'}")
    print(f"Looping Video    : {'Ya' if loop_video else 'Tidak'}")
    print(f"GUI Desktop      : {'Ya' if args.show else 'Tidak (Headless / Web-only)'}")
    print("=" * 70)

    # Konfigurasi worker
    config = VisionWorkerConfig(
        video_source=str(video_source) if not isinstance(video_source, int) else str(video_source),
        camera_id=args.camera_id,
        backend_api_url=args.api,
        show_preview=args.show,
        max_frames=args.max_frames,
        loop_video=loop_video,
        enable_stream=not args.no_stream,
        stream_port=args.port,
        line_coords=line_coords
    )

    worker = VisionWorker(config)
    try:
        summary = worker.run()
        print("\n" + "=" * 70)
        print("Pemrosesan Selesai!")
        print(f"Total Masuk (IN) : {summary.get('total_in', 0)}")
        print(f"Total Keluar (OUT): {summary.get('total_out', 0)}")
        print(f"Total Frame      : {summary.get('frames_processed', 0)}")
        print(f"Event Terkirim   : {worker.dispatcher.delivered_count}")
        print(f"Event Gagal      : {worker.dispatcher.failed_count}")
        print("=" * 70)
    except KeyboardInterrupt:
        print("\nWorker dihentikan oleh pengguna.")
    except Exception as e:
        print(f"\nError saat menjalankan worker: {e}")


if __name__ == "__main__":
    main()
