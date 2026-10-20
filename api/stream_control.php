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
        if ($status['running']) {
            echo json_encode([
                'success' => true,
                'running' => true,
                'message' => 'Kamera sudah aktif berjalan',
                'feed_url' => "http://{$streamHost}:{$streamPort}/video_feed"
            ]);
            exit;
        }

        // Jalankan worker python di background
        $rootDir = realpath(__DIR__ . '/..');
        $cmd = "python \"{$rootDir}\\vision\\worker_php.py\" --camera-id={$cameraId}";
        
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            pclose(popen("start /B " . $cmd . " > NUL 2>&1", "r"));
        } else {
            exec($cmd . " > /dev/null 2>&1 &");
        }

        // Tunggu 1.5 detik agar socket port 8080 bind
        usleep(1500000);
        $newStatus = checkStreamStatus($statusUrl);

        echo json_encode([
            'success' => true,
            'running' => $newStatus['running'],
            'message' => $newStatus['running'] ? 'Kamera CCTV dan modul AI berhasil dinyalakan!' : 'Perintah start telah dikirim, sedang menginisialisasi kamera...',
            'feed_url' => "http://{$streamHost}:{$streamPort}/video_feed"
        ]);
        exit;
    }

    if ($action === 'stop') {
        // Hentikan proses worker_php.py
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            exec('wmic process where "caption=\'python.exe\' and commandline like \'%worker_php.py%\'" call terminate > NUL 2>&1');
            exec('taskkill /F /IM python.exe /FI "WINDOWTITLE eq worker_php*" > NUL 2>&1');
        } else {
            exec("pkill -f worker_php.py");
        }

        usleep(500000);
        echo json_encode([
            'success' => true,
            'running' => false,
            'message' => 'Kamera CCTV telah dihentikan'
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Aksi tidak dikenali']);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Metode tidak diizinkan']);
