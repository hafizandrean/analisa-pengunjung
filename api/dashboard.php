<?php
/**
 * API Endpoint: /api/dashboard.php
 * Mengembalikan metrik ringkasan operasional:
 * - Total pengunjung hari ini (IN, OUT, Total)
 * - Tren harian vs kemarin
 * - Total pengunjung minggu ini
 * - Tren mingguan vs minggu sebelumnya
 * - Rata-rata harian
 * - Jam tersibuk hari ini
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDBConnection();

    $cameraId = isset($_GET['camera_id']) && $_GET['camera_id'] !== 'all' ? trim($_GET['camera_id']) : null;
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $sevenDaysAgo = date('Y-m-d', strtotime('-7 days'));
    $fourteenDaysAgo = date('Y-m-d', strtotime('-14 days'));

    $camFilter = $cameraId ? "AND camera_id = :cam" : "";
    $params = $cameraId ? [':cam' => $cameraId] : [];

    // 1. Hari Ini (IN, OUT, Total)
    $todayInStmt = $pdo->prepare("SELECT COALESCE(SUM(count_value), 0) FROM counting_events WHERE DATE(event_timestamp) = :today AND direction = 'IN' $camFilter");
    $todayInStmt->execute(array_merge([':today' => $today], $params));
    $todayIn = (int)$todayInStmt->fetchColumn();

    $todayOutStmt = $pdo->prepare("SELECT COALESCE(SUM(count_value), 0) FROM counting_events WHERE DATE(event_timestamp) = :today AND direction = 'OUT' $camFilter");
    $todayOutStmt->execute(array_merge([':today' => $today], $params));
    $todayOut = (int)$todayOutStmt->fetchColumn();

    $totalToday = $todayIn + $todayOut;

    // 2. Kemarin (untuk tren)
    $yesterdayStmt = $pdo->prepare("SELECT COALESCE(SUM(count_value), 0) FROM counting_events WHERE DATE(event_timestamp) = :yesterday $camFilter");
    $yesterdayStmt->execute(array_merge([':yesterday' => $yesterday], $params));
    $yesterdayTotal = (int)$yesterdayStmt->fetchColumn();

    $trendToday = 0.0;
    if ($yesterdayTotal > 0) {
        $trendToday = round((($totalToday - $yesterdayTotal) / $yesterdayTotal) * 100, 1);
    }

    // 3. Minggu Ini (7 hari terakhir)
    $weekStmt = $pdo->prepare("SELECT COALESCE(SUM(count_value), 0) FROM counting_events WHERE DATE(event_timestamp) >= :week_start $camFilter");
    $weekStmt->execute(array_merge([':week_start' => $sevenDaysAgo], $params));
    $totalWeek = (int)$weekStmt->fetchColumn();

    // Minggu Sebelumnya (hari ke-8 s/d ke-14)
    $prevWeekStmt = $pdo->prepare("SELECT COALESCE(SUM(count_value), 0) FROM counting_events WHERE DATE(event_timestamp) >= :two_weeks_ago AND DATE(event_timestamp) < :week_start $camFilter");
    $prevWeekStmt->execute(array_merge([':two_weeks_ago' => $fourteenDaysAgo, ':week_start' => $sevenDaysAgo], $params));
    $lastWeekTotal = (int)$prevWeekStmt->fetchColumn();

    $trendWeek = 0.0;
    if ($lastWeekTotal > 0) {
        $trendWeek = round((($totalWeek - $lastWeekTotal) / $lastWeekTotal) * 100, 1);
    }

    $averageDaily = $totalWeek > 0 ? round($totalWeek / 7.0, 1) : 0.0;

    // 4. Jam Tersibuk Hari Ini
    // Ekstraksi jam: di SQLite strftime('%H', event_timestamp), di MySQL HOUR(event_timestamp)
    $isSqlite = ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite');
    $hourExpr = $isSqlite ? "CAST(strftime('%H', event_timestamp) AS INTEGER)" : "HOUR(event_timestamp)";

    $busiestSql = "SELECT $hourExpr as hour_val, SUM(count_value) as cnt 
                   FROM counting_events 
                   WHERE DATE(event_timestamp) = :today $camFilter 
                   GROUP BY hour_val 
                   ORDER BY cnt DESC 
                   LIMIT 1";
    $busiestStmt = $pdo->prepare($busiestSql);
    $busiestStmt->execute(array_merge([':today' => $today], $params));
    $busiestRow = $busiestStmt->fetch();

    $busiestHour = "-";
    $busiestCount = 0;
    if ($busiestRow && isset($busiestRow['hour_val'])) {
        $h = (int)$busiestRow['hour_val'];
        $busiestHour = sprintf("%02d:00 - %02d:00", $h, $h + 1);
        $busiestCount = (int)$busiestRow['cnt'];
    }

    // 5. Kamera Aktif
    $camCount = (int)$pdo->query("SELECT COUNT(*) FROM cameras WHERE is_active = 1")->fetchColumn();

    $currentDineIn = max(0, $todayIn - $todayOut);

    echo json_encode([
        'total_today' => $totalToday,
        'total_today_in' => $todayIn,
        'total_today_out' => $todayOut,
        'current_dine_in' => $currentDineIn,
        'total_week' => $totalWeek,
        'average_daily' => $averageDaily,
        'busiest_hour' => $busiestHour,
        'busiest_hour_count' => $busiestCount,
        'active_cameras' => $camCount,
        'worker_status' => 'ONLINE',
        'trend_today_vs_yesterday' => $trendToday,
        'trend_week_vs_last_week' => $trendWeek
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
