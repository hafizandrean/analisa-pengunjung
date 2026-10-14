import os
from typing import List, Dict, Any, Tuple
import numpy as np

# Pastikan Ultralytics tidak mencoba memeriksa koneksi internet saat runtime
os.environ["YOLO_OFFLINE"] = "true"
os.environ["YOLO_VERBOSE"] = "false"

from ultralytics import YOLO


class YOLOPeopleDetector:
    """Modul pendeteksi orang berbasis model YOLOv8."""

    def __init__(self, model_path: str = "vision/models/yolov8n.pt", conf: float = 0.30, iou: float = 0.45):
        if not os.path.exists(model_path):
            raise FileNotFoundError(f"Berkas model YOLO tidak ditemukan di: {model_path}")
        
        self.model_path = model_path
        self.conf = conf
        self.iou = iou
        self.model = YOLO(model_path)
        # Class 0 pada COCO dataset adalah 'person'
        self.target_class_id = 0

    def detect(self, frame: np.ndarray) -> List[Dict[str, Any]]:
        """Mendeteksi objek manusia pada frame dan mengembalikan bounding box serta confidence."""
        results = self.model.predict(
            source=frame,
            classes=[self.target_class_id],
            conf=self.conf,
            iou=self.iou,
            verbose=False
        )

        detections = []
        if not results or len(results) == 0:
            return detections

        boxes = results[0].boxes
        if boxes is None:
            return detections

        for box in boxes:
            xyxy = box.xyxy[0].cpu().numpy()
            conf = float(box.conf[0].cpu().numpy())
            cls_id = int(box.cls[0].cpu().numpy())

            if cls_id == self.target_class_id:
                x1, y1, x2, y2 = int(xyxy[0]), int(xyxy[1]), int(xyxy[2]), int(xyxy[3])
                cx = (x1 + x2) // 2
                cy = (y1 + y2) // 2
                detections.append({
                    "bbox": (x1, y1, x2, y2),
                    "centroid": (cx, cy),
                    "confidence": conf,
                    "class_id": cls_id
                })

        return detections
