import os
import pytest
import numpy as np
from vision.detector import YOLOPeopleDetector
from vision.tracker import ByteTrackTracker


def test_yolo_model_loading_and_detection():
    model_path = "vision/models/yolov8n.pt"
    assert os.path.exists(model_path), "Model weights must exist locally"

    detector = YOLOPeopleDetector(model_path=model_path, conf=0.25)
    assert detector.model is not None

    # Test detection on synthetic blank image (height=368, width=480, 3 channels)
    blank_frame = np.zeros((368, 480, 3), dtype=np.uint8)
    detections = detector.detect(blank_frame)
    # Blank frame should have 0 detections
    assert isinstance(detections, list)
    assert len(detections) == 0


def test_tracker_initialization_and_track():
    tracker = ByteTrackTracker(
        model_path="vision/models/yolov8n.pt",
        conf=0.25
    )
    blank_frame = np.zeros((368, 480, 3), dtype=np.uint8)
    active_tracks = tracker.track(blank_frame)
    assert isinstance(active_tracks, list)
    assert len(active_tracks) == 0
