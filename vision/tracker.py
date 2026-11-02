from collections import deque
from typing import Dict, List, Any, Tuple, Optional
import numpy as np
from ultralytics import YOLO


class ByteTrackTracker:
    """Modul pelacakan objek orang lintas-frame menggunakan ByteTrack."""

    def __init__(
        self,
        model_path: str = "vision/models/yolov8n.pt",
        tracker_config: str = "vision/bytetrack_custom.yaml",
        conf: float = 0.25,
        iou: float = 0.45,
        max_history_len: int = 30
    ):
        self.model = YOLO(model_path)
        self.tracker_config = tracker_config
        self.conf = conf
        self.iou = iou
        self.max_history_len = max_history_len
        # Menyimpan jejak koordinat kaki dan centroid per track_id
        self.track_trajectories: Dict[int, deque] = {}      # foot anchor points
        self.centroid_trajectories: Dict[int, deque] = {}   # torso/chest centroid points
        # Menghitung frame tidak terdeteksi untuk pembersihan memori
        self.track_lost_count: Dict[int, int] = {}

    def track(self, frame: np.ndarray) -> List[Dict[str, Any]]:
        """Melacak orang pada frame dan mengembalikan list objek terlacak beserta track_id unik."""
        results = self.model.track(
            source=frame,
            persist=True,
            tracker=self.tracker_config,
            classes=[0],  # person
            conf=self.conf,
            iou=self.iou,
            imgsz=640,
            verbose=False
        )

        active_tracks = []
        current_active_ids = set()

        if not results or len(results) == 0:
            self._increment_lost_tracks(current_active_ids)
            return active_tracks

        boxes = results[0].boxes
        if boxes is None or boxes.id is None:
            self._increment_lost_tracks(current_active_ids)
            return active_tracks

        for box in boxes:
            if box.id is None:
                continue

            track_id = int(box.id[0].cpu().numpy())
            xyxy = box.xyxy[0].cpu().numpy()
            conf = float(box.conf[0].cpu().numpy())

            x1, y1, x2, y2 = int(xyxy[0]), int(xyxy[1]), int(xyxy[2]), int(xyxy[3])
            
            # Filter objek terlalu kecil (noise / bayangan) dengan toleransi adaptif
            if (x2 - x1) < 12 or (y2 - y1) < 24:
                continue

            cx = (x1 + x2) // 2
            cy = (y1 + y2) // 2
            # Titik tumpu kaki di lantai (Bottom-Center Foot Anchor)
            foot_x = cx
            foot_y = y2

            current_active_ids.add(track_id)

            if track_id not in self.track_trajectories:
                self.track_trajectories[track_id] = deque(maxlen=self.max_history_len)
                self.centroid_trajectories[track_id] = deque(maxlen=self.max_history_len)
            
            prev_foot = self.track_trajectories[track_id][-1] if len(self.track_trajectories[track_id]) > 0 else (foot_x, foot_y)
            prev_centroid = self.centroid_trajectories[track_id][-1] if len(self.centroid_trajectories[track_id]) > 0 else (cx, cy)

            self.track_trajectories[track_id].append((foot_x, foot_y))
            self.centroid_trajectories[track_id].append((cx, cy))
            self.track_lost_count[track_id] = 0

            active_tracks.append({
                "track_id": track_id,
                "bbox": (x1, y1, x2, y2),
                "centroid": (cx, cy),
                "foot_point": (foot_x, foot_y),
                "prev_centroid": prev_centroid,
                "prev_foot_point": prev_foot,
                "confidence": conf,
                "trajectory": list(self.centroid_trajectories[track_id]),
                "foot_trajectory": list(self.track_trajectories[track_id])
            })

        self._increment_lost_tracks(current_active_ids)
        return active_tracks

    def _increment_lost_tracks(self, active_ids: set, max_lost_frames: int = 45):
        """Membersihkan memori track yang sudah keluar dari jangkauan kamera."""
        to_delete = []
        for tid in self.track_trajectories:
            if tid not in active_ids:
                self.track_lost_count[tid] = self.track_lost_count.get(tid, 0) + 1
                if self.track_lost_count[tid] > max_lost_frames:
                    to_delete.append(tid)

        for tid in to_delete:
            del self.track_trajectories[tid]
            if tid in self.centroid_trajectories:
                del self.centroid_trajectories[tid]
            if tid in self.track_lost_count:
                del self.track_lost_count[tid]
