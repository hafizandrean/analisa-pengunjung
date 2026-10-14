from typing import Tuple, Optional, Dict, Any
import numpy as np


class VirtualCountingLine:
    """
    Logika evaluasi penyeberangan garis virtual dengan pencegahan double counting:
    - Mendukung garis horizontal, vertikal, maupun diagonal berkoordinat fleksibel (x1, y1) ke (x2, y2).
    - Hysteresis buffer zone (offset) untuk mencegah fluktuasi titik centroid di sekitar garis.
    - Status state per-track ID untuk memastikan 1 orang hanya dihitung 1 kali per peristiwa melintas.
    """

    def __init__(
        self,
        pt1: Tuple[int, int] = (40, 184),
        pt2: Tuple[int, int] = (440, 184),
        direction_in: str = "down",
        offset: int = 25,
        cooldown_frames: int = 30
    ):
        self.x1, self.y1 = pt1
        self.x2, self.y2 = pt2
        self.direction_in = direction_in.lower()  # 'down', 'up', 'left', 'right'
        self.offset = offset
        self.cooldown_frames = cooldown_frames

        # Vektor arah garis AB
        self.dx = self.x2 - self.x1
        self.dy = self.y2 - self.y1
        self.line_length = np.sqrt(self.dx ** 2 + self.dy ** 2)

        # Status pelacakan per track_id:
        # {track_id: {"counted_dir": "IN"|"OUT", "counted_at_frame": int, "initial_side": int}}
        self.track_states: Dict[int, Dict[str, Any]] = {}

    def update_coordinates(self, pt1: Tuple[int, int], pt2: Tuple[int, int], direction_in: Optional[str] = None, offset: Optional[int] = None):
        """Memperbarui koordinat garis virtual secara dinamis dari konfigurasi API/kamera."""
        self.x1, self.y1 = pt1
        self.x2, self.y2 = pt2
        self.dx = self.x2 - self.x1
        self.dy = self.y2 - self.y1
        self.line_length = np.sqrt(self.dx ** 2 + self.dy ** 2)
        if direction_in:
            self.direction_in = direction_in.lower()
        if offset is not None:
            self.offset = offset

    def _cross_product_side(self, px: float, py: float) -> float:
        """
        Menghitung posisi titik (px, py) terhadap garis berarah (x1, y1) -> (x2, y2).
        Nilai positif = satu sisi (misal: atas/kiri), Nilai negatif = sisi sebaliknya.
        """
        return (self.x2 - self.x1) * (py - self.y1) - (self.y2 - self.y1) * (px - self.x1)

    def _is_near_line_segment(self, px: float, py: float) -> bool:
        """Mengecek apakah titik berada dalam proyeksi bounding range segmen garis."""
        min_x = min(self.x1, self.x2) - self.offset
        max_x = max(self.x1, self.x2) + self.offset
        min_y = min(self.y1, self.y2) - self.offset
        max_y = max(self.y1, self.y2) + self.offset
        return min_x <= px <= max_x and min_y <= py <= max_y

    def evaluate_crossing(
        self,
        track_id: int,
        curr_centroid: Tuple[int, int],
        prev_centroid: Tuple[int, int],
        current_frame: int
    ) -> Optional[str]:
        """
        Mengevaluasi apakah perpindahan dari prev_centroid ke curr_centroid memotong garis virtual.
        Mengembalikan 'IN', 'OUT', atau None jika tidak ada penyeberangan valid.
        """
        cx, cy = curr_centroid
        px, py = prev_centroid

        # Jika objek tidak berada di sekitar bentang garis, lewati
        if not self._is_near_line_segment(cx, cy) and not self._is_near_line_segment(px, py):
            return None

        # Hitung sisi garis untuk posisi sebelumnya dan saat ini
        prev_side = self._cross_product_side(px, py)
        curr_side = self._cross_product_side(cx, py_curr := cy)

        # Inisialisasi state jika track baru terlihat
        if track_id not in self.track_states:
            self.track_states[track_id] = {
                "counted_dir": None,
                "counted_at_frame": -999,
                "initial_side": 1 if prev_side >= 0 else -1
            }

        state = self.track_states[track_id]

        # Pencegahan double counting berbasis cooldown frame
        if current_frame - state["counted_at_frame"] < self.cooldown_frames:
            return None

        # Deteksi perpotongan tanda sisi (sign transition crossing)
        # Sisi berganti tanda jika prev_side * curr_side < 0
        has_crossed = (prev_side * curr_side < 0)

        # Untuk garis horizontal (dy = 0), kita juga dapat memeriksa transisi sumbu Y secara tegas
        if abs(self.dy) < 5:
            line_y = (self.y1 + self.y2) / 2
            # Periksa apakah lintasan melintasi line_y
            crossed_horizontal = (py <= line_y <= cy) or (cy <= line_y <= py)
            if crossed_horizontal and (abs(cy - py) > 1):
                has_crossed = True

        if not has_crossed:
            return None

        # Tentukan arah penyeberangan (IN atau OUT)
        direction: Optional[str] = None

        if self.direction_in == "down":
            # Bergerak ke bawah (Y membesar): MASUK (IN), Bergerak ke atas (Y mengecil): KELUAR (OUT)
            if cy > py:
                direction = "IN"
            elif cy < py:
                direction = "OUT"
        elif self.direction_in == "up":
            # Bergerak ke atas: MASUK (IN), Bergerak ke bawah: KELUAR (OUT)
            if cy < py:
                direction = "IN"
            elif cy > py:
                direction = "OUT"
        else:
            # Berbasis orientasi cross product umum
            if prev_side < 0 and curr_side > 0:
                direction = "IN"
            elif prev_side > 0 and curr_side < 0:
                direction = "OUT"

        if direction is None:
            return None

        # Cegah penghitungan ulang arah yang sama untuk person yang sama
        if state["counted_dir"] == direction:
            return None

        # Catat peristiwa sukses dihitung
        state["counted_dir"] = direction
        state["counted_at_frame"] = current_frame

        return direction

    def cleanup_old_tracks(self, active_track_ids: set):
        """Menghapus track yang sudah tidak aktif untuk menjaga efisiensi memori."""
        to_remove = [tid for tid in self.track_states if tid not in active_track_ids]
        for tid in to_remove:
            del self.track_states[tid]
