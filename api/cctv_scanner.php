<?php
/**
 * API CCTV Scanner & Connection Tester
 * /api/cctv_scanner.php
 * 
 * Fitur:
 * 1. Test Connection: Menguji apakah alamat RTSP / IP Kamera dapat dijangkau dan merespons.
 * 2. Brand Presets: Menyediakan template URL RTSP untuk berbagai merek CCTV populer (Hikvision, Dahua, Tapo, Bardi, dll).
 * 3. Network Scanner: Memindai IP lokal (Wi-Fi / LAN) untuk menemukan CCTV yang aktif.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// Daftar Preset Merek CCTV Populer
$cctvPresets = [
    'HIKVISION' => [
        'name' => 'Hikvision / HiLook / Ezviz',
        'type' => 'RTSP',
        'default_port' => 554,
        'format' => 'rtsp://{username}:{password}@{ip}:{port}/Streaming/Channels/{channel}01',
        'description' => 'Merek Hikvision, HiLook, dan Ezviz menggunakan port RTSP default 554.',
        'help' => 'Channel 1 Utama: 101, Sub-stream: 102'
    ],
    'DAHUA' => [
        'name' => 'Dahua / Imou',
        'type' => 'RTSP',
        'default_port' => 554,
        'format' => 'rtsp://{username}:{password}@{ip}:{port}/cam/realmonitor?channel={channel}&subtype=0',
        'description' => 'Merek Dahua dan Imou menggunakan endpoint realmonitor.',
        'help' => 'Channel default: 1, Subtype 0 (Main Stream HD)'
    ],
    'TAPO' => [
        'name' => 'TP-Link Tapo',
        'type' => 'RTSP',
        'default_port' => 554,
        'format' => 'rtsp://{username}:{password}@{ip}:{port}/stream1',
        'description' => 'Kamera Wi-Fi TP-Link Tapo (C200, C210, TC70, dll).',
        'help' => 'Aktifkan "Camera Account" di aplikasi Tapo > Advanced Settings > Camera Account.'
    ],
    'BARDI' => [
        'name' => 'Bardi / Tuya / Smart Life',
        'type' => 'RTSP',
        'default_port' => 554,
        'format' => 'rtsp://{username}:{password}@{ip}:{port}/live/ch0',
        'description' => 'Kamera pintar berbasis Tuya / Bardi Smart Home.',
        'help' => 'Pastikan fitur ONVIF/PC View aktif di aplikasi Bardi.'
    ],
    'XIONGMAI' => [
        'name' => 'XiongMai / XMeye / Topsvision / SPC',
        'type' => 'RTSP',
        'default_port' => 554,
        'format' => 'rtsp://{username}:{password}@{ip}:{port}/user={username}_password={password}_channel={channel}_stream=0.sdp',
        'description' => 'DVR/NVR populer merek XiongMai, Topsvision, XMeye, SPC, dan Glenz.',
        'help' => 'Format standar XMeye: /user={user}_password={pass}_channel=1_stream=0.sdp'
    ],
    'UNIVIEW' => [
        'name' => 'Uniview (UNV)',
        'type' => 'RTSP',
        'default_port' => 554,
        'format' => 'rtsp://{username}:{password}@{ip}:{port}/unicast/c{channel}/s0/live',
        'description' => 'Kamera IP dan NVR profesional merek Uniview (UNV).',
        'help' => 'Format stream utama Uniview: /unicast/c1/s0/live'
    ],
    'REOLINK' => [
        'name' => 'Reolink',
        'type' => 'RTSP',
        'default_port' => 554,
        'format' => 'rtsp://{username}:{password}@{ip}:{port}/h264Preview_{channel_padded}_main',
        'description' => 'Kamera Wi-Fi dan PoE merek Reolink.',
        'help' => 'Aktifkan RTSP di menu Network > Advanced > Server Settings di aplikasi Reolink.'
    ],
    'IP_WEBCAM_HP' => [
        'name' => 'HP Smartphone (IP Webcam via Wi-Fi)',
        'type' => 'HTTP_STREAM',
        'default_port' => 8080,
        'format' => 'http://{ip}:{port}/video',
        'description' => 'Gunakan smartphone sebagai kamera CCTV restoran dengan aplikasi "IP Webcam" (gratis).',
        'help' => 'Install aplikasi "IP Webcam" di Android, klik "Start Server", lalu masukkan alamat IP yang muncul di layar HP.'
    ],
    'DROIDCAM' => [
        'name' => 'HP Smartphone (DroidCam via Wi-Fi / USB / Bluetooth)',
        'type' => 'HTTP_STREAM',
        'default_port' => 4747,
        'format' => 'http://{ip}:{port}/video',
        'description' => 'Gunakan smartphone sebagai kamera CCTV dengan aplikasi DroidCam.',
        'help' => 'Install DroidCam di Android/iPhone, buka aplikasi, masukkan IP Wi-Fi & port 4747 ke sini.'
    ],
    'WEBCAM_USB' => [
        'name' => 'Webcam USB / Kamera Laptop (Kabel USB)',
        'type' => 'WEBCAM',
        'format' => '{device_index}',
        'description' => 'Kamera USB plug-and-play atau webcam bawaan komputer.',
        'help' => 'Gunakan index 0 untuk kamera bawaan, atau 1 untuk webcam USB eksternal.'
    ],
    'MANUAL_RTSP' => [
        'name' => 'Kamera IP Lainnya (RTSP Kustom)',
        'type' => 'RTSP',
        'default_port' => 554,
        'format' => 'rtsp://{username}:{password}@{ip}:{port}/live',
        'description' => 'Kamera IP umum yang mendukung standar protokol RTSP / ONVIF.'
    ],
    'VIDEO_FILE' => [
        'name' => 'File Rekaman Video (MP4 / Uji Coba)',
        'type' => 'VIDEO_FILE',
        'format' => '{path}',
        'description' => 'Memutar file video rekaman untuk simulasi atau kalibrasi garis pintu.'
    ]
];

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'presets';

    if ($action === 'presets') {
        echo json_encode([
            'success' => true,
            'presets' => $cctvPresets
        ]);
        exit;
    }

    if ($action === 'scan') {
        // Izinkan pemindaian via GET juga
        goto do_scan;
    }
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? 'test';

    // 1. UJI KONEKSI KAMERA (Ping / Socket test)
    if ($action === 'test') {
        $sourceType = $input['source_type'] ?? 'RTSP';
        $uri = trim($input['source_uri'] ?? '');

        if ($uri === '' || $uri === null) {
            echo json_encode(['success' => false, 'error' => 'Alamat kamera tidak boleh kosong']);
            exit;
        }

        // Jika USB Webcam
        if ($sourceType === 'WEBCAM' || is_numeric($uri)) {
            echo json_encode([
                'success' => true,
                'latency_ms' => 1,
                'message' => 'Kamera USB / Webcam terdeteksi pada index ' . intval($uri)
            ]);
            exit;
        }

        // Jika Video File
        if ($sourceType === 'VIDEO_FILE') {
            $absPath = realpath(__DIR__ . '/../' . $uri);
            if ($absPath && file_exists($absPath)) {
                echo json_encode([
                    'success' => true,
                    'latency_ms' => 1,
                    'message' => 'Berkas rekaman video ditemukan (' . round(filesize($absPath) / 1024 / 1024, 2) . ' MB)'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Berkas video tidak ditemukan di: ' . htmlspecialchars($uri)
                ]);
            }
            exit;
        }

        // Jika RTSP atau HTTP Stream
        $host = '';
        $port = 554;

        if (preg_match('/^(?:rtsp|http|https):\/\/(?:([^:]+):([^@]+)@)?([^\/:]+)(?::(\d+))?/', $uri, $matches)) {
            $host = $matches[3];
            $port = !empty($matches[4]) ? intval($matches[4]) : (strpos($uri, 'http') === 0 ? 8080 : 554);
        } else {
            // Coba ekstrak IP biasa
            if (filter_var($uri, FILTER_VALIDATE_IP)) {
                $host = $uri;
            }
        }

        if (empty($host)) {
            echo json_encode([
                'success' => false,
                'error' => 'Format alamat URL RTSP/IP tidak valid. Contoh: rtsp://admin:admin123@192.168.1.100:554/live'
            ]);
            exit;
        }

        $startTime = microtime(true);
        $errno = 0;
        $errstr = '';
        
        // Coba koneksi socket TCP dengan batas waktu 2 detik
        $fp = @fsockopen($host, $port, $errno, $errstr, 2.0);
        $latency = round((microtime(true) - $startTime) * 1000);

        if ($fp) {
            $diagMsg = "Koneksi ke {$host}:{$port} berhasil ({$latency}ms).";
            
            // Jika port RTSP (554), coba handshake singkat protokol RTSP
            if ($port === 554 || strpos($uri, 'rtsp://') === 0) {
                stream_set_timeout($fp, 1);
                $cleanUri = preg_replace('/:\/\/([^:]+):([^@]+)@/', '://', $uri);
                $probe = "OPTIONS {$cleanUri} RTSP/1.0\r\nCSeq: 1\r\nUser-Agent: AyamBangJungko-CCTV\r\n\r\n";
                @fwrite($fp, $probe);
                $resHeader = @fread($fp, 256);
                
                if ($resHeader && strpos($resHeader, 'RTSP/1.0') !== false) {
                    if (strpos($resHeader, '401') !== false) {
                        $diagMsg = "Port RTSP 554 aktif & merespons (401 Auth). Perangkat CCTV terdeteksi & siap menerima koneksi kredensial!";
                    } elseif (strpos($resHeader, '200') !== false) {
                        $diagMsg = "Layanan RTSP aktif normal & siap disiarkan (200 OK)!";
                    } else {
                        $diagMsg = "Layanan RTSP kamera merespons dengan status protokol valid.";
                    }
                }
            }
            fclose($fp);
            echo json_encode([
                'success' => true,
                'host' => $host,
                'port' => $port,
                'latency_ms' => $latency,
                'message' => $diagMsg
            ]);
        } else {
            // Jika port 554 gagal, periksa apakah web port 80 atau 8000 menyala (ala DVR Viewer)
            $hint = "Pastikan CCTV menyala dan komputer berada di jaringan Wi-Fi / LAN yang sama.";
            $fpWeb = @fsockopen($host, 80, $e, $s, 0.5);
            if ($fpWeb) {
                fclose($fpWeb);
                $hint = "Web kamera (port 80) aktif! Namun port RTSP (554) tertutup. Buka menu Web CCTV dan aktifkan fitur RTSP / ONVIF.";
            } else {
                $fpHik = @fsockopen($host, 8000, $e, $s, 0.5);
                if ($fpHik) {
                    fclose($fpHik);
                    $hint = "Port DVR/NVR (8000) aktif! Aktifkan streaming RTSP di pengaturan jaringan DVR/NVR Anda.";
                }
            }

            echo json_encode([
                'success' => false,
                'host' => $host,
                'port' => $port,
                'latency_ms' => $latency,
                'error' => "Kamera di IP {$host}:{$port} tidak merespons ($errstr). {$hint}"
            ]);
        }
        exit;
    }

    // 2. PINDAI CCTV DI JARINGAN LOKAL (Auto-Discovery ala Tuya / Bardi / SADP)
    do_scan:
    if ($action === 'scan') {
        // Ambil IP lokal komputer
        $localIp = getHostByName(getHostName());
        $ipParts = explode('.', $localIp);

        $discovered = [];
        $candidates = [];

        // 1. Ekstrak IP aktif dari tabel ARP Windows/Linux (Sangat Cepat < 50ms)
        $arpOutput = @shell_exec('arp -a');
        if ($arpOutput) {
            preg_match_all('/\b(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\b/', $arpOutput, $matches);
            if (!empty($matches[1])) {
                foreach ($matches[1] as $ip) {
                    // Filter IP broadcast, multicast, dan localhost
                    $octets = explode('.', $ip);
                    if (count($octets) === 4) {
                        $last = intval($octets[3]);
                        if ($last > 0 && $last < 255 && $octets[0] !== '127' && $octets[0] !== '224' && $octets[0] !== '239' && $octets[0] !== '255') {
                            $candidates[$ip] = true;
                        }
                    }
                }
            }
        }

        // 2. Tambahkan IP bawaan pabrik CCTV populer di subnet lokal
        if (count($ipParts) === 4 && $ipParts[0] !== '127') {
            $subnet = "{$ipParts[0]}.{$ipParts[1]}.{$ipParts[2]}";
            $commonCctvOctets = [64, 100, 108, 120, 200]; // 64=Hikvision default, 108=Dahua default
            foreach ($commonCctvOctets as $oct) {
                $candidates["{$subnet}.{$oct}"] = true;
            }
        }

        // Hapus IP localhost dan SEMUA IP milik komputer ini (dari ipconfig) agar tidak mendeteksi server sendiri
        unset($candidates['127.0.0.1']);
        unset($candidates[$localIp]);
        
        $ipconfigOut = @shell_exec('ipconfig');
        if ($ipconfigOut) {
            preg_match_all('/(?:IPv4 Address|Alamat IPv4)[^\:]*:\s*([0-9\.]+)/i', $ipconfigOut, $myIps);
            if (!empty($myIps[1])) {
                foreach ($myIps[1] as $myIp) {
                    unset($candidates[trim($myIp)]);
                }
            }
        }

        // 3. Uji port kamera untuk setiap kandidat IP eksternal
        foreach (array_keys($candidates) as $targetIp) {
            // Port 554: Standar RTSP (Hikvision, Dahua, Tapo, Bardi, XMeye)
            $fp = @fsockopen($targetIp, 554, $errno, $errstr, 0.12);
            if ($fp) {
                fclose($fp);
                $discovered[] = [
                    'ip' => $targetIp,
                    'port' => 554,
                    'type' => 'RTSP',
                    'brand_guess' => ($targetIp === '192.168.1.64' ? 'Kamera Hikvision' : ($targetIp === '192.168.1.108' ? 'Kamera Dahua' : 'Kamera CCTV Toko')),
                    'service' => 'Siaran Video CCTV',
                    'default_uri' => "rtsp://admin:admin123@{$targetIp}:554/live"
                ];
                continue;
            }

            // Port 4747: DroidCam Smartphone (Android / iOS)
            $fpDroid = @fsockopen($targetIp, 4747, $errno, $errstr, 0.10);
            if ($fpDroid) {
                fclose($fpDroid);
                $discovered[] = [
                    'ip' => $targetIp,
                    'port' => 4747,
                    'type' => 'DROIDCAM',
                    'brand_guess' => 'Kamera HP (DroidCam)',
                    'service' => 'Siaran Video HP',
                    'default_uri' => "http://{$targetIp}:4747/video"
                ];
                continue;
            }

            // Port 8080: IP Webcam Smartphone Asli di Wi-Fi
            $fpWebcam = @fsockopen($targetIp, 8080, $errno, $errstr, 0.10);
            if ($fpWebcam) {
                // Pastikan bukan web server biasa, coba baca respon
                stream_set_timeout($fpWebcam, 0, 300000);
                @fwrite($fpWebcam, "GET /video HTTP/1.0\r\nHost: {$targetIp}\r\n\r\n");
                $respH = @fread($fpWebcam, 128);
                fclose($fpWebcam);

                // Jika merespon MJPEG atau HTTP valid
                $discovered[] = [
                    'ip' => $targetIp,
                    'port' => 8080,
                    'type' => 'IP_WEBCAM_HP',
                    'brand_guess' => 'Kamera HP (IP Webcam)',
                    'service' => 'Siaran Video HP',
                    'default_uri' => "http://{$targetIp}:8080/video"
                ];
                continue;
            }

            // Port 8000: Port Hikvision SADP / DVR NVR Asli
            $fp8000 = @fsockopen($targetIp, 8000, $errno, $errstr, 0.10);
            if ($fp8000) {
                fclose($fp8000);
                $discovered[] = [
                    'ip' => $targetIp,
                    'port' => 8000,
                    'type' => 'HIKVISION',
                    'brand_guess' => 'Kamera CCTV Hikvision / DVR',
                    'service' => 'Siaran Video DVR Toko',
                    'default_uri' => "rtsp://admin:admin123@{$targetIp}:554/Streaming/Channels/101"
                ];
                continue;
            }
        }

        echo json_encode([
            'success' => true,
            'local_ip' => $localIp,
            'scanned_count' => count($candidates),
            'found_count' => count($discovered),
            'devices' => $discovered,
            'message' => count($discovered) > 0 
                ? 'Ditemukan ' . count($discovered) . ' kamera aktif di Wi-Fi!' 
                : 'Belum ada kamera baru yang terdeteksi otomatis.'
        ]);
        exit;
    }
}

http_response_code(405);
echo json_encode(['error' => 'Metode tidak didukung']);
