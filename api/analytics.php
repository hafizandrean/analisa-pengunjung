<?php
/**
 * API Endpoint: /api/analytics.php
 * Mendukung query:
 * - ?type=hourly&date=YYYY-MM-DD
 * - ?type=daily&days=7
 * - ?type=peak-hours&date=YYYY-MM-DD
 * - ?type=comparison
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

try {
    $pdo = getDBConnection();
    $type = $_GET['type'] ?? 'hourly';
    $isSqlite = ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite');
    $hourExpr = $isSqlite ? "CAST(strftime('%H', event_timestamp) AS INTEGER)" : "HOUR(event_timestamp)";
    $dateExpr = $isSqlite ? "strftime('%Y-%m-%d', event_timestamp)" : "DATE(event_timestamp)";

    $currentLang = getSetting('language', 'id');
    $dayNames = ($currentLang === 'en') ? [
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
        7 => 'Sunday'
    ] : [
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
        7 => 'Minggu'
    ];

    if ($type === 'hourly') {
        $targetDate = isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date']) ? $_GET['date'] : date('Y-m-d');
        $cameraId = isset($_GET['camera_id']) && $_GET['camera_id'] !== 'all' ? $_GET['camera_id'] : null;

        $camFilter = $cameraId ? "AND camera_id = :cam" : "";
        $params = [':target_date' => $targetDate];
        if ($cameraId) $params[':cam'] = $cameraId;

        $sql = "SELECT 
                    $hourExpr as hour_val,
                    SUM(CASE WHEN direction = 'IN' THEN count_value ELSE 0 END) as in_cnt,
                    SUM(CASE WHEN direction = 'OUT' THEN count_value ELSE 0 END) as out_cnt
                FROM counting_events 
                WHERE $dateExpr = :target_date $camFilter
                GROUP BY hour_val 
                ORDER BY hour_val ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $hourMap = [];
        foreach ($rows as $r) {
            $h = (int)$r['hour_val'];
            $hourMap[$h] = [
                'in' => (int)$r['in_cnt'],
                'out' => (int)$r['out_cnt']
            ];
        }

        // Rentang jam grafik: adaptif sesuai jam operasional di Pengaturan
        // serta otomatis melebar jika restoran buka lebih awal (pagi) atau tutup lebih larut (lembur)
        $openTime = getSetting('opening_time', '10:00');
        $closeTime = getSetting('closing_time', '22:00');
        $openH = (int)explode(':', $openTime)[0];
        $closeH = (int)explode(':', $closeTime)[0];
        if ($closeH <= $openH) $closeH = 22;

        $recordedHours = array_keys($hourMap);
        $minH = !empty($recordedHours) ? min(min($recordedHours), $openH) : $openH;
        $maxH = !empty($recordedHours) ? max(max($recordedHours), $closeH) : $closeH;

        $result = [];
        for ($h = $minH; $h <= $maxH; $h++) {
            $inVal = $hourMap[$h]['in'] ?? 0;
            $outVal = $hourMap[$h]['out'] ?? 0;
            $result[] = [
                'hour' => sprintf("%02d:00", $h),
                'count_in' => $inVal,
                'count_out' => $outVal,
                'total' => $inVal + $outVal
            ];
        }

        echo json_encode($result);
        exit;
    }

    if ($type === 'daily') {
        $days = isset($_GET['days']) ? min(30, max(1, (int)$_GET['days'])) : 7;
        $startDate = date('Y-m-d', strtotime("-" . ($days - 1) . " days"));

        $sql = "SELECT 
                    $dateExpr as day_date,
                    SUM(CASE WHEN direction = 'IN' THEN count_value ELSE 0 END) as in_cnt,
                    SUM(CASE WHEN direction = 'OUT' THEN count_value ELSE 0 END) as out_cnt
                FROM counting_events 
                WHERE $dateExpr >= :start_date
                GROUP BY day_date 
                ORDER BY day_date ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':start_date' => $startDate]);
        $rows = $stmt->fetchAll();

        $dateMap = [];
        foreach ($rows as $r) {
            $dateMap[$r['day_date']] = [
                'in' => (int)$r['in_cnt'],
                'out' => (int)$r['out_cnt']
            ];
        }

        $result = [];
        for ($i = 0; $i < $days; $i++) {
            $d = date('Y-m-d', strtotime("$startDate +$i days"));
            $inVal = $dateMap[$d]['in'] ?? 0;
            $outVal = $dateMap[$d]['out'] ?? 0;
            $dayOfWeek = (int)date('N', strtotime($d));
            $result[] = [
                'date' => $d,
                'day_name' => $dayNames[$dayOfWeek],
                'count_in' => $inVal,
                'count_out' => $outVal,
                'total' => $inVal + $outVal
            ];
        }

        echo json_encode($result);
        exit;
    }

    if ($type === 'peak-hours') {
        $targetDate = isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date']) ? $_GET['date'] : date('Y-m-d');

        // Total hari tersebut
        $totalStmt = $pdo->prepare("SELECT COALESCE(SUM(count_value), 0) FROM counting_events WHERE $dateExpr = :target_date");
        $totalStmt->execute([':target_date' => $targetDate]);
        $totalDay = max(1, (int)$totalStmt->fetchColumn());

        $sql = "SELECT 
                    $hourExpr as hour_val,
                    SUM(count_value) as cnt
                FROM counting_events 
                WHERE $dateExpr = :target_date
                GROUP BY hour_val 
                ORDER BY cnt DESC 
                LIMIT 5";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':target_date' => $targetDate]);
        $rows = $stmt->fetchAll();

        $peakItems = [];
        foreach ($rows as $r) {
            if (!isset($r['hour_val'])) continue;
            $h = (int)$r['hour_val'];
            $cnt = (int)$r['cnt'];
            $pct = round(($cnt / $totalDay) * 100, 1);

            $label = ($h >= 11 && $h <= 14) ? "Makan Siang" : (($h >= 17 && $h <= 20) ? "Makan Malam" : "Sore Hari");
            $occupancy = ($pct >= 20.0) ? "PADAT" : (($pct >= 10.0) ? "SEDANG" : "LENGANG");

            $peakItems[] = [
                'time_window' => sprintf("%02d:00 - %02d:00 (%s)", $h, $h + 1, $label),
                'count' => $cnt,
                'occupancy_status' => $occupancy,
                'percentage_of_daily' => $pct
            ];
        }

        echo json_encode($peakItems);
        exit;
    }

    if ($type === 'comparison') {
        $today = date('Y-m-d');
        $result = [];

        for ($i = 0; $i < 7; $i++) {
            $currD = date('Y-m-d', strtotime("$today -" . (6 - $i) . " days"));
            $prevD = date('Y-m-d', strtotime("$currD -7 days"));

            $currStmt = $pdo->prepare("SELECT COALESCE(SUM(count_value), 0) FROM counting_events WHERE $dateExpr = :d");
            $currStmt->execute([':d' => $currD]);
            $currCnt = (int)$currStmt->fetchColumn();

            $prevStmt = $pdo->prepare("SELECT COALESCE(SUM(count_value), 0) FROM counting_events WHERE $dateExpr = :d");
            $prevStmt->execute([':d' => $prevD]);
            $prevCnt = (int)$prevStmt->fetchColumn();

            $dayNum = (int)date('N', strtotime($currD));
            $pctChange = 0.0;
            if ($prevCnt > 0) {
                $pctChange = round((($currCnt - $prevCnt) / $prevCnt) * 100, 1);
            }

            $result[] = [
                'label' => $dayNames[$dayNum],
                'current_period_count' => $currCnt,
                'previous_period_count' => $prevCnt,
                'percentage_change' => $pctChange
            ];
        }

        echo json_encode($result);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Unknown analytics type']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
