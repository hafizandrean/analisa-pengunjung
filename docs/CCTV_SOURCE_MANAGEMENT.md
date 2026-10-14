# Panduan & Dokumentasi Manajemen Sumber CCTV
## Sistem Analitika Keramaian Ayam Bang Jungko

Dokumentasi arsitektur, spesifikasi teknis, dan panduan penggunaan fitur **Manajemen Sumber CCTV** (*CCTV Source Management*) pada aplikasi Ayam Bang Jungko.

---

## 1. Gambaran Umum

Fitur Manajemen Sumber CCTV memungkinkan pengelola restoran mendaftarkan, menguji, dan mengonfigurasi beberapa kamera CCTV secara dinamis dan independen tanpa perlu menyentuh kode sumber atau mengubah konfigurasi berkas secara manual.

Sistem mendukung:
- Pendaftaran banyak kamera (*multi-camera registration*).
- Empat tipe sumber video: **RTSP IP Camera**, **USB/Webcam Device**, **Uploaded Video File**, dan **HTTP/HTTPS Video Stream**.
- Enkripsi kata sandi kamera IP yang aman (*Fernet symmetric encryption*) dan penyembunyian kredensial otomatis (*credential masking*).
- Pengujian koneksi riil dengan batas waktu (*connection timeout*) untuk mendeteksi status **ONLINE**, **OFFLINE**, **CONNECTING**, atau **ERROR**.
- Konfigurasi garis virtual crossing individual yang tersimpan secara terisolasi per kamera.
- Pengendalian pemrosesan analitika AI (*Start / Stop Processing*) berbasis kamera.

---

## 2. Arsitektur Keamanan Kredensial

> [!IMPORTANT]
> Sistem menerapkan prinsip **Zero Credential Exposure**. Kata sandi kamera IP tidak pernah ditampilkan dalam teks biasa (*plain text*) baik di antarmuka web, respons REST API, maupun catatan log.

1. **Enkripsi Kredensial Simetris**:
   - Kata sandi kamera IP dienkripsi menggunakan pustaka `cryptography.fernet` dengan kunci rahasia turunan dari `SECRET_KEY` backend.
   - Kolom basis data: `cameras.password_encrypted`.
2. **Penyembunyian URI (*Masked URI Display*)**:
   - Properti `source_uri_masked` pada model basis data secara otomatis menggantikan kata sandi dengan karakter asteris (`***`).
   - Contoh: `rtsp://operator:***@192.168.1.188:554/stream1`.
3. **Penyimpanan Terpisah**:
   - Jika pengguna memasukkan nama pengguna dan kata sandi pada formulir modal, kredensial disimpan terenkripsi dan disuntikkan secara dinamis saat OpenCV melakukan koneksi (*runtime injection*).

---

## 3. Tipe Sumber Video yang Didukung

| Tipe Sumber | Nilai Kode | Format Input | Mekanisme Pengujian Koneksi |
|---|---|---|---|
| **RTSP IP Camera** | `RTSP` | `rtsp://[IP]:[PORT]/[PATH]` | Menghubungkan socket RTSP via OpenCV FFMPEG dengan timeout 6 detik; membaca 1 frame untuk memvalidasi resolusi & FPS. |
| **USB / Webcam** | `USB_WEBCAM` | Index integer (`0`, `1`, `2`) | Mengakses interface DirectShow/V4L2 OpenCV pada index perangkat; membaca 1 frame. |
| **Berkas Rekaman Video** | `VIDEO_FILE` | Jalur berkas (`video sample.mp4`, `samples/...`) | Memverifikasi keberadaan berkas di server lokal, integritas decoder codec video, dan resolusi. |
| **HTTP/HTTPS Stream** | `HTTP_STREAM` | `http://.../video.mjpg` atau `.m3u8` | Menguji URL stream HTTP dengan OpenCV; memverifikasi kesesuaian format video live stream. |

---

## 4. Spesifikasi REST API Endpoint

Seluruh endpoint terintegrasi di bawah prefix `/api/v1/cameras`:

### A. Mendapatkan Daftar Kamera
- **Metode**: `GET /api/v1/cameras`
- **Respons**: Daftar objek kamera lengkap dengan status koneksi, resolusi, status pemrosesan, dan URI bermaskir.

### B. Mendaftarkan Kamera Baru
- **Metode**: `POST /api/v1/cameras`
- **Body JSON**:
  ```json
  {
    "name": "CCTV Area Dining Lantai 1",
    "location": "Pintu Utama Dining",
    "source_type": "RTSP",
    "source_uri": "rtsp://192.168.1.100:554/live",
    "username": "admin",
    "password": "PasswordCCTV123!",
    "resolution_width": 1920,
    "resolution_height": 1080,
    "fps": 25,
    "line_coords": {
      "x1": 150, "y1": 500, "x2": 1750, "y2": 500,
      "direction_in": "down", "offset": 35
    },
    "is_active": true,
    "notes": "Kamera outdoor sudut kasir"
  }
  ```
- **Catatan**: Status awal selalu `OFFLINE` dan `IDLE` sampai diuji atau diaktifkan.

### C. Menguji Konektivitas Kamera
- **Metode**: `POST /api/v1/cameras/{camera_id}/test`
- **Respons**:
  ```json
  {
    "camera_id": "c0000000-0000-0000-0000-000000000001",
    "is_accessible": true,
    "connection_status": "ONLINE",
    "resolution_width": 480,
    "resolution_height": 368,
    "fps": 30,
    "message": "Berkas video valid dan siap diproses (480x368 @ 30 FPS).",
    "checked_at": "2026-10-13T15:32:06Z"
  }
  ```

### D. Kontrol Pemrosesan AI
- **Mulai Analisis**: `POST /api/v1/cameras/{camera_id}/start-processing`
- **Hentikan Analisis**: `POST /api/v1/cameras/{camera_id}/stop-processing`
- **Status Kamera**: `GET /api/v1/cameras/{camera_id}/status`

### E. Memperbarui & Menghapus Kamera
- **Perbarui**: `PATCH /api/v1/cameras/{camera_id}` (Mendukung pembaruan koordinat garis, nama, lokasi, sandi baru).
- **Hapus**: `DELETE /api/v1/cameras/{camera_id}` (Menghapus kamera dan membersihkan relasi cascade).

### F. Unggah Berkas Sampel
- **Metode**: `POST /api/v1/cameras/upload-sample`
- **Headers**: `X-Filename: sample_rekaman.mp4`, `Content-Type: application/octet-stream`
- **Body**: Raw binary data berkas video.

---

## 5. Konfigurasi Garis Virtual Per Kamera

Setiap kamera memiliki rekaman koordinat garis virtual tersendiri di dalam kolom JSON `line_coords`:
- **P1**: Titik awal penyeberangan `(x1, y1)`.
- **P2**: Titik akhir penyeberangan `(x2, y2)`.
- **Offset**: Jarak toleransi buffer histeresis dalam piksel (default: $25 - 35$ px).
- **Direction IN**: Arah masuk pengunjung (`down`, `up`, `left`, `right`).

Mengubah koordinat pada satu kamera tidak akan memengaruhi konfigurasi kamera lainnya.
