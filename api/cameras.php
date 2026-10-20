<?php
/**
 * API Endpoint: /api/cameras.php
 * Mendukung:
 * - GET: Mengambil daftar kamera CCTV
 * - POST: Menambah kamera baru atau memperbarui koordinat garis virtual
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDBConnection();
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $stmt = $pdo->query("SELECT * FROM cameras ORDER BY created_at ASC");
        $cameras = $stmt->fetchAll();

        // Decode json line_coords
        foreach ($cameras as &$cam) {
            $cam['line_coords_obj'] = json_decode($cam['line_coords'], true) ?: [
                'x1' => 100, 'y1' => 360, 'x2' => 1180, 'y2' => 360,
                'direction_in' => 'down', 'offset' => 30
            ];
            // Mask URL bila sensitif
            if (strpos($cam['source_uri'], '@') !== false) {
                $cam['masked_uri'] = preg_replace('/:\/\/([^:]+):([^@]+)@/', '://$1:***@', $cam['source_uri']);
            } else {
                $cam['masked_uri'] = $cam['source_uri'];
            }
        }

        echo json_encode($cameras);
        exit;
    }

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON']);
            exit;
        }

        $action = $input['action'] ?? 'update_line';

        if ($action === 'update_line') {
            $cameraId = $input['camera_id'] ?? null;
            $lineCoords = $input['line_coords'] ?? null;

            if (!$cameraId || !$lineCoords) {
                http_response_code(422);
                echo json_encode(['error' => 'camera_id and line_coords required']);
                exit;
            }

            $lineJson = is_string($lineCoords) ? $lineCoords : json_encode($lineCoords);

            $stmt = $pdo->prepare("UPDATE cameras SET line_coords = ? WHERE id = ?");
            $stmt->execute([$lineJson, $cameraId]);

            echo json_encode(['success' => true, 'message' => 'Koordinat garis virtual berhasil diperbarui']);
            exit;
        }

        if ($action === 'create_camera') {
            $id = 'cam-' . uniqid();
            $name = $input['name'] ?? 'Kamera CCTV Baru';
            $location = $input['location'] ?? 'Area Restoran';
            $sourceType = $input['source_type'] ?? 'VIDEO_FILE';
            $sourceUri = $input['source_uri'] ?? 'samples/video_sample.mp4';
            $lineCoords = json_encode([
                'x1' => 100, 'y1' => 360, 'x2' => 1180, 'y2' => 360,
                'direction_in' => 'down', 'offset' => 30
            ]);

            $stmt = $pdo->prepare("INSERT INTO cameras (id, name, location, source_type, source_uri, line_coords) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$id, $name, $location, $sourceType, $sourceUri, $lineCoords]);

            echo json_encode(['success' => true, 'id' => $id, 'message' => 'Kamera berhasil ditambahkan']);
            exit;
        }

        if ($action === 'delete_camera') {
            $cameraId = $input['camera_id'] ?? null;
            if (!$cameraId) {
                http_response_code(422);
                echo json_encode(['error' => 'camera_id required']);
                exit;
            }
            $stmt = $pdo->prepare("DELETE FROM cameras WHERE id = ?");
            $stmt->execute([$cameraId]);
            echo json_encode(['success' => true, 'message' => 'Kamera berhasil dihapus']);
            exit;
        }

        if ($action === 'toggle_camera') {
            $cameraId = $input['camera_id'] ?? null;
            if (!$cameraId) {
                http_response_code(422);
                echo json_encode(['error' => 'camera_id required']);
                exit;
            }
            $stmt = $pdo->prepare("UPDATE cameras SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END WHERE id = ?");
            $stmt->execute([$cameraId]);
            echo json_encode(['success' => true, 'message' => 'Status koneksi kamera berhasil diperbarui']);
            exit;
        }
    }

    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
