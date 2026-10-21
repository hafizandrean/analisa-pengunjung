<?php
/**
 * API Stream Control & Status
 * /api/stream_control.php
 * Mengontrol worker Python AI Vision dan memeriksa status live streaming kamera CCTV (port 8080).
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$streamHost = '127.0.0.1';
$streamPort = 8080;
$statusUrl = "http://{$streamHost}:{$streamPort}/status";

// Fungsi cek ketersediaan stream
function checkStreamStatus($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $json = json_decode($response, true);
        if ($json && !empty($json['is_active'])) {
            return ['running' => true, 'stats' => $json];
        }
    }
    return ['running' => false, 'stats' => null];
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $status = checkStreamStatus($statusUrl);
    echo json_encode([
        'success' => true,
        'running' => $status['running'],
        'feed_url' => "http://{$streamHost}:{$streamPort}/video_feed",
        'stats' => $status['stats']
    ]);
    exit;
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? 'status';
    $cameraId = $input['camera_id'] ?? 'cam-1';

    if ($action === 'status') {
        $status = checkStreamStatus($statusUrl);
        echo json_encode([
            'success' => true,
            'running' => $status['running'],
            'feed_url' => "http://{$streamHost}:{$streamPort}/video_feed",
            'stats' => $status['stats']
        ]);
        exit;
    }

    if ($action === 'start') {
        $status = checkStreamStatus($statusUrl);
        $currentCam = $status['stats']['camera_id'] ?? null;

        // Jika sudah berjalan untuk kamera yang SAMA persis
        if ($status['running'] && $currentCam === $cameraId) {
            echo json_encode([
                'success' => true,
                'running' => true,
                'message' => 'Kamera sudah aktif berjalan',
                'feed_url' => "http://{$streamHost}:{$streamPort}/video_feed"
            ]);
            exit;
        }

        // Jika berjalan untuk kamera yang BERBEDA, matikan dulu worker lama
        if ($status['running'] && $currentCam !== $cameraId) {
            $ch = curl_init("http://{$streamHost}:{$streamPort}/shutdown");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 1);
            @curl_exec($ch);
            curl_close($ch);
            usleep(400000);
        }

        // Jalankan worker python di background secara independen
        $rootDir = realpath(__DIR__ . '/..');
        $script = $rootDir . '\\vision\\worker_php.py';
        $pyPath = 'python';
        if (file_exists('C:\\laragon\\bin\\python\\python-3.10\\python.exe')) {
            $pyPath = 'C:\\laragon\\bin\\python\\python-3.10\\python.exe';
        }

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $psCmd = "powershell -WindowStyle Hidden -Command \"Start-Process '{$pyPath}' -ArgumentList '\\\"{$script}\\\" --camera-id={$cameraId}' -WorkingDirectory '{$rootDir}' -WindowStyle Hidden\"";
            pclose(popen($psCmd, 'r'));
        } else {
            exec("python \"{$script}\" --camera-id={$cameraId} > /dev/null 2>&1 &");
        }

        // Tunggu hingga socket port 8080 aktif (maksimal 3 detik)
        $newStatus = ['running' => false];
        for ($i = 0; $i < 6; $i++) {
            usleep(500000);
            $newStatus = checkStreamStatus($statusUrl);
            if ($newStatus['running']) break;
        }

        echo json_encode([
            'success' => true,
            'running' => $newStatus['running'],
            'message' => $newStatus['running'] ? 'Kamera CCTV dan modul AI berhasil dinyalakan!' : 'Kamera sedang memulai...',
            'feed_url' => "http://{$streamHost}:{$streamPort}/video_feed"
        ]);
        exit;
    }

    if ($action === 'stop') {
        // 1. Coba kirim sinyal shutdown lewat HTTP endpoint server stream
        $ch = curl_init("http://{$streamHost}:{$streamPort}/shutdown");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
        @curl_exec($ch);
        curl_close($ch);

        usleep(300000);

        // 2. Jika masih aktif, matikan proses berdasarkan port / nama
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Cari PID yang sedang mendengarkan di port 8080
            $netstat = @shell_exec("netstat -ano | findstr :{$streamPort}");
            if ($netstat) {
                preg_match('/LISTENING\s+(\d+)/', $netstat, $m);
                if (!empty($m[1])) {
                    $pid = intval($m[1]);
                    @exec("taskkill /F /PID {$pid} > NUL 2>&1");
                }
            }
            @exec('taskkill /F /IM python.exe /FI "WINDOWTITLE eq worker_php*" > NUL 2>&1');
        } else {
            @exec("pkill -f worker_php.py");
        }

        usleep(300000);
        $finalStatus = checkStreamStatus($statusUrl);

        echo json_encode([
            'success' => true,
            'running' => $finalStatus['running'],
            'message' => $finalStatus['running'] ? 'Gagal menghentikan kamera' : 'Kamera CCTV telah dimatikan'
        ]);
        exit;
    }

    if ($action === 'toggle_store_status' || $action === 'set_store_status') {
        require_once __DIR__ . '/../config/settings.php';
        
        $currentStatus = getSetting('store_status', 'closed');
        if (isset($input['status'])) {
            $newStatus = ($input['status'] === 'open' || $input['status'] === '1') ? 'open' : 'closed';
        } else {
            $newStatus = ($currentStatus === 'open') ? 'closed' : 'open';
        }
        
        saveSetting('store_status', $newStatus);
        
        // Notifikasi langsung ke worker Python lewat HTTP port 8080
        @file_get_contents("http://{$streamHost}:{$streamPort}/set_store_status?status={$newStatus}");
        
        $isOpen = ($newStatus === 'open');
        echo json_encode([
            'success' => true,
            'store_status' => $newStatus,
            'is_open' => $isOpen,
            'message' => $isOpen ? 'Restoran dibuka! Kamera mulai aktif menghitung pengunjung.' : 'Restoran ditutup. Kamera beralih ke mode standby (tidak menghitung).'
        ]);
        exit;
    }

    if ($action === 'reset_today_counters') {
        require_once __DIR__ . '/../config/database.php';
        require_once __DIR__ . '/../config/settings.php';
        
        $pdo = getDBConnection();
        $today = date('Y-m-d');
        
        // Hapus catatan event hari ini untuk kamera ini
        $delStmt = $pdo->prepare("DELETE FROM counting_events WHERE camera_id = ? AND DATE(event_timestamp) = ?");
        $delStmt->execute([$cameraId, $today]);

        // Beri tahu worker Python untuk mereset memori counter ke 0
        @file_get_contents("http://{$streamHost}:{$streamPort}/reset");

        echo json_encode([
            'success' => true,
            'message' => 'Hitungan pengunjung hari ini berhasil direset ke 0',
            'today' => $today
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Aksi tidak dikenali']);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Metode tidak diizinkan']);
