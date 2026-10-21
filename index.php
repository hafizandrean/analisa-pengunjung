<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/database.php';

$pageTitle = 'Dashboard Ringkasan';
$pdo = getDBConnection();

$selectedCamera = $_GET['camera_id'] ?? 'all';
$camFilter = ($selectedCamera !== 'all') ? "AND camera_id = :cam" : "";
$params = ($selectedCamera !== 'all') ? [':cam' => $selectedCamera] : [];

// Ambil daftar kamera untuk dropdown filter
$cameras = $pdo->query("SELECT id, name, location FROM cameras WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

// 1. Data Ringkasan Hari Ini
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$sevenDaysAgo = date('Y-m-d', strtotime('-7 days'));
$fourteenDaysAgo = date('Y-m-d', strtotime('-14 days'));

// Hari Ini
$inStmt = $pdo->prepare("SELECT COALESCE(SUM(count_value), 0) FROM counting_events WHERE DATE(event_timestamp) = :d AND direction = 'IN' $camFilter");
$inStmt->execute(array_merge([':d' => $today], $params));
$todayIn = (int)$inStmt->fetchColumn();

$outStmt = $pdo->prepare("SELECT COALESCE(SUM(count_value), 0) FROM counting_events WHERE DATE(event_timestamp) = :d AND direction = 'OUT' $camFilter");
$outStmt->execute(array_merge([':d' => $today], $params));
$todayOut = (int)$outStmt->fetchColumn();
$totalToday = $todayIn + $todayOut;

// Kemarin
$yestStmt = $pdo->prepare("SELECT COALESCE(SUM(count_value), 0) FROM counting_events WHERE DATE(event_timestamp) = :d $camFilter");
$yestStmt->execute(array_merge([':d' => $yesterday], $params));
$yesterdayTotal = (int)$yestStmt->fetchColumn();
$trendToday = $yesterdayTotal > 0 ? round((($totalToday - $yesterdayTotal) / $yesterdayTotal) * 100, 1) : 0;

// Minggu Ini (7 hari)
$weekStmt = $pdo->prepare("SELECT COALESCE(SUM(count_value), 0) FROM counting_events WHERE DATE(event_timestamp) >= :w $camFilter");
$weekStmt->execute(array_merge([':w' => $sevenDaysAgo], $params));
$totalWeek = (int)$weekStmt->fetchColumn();

// Minggu Lalu
$lastWeekStmt = $pdo->prepare("SELECT COALESCE(SUM(count_value), 0) FROM counting_events WHERE DATE(event_timestamp) >= :w1 AND DATE(event_timestamp) < :w2 $camFilter");
$lastWeekStmt->execute(array_merge([':w1' => $fourteenDaysAgo, ':w2' => $sevenDaysAgo], $params));
$lastWeekTotal = (int)$lastWeekStmt->fetchColumn();
$trendWeek = $lastWeekTotal > 0 ? round((($totalWeek - $lastWeekTotal) / $lastWeekTotal) * 100, 1) : 0;

$averageDaily = $totalWeek > 0 ? round($totalWeek / 7.0, 1) : 0;

// Jam Tersibuk
$isSqlite = ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite');
$hourExpr = $isSqlite ? "CAST(strftime('%H', event_timestamp) AS INTEGER)" : "HOUR(event_timestamp)";
$busiestStmt = $pdo->prepare("SELECT $hourExpr as h, SUM(count_value) as cnt FROM counting_events WHERE DATE(event_timestamp) = :d $camFilter GROUP BY h ORDER BY cnt DESC LIMIT 1");
$busiestStmt->execute(array_merge([':d' => $today], $params));
$busiestRow = $busiestStmt->fetch();
$busiestHour = ($busiestRow && isset($busiestRow['h'])) ? sprintf("%02d:00 - %02d:00", $busiestRow['h'], $busiestRow['h'] + 1) : "Belum Ada Data";
$busiestCount = ($busiestRow && isset($busiestRow['cnt'])) ? (int)$busiestRow['cnt'] : 0;

// 8 Event Terakhir
$recentStmt = $pdo->prepare("SELECT e.*, c.name as camera_name FROM counting_events e LEFT JOIN cameras c ON e.camera_id = c.id $camFilter ORDER BY e.event_timestamp DESC LIMIT 8");
$recentStmt->execute($params);
$recentEvents = $recentStmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<!-- Header Title & Filter Bar -->
<div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
    <div>
        <h2 class="text-2xl font-bold tracking-tight text-slate-900"><?= __('dash_title') ?></h2>
        <p class="text-xs text-slate-500 mt-1"><?= __('dash_subtitle') ?></p>
    </div>

    <!-- Filter Form -->
    <form method="GET" action="index.php" class="flex flex-wrap items-center gap-3 bg-white p-2 rounded-2xl border border-slate-200 shadow-sm">
        <div class="flex items-center gap-2 pl-2 text-xs font-semibold text-slate-500 uppercase">
            <i data-lucide="filter" class="w-3.5 h-3.5 text-slate-400"></i>
            <span><?= __('filter_camera') ?></span>
        </div>
        <select name="camera_id" onchange="this.form.submit()" class="text-xs font-medium bg-slate-50 border border-slate-200 rounded-xl px-3 py-1.5 text-slate-700 focus:outline-none focus:border-orange-500">
            <option value="all"><?= __('all_cctv_cams') ?></option>
            <?php foreach ($cameras as $cam): ?>
                <option value="<?= htmlspecialchars($cam['id']) ?>" <?= $selectedCamera === $cam['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cam['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="p-1.5 bg-orange-50 hover:bg-orange-100 text-orange-600 rounded-lg transition-colors" title="Muat Ulang">
            <i data-lucide="refresh-cw" class="w-4 h-4"></i>
        </button>
    </form>
</div>

<!-- 4 Metric Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    <!-- Card 1: Hari Ini -->
    <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider"><?= __('metric_today') ?></span>
            <div class="w-9 h-9 rounded-xl bg-orange-50 text-orange-600 flex items-center justify-center">
                <i data-lucide="users" class="w-5 h-5"></i>
            </div>
        </div>
        <div class="flex items-center justify-between mb-2">
            <div class="flex items-baseline gap-2">
                <span class="text-3xl font-bold text-slate-900" id="dashTotalToday"><?= number_format($totalToday) ?></span>
                <span class="text-xs text-slate-400 font-medium"><?= __('unit_people') ?></span>
            </div>
            <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-orange-50 border border-orange-200 text-orange-700 text-xs font-bold" title="Tamu yang saat ini sedang berada di dalam restoran">
                <span class="w-2 h-2 rounded-full bg-orange-500 animate-pulse"></span>
                <span><?= __('metric_inside') ?> <strong id="dashDineIn"><?= max(0, $todayIn - $todayOut) ?></strong></span>
            </div>
        </div>
        <div class="flex items-center justify-between text-xs pt-2 border-t border-slate-100">
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center text-emerald-600 font-semibold">
                    <i data-lucide="arrow-down-left" class="w-3.5 h-3.5"></i> <?= __('label_in') ?> <?= number_format($todayIn) ?>
                </span>
                <span class="text-slate-300">&bull;</span>
                <span class="inline-flex items-center text-blue-600 font-semibold">
                    <i data-lucide="arrow-up-right" class="w-3.5 h-3.5"></i> <?= __('label_out') ?> <?= number_format($todayOut) ?>
                </span>
            </div>
            <span class="text-[11px] font-semibold <?= $trendToday >= 0 ? 'text-emerald-600' : 'text-rose-600' ?>">
                <?= ($trendToday >= 0 ? '+' : '') . $trendToday ?>% <?= __('vs_yesterday') ?>
            </span>
        </div>
    </div>

    <!-- Card 2: Minggu Ini -->
    <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider"><?= __('metric_week') ?></span>
            <div class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                <i data-lucide="calendar" class="w-5 h-5"></i>
            </div>
        </div>
        <div class="flex items-baseline gap-2 mb-2">
            <span class="text-3xl font-bold text-slate-900"><?= number_format($totalWeek) ?></span>
            <span class="text-xs text-slate-400 font-medium"><?= __('unit_people') ?></span>
        </div>
        <div class="flex items-center justify-between text-xs pt-2 border-t border-slate-100">
            <span class="text-slate-500"><?= __('active_days') ?></span>
            <span class="text-[11px] font-semibold <?= $trendWeek >= 0 ? 'text-emerald-600' : 'text-rose-600' ?>">
                <?= ($trendWeek >= 0 ? '+' : '') . $trendWeek ?>% <?= __('vs_last_week') ?>
            </span>
        </div>
    </div>

    <!-- Card 3: Rata-rata Harian -->
    <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider"><?= __('metric_avg_daily') ?></span>
            <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                <i data-lucide="trending-up" class="w-5 h-5"></i>
            </div>
        </div>
        <div class="flex items-baseline gap-2 mb-2">
            <span class="text-3xl font-bold text-slate-900"><?= number_format($averageDaily, 1) ?></span>
            <span class="text-xs text-slate-400 font-medium"><?= __('unit_people_per_day', 'orang / hari') ?></span>
        </div>
        <div class="flex items-center justify-between text-xs pt-2 border-t border-slate-100">
            <span class="text-slate-500"><?= __('table_readiness') ?></span>
            <span class="text-[11px] font-medium text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md"><?= __('status_optimal') ?></span>
        </div>
    </div>

    <!-- Card 4: Jam Tersibuk -->
    <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider"><?= __('metric_busiest_hour') ?></span>
            <div class="w-9 h-9 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center">
                <i data-lucide="clock" class="w-5 h-5"></i>
            </div>
        </div>
        <div class="flex items-baseline gap-2 mb-2">
            <span class="text-xl font-bold text-slate-900 truncate"><?= htmlspecialchars($busiestHour) ?></span>
        </div>
        <div class="flex items-center justify-between text-xs pt-2 border-t border-slate-100">
            <span class="text-slate-500"><?= __('peak_volume') ?></span>
            <span class="text-xs font-bold text-purple-700 bg-purple-50 px-2.5 py-0.5 rounded-md">
                <?= number_format($busiestCount) ?> <?= __('crossings') ?>
            </span>
        </div>
    </div>
</div>

<!-- Charts Section -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Chart: Distribusi Per Jam (2 Col) -->
    <div class="lg:col-span-2 bg-white rounded-3xl p-6 border border-slate-200/80 shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-sm font-bold text-slate-900"><?= __('chart_hourly_title') ?></h3>
                <p class="text-xs text-slate-500 mt-0.5"><?= __('chart_hourly_subtitle') ?></p>
            </div>
            <div class="flex items-center gap-3 text-xs font-medium">
                <span class="flex items-center gap-1.5 text-emerald-600"><span class="w-3 h-3 rounded-md bg-emerald-500"></span> <?= __('badge_in') ?> (IN)</span>
                <span class="flex items-center gap-1.5 text-blue-600"><span class="w-3 h-3 rounded-md bg-blue-500"></span> <?= __('badge_out') ?> (OUT)</span>
            </div>
        </div>
        <div class="h-72 w-full">
            <canvas id="hourlyChart"></canvas>
        </div>
    </div>

    <!-- Chart: Tren Harian 7 Hari Terakhir (1 Col) -->
    <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-sm font-bold text-slate-900"><?= __('chart_daily_title') ?></h3>
                <p class="text-xs text-slate-500 mt-0.5"><?= __('chart_daily_subtitle') ?></p>
            </div>
        </div>
        <div class="h-72 w-full">
            <canvas id="dailyChart"></canvas>
        </div>
    </div>
</div>

<!-- Bottom Section: Video Preview / Worker Status & Recent Table -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Status CCTV & AI Worker Status -->
    <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-sm flex flex-col justify-between">
        <div>
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                    <i data-lucide="video" class="w-4 h-4 text-orange-500"></i>
                    <span><?= __('cctv_status_title') ?></span>
                </h3>
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-ping"></span>
                    ONLINE
                </span>
            </div>

            <!-- Video Live Feed Stream / Preview -->
            <div class="relative rounded-2xl overflow-hidden bg-slate-950 aspect-video flex items-center justify-center border border-slate-800 shadow-inner">
                <img id="dashLiveStream" src="http://127.0.0.1:8080/video_feed" class="w-full h-full object-cover hidden" alt="Live Feed" />
                <div id="dashLiveFallback" class="flex flex-col items-center justify-center p-4 text-center">
                    <i data-lucide="video" class="w-8 h-8 text-slate-600 mb-2"></i>
                    <div class="text-xs font-bold text-slate-300"><?= __('cctv_status_standby') ?></div>
                    <div class="text-[11px] text-slate-500 mt-1"><?= __('cctv_status_standby_desc') ?></div>
                </div>
                <div id="dashStreamBadge" class="absolute top-2 right-2 bg-emerald-500/20 border border-emerald-500/30 text-emerald-400 px-2 py-0.5 rounded-lg text-[10px] font-bold hidden flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span>LIVE CCTV</span>
                </div>
            </div>

            <div class="mt-4 space-y-2 text-xs">
                <div class="flex justify-between py-1.5 border-b border-slate-100 text-slate-600">
                    <span>Pendeteksi:</span>
                    <span class="font-semibold text-slate-800"><?= __('detector_auto') ?></span>
                </div>
                <div class="flex justify-between py-1.5 border-b border-slate-100 text-slate-600">
                    <span>Penyimpanan:</span>
                    <span class="font-semibold text-emerald-600"><?= __('storage_live') ?></span>
                </div>
                <div class="flex justify-between py-1.5 text-slate-600">
                    <span>Keamanan:</span>
                    <span class="font-semibold text-slate-700"><?= __('security_safe') ?></span>
                </div>
            </div>
        </div>

        <div class="pt-4 mt-4 border-t border-slate-100">
            <a href="cameras.php" class="w-full inline-flex items-center justify-center gap-2 py-2.5 px-4 rounded-xl bg-slate-100 hover:bg-slate-200 text-xs font-semibold text-slate-700 transition-colors">
                <i data-lucide="sliders" class="w-3.5 h-3.5"></i>
                <span><?= __('btn_adjust_line') ?></span>
            </a>
        </div>
    </div>

    <!-- Recent Events Table (2 Cols) -->
    <div class="lg:col-span-2 bg-white rounded-3xl p-6 border border-slate-200/80 shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-sm font-bold text-slate-900"><?= __('recent_activity_title') ?></h3>
                <p class="text-xs text-slate-500 mt-0.5"><?= __('recent_activity_subtitle') ?></p>
            </div>
            <a href="history.php" class="text-xs font-semibold text-orange-600 hover:text-orange-700 flex items-center gap-1">
                <span><?= __('view_all') ?></span>
                <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-100 text-slate-400 uppercase font-semibold text-[10px]">
                        <th class="py-2.5 px-3"><?= __('th_time') ?></th>
                        <th class="py-2.5 px-3"><?= __('th_camera') ?></th>
                        <th class="py-2.5 px-3"><?= __('th_direction') ?></th>
                        <th class="py-2.5 px-3 text-center"><?= __('th_track_id') ?></th>
                        <th class="py-2.5 px-3 text-right"><?= __('th_confidence') ?></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    <?php if (empty($recentEvents)): ?>
                        <tr>
                            <td colspan="5" class="py-8 text-center text-slate-400"><?= __('no_recent_events') ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentEvents as $evt): ?>
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="py-2.5 px-3 font-medium text-slate-900">
                                    <?= date('H:i:s', strtotime($evt['event_timestamp'])) ?>
                                    <span class="text-[10px] text-slate-400 block"><?= date('d/m/Y', strtotime($evt['event_timestamp'])) ?></span>
                                </td>
                                <td class="py-2.5 px-3 text-slate-600">
                                    <?= htmlspecialchars($evt['camera_name'] ?: 'CCTV') ?>
                                </td>
                                <td class="py-2.5 px-3">
                                    <?php if ($evt['direction'] === 'IN'): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            <i data-lucide="arrow-down-left" class="w-3 h-3"></i> <?= __('badge_in') ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                                            <i data-lucide="arrow-up-right" class="w-3 h-3"></i> <?= __('badge_out') ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-2.5 px-3 text-center font-mono text-slate-500">
                                    #<?= htmlspecialchars($evt['track_id'] ?: '-') ?>
                                </td>
                                <td class="py-2.5 px-3 text-right font-medium text-slate-600">
                                    <?= number_format($evt['confidence'] * 100, 1) ?>%
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Chart.js AJAX Rendering Scripts -->
<script>
document.addEventListener('DOMContentLoaded', async function() {
    const selectedCam = '<?= htmlspecialchars($selectedCamera) ?>';

    // 1. Fetch & Render Hourly Chart
    try {
        const hourlyRes = await fetch(`api/analytics.php?type=hourly&camera_id=${selectedCam}`);
        const hourlyData = await hourlyRes.json();

        const labels = hourlyData.map(d => d.hour);
        const inData = hourlyData.map(d => d.count_in);
        const outData = hourlyData.map(d => d.count_out);

        const ctxH = document.getElementById('hourlyChart').getContext('2d');
        new Chart(ctxH, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Masuk (IN)',
                        data: inData,
                        backgroundColor: '#10b981',
                        borderRadius: 6,
                    },
                    {
                        label: 'Keluar (OUT)',
                        data: outData,
                        backgroundColor: '#3b82f6',
                        borderRadius: 6,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true, grid: { color: '#f1f5f9' } }
                }
            }
        });
    } catch (e) {
        console.error('Error loading hourly chart:', e);
    }

    // 2. Fetch & Render Daily Chart
    try {
        const dailyRes = await fetch(`api/analytics.php?type=daily&days=7`);
        const dailyData = await dailyRes.json();

        const dLabels = dailyData.map(d => d.day_name);
        const dTotals = dailyData.map(d => d.total);

        const ctxD = document.getElementById('dailyChart').getContext('2d');
        new Chart(ctxD, {
            type: 'bar',
            data: {
                labels: dLabels,
                datasets: [{
                    label: 'Total Pengunjung',
                    data: dTotals,
                    backgroundColor: '#f97316',
                    borderRadius: 8,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true, grid: { color: '#f1f5f9' } }
                }
            }
        });
    } catch (e) {
        console.error('Error loading daily chart:', e);
    }

    // 3. Live CCTV Feed & Status Poller
    async function checkDashStream() {
        try {
            const res = await fetch('api/stream_control.php');
            const data = await res.json();
            const img = document.getElementById('dashLiveStream');
            const fallback = document.getElementById('dashLiveFallback');
            const badge = document.getElementById('dashStreamBadge');

            if (data.running) {
                if (img && img.classList.contains('hidden')) {
                    img.src = 'http://127.0.0.1:8080/video_feed?' + new Date().getTime();
                    img.classList.remove('hidden');
                }
                if (fallback) fallback.classList.add('hidden');
                if (badge) badge.classList.remove('hidden');
            } else {
                if (img) img.classList.add('hidden');
                if (fallback) fallback.classList.remove('hidden');
                if (badge) badge.classList.add('hidden');
            }
        } catch (e) {}
    }

    checkDashStream();
    setInterval(checkDashStream, 4000);
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
