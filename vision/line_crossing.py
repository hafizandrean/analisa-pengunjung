from typing import Tuple, Optional, Dict, Any, List
import numpy as np


def ccw(A: Tuple[float, float], B: Tuple[float, float], C: Tuple[float, float]) -> bool:
    """Mengecek apakah tiga titik berorientasi berlawanan arah jarum jam (Counter-Clockwise)."""
    return (C[1] - A[1]) * (B[0] - A[0]) > (B[1] - A[1]) * (C[0] - A[0])


def segments_intersect(
    A: Tuple[float, float],
    B: Tuple[float, float],
    C: Tuple[float, float],
    D: Tuple[float, float]
) -> bool:
    """Mengecek apakah segmen garis AB dan segmen gerak CD berpotongan secara geometris."""
    return (ccw(A, C, D) != ccw(B, C, D)) and (ccw(A, B, C) != ccw(A, B, D))


def point_line_side(p: Tuple[float, float], a: Tuple[float, float], b: Tuple[float, float]) -> float:
    """
    Menghitung cross product sisi titik p terhadap garis berarah a -> b.
    > 0: satu sisi, < 0: sisi sebaliknya
    """
    return (b[0] - a[0]) * (p[1] - a[1]) - (b[1] - a[1]) * (p[0] - a[0])


def point_to_segment_distance(p: Tuple[float, float], a: Tuple[float, float], b: Tuple[float, float]) -> float:
    """Jarak euclidean terpendek dari titik p ke segmen garis ab."""
    px, py = p
    ax, ay = a
    bx, by = b
    dx, dy = bx - ax, by - ay
    line_len_sq = dx * dx + dy * dy
    if line_len_sq == 0:
        return float(np.hypot(px - ax, py - ay))
    t = max(0.0, min(1.0, ((px - ax) * dx + (py - ay) * dy) / line_len_sq))
    proj_x = ax + t * dx
    proj_y = ay + t * dy
    return float(np.hypot(px - proj_x, py - proj_y))


class VirtualCountingLine:
    """
    Logika evaluasi penyeberangan garis virtual cerdas dengan pemisahan arah IN vs OUT berakurasi tinggi:
    - Perhitungan matematis perpotongan segmen garis (Segment-Segment Intersection)
    - Verifikasi Asal Geografis (Origin Side Transition) dari jejak lintasan historis (Trajectory History)
    - Kebal terhadap jitter/noise deteksi bounding box antar-frame tunggal
    - Evaluasi ganda: Centroid (badan/torso) dan Foot Anchor (kaki di lantai)
    - Toleransi endpoint margin: memperpanjang jangkauan garis agar pergerakan di tepi pintu tetap terhitung
    - State memory & cooldown: mencegah double counting pada orang yang sama
    """

    def __init__(
        self,
        pt1: Tuple[int, int] = (40, 184),
        pt2: Tuple[int, int] = (440, 184),
        direction_in: str = "down",
        offset: int = 35,
        cooldown_frames: int = 15
    ):
        self.direction_in = direction_in.lower()
        self.offset = offset
        self.cooldown_frames = cooldown_frames
        self.track_states: Dict[int, Dict[str, Any]] = {}
        self.update_coordinates(pt1, pt2, direction_in, offset)

    def update_coordinates(
        self,
        pt1: Tuple[int, int],
        pt2: Tuple[int, int],
        direction_in: Optional[str] = None,
        offset: Optional[int] = None
    ):
        """Memperbarui koordinat garis virtual dan memetakan sisi arah masuk (IN)."""
        self.x1, self.y1 = int(pt1[0]), int(pt1[1])
        self.x2, self.y2 = int(pt2[0]), int(pt2[1])
        self.p1 = (float(self.x1), float(self.y1))
        self.p2 = (float(self.x2), float(self.y2))

        self.dx = float(self.x2 - self.x1)
        self.dy = float(self.y2 - self.y1)
        self.line_length = float(np.hypot(self.dx, self.dy)) or 1.0

        if direction_in:
            self.direction_in = direction_in.lower()
        if offset is not None:
            self.offset = offset

        # Toleransi jangkauan lateral di sekitar garis
        self.tolerance_dist = max(35.0, float(self.offset))

        # Perluasan segmen garis sebesar 18% di kedua ujung agar orang yang melintas di tepi pintu tetap terhitung
        ext_ratio = 0.18
        self.ext_p1 = (self.x1 - self.dx * ext_ratio, self.y1 - self.dy * ext_ratio)
        self.ext_p2 = (self.x2 + self.dx * ext_ratio, self.y2 + self.dy * ext_ratio)

        # Vektor target arah MASUK (IN) yang dipilih pengguna di web UI
        if self.direction_in == "down":
            self.target_dir_vec = (0.0, 1.0)
        elif self.direction_in == "up":
            self.target_dir_vec = (0.0, -1.0)
        elif self.direction_in == "right":
            self.target_dir_vec = (1.0, 0.0)
        elif self.direction_in == "left":
            self.target_dir_vec = (-1.0, 0.0)
        else:
            self.target_dir_vec = (0.0, 1.0)

        # Kalibrasi sisi geometris mana yang merupakan sisi MASUK (IN Side):
        # Ambil titik tengah garis, lalu proyeksikan 50px ke arah target_dir_vec
        mid_x = (self.x1 + self.x2) / 2.0
        mid_y = (self.y1 + self.y2) / 2.0
        test_in_pt = (mid_x + self.target_dir_vec[0] * 50.0, mid_y + self.target_dir_vec[1] * 50.0)
        side_test = self._cross_product_side(test_in_pt[0], test_in_pt[1])
        self.in_side_sign = 1 if side_test > 0 else -1

    def _cross_product_side(self, px: float, py: float) -> float:
        """Menghitung posisi titik (px, py) terhadap garis berarah (x1, y1) -> (x2, y2)."""
        return (self.x2 - self.x1) * (py - self.y1) - (self.y2 - self.y1) * (px - self.x1)

    def _determine_direction(
        self,
        track_id: int,
        p_from: Tuple[float, float],
        p_to: Tuple[float, float],
        trajectory: Optional[List[Tuple[int, int]]] = None
    ) -> str:
        """
        Menentukan arah penyeberangan (IN atau OUT) dengan akurasi tinggi:
        1. Evaluasi transisi sisi geografis (Origin Side) dari jejak riwayat kemunculan orang.
        2. Vektor perpindahan bersih (Net Displacement) yang kebal terhadap jitter 1 frame.
        3. Fallback ke vektor pergerakan lokal jika riwayat sangat pendek.
        """
        curr_side = self._cross_product_side(p_to[0], p_to[1])

        # Ambil titik asal (origin) dari riwayat lintasan objek
        origin_pt = None
        anchor_pt = None
        if trajectory and len(trajectory) >= 2:
            origin_pt = (float(trajectory[0][0]), float(trajectory[0][1]))
            # Ambil titik anchor 5 - 10 frame sebelum crossing untuk memverifikasi arah mantap
            anchor_idx = max(0, len(trajectory) - min(8, len(trajectory)))
            anchor_pt = (float(trajectory[anchor_idx][0]), float(trajectory[anchor_idx][1]))
        else:
            origin_pt = p_from
            anchor_pt = p_from

        origin_side = self._cross_product_side(origin_pt[0], origin_pt[1])
        anchor_side = self._cross_product_side(anchor_pt[0], anchor_pt[1])

        # 1. Evaluasi Transisi Sisi Geografis (Outside <-> Inside)
        # Sisi IN adalah sisi di mana (side * self.in_side_sign > 0)
        # Sisi OUT adalah sisi di mana (side * self.in_side_sign < 0)
        is_now_on_in_side = (curr_side * self.in_side_sign > 0)
        came_from_out_side = (origin_side * self.in_side_sign < 0) or (anchor_side * self.in_side_sign < 0)

        is_now_on_out_side = (curr_side * self.in_side_sign < 0)
        came_from_in_side = (origin_side * self.in_side_sign > 0) or (anchor_side * self.in_side_sign > 0)

        if is_now_on_in_side and came_from_out_side:
            return "IN"
        elif is_now_on_out_side and came_from_in_side:
            return "OUT"

        # 2. Evaluasi Vektor Perpindahan Bersih (Net Displacement Vector)
        net_dx = p_to[0] - origin_pt[0]
        net_dy = p_to[1] - origin_pt[1]
        net_dist = np.hypot(net_dx, net_dy)

        if net_dist >= 8.0:
            dot = net_dx * self.target_dir_vec[0] + net_dy * self.target_dir_vec[1]
            if abs(dot) >= 1.0:
                return "IN" if dot > 0 else "OUT"

        # 3. Fallback ke vektor pergerakan lokal
        mx = p_to[0] - p_from[0]
        my = p_to[1] - p_from[1]
        local_dot = mx * self.target_dir_vec[0] + my * self.target_dir_vec[1]
        if abs(local_dot) < 0.5:
            if self.target_dir_vec[1] != 0:
                local_dot = my * self.target_dir_vec[1]
            else:
                local_dot = mx * self.target_dir_vec[0]

        return "IN" if local_dot > 0 else "OUT"

    def evaluate_crossing(
        self,
        track_id: int,
        curr_centroid: Tuple[int, int],
        prev_centroid: Tuple[int, int],
        current_frame: int,
        curr_foot: Optional[Tuple[int, int]] = None,
        prev_foot: Optional[Tuple[int, int]] = None,
        trajectory: Optional[List[Tuple[int, int]]] = None,
        foot_trajectory: Optional[List[Tuple[int, int]]] = None
    ) -> Optional[str]:
        """
        Mengevaluasi apakah orang melintasi garis virtual dan menentukan arah penyeberangan (IN atau OUT).
        """
        # Inisialisasi status per-track
        if track_id not in self.track_states:
            self.track_states[track_id] = {
                "counted_dir": None,
                "counted_at_frame": -999,
                "last_seen_frame": current_frame
            }

        state = self.track_states[track_id]
        state["last_seen_frame"] = current_frame

        # Cegah double counting berbasis cooldown frame
        if current_frame - state["counted_at_frame"] < self.cooldown_frames:
            return None

        # Kumpulkan pasangan perpindahan (prev, curr) yang akan diuji
        pairs_to_test: List[Tuple[Tuple[float, float], Tuple[float, float]]] = []

        # 1. Centroid (torso/badan)
        if prev_centroid and curr_centroid:
            pairs_to_test.append(((float(prev_centroid[0]), float(prev_centroid[1])),
                                  (float(curr_centroid[0]), float(curr_centroid[1]))))

        # 2. Foot anchor (kaki di lantai)
        if prev_foot and curr_foot:
            pairs_to_test.append(((float(prev_foot[0]), float(prev_foot[1])),
                                  (float(curr_foot[0]), float(curr_foot[1]))))

        # 3. Trajectory multi-step (cek titik dari 2, 4, 6 frame sebelumnya ke titik saat ini)
        if trajectory and len(trajectory) >= 3 and curr_centroid:
            for step_back in [2, 4, 6]:
                if len(trajectory) > step_back:
                    hist_pt = trajectory[-step_back - 1]
                    pairs_to_test.append(((float(hist_pt[0]), float(hist_pt[1])),
                                          (float(curr_centroid[0]), float(curr_centroid[1]))))

        if foot_trajectory and len(foot_trajectory) >= 3 and curr_foot:
            for step_back in [2, 4, 6]:
                if len(foot_trajectory) > step_back:
                    hist_f = foot_trajectory[-step_back - 1]
                    pairs_to_test.append(((float(hist_f[0]), float(hist_f[1])),
                                          (float(curr_foot[0]), float(curr_foot[1]))))

        # Uji setiap pasang pergerakan
        for p_from, p_to in pairs_to_test:
            # Skip jika pergerakan sangat kecil / noise
            if np.hypot(p_to[0] - p_from[0], p_to[1] - p_from[1]) < 1.0:
                continue

            # Uji perpotongan segmen gerak dengan garis virtual (asli maupun extended)
            intersects = (segments_intersect(self.p1, self.p2, p_from, p_to) or
                          segments_intersect(self.ext_p1, self.ext_p2, p_from, p_to))

            # Uji transisi sisi tanda cross product
            side_from = point_line_side(p_from, self.p1, self.p2)
            side_to = point_line_side(p_to, self.p1, self.p2)
            side_transition = (side_from * side_to < 0)

            # Cek apakah berada dalam jarak toleransi bentang garis
            near = (point_to_segment_distance(p_to, self.p1, self.p2) <= self.tolerance_dist or
                    point_to_segment_distance(p_from, self.p1, self.p2) <= self.tolerance_dist)

            if intersects or (side_transition and near):
                # Tentukan arah penyeberangan berdasarkan jejak historis objek
                direction = self._determine_direction(track_id, p_from, p_to, trajectory=trajectory)

                # Cegah hitung ulang arah yang sama untuk orang yang sama
                if state["counted_dir"] == direction:
                    continue

                # Berhasil dihitung!
                state["counted_dir"] = direction
                state["counted_at_frame"] = current_frame
                return direction

        return None

    def cleanup_old_tracks(self, active_track_ids: set, max_inactive_frames: int = 90):
        """Membersihkan memori track yang sudah tidak aktif dengan grace period aman."""
        to_remove = []
        for tid, st in self.track_states.items():
            if tid not in active_track_ids:
                if (st.get("last_seen_frame", 0) + max_inactive_frames) < (max(st.get("last_seen_frame", 0), 100)):
                    to_remove.append(tid)
        for tid in to_remove:
            del self.track_states[tid]
