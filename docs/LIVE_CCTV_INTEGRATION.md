# Panduan Integrasi Live CCTV — Ayam Bang Jungko Crowd Analytics

Dokumen ini menjelaskan arsitektur integrasi **live camera streams strictly** untuk sistem penghitungan pengunjung otomatis berbasis computer vision pada usaha kuliner **Ayam Bang Jungko**.

---

## 1. Prinsip Utama (Strict Live Camera Requirement)

Sesuai arahan teknis sistem, aplikasi ini beroperasi secara eksklusif menggunakan **aliran video langsung (live streams)** dari perangkat kamera fisik:
1. **Tidak ada pemutaran berkas rekaman video** (MP4/AVI/MKV upload telah dihapus dari sistem).
2. **Tidak ada fallback video sampel palsu** yang disamarkan sebagai status kamera online.
3. Status `ONLINE` hanya diberikan setelah backend berhasil membuka stream riil dan membaca frame gambar yang valid.
4. Peramban web (browser) tidak dapat memutar RTSP secara native, sehingga disediakan secure HTTP MJPEG streaming proxy (`/api/v1/cameras/{id}/preview`) dengan visual placeholder eksplisit saat kamera offline.

---

## 2. Sumber Kamera yang Didukung

### A. IP Camera (Sumber Utama Restoran)
- **Protokol**: Live RTSP (Real-Time Streaming Protocol) melalui port default `554` (atau custom).
- **Konektivitas Fisik**: Terhubung ke jaringan lokal restoran via kabel Ethernet (LAN) atau Wi-Fi 2.4/5GHz yang stabil.
- **Autentikasi**: Mendukung authenticated RTSP. Kredensial disimpan dengan enkripsi simetris Fernet (AES-128-CBC + HMAC-SHA256) dan tidak pernah dikirimkan dalam bentuk teks terbuka ke peramban.
- **Format URL**:
  - `rtsp://[username]:[password]@[IP_KAMERA]:[PORT]/[STREAM_PATH]`
  - Contoh Hikvision: `rtsp://admin:pass123@192.168.1.100:554/Streaming/Channels/101`
  - Contoh Dahua: `rtsp://admin:pass123@192.168.1.100:554/cam/realmonitor?channel=1&subtype=0`

### B. Channel DVR / NVR (Multi-Kamera Perekam)
- **Protokol**: Live RTSP stream per channel yang diekspos oleh perekam NVR/DVR.
- **Konfigurasi**: Setiap kamera terdaftar sebagai entitas logis independen di basis data dengan koordinat garis virtual masing-masing.
- **Penomoran Channel**: Bergantung pada vendor (misal channel 1 = `101`, channel 2 = `201`).

### C. Laptop Camera & External USB Webcam (Live Testing)
- **Antarmuka OS**: Diakses langsung melalui subsistem DirectShow (`CAP_DSHOW` di Windows) atau V4L2 (`CAP_ANY` di Linux).
- **Penggunaan**: Untuk pengujian live di mesin kasir/laptop tanpa memerlukan kamera IP fisik saat pengujian atau demonstrasi.
- **Proteksi Single-Worker Mutex**: Webcam fisik tidak dapat dibuka secara bersamaan oleh dua thread. Sistem menggunakan `WebcamLiveVideoSource._active_device_indices` untuk mencegah tabrakan/device lock.

### D. HTTP/HTTPS Live Stream
- Mendukung live MJPEG stream over HTTP atau HLS live streams (`.m3u8`).

---

## 3. Topologi Jaringan & Penempatan Kamera

### Skenario 1: Deployment Jaringan Lokal (Local LAN / Wi-Fi Sama)
```
[ Kamera IP CCTV ] (192.168.1.100)
       │
       ▼ (Ethernet / Wi-Fi)
[ Router Restoran ] (192.168.1.1)
       │
       ▼ (LAN)
[ PC Server Mini / NUC ] (192.168.1.10)
  ├── PostgreSQL (Port 5432)
  ├── FastAPI Backend (Port 8000)
  ├── AI Inference Engine (YOLOv8 + ByteTrack)
  └── Vite Frontend Dashboard (Port 5173)
```
- Memberikan latensi paling rendah (< 200 ms).
- Tidak memerlukan kuota internet untuk transmisi video CCTV lokal.

### Skenario 2: Multi-Cabang / Remote Server via VPN (WireGuard / Tailscale)
Jika server pengolah terpusat di kantor pusat / cloud:
- Buat tunnel VPN aman (WireGuard / Tailscale / OpenVPN) antara router cabang restoran dan server.
- Hindari port-forwarding RTSP mentah ke internet publik untuk mencegah sniffing dan serangan brute-force.

---

## 4. Keamanan & Proteksi SSRF

1. **Enkripsi Kredensial**: Password kamera dienkripsi di level basis data menggunakan kunci enkripsi `SECURITY_SECRET_KEY`.
2. **Penyembunyian Endpoint (Masking)**: Respons API menyembunyikan username & password dengan masker `***` pada `source_uri_masked`.
3. **Pencegahan SSRF (Server-Side Request Forgery)**: RTSP adapter memblokir akses ke port loopback sensitif internal (misal port 22, 25, 5432).

---

## 5. Ringkasan API Endpoint Terkait CCTV

| Metode | Path | Deskripsi |
|---|---|---|
| `GET` | `/api/v1/cameras` | Mengambil daftar seluruh CCTV dengan masked URI |
| `POST` | `/api/v1/cameras` | Mendaftarkan kamera live baru |
| `GET` | `/api/v1/cameras/{id}` | Mengambil detail spesifik kamera |
| `PATCH` | `/api/v1/cameras/{id}` | Memperbarui nama, lokasi, koordinat garis, atau kredensial |
| `DELETE` | `/api/v1/cameras/{id}` | Menghapus kamera dan menghentikan worker AI |
| `POST` | `/api/v1/cameras/{id}/test` | Menguji koneksi riil dan validasi frame live |
| `POST` | `/api/v1/cameras/{id}/start-processing` | Memulai background thread inferensi AI live |
| `POST` | `/api/v1/cameras/{id}/stop-processing` | Menghentikan inferensi dan melepaskan stream |
| `GET` | `/api/v1/cameras/{id}/status` | Mengambil status koneksi & pemrosesan |
| `GET` | `/api/v1/cameras/{id}/preview` | Stream live MJPEG untuk tampilan peramban web |
| `GET` | `/api/v1/cameras/webcams/detect` | Memindai index kamera laptop/USB yang tersedia |
| `POST` | `/api/v1/cameras/discovery/scan` | Memindai CCTV & NVR ONVIF otomatis via UDP Multicast WS-Discovery |
| `POST` | `/api/v1/cameras/discovery/validate` | Menguji koneksi & verifikasi 1 frame live sebelum registrasi |

---

## 6. Penemuan Perangkat CCTV Otomatis (ONVIF WS-Discovery)

### A. Protokol & Mekanisme
Sistem mengimplementasikan **ONVIF WS-Discovery (OASIS Standard)** secara murni pada lapisan backend Python tanpa ketergantungan pustaka C eksternal:
1. **Multicast UDP Probe**: Mengirim paket SOAP Probe ke grup multicast `239.255.255.250` pada port `3702` dengan action `http://schemas.xmlsoap.org/ws/2005/04/discovery/Probe`.
2. **ProbeMatches Unicast Receiver**: Menerima respons XML dari seluruh IP Camera, DVR, dan NVR yang mendukung standar ONVIF di subnet lokal restoran.
3. **Ekstraksi Metadata Otomatis**: Mengekstrak alamat IP, port, ONVIF service endpoint (`XAddrs`), vendor/pabrikan (Hikvision, Dahua, Uniview, Axis, TP-Link, dll.), model, dan lokasi fisik dari scope ONVIF.
4. **RTSP Stream URI Suggestion**: Secara otomatis merekomendasikan path RTSP yang tepat sesuai konvensi pabrikan (misal `/Streaming/Channels/101` untuk Hikvision, `/cam/realmonitor?channel=1&subtype=0` untuk Dahua).

### B. Kontrol Keamanan & Proteksi Jaringan
- **Pembatasan IP Privat (RFC 1918)**: Sistem menolak secara ketat setiap respons atau permintaan validasi yang mengarah ke alamat IP publik internet (`is_authorized_local_ip`). Hal ini menjamin penemuan hanya terjadi pada jaringan lokal yang sah dan mencegah serangan *Server-Side Request Forgery* (SSRF).
- **Isolasi Kredensial**: Password kamera hanya dimasukkan pengguna pada form registrasi/uji koneksi, tidak pernah disiarkan dalam paket discovery, dan disimpan dengan enkripsi simetris Fernet di basis data.
- **Asinkron & Non-Blocking**: Proses discovery dijalankan di worker thread (`asyncio.to_thread`) dengan batas waktu (timeout default 3 detik) sehingga API server tetap responsif melayani dashboard.

### C. Keterbatasan Jaringan & Deployment Cloud
- **Broadcast Domain**: Multicast UDP hanya dapat menjangkau perangkat dalam satu broadcast domain (VLAN/subnet yang sama). Jika router memblokir IGMP/multicast atau menggunakan isolasi AP (Client Isolation), discovery otomatis tidak akan menemukan perangkat. Dalam kondisi ini, pengguna tetap dapat menggunakan opsi **Tambah IP/RTSP Manual**.
- **Remote / Cloud Deployment**: Jika backend dijalankan di VPS cloud, IP privat restoran tidak dapat dijangkau tanpa tunnel VPN (WireGuard/Tailscale) atau gateway edge lokal.
- **Mode Simulasi / Lab**: Sistem menyediakan parameter `simulate=true` untuk keperluan verifikasi otomatis dan demonstrasi fitur di lingkungan tanpa kamera fisik. Mode ini ditandai jelas pada antarmuka pengguna sebagai simulasi.
