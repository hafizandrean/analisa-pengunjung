# Pipeline Computer Vision & Evaluasi Ground-Truth

## Sistem Penghitungan Pengunjung Ayam Bang Jungko

### 1. Arsitektur Pipeline Computer Vision

Pipeline pemrosesan video computer vision dirancang modular dan terpisah dari siklus hidup API backend guna menjamin inferensi berkecepatan tinggi tanpa membebani layanan web:

```
[Sumber Video / File MP4] 
         │
         ▼
[Perekaman Frame (OpenCV)]
         │
         ▼
[YOLOv8 People Detector (yolov8n.pt)] ──> Filter Class 0 ('person') & Confidence >= 0.30
         │
         ▼
[ByteTrack Multi-Object Tracker]      ──> Menghasilkan track_id unik & riwayat lintasan centroid
         │
         ▼
[Virtual Counting Line Crossing]      ──> Evaluasi vektor crossing, hysteresis offset & debouncing
         │
         ▼
[Backend Event Dispatcher]            ──> Antrian thread asinkron ke POST /api/v1/events
```

---

### 2. Logika Pencegahan Duplikasi (Anti-Double Counting)

Untuk mencegah penghitungan berulang akibat getaran frame (*jitter*), seseorang yang diam di atas garis, atau osilasi titik centroid:

1. **Hysteresis Buffer Zone (Offset)**:
   Sebuah zona penyangga selebar $\pm \text{offset}$ pixel (default 25px) diterapkan di sekitar garis virtual. Perpotongan hanya dihitung bila objek benar-benar melintasi batas atas ke batas bawah atau sebaliknya.
2. **State Machine per Track ID**:
   Setiap `track_id` memiliki status `counted_dir` (`IN` atau `OUT`). Sekali objek terdeteksi melintas dalam arah `IN`, statusnya ditandai sehingga frame-frame berikutnya dari objek yang sama tidak akan memicu penambahan hitungan lagi.
3. **Debounce Frame Cooldown**:
   Diterapkan batas cooldown minimum frame (default 30 frame = ~1 detik) sebelum perubahan status pada objek yang sama diizinkan dievaluasi kembali.

---

### 3. Panduan Menjalankan Worker Vision (Demo Video Workflow)

#### A. Persiapan Backend API
Pastikan backend FastAPI aktif untuk menerima event crossing:
```powershell
cd d:\Documents\GitHub\analisa-pengunjung\backend
uvicorn app.main:app --host 127.0.0.1 --port 8000
```

#### B. Menjalankan Worker Pemrosesan Video Lokal
Jalankan worker menggunakan modul CLI:
```powershell
cd d:\Documents\GitHub\analisa-pengunjung

# Menjalankan pemrosesan pada video sample.mp4
python -m vision.cli --video "video sample.mp4" --line-y 215 --dir-in down --start-frame 750 --max-frames 200
```

#### Argumen CLI yang Tersedia:
- `--video`: Path berkas video lokal (default: `video sample.mp4`).
- `--api`: Endpoint REST API backend (default: `http://127.0.0.1:8000/api/v1`).
- `--line-y`: Posisi koordinat Y garis virtual horizontal (sesuai resolusi video).
- `--dir-in`: Arah pergerakan yang dianggap MASUK (`down` atau `up`).
- `--start-frame`: Nomor frame awal untuk pengujian segmen spesifik.
- `--max-frames`: Batas maksimal frame uji coba.
- `--show`: Menampilkan jendela pratinjau visual OpenCV dengan HUD anotasi.

---

### 4. Protokol Evaluasi Akurasi & Ground-Truth Manual

> **Catatan Kejujuran Ilmiah (Ground-Truth Statement):**  
> Sistem **tidak mengklaim akurasi 100% di dunia nyata** tanpa pengujian komparasi terhadap data hitungan manual (*ground-truth*). Evaluasi formal sistem dilakukan dengan membandingkan hasil otomatis model terhadap pencatatan manual operator manusia.

#### Metrik Evaluasi:
1. **True Positive ($TP$)**: Orang yang melintasi garis dan berhasil dihitung dengan benar.
2. **False Positive ($FP$)**: Objek bukan orang yang terhitung, atau 1 orang yang terhitung lebih dari 1 kali (double count).
3. **False Negative ($FN$)**: Orang melintas namun gagal terdeteksi oleh model (missed detection).

$$\text{Precision} = \frac{TP}{TP + FP}, \quad \text{Recall} = \frac{TP}{TP + FN}$$
$$\text{Counting Accuracy (\%)} = 100\% - \left(\frac{|\text{Hitungan Otomatis} - \text{Ground Truth}|}{\text{Ground Truth}} \times 100\%\right)$$
