import pytest
from vision.line_crossing import VirtualCountingLine


def test_horizontal_line_crossing_in():
    # Horizontal line at Y=200, X from 50 to 450, direction_in="down"
    line = VirtualCountingLine(pt1=(50, 200), pt2=(450, 200), direction_in="down", offset=20)

    # Person 1 moves from (200, 180) to (200, 220) -> crosses downward -> IN
    res = line.evaluate_crossing(
        track_id=1,
        curr_centroid=(200, 220),
        prev_centroid=(200, 180),
        current_frame=10
    )
    assert res == "IN"


def test_horizontal_line_crossing_out():
    line = VirtualCountingLine(pt1=(50, 200), pt2=(450, 200), direction_in="down", offset=20)

    # Person 2 moves from (200, 220) to (200, 180) -> crosses upward -> OUT
    res = line.evaluate_crossing(
        track_id=2,
        curr_centroid=(200, 180),
        prev_centroid=(200, 220),
        current_frame=10
    )
    assert res == "OUT"


def test_no_crossing_movement():
    line = VirtualCountingLine(pt1=(50, 200), pt2=(450, 200), direction_in="down", offset=20)

    # Person 3 moves horizontally above the line (200, 150) -> (250, 150)
    res = line.evaluate_crossing(
        track_id=3,
        curr_centroid=(250, 150),
        prev_centroid=(200, 150),
        current_frame=10
    )
    assert res is None


def test_duplicate_crossing_suppression():
    line = VirtualCountingLine(pt1=(50, 200), pt2=(450, 200), direction_in="down", offset=20, cooldown_frames=20)

    # Frame 10: Person 4 crosses downward -> IN
    res1 = line.evaluate_crossing(
        track_id=4,
        curr_centroid=(200, 210),
        prev_centroid=(200, 190),
        current_frame=10
    )
    assert res1 == "IN"

    # Frame 11: Person 4 continues downward to (200, 225) -> should NOT trigger duplicate count!
    res2 = line.evaluate_crossing(
        track_id=4,
        curr_centroid=(200, 225),
        prev_centroid=(200, 210),
        current_frame=11
    )
    assert res2 is None

    # Frame 15: Person 4 still in vicinity -> should NOT trigger duplicate count
    res3 = line.evaluate_crossing(
        track_id=4,
        curr_centroid=(200, 230),
        prev_centroid=(200, 225),
        current_frame=15
    )
    assert res3 is None


def test_diagonal_line_crossing():
    # Diagonal line from (100, 100) to (300, 300)
    line = VirtualCountingLine(pt1=(100, 100), pt2=(300, 300), direction_in="down", offset=30)

    # Move from one side (150, 250) to the other (250, 150)
    res = line.evaluate_crossing(
        track_id=5,
        curr_centroid=(250, 150),
        prev_centroid=(150, 250),
        current_frame=10
    )
    assert res in ["IN", "OUT"]
