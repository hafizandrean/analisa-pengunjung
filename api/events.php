<?php
/**
 * API Endpoint: /api/events.php
 * Mendukung:
 * - GET: Mengambil riwayat crossing dengan filter, paginasi, & pencarian
 * - POST: Menerima event penyeberangan baru dari Python Computer Vision Worker
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDBConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed', 'message' => $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Parameter Query
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $pageSize = isset($_GET['page_size']) ? min(100, max(1, (int)$_GET['page_size'])) : 15;
    $direction = isset($_GET['direction']) ? strtoupper(trim($_GET['direction'])) : null;
    $cameraId = isset($_GET['camera_id']) && $_GET['camera_id'] !== 'all' ? trim($_GET['camera_id']) : null;
    $search = isset($_GET['search']) ? trim($_GET['search']) : null;

    $where = [];
    $params = [];

    if ($direction && in_array($direction, ['IN', 'OUT'])) {
        $where[] = "e.direction = ?";
        $params[] = $direction;
    }

    if ($cameraId) {
        $where[] = "e.camera_id = ?";
        $params[] = $cameraId;
    }

    if ($search) {
        $where[] = "(e.direction LIKE ? OR c.name LIKE ? OR CAST(e.track_id AS TEXT) LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    $whereSql = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

    // Hitung total data
    $countSql = "SELECT COUNT(*) FROM counting_events e LEFT JOIN cameras c ON e.camera_id = c.id $whereSql";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    // Ambil data halaman aktif
    $offset = ($page - 1) * $pageSize;
    $dataSql = "SELECT e.*, c.name as camera_name 
                FROM counting_events e 
                LEFT JOIN cameras c ON e.camera_id = c.id 
                $whereSql 
                ORDER BY e.event_timestamp DESC 
                LIMIT $pageSize OFFSET $offset";
    
    $stmt = $pdo->prepare($dataSql);
    $stmt->execute($params);
    $records = $stmt->fetchAll();

    echo json_encode([
        'total' => $total,
        'page' => $page,
        'page_size' => $pageSize,
        'total_pages' => ceil($total / $pageSize),
        'items' => $records
    ]);
    exit;
}

if ($method === 'POST') {
    // Menerima event dari Python Computer Vision Worker
    $inputRaw = file_get_contents('php://input');
    $data = json_decode($inputRaw, true);

    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON body']);
        exit;
    }

    $cameraId = $data['camera_id'] ?? 'cam-1';
    $direction = strtoupper($data['direction'] ?? 'IN');
    $countVal = (int)($data['count_value'] ?? 1);
    $trackId = isset($data['track_id']) ? (int)$data['track_id'] : null;
    $confidence = isset($data['confidence']) ? (float)$data['confidence'] : 0.85;
    $timestamp = $data['event_timestamp'] ?? date('Y-m-d H:i:s');
    $sourceMode = $data['source_mode'] ?? 'LIVE';
    $metadata = isset($data['metadata_json']) ? (is_string($data['metadata_json']) ? $data['metadata_json'] : json_encode($data['metadata_json'])) : null;

    if (!in_array($direction, ['IN', 'OUT'])) {
        http_response_code(422);
        echo json_encode(['error' => 'Direction harus IN atau OUT']);
        exit;
    }

    $eventId = 'evt-' . uniqid();

    $sql = "INSERT INTO counting_events (id, camera_id, session_id, event_timestamp, direction, count_value, track_id, confidence, source_mode, metadata_json) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $eventId,
        $cameraId,
        $data['session_id'] ?? null,
        $timestamp,
        $direction,
        $countVal,
        $trackId,
        $confidence,
        $sourceMode,
        $metadata
    ]);

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Event penyeberangan tercatat',
        'event_id' => $eventId,
        'direction' => $direction,
        'timestamp' => $timestamp
    ]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
