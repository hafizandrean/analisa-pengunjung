import argparse
import sys
from vision.config import VisionWorkerConfig
from vision.worker import VisionWorker


def main():
    parser = argparse.ArgumentParser(
        description="Ayam Bang Jungko — CCTV Visitor Counting Computer Vision Worker (YOLOv8 + ByteTrack)"
    )
    parser.add_argument(
        "--video",
        type=str,
        default="video sample.mp4",
        help="Path ke file video sampel (default: 'video sample.mp4')"
    )
    parser.add_argument(
        "--api",
        type=str,
        default="http://127.0.0.1:8000/api/v1",
        help="URL REST API backend untuk pengiriman event (default: http://127.0.0.1:8000/api/v1)"
    )
    parser.add_argument(
        "--camera-id",
        type=str,
        default="c0000000-0000-0000-0000-000000000001",
        help="UUID Kamera sumber"
    )
    parser.add_argument(
        "--session-id",
        type=str,
        default="e0000000-0000-0000-0000-000000000001",
        help="UUID Sesi pemrosesan"
    )
    parser.add_argument(
        "--conf",
        type=float,
        default=0.30,
        help="Ambang batas kepercayaan (confidence threshold) YOLOv8"
    )
    parser.add_argument(
        "--line-y",
        type=int,
        default=184,
        help="Posisi Y garis virtual horizontal (default 184)"
    )
    parser.add_argument(
        "--dir-in",
        type=str,
        default="down",
        choices=["down", "up"],
        help="Arah masuk (IN): down atau up"
    )
    parser.add_argument(
        "--start-frame",
        type=int,
        default=0,
        help="Frame awal pemrosesan video (default: 0)"
    )
    parser.add_argument(
        "--max-frames",
        type=int,
        default=None,
        help="Batas maksimal frame untuk pengujian cepat"
    )
    parser.add_argument(
        "--show",
        action="store_true",
        help="Tampilkan pratinjau window OpenCV"
    )

    args = parser.parse_args()

    config = VisionWorkerConfig(
        video_source=args.video,
        backend_api_url=args.api,
        camera_id=args.camera_id,
        session_id=args.session_id,
        confidence_threshold=args.conf,
        show_preview=args.show,
        start_frame=args.start_frame,
        max_frames=args.max_frames,
        line_coords={
            "x1": 30,
            "y1": args.line_y,
            "x2": 450,
            "y2": args.line_y,
            "direction_in": args.dir_in,
            "offset": 25
        }
    )

    print("==================================================================")
    print("Ayam Bang Jungko — CV People Counter & Line Crossing Worker")
    print("==================================================================")
    print(f"Video Source : {config.video_source}")
    print(f"Backend API  : {config.backend_api_url}")
    print(f"Line Position: Y={args.line_y}, Direction IN: {args.dir_in}")
    print(f"Model Path   : {config.model_path}")
    print("------------------------------------------------------------------")

    worker = VisionWorker(config)
    summary = worker.run()

    print("\nProses selesai.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
