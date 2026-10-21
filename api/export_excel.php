<?php
/**
 * API Endpoint: /api/export_excel.php
 * Mengunduh berkas Microsoft Excel (.xls) berformat rapi dengan styling warna dan tabel
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

    $filename = "Laporan_Pengunjung_AyamBangJungko_" . date('Ymd_His') . ".xls";

    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Cache-Control: max-age=0");
    header("Pragma: no-cache");
    header("Expires: 0");

    // Total hitungan
    $totalRecords = count($rows);
    $totalIn = 0;
    $totalOut = 0;
    foreach ($rows as $r) {
        if ($r['direction'] === 'IN') $totalIn++;
        else $totalOut++;
    }

    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head>';
    echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
    echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Riwayat Pengunjung</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
    echo '<style>
        body { font-family: Arial, sans-serif; }
        .title { font-size: 16pt; font-weight: bold; color: #ea580c; text-align: left; }
        .subtitle { font-size: 10pt; color: #64748b; margin-bottom: 12px; }
        .meta-box { background-color: #f8fafc; border: 1px solid #e2e8f0; font-size: 9.5pt; }
        table { border-collapse: collapse; width: 100%; margin-top: 15px; }
        th { background-color: #0f172a; color: #ffffff; font-weight: bold; padding: 10px 8px; border: 1px solid #334155; font-size: 10pt; text-align: left; }
        td { padding: 8px 10px; border: 1px solid #cbd5e1; font-size: 9.5pt; vertical-align: middle; }
        .row-even { background-color: #f8fafc; }
        .badge-in { background-color: #d1fae5; color: #065f46; font-weight: bold; text-align: center; }
        .badge-out { background-color: #dbeafe; color: #1e40af; font-weight: bold; text-align: center; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
    </style>';
    echo '</head>';
    echo '<body>';

    echo '<table>';
    echo '<tr><td colspan="7" class="title">AYAM BANG JUNGKO — LAPORAN RIWAYAT PENGUNJUNG</td></tr>';
    echo '<tr><td colspan="7" class="subtitle">Sistem CCTV Visitor Counting & Crowd Analytics &bull; Waktu Ekspor: ' . date('d F Y, H:i:s') . ' WIB</td></tr>';
    echo '<tr><td colspan="7" style="height: 10px;"></td></tr>';

    // Summary Box
    echo '<tr>';
    echo '<td colspan="2" class="meta-box"><strong>Total Data:</strong> ' . number_format($totalRecords) . ' penyeberangan</td>';
    echo '<td colspan="2" class="meta-box" style="color: #065f46;"><strong>Total Masuk (IN):</strong> ' . number_format($totalIn) . ' orang</td>';
    echo '<td colspan="3" class="meta-box" style="color: #1e40af;"><strong>Total Keluar (OUT):</strong> ' . number_format($totalOut) . ' orang</td>';
    echo '</tr>';
    echo '<tr><td colspan="7" style="height: 10px;"></td></tr>';

    // Table Headers
    echo '<tr>';
    echo '<th style="width: 50px;" class="text-center">No</th>';
    echo '<th style="width: 180px;">Waktu Kunjungan (WIB)</th>';
    echo '<th style="width: 220px;">Kamera CCTV / Lokasi</th>';
    echo '<th style="width: 130px;" class="text-center">Arah Melintas</th>';
    echo '<th style="width: 90px;" class="text-center">ID Tamu</th>';
    echo '<th style="width: 110px;" class="text-center">Akurasi AI</th>';
    echo '<th style="width: 100px;" class="text-center">Mode Sumber</th>';
    echo '</tr>';

    // Table Rows
    $no = 1;
    foreach ($rows as $r) {
        $rowClass = ($no % 2 === 0) ? 'row-even' : '';
        $isMasuk = ($r['direction'] === 'IN');
        $directionStyle = $isMasuk ? 'badge-in' : 'badge-out';
        $directionText = $isMasuk ? 'MASUK (IN)' : 'KELUAR (OUT)';
        $confidencePct = number_format(($r['confidence'] ?: 0.85) * 100, 1) . '%';

        echo '<tr class="' . $rowClass . '">';
        echo '<td class="text-center">' . $no++ . '</td>';
        echo '<td>' . date('d/m/Y H:i:s', strtotime($r['event_timestamp'])) . '</td>';
        echo '<td>' . htmlspecialchars($r['camera_name'] ?: 'CCTV Pintu Masuk') . '</td>';
        echo '<td class="' . $directionStyle . '">' . $directionText . '</td>';
        echo '<td class="text-center">#' . htmlspecialchars($r['track_id'] ?: '-') . '</td>';
        echo '<td class="text-center">' . $confidencePct . '</td>';
        echo '<td class="text-center">' . htmlspecialchars($r['source_mode']) . '</td>';
        echo '</tr>';
    }

    echo '</table>';
    echo '</body></html>';
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo "Gagal membuat berkas Excel: " . $e->getMessage();
}
