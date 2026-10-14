# Ayam Bang Jungko — CCTV Visitor Counting & Crowd Analytics

> **Capstone Design Project:**  
> *"Sistem Penghitungan Pengunjung untuk Analisa Pola Keramaian Berbasis CCTV untuk Usaha Ayam Bang Jungko"*

---

## 1. Ringkasan Proyek

Aplikasi analitik operasional berbasis web untuk membantu pemilik dan manajer operasional **Ayam Bang Jungko** memahami pola lalu lintas pengunjung restoran, mendeteksi jam-jam sibuk (*peak hours*), serta mengoptimalkan kesiapan staf dan meja tanpa menyimpan citra wajah pengunjung (*privacy-first*).

Sistem menggunakan arsitektur *hybrid edge-cloud analytics*:
- **Edge / Worker**: Inferensi deteksi manusia (YOLOv8) dan pelacakan lintasan (ByteTrack) berbasis garis virtual crossing.
- **REST API & Database**: Penyimpanan terstruktur kejadian melintas (*counting events*) dengan stempel waktu zona **Asia/Jakarta (WIB)**.
- **Web Dashboard**: Antarmuka SaaS modern untuk visualisasi distribusi keramaian per jam, tren mingguan, riwayat crossing, dan konfigurasi koordinat garis virtual.

---

## 2. Fitur Utama

- **Dashboard Real-Time**: Ringkasan total pengunjung hari ini (masuk & keluar), total minggu ini, rata-rata harian, dan jam tersibuk.
- **Analisa Pola Keramaian (Crowd Analytics)**: Identifikasi jam sibuk dengan status kepadatan (*PADAT*, *SEDANG*, *LENGANG*), evaluasi kontribusi trafik harian, dan perbandingan antar periode.
- **Riwayat Hitungan & Ekspor CSV**: Tabel interaktif berfilter (arah, kamera, kata kunci) dengan paginasi dan fitur unduh berkas CSV.
- **Editor Garis Virtual Interaktif**: Visualisasi canvas koordinat titik penyeberangan P1 `(x1, y1)` dan P2 `(x2, y2)` yang dapat dikonfigurasi langsung tanpa mengubah kode sumber.
- **Mode Demo Mandiri (Deterministic Demo Provider)**: Pratinjau langsung dashboard lengkap dengan metrik realistis pola operasional restoran kuliner Ayam Bang Jungko.
- **Privasi & Keamanan**: Tanpa fitur facial recognition, tidak menyimpan foto pengunjung, serta penyembunyian kredensial CCTV RTSP secara otomatis.

---

## 3. Struktur Monorepo

```
analisa-pengunjung/
├── backend/            # FastAPI REST API, Pydantic schemas, endpoints, pytest
│   ├── app/
│   │   ├── api/v1/     # Endpoint modular (/health, /dashboard, /analytics, dll.)
│   │   ├── core/       # Konfigurasi aplikasi & settings
│   │   ├── schemas/    # Pydantic v2 validation models
│   │   └── main.py     # Entry point FastAPI
│   ├── tests/          # Pytest backend tests
│   └── Dockerfile
├── frontend/           # React 18, TypeScript, Vite, Tailwind CSS, Recharts
│   ├── src/
│   │   ├── components/ # Komponen UI, Canvas garis virtual, Charts
│   │   ├── context/    # AuthContext & ProtectedRoute
│   │   ├── pages/      # Login, Dashboard, Analytics, History, Cameras, System
│   │   ├── services/   # Client API terpusat & Demo Data Provider
│   │   └── types/      # Kontrak tipe TypeScript terpadu
│   └── tests/          # Vitest suite
├── vision/             # Pipeline computer vision YOLOv8 + ByteTrack
├── database/           # DDL schema PostgreSQL & relasi ERD
├── docs/               # Dokumentasi arsitektur sistem & desain basis data
├── docker-compose.yml  # Orkestrasi Docker lokal
├── .env.example        # Templat variabel lingkungan
└── README.md           # Panduan penggunaan
```

---

## 4. Panduan Menjalankan Aplikasi

### Kebutuhan Sistem:
- **Node.js**: v18+ (Disarankan v20+)
- **Python**: 3.10+
- **Docker & Docker Compose** (opsional untuk container database PostgreSQL)

---

### A. Menjalankan Backend (FastAPI)

1. Buka terminal pada folder `backend`:
   ```bash
   cd backend
   ```
2. Pasang dependensi Python:
   ```bash
   pip install -r requirements.txt
   ```
3. Jalankan server FastAPI:
   ```bash
   uvicorn app.main:app --reload --host 127.0.0.1 --port 8000
   ```
4. Akses dokumentasi interaktif API:
   - **Swagger UI**: [http://127.0.0.1:8000/api/v1/docs](http://127.0.0.1:8000/api/v1/docs)
   - **ReDoc**: [http://127.0.0.1:8000/api/v1/redoc](http://127.0.0.1:8000/api/v1/redoc)
   - **Health Check**: [http://127.0.0.1:8000/health](http://127.0.0.1:8000/health)

---

### B. Menjalankan Frontend (React + Vite)

1. Buka terminal baru pada folder `frontend`:
   ```bash
   cd frontend
   ```
2. Pasang dependensi npm:
   ```bash
   npm install
   ```
3. Jalankan server pengembang Vite:
   ```bash
   npm run dev
   ```
4. Buka antarmuka web di peramban:
   [http://localhost:5173](http://localhost:5173)

### C. Menjalankan Vision Worker (YOLOv8 + ByteTrack)

Worker computer vision dapat dijalankan secara terpisah dari backend API untuk memproses rekaman video:

```bash
# Menjalankan worker pada video sample.mp4
python -m vision.cli --video "video sample.mp4" --line-y 215 --dir-in down --start-frame 750 --max-frames 200
```
- Gunakan opsi `--show` jika ingin menampilkan visualisasi bounding box, ID pelacak, dan HUD counter secara langsung.

---

### Kredensial Masuk (Login):
- **Email**: `admin@ayambangjungko.com`
- **Kata Sandi**: `password123`

---

## 5. Menjalankan Pengujian Otomatis (Automated Testing)

### Pengujian Backend & Vision Pipeline:
```bash
python -m pytest backend/tests/test_api.py vision/tests/ -v
```
*(Seluruh 19 uji coba integrasi API, database, detektor YOLO, tracker ByteTrack, dan line crossing lulus 100%)*

### Pengujian Frontend:
```bash
cd frontend
npm test
```
*(Seluruh 9 uji coba validasi formatting lokal dan integritas data lulus 100%)*

---

## 6. Menjalankan dengan Docker Compose

Untuk menjalankan seluruh stack (PostgreSQL 15, FastAPI, React Frontend) secara terorkestrasi:

```bash
docker compose up -d
```
- Frontend: [http://localhost:5173](http://localhost:5173)
- Backend: [http://localhost:8000](http://localhost:8000)
- PostgreSQL: `localhost:5432`
