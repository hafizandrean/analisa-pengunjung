# Arsitektur Sistem — Ayam Bang Jungko

## Sistem Penghitungan Pengunjung untuk Analisa Pola Keramaian Berbasis CCTV

### 1. Gambaran Umum Arsitektur (Hybrid Edge-Cloud)

Sistem ini menerapkan arsitektur *hybrid CCTV analytics*. Pemrosesan inferensi video (deteksi orang dan tracking penyeberangan garis virtual) dijalankan secara lokal (edge/worker), sementara data agregat penyeberangan (counting events) dikirim secara asinkron ke REST API backend untuk disimpan di PostgreSQL dan dianalisis pada dashboard web.

```mermaid
flowchart TD
    subgraph VideoSource["1. Sumber Masukan Video"]
        CCTV["CCTV IP (RTSP Stream)"]
        File["Video Sampel (MP4 Rekaman)"]
    end

    subgraph VisionPipeline["2. Pipeline Computer Vision (Worker)"]
        Frame["Perekaman Frame (OpenCV)"]
        YOLO["Deteksi Orang (YOLOv8)"]
        Tracker["Pelacakan Objek (ByteTrack)"]
        Crossing["Evaluasi Garis Virtual (Crossing Logic)"]
    end

    subgraph BackendAPI["3. Backend API (FastAPI)"]
        Ingest["Event Ingestion Endpoint (/api/v1/events)"]
        AnalyticsEngine["Mesin Agregasi & Analitik Keramaian"]
        AuthSecurity["Autentikasi JWT & Masking Kredensial"]
    end

    subgraph Database["4. Basis Data (PostgreSQL)"]
        DB[(users, cameras, sessions, counting_events, system_logs)]
    end

    subgraph Frontend["5. Antarmuka Web (React + TS + Tailwind)"]
        Dash["Ringkasan Dashboard Real-Time"]
        AnalyticsView["Analisa Pola Keramaian & Jam Sibuk"]
        HistoryView["Riwayat Hitungan & Ekspor CSV"]
        SettingsView["Editor Garis Virtual & Sumber Video"]
        StatusView["Audit Trail & Status Sistem"]
    end

    CCTV --> Frame
    File --> Frame
    Frame --> YOLO
    YOLO --> Tracker
    Tracker --> Crossing
    Crossing -->|Payload Counting Event| Ingest
    Ingest --> DB
    AnalyticsEngine --> DB
    AuthSecurity --> DB
    Frontend <-->|REST API JSON| BackendAPI
```

---

### 2. Prinsip Privasi & Keamanan Data (Privacy-by-Design)

1. **Tanpa Pengenalan Wajah (No Face Recognition)**:
   Model deteksi hanya mengidentifikasi bounding box kelas `person` (orang) tanpa mengekstraksi fitur biometrik wajah.
2. **Tidak Menyimpan Foto Pengunjung**:
   Sistem tidak pernah menyimpan snapshot frame beresolusi tinggi atau foto pengunjung ke dalam basis data; hanya koordinat matematika penyeberangan, timestamp, arah (IN/OUT), dan ID pelacak yang disimpan.
3. **Masking Kredensial CCTV (RTSP Stream)**:
   URL RTSP yang memuat kredensial `rtsp://user:pass@host` disamarkan menjadi `rtsp://***:***@host` pada layer REST API sebelum disajikan ke antarmuka pengguna.
4. **Isolasi Data Demo vs Data Riil**:
   Setiap event memiliki atribut eksplisit `source_mode` (`DEMO`, `VIDEO`, atau `LIVE`). Data simulasi demo tidak pernah dicampur secara diam-diam dengan hasil rekaman CCTV riil.

---

### 3. Alur Deteksi & Penghitungan (Line-Crossing Detection)

```mermaid
sequenceDiagram
    autonumber
    participant V as Sumber Video
    participant CV as Worker CV (YOLOv8 + ByteTrack)
    participant API as FastAPI Backend
    participant DB as PostgreSQL
    participant UI as Web Dashboard

    V->>CV: Stream frame video (1280x720 @ 25 FPS)
    CV->>CV: Deteksi Bounding Box Orang (Confidence >= 0.35)
    CV->>CV: Asosiasi Track ID objek lintas frame (ByteTrack)
    CV->>CV: Hitung perpindahan centroid (cx, cy) terhadap garis virtual
    alt Melintas Garis ke Bawah (Y_start -> Y_end)
        CV->>API: POST /api/v1/events (direction='IN', track_id=X)
    else Melintas Garis ke Atas (Y_end -> Y_start)
        CV->>API: POST /api/v1/events (direction='OUT', track_id=Y)
    end
    API->>DB: INSERT INTO counting_events (...)
    UI->>API: GET /api/v1/dashboard/summary & /analytics/hourly
    API->>UI: Kembalikan metrik agregasi terkini
```

---

### 4. Struktur Monorepo

```
analisa-pengunjung/
├── backend/            # FastAPI REST API, Pydantic schemas, Auth, Endpoints, Pytest
│   ├── app/
│   │   ├── api/v1/     # Endpoint modular (/health, /dashboard, /analytics, /cameras, dll.)
│   │   ├── core/       # Konfigurasi aplikasi & CORS
│   │   ├── schemas/    # Skema validasi Pydantic
│   │   └── main.py     # Titik masuk FastAPI
│   └── tests/          # Pengujian backend otomatis
├── frontend/           # React 18, TypeScript, Vite, Tailwind CSS, Recharts
│   ├── src/
│   │   ├── components/ # Komponen UI, Canvas garis virtual, Charts
│   │   ├── context/    # AuthContext (state manajemen autentikasi)
│   │   ├── pages/      # 5 Halaman utama + Login
│   │   ├── services/   # Client API terpusat & Demo Data Provider
│   │   └── types/      # Kontrak tipe TypeScript terpadu
│   └── tests/          # Pengujian vitest frontend
├── vision/             # Modul inferensi computer vision (YOLOv8, ByteTrack, Line Crossing)
├── database/           # PostgreSQL DDL schema & migrasi
├── docs/               # Dokumentasi arsitektur & desain basis data
├── docker-compose.yml  # Orkestrasi container Docker lokal
├── .env.example        # Templat variabel lingkungan
└── README.md           # Panduan instalasi dan penggunaan
```
