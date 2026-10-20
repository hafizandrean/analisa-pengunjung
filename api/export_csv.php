<?php
/**
 * API Endpoint: /api/export_csv.php
 * Mengunduh berkas CSV dari riwayat penyeberangan pengunjung
 */

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDBConnection();

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
        $term = "%$search%";
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }

    $whereSql = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

    $sql = "SELECT e.event_timestamp, c.name as camera_name, e.direction, e.count_value, e.track_id, e.confidence, e.source_mode 
            FROM counting_events e 
            LEFT JOIN cameras c ON e.camera_id = c.id 
            $whereSql 
            ORDER BY e.event_timestamp DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $filename = "riwayat_pengunjung_" . date('Ymd_His') . ".csv";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    // Output BOM untuk kompatibilitas Excel Windows
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Header baris CSV
    fputcsv($output, ['Waktu Kejadian (WIB)', 'Nama CCTV / Lokasi', 'Arah (IN/OUT)', 'Jumlah', 'Track ID', 'Tingkat Keyakinan (Confidence)', 'Mode Sumber']);

    foreach ($rows as $row) {
        fputcsv($output, [
            $row['event_timestamp'],
            $row['camera_name'] ?: 'Kamera CCTV',
            $row['direction'],
            $row['count_value'],
            $row['track_id'],
            $row['confidence'],
            $row['source_mode']
        ]);
    }

    fclose($output);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo "Gagal membuat berkas CSV: " . $e->getMessage();
}
