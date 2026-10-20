# Arsitektur Sistem — Ayam Bang Jungko

## Sistem Penghitungan Pengunjung untuk Analisa Pola Keramaian Berbasis CCTV

### 1. Gambaran Umum Arsitektur (Hybrid PHP Native + Python AI)

Sistem ini menerapkan arsitektur *hybrid CCTV analytics*:
- **Edge / Worker (Python)**: Inferensi video real-time (YOLOv8 + ByteTrack) membaca feed CCTV/video sampel dan mendeteksi penyeberangan garis virtual (crossing logic).
- **Web App & REST API (PHP Native)**: Menangani antarmuka web dashboard, kalkulasi analitik pola keramaian, ekspor laporan spreadsheet Excel (.xls) dan dokumen cetak/PDF, serta penyimpanan basis data terstruktur via PDO.

```mermaid
flowchart TD
    subgraph VideoSource["1. Sumber Masukan Video"]
        CCTV["CCTV IP (RTSP Stream)"]
        File["Video Sampel (MP4 Rekaman)"]
    end

    subgraph VisionPipeline["2. Pipeline Computer Vision (Python Worker)"]
        Frame["Perekaman Frame (OpenCV)"]
        YOLO["Deteksi Orang (YOLOv8)"]
        Tracker["Pelacakan Objek (ByteTrack)"]
        Crossing["Evaluasi Garis Virtual (Crossing Logic)"]
    end

    subgraph BackendAPI["3. Backend API (PHP Native)"]
        Ingest["Event Ingestion Endpoint (/api/events.php)"]
        AnalyticsEngine["Kalkulasi Pola Keramaian (/api/analytics.php)"]
        DashboardEngine["Metrik Dashboard (/api/dashboard.php)"]
        ExportEngine["Laporan Excel & PDF (/api/export_excel.php, print_report.php)"]
    end

    subgraph Database["4. Basis Data (PDO MySQL / SQLite)"]
        DB[(users, cameras, counting_events, system_logs)]
    end

    subgraph Frontend["5. Antarmuka Web (PHP Native + Tailwind + Chart.js)"]
        Dash["Dashboard Utama (index.php)"]
        AnalyticsView["Analisa Pola Keramaian (analytics.php)"]
        HistoryView["Riwayat Hitungan & Ekspor Excel/PDF (history.php)"]
        SettingsView["Editor Garis Virtual Canvas (cameras.php)"]
        StatusView["Audit Trail & Status Sistem (system.php)"]
    end

    VideoSource --> VisionPipeline
    VisionPipeline -- "HTTP POST JSON" --> Ingest
    Ingest --> DB
    DB --> AnalyticsEngine
    DB --> DashboardEngine
    DB --> ExportEngine
    AnalyticsEngine --> AnalyticsView
    DashboardEngine --> Dash
    ExportEngine --> HistoryView
```

### 2. Keunggulan Arsitektur Ini
1. **Performa Tinggi**: Modul AI berbasis Python memanfaatkan GPU/CUDA dan PyTorch untuk kecepatan deteksi real-time.
2. **Ringan & Portabel**: Antarmuka web PHP Native tidak memerlukan kompilasi Node.js, `npm install`, atau dependencies besar; langsung dapat dijalankan di web server bawaan PHP (`php -S`) atau XAMPP.
3. **Privasi Pelanggan**: Sistem hanya mencatat koordinat lintasan (*centroid bounding box*) dan arah masuk/keluar, tanpa menyimpan data biometrik maupun rekaman wajah pengunjung.
