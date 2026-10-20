# Ayam Bang Jungko — CCTV Visitor Counting & Crowd Analytics

> **Capstone Design Project:**  
> *"Sistem Penghitungan Pengunjung untuk Analisa Pola Keramaian Berbasis CCTV untuk Usaha Ayam Bang Jungko"*  
> **Teknologi:** PHP Native (PDO, Tailwind CSS, Chart.js) &bull; Python AI (YOLOv8 + ByteTrack)

---

## 1. Ringkasan Proyek

Aplikasi analitik operasional berbasis web untuk membantu pemilik dan manajer operasional **Ayam Bang Jungko** memahami pola lalu lintas pengunjung restoran, mendeteksi jam-jam sibuk (*peak hours*), serta mengoptimalkan kesiapan staf dan meja tanpa menyimpan citra wajah pengunjung (*privacy-first*).

Sistem menggunakan arsitektur **Hybrid PHP Native + AI Worker**:
- **Web Dashboard & REST API (PHP Native)**: Menangani antarmuka visualisasi, kalkulasi pola keramaian, riwayat penyeberangan, editor koordinat garis virtual interaktif (Canvas), serta penyimpanan basis data (PDO MySQL/SQLite).
- **AI Vision Worker (Python YOLOv8 + ByteTrack)**: Memproses feed video/CCTV secara real-time, mendeteksi orang melintasi garis virtual, lalu mengirimkan event secara otomatis ke endpoint PHP Native.

---

## 2. Fitur Utama

- **Dashboard Real-Time**: Ringkasan total pengunjung hari ini (masuk & keluar), total minggu ini, rata-rata harian, dan jam tersibuk (*busiest hour*).
- **Analisa Pola Keramaian (Crowd Analytics)**: Identifikasi jam sibuk dengan status kepadatan (**PADAT**, **SEDANG**, **LENGANG**), evaluasi kontribusi trafik per shift operasional (Makan Siang, Sore Hari, Makan Malam).
- **Riwayat Hitungan & Ekspor Laporan Human-Readable**: Tabel interaktif berfilter (arah, kamera, kata kunci) dengan paginasi, serta ekspor ke **Microsoft Excel (.xls)** (rapi dengan warna dan kolom terformat), **Cetak / Simpan Dokumen PDF**, dan CSV.
- **Editor Garis Virtual Interaktif (HTML5 Canvas)**: Visualisasi canvas koordinat titik penyeberangan P1 `(x1, y1)` dan P2 `(x2, y2)` yang dapat disesuaikan langsung lewat antarmuka web tanpa menyentuh kode program.
- **Privasi Terjamin**: Tanpa fitur pengenalan wajah (*facial recognition*), tidak menyimpan foto/wajah pengunjung, menjunjung tinggi privasi pelanggan.

---

## 3. Struktur Direktori Proyek

```
analisa-pengunjung/
├── api/                        # REST API PHP Native
│   ├── analytics.php           # Endpoint data per jam, harian, & peak hours
│   ├── cameras.php             # Endpoint CRUD kamera & update koordinat garis
│   ├── dashboard.php           # Endpoint ringkasan metrik dashboard
│   ├── events.php              # Endpoint penerima event dari Python AI
│   ├── export_excel.php        # Endpoint unduh berkas spreadsheet Excel (.xls)
│   └── export_csv.php          # Endpoint unduh berkas CSV
├── config/
│   └── database.php            # Koneksi PDO (Mendukung SQLite & MySQL/XAMPP)
├── database/
│   ├── schema_mysql.sql        # DDL Skema basis data untuk MySQL (XAMPP)
│   └── analisa_pengunjung.sqlite # File database SQLite lokal (Auto-generate)
├── includes/
│   ├── auth_check.php          # Proteksi sesi pengguna
│   ├── footer.php              # Footer template
│   └── header.php              # Header template & Sidebar navigasi
├── samples/
│   └── video_sample.mp4        # Berkas sampel video CCTV uji coba
├── vision/                     # Modul AI Computer Vision (Python)
│   ├── models/                 # Model bobot YOLOv8n
│   ├── detector.py             # Detektor orang YOLOv8
│   ├── tracker.py              # Pelacak ByteTrack
│   ├── line_crossing.py        # Logika perpotongan garis virtual
│   ├── event_dispatcher.py     # Pengirim event ke PHP Native API
│   ├── worker.py               # Worker video processing
│   └── worker_php.py           # Runner utama AI yang terhubung ke PHP
├── analytics.php               # Halaman Analisa Pola Keramaian
├── cameras.php                 # Halaman Pengaturan Kamera & Editor Canvas
├── history.php                 # Halaman Riwayat Penyeberangan
├── index.php                   # Halaman Dashboard Utama
├── login.php                   # Halaman Masuk
├── logout.php                  # Skrip Keluar Sesi
├── system.php                  # Halaman Status Sistem & Diagnostik
├── .env.example                # Templat konfigurasi environment
├── .gitignore                  # Berkas pengecualian Git
└── README.md                   # Dokumentasi proyek
```

---

## 4. Panduan Menjalankan Aplikasi

### Kebutuhan Sistem:
- **PHP**: 8.0+ (Telah terpasang PHP 8.3)
- **Python**: 3.10+ (Dengan OpenCV, Ultralytics YOLOv8, PyTorch, dan Requests)

---

### A. Menjalankan Web Dashboard (PHP Native)

#### Cara 1: Menggunakan Built-in Web Server PHP (Instan & Tanpa XAMPP)
1. Buka terminal di folder proyek:
   ```bash
   cd d:\Documents\GitHub\analisa-pengunjung
   ```
2. Jalankan web server bawaan PHP:
   ```bash
   php -S 127.0.0.1:8000
   ```
3. Buka browser:
   - Dashboard: [http://127.0.0.1:8000/index.php](http://127.0.0.1:8000/index.php)
   - Login: [http://127.0.0.1:8000/login.php](http://127.0.0.1:8000/login.php)

> **Kredensial Default Login:**  
> Email: `admin@ayambangjungko.com`  
> Kata Sandi: `admin123`

#### Cara 2: Menggunakan XAMPP (Apache + MySQL)
1. Salin folder proyek ini ke `C:\xampp\htdocs\analisa-pengunjung`.
2. Aktifkan **Apache** dan **MySQL** di XAMPP Control Panel.
3. Buka [http://localhost/phpmyadmin](http://localhost/phpmyadmin) lalu buat database bernama `analisa_pengunjung` dan impor file `database/schema_mysql.sql`.
4. Buka file `config/database.php` dan pastikan konfigurasi driver adalah `mysql`.
5. Akses [http://localhost/analisa-pengunjung/index.php](http://localhost/analisa-pengunjung/index.php).

---

### B. Menjalankan AI Vision Worker (Python)

Worker AI membaca video/CCTV, mendeteksi pengunjung melintasi garis virtual, lalu mengirimkan event secara real-time ke web PHP Native:

1. Buka terminal baru:
   ```bash
   cd d:\Documents\GitHub\analisa-pengunjung
   ```
2. Jalankan worker:
   ```bash
   python vision/worker_php.py --video "samples/video_sample.mp4" --api "http://127.0.0.1:8000/api/events.php"
   ```
3. Opsi tambahan:
   - Tambahkan `--show` untuk menampilkan preview video GUI OpenCV:
     ```bash
     python vision/worker_php.py --video "samples/video_sample.mp4" --api "http://127.0.0.1:8000/api/events.php" --show
     ```
   - Gunakan `--video 0` jika ingin menguji dengan webcam laptop/PC secara langsung.
