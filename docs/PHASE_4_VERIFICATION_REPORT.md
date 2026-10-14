# Laporan Verifikasi Integrasi End-to-End (Fase 4)
## Proyek Capstone: Sistem Penghitungan Pengunjung Ayam Bang Jungko

**Status:** LULUS 100% (Verifikasi Penuh)  
**Tanggal:** 13 Oktober 2026  
**Lingkungan:** Local / Hybrid Development (PostgreSQL 15 Container, FastAPI Backend, React 18 Frontend, YOLOv8 + ByteTrack Vision Worker)

---

## 1. Ringkasan Eksekusi Fase 4

Fase 4 mengintegrasikan dan menguji seluruh komponen sistem secara bersamaan:
1. **Basis Data**: PostgreSQL 15 (`abj_postgres`) terhubung via SQLAlchemy dan termigrasi penuh dengan Alembic.
2. **Backend**: FastAPI (`http://127.0.0.1:8000`) mengelola autentikasi JWT, validasi skema Pydantic v2, agregasi SQL analitik, dan penanganan status error.
3. **Frontend**: React 18 + TypeScript + Vite + Tailwind CSS (`http://127.0.0.1:5173`) berinteraksi dengan API, menyajikan visualisasi Recharts, dan mendukung toggle Mode Demo / Live Backend.
4. **Computer Vision Worker**: Ultralytics YOLOv8n + ByteTrack memproses berkas rekaman `video sample.mp4`, mendeteksi crossing garis virtual, dan mengirimkan event asinkron ke backend.

---

## 2. Hasil Pengujian Integrasi API & Autentikasi

Pengujian otomatis dilakukan terhadap endpoint aktif backend menggunakan skrip verifikasi:

| No | Endpoint | Metode | Status | Hasil Pengujian |
|---|---|---|---|---|
| 1 | `/health` | GET | **200 OK** | Status 'ok', zona waktu terkonfirmasi `'Asia/Jakarta'` |
| 2 | `/api/v1/system/status` | GET | **200 OK** | DB connected: `True`, CV worker: `RUNNING` |
| 3 | `/api/v1/auth/login` | POST | **200 OK** | Login valid `admin@ayambangjungko.com` menghasilkan token JWT |
| 4 | `/api/v1/auth/login` | POST | **401 Unauthorized** | Kata sandi salah ditolak dengan pesan edukatif |
| 5 | `/api/v1/auth/me` | GET | **200 OK** | Mengembalikan profil pengguna tanpa membocorkan hash sandi |
| 6 | `/api/v1/auth/me` | GET | **401 Unauthorized** | Token palsu/tampered ditolak dengan aman |
| 7 | `/api/v1/auth/me` | GET | **401 Unauthorized** | Akses tanpa token ditolak dengan aman |
| 8 | `/api/v1/cameras` | GET | **200 OK** | Daftar kamera dimuat, URI sensitif/RTSP dimaskir |
| 9 | `/api/v1/cameras/{id}` | GET | **200 OK & 404** | Mengambil detail kamera spesifik; ID non-existent menghasilkan 404 |
| 10 | `/api/v1/dashboard/summary` | GET | **200 OK** | Agregasi metrik hari ini, minggu ini, dan jam tersibuk |
| 11 | `/api/v1/analytics/hourly` | GET | **200 OK** | 12 bucket jam operasional (10:00 - 21:00 WIB) |
| 12 | `/api/v1/analytics/daily` | GET | **200 OK** | 7 hari tren volume pengunjung |
| 13 | `/api/v1/analytics/peak-hours` | GET | **200 OK** | Jam sibuk dengan status okupansi (PADAT / SEDANG / LENGANG) |
| 14 | `/api/v1/analytics/comparison` | GET | **200 OK** | Perbandingan volume antar periode |
| 15 | `/api/v1/events` | GET | **200 OK** | Filter paginasi, arah IN/OUT, dan pemisahan mode sumber |
| 16 | `/api/v1/events` | POST | **201 Created** | Perekaman penyeberangan baru dan pembaruan counter sesi |
| 17 | `/api/v1/sessions` | GET | **200 OK** | Riwayat sesi pemrosesan |
| 18 | `/api/v1/system/logs` | GET | **200 OK** | Log audit aktivitas sistem |

---

## 3. Hasil Pengujian End-to-End Pipeline Video

Pengujian alur penuh dilakukan dengan memproses berkas rekaman `video sample.mp4` menggunakan `VisionWorker` dan mengirimkan event secara nyata ke backend API:

- **Konfigurasi Garis Virtual**: P1 `(30, 184)` ke P2 `(450, 184)`, Offset Buffer = $\pm 25$ px, Arah IN = *down*.
- **Kecepatan Inferensi**: Rata-rata 21.13 FPS pada CPU.
- **Deteksi Crossing**:
  - Person #1 terdeteksi melintas garis ke arah OUT pada frame 317 (Confidence: 0.76).
  - Event dikemas dan dikirimkan oleh `BackendEventDispatcher` ke `POST /api/v1/events`.
- **Integritas & Pemisahan Data**:
  - Data DEMO sebelum uji coba: 1.009 event.
  - Data DEMO sesudah uji coba: 1.009 event (**0% kontaminasi / isolasi sempurna**).
  - Data VIDEO bertambah tepat 1 event di PostgreSQL (`id: 7929db8e-797b-4631-8287-d59626adfd18`).
  - Metrik dashboard `GET /api/v1/dashboard/summary?source_mode=VIDEO` langsung diperbarui secara otomatis.

---

## 4. Hasil Pengujian Otomatis (Unit & Integration Suites)

| Komponen | Suite | Jumlah Uji Coba | Status | Waktu Eksekusi |
|---|---|---|---|---|
| **Backend & Vision** | `pytest backend/tests/ vision/tests/` | 19 Test Cases | **100% PASSED** | 5.52 detik |
| **Frontend** | `vitest run` (`npm test`) | 9 Test Cases | **100% PASSED** | 0.52 detik |
| **Production Build** | `tsc && vite build` | 2.379 Modul | **SUCCESS** | 3.75 detik |

---

## 5. Panduan Menjalankan Seluruh Layanan (Setup & Run Commands)

### 1. Menjalankan Database PostgreSQL
```bash
docker compose up -d abj_postgres
```

### 2. Menjalankan Backend FastAPI
```bash
cd backend
python -m uvicorn app.main:app --host 127.0.0.1 --port 8000 --reload
```
Akses Swagger UI: `http://127.0.0.1:8000/api/v1/docs`

### 3. Menjalankan Frontend React
```bash
cd frontend
npm run dev
```
Akses Dashboard: `http://localhost:5173` (Login: `admin@ayambangjungko.com` / `password123`)

### 4. Menjalankan Vision Worker pada Video Lokal
```bash
python -m vision.cli --video "video sample.mp4" --line-y 184 --dir-in down --conf 0.30 --show
```

---

## 6. Matriks Fitur: Terverifikasi vs Belum Terverifikasi

### A. Fitur yang Telah Terverifikasi (Verified Features)
1. **Deteksi Manusia & Tracking Lintas Frame**:
   - Model YOLOv8n memfilter kelas `person` (class ID 0).
   - ByteTrack mempertahankan ID unik pelacak pada pergerakan pengunjung.
2. **Virtual Line-Crossing dengan Histeresis**:
   - Perhitungan produk silang 2D (*cross-product vector*) menentukan orientasi penyeberangan.
   - Status lock pada track ID mencegah penghitungan ganda (*double-counting suppression*).
3. **Penyimpanan Terstruktur PostgreSQL**:
   - Tabel `counting_events`, `cameras`, `counting_sessions`, `users`, dan `system_logs` terindeks dengan baik.
4. **Isolasi Mutlak Mode Data**:
   - Pemisahan eksplisit antara `source_mode="DEMO"` dan `source_mode="VIDEO"`.
5. **Autentikasi & Keamanan Kredensial**:
   - Password di-hash menggunakan direct bcrypt (`salt` otomatis).
   - Kredensial RTSP/koneksi kamera dimaskir dan tidak pernah dikirim ke browser.
6. **Agregasi Analitik Jam Operasional**:
   - Distribusi per jam (10:00 - 21:00 WIB), volume harian, dan klasifikasi jam sibuk (*PADAT*, *SEDANG*, *LENGANG*).
7. **Privasi Pengunjung**:
   - Tanpa penyimpanan foto wajah atau identitas biometrik apa pun.

### B. Fitur yang Belum Terverifikasi di Lapangan Nyata (Unverified Features)
1. **Hardware CCTV RTSP Fisik di Lokasi Restoran**:
   - Belum dihubungkan ke stream kamera IP fisik secara langsung (diuji menggunakan berkas video lokal resolusi 480x368).
2. **Kondisi Pencahayaan Ekstrem & Malam Hari Tanpa Lampu**:
   - Inferensi belum diuji pada kondisi *infrared night vision* CCTV malam hari atau silau sinar matahari langsung pada kaca restoran.
3. **Kepadatan Ekstrem (>10 Orang Berhimpitan Sekaligus)**:
   - Pada rombongan yang berdesakan sangat padat masuk pintu bersamaan, oklusi parsial objek berpotensi memicu *ID switch* pada pelacak.
4. **Re-identifikasi Lintas Kamera (Multi-Camera Re-ID)**:
   - Sistem saat ini melacak per kamera individual dan belum mengidentifikasi perpindahan orang yang sama dari CCTV Pintu Masuk ke CCTV Area Makan Lantai 2.
