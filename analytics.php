<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/database.php';

$pageTitle = 'Analisa Pola Keramaian';
$pdo = getDBConnection();

$selectedDate = isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date']) ? $_GET['date'] : date('Y-m-d');
$isSqlite = ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite');
$dateExpr = $isSqlite ? "strftime('%Y-%m-%d', event_timestamp)" : "DATE(event_timestamp)";
$hourExpr = $isSqlite ? "CAST(strftime('%H', event_timestamp) AS INTEGER)" : "HOUR(event_timestamp)";

// Total pengunjung pada tanggal terpilih
$totStmt = $pdo->prepare("SELECT COALESCE(SUM(count_value), 0) FROM counting_events WHERE $dateExpr = :d");
$totStmt->execute([':d' => $selectedDate]);
$totalDay = (int)$totStmt->fetchColumn();

// Hitung persentase shift secara aman
$lunchPct = ($totalDay > 0) ? round(($lunchCount ?? 0) / $totalDay * 100, 1) : 0;
$afternoonPct = ($totalDay > 0) ? round(($afternoonCount ?? 0) / $totalDay * 100, 1) : 0;
$dinnerPct = ($totalDay > 0) ? round(($dinnerCount ?? 0) / $totalDay * 100, 1) : 0;

// Peak Hours Query
$peakSql = "SELECT $hourExpr as h, SUM(count_value) as cnt 
            FROM counting_events 
            WHERE $dateExpr = :d 
            GROUP BY h 
            ORDER BY cnt DESC 
            LIMIT 5";
$peakStmt = $pdo->prepare($peakSql);
$peakStmt->execute([':d' => $selectedDate]);
$peakRows = $peakStmt->fetchAll();

// Hitung total per Shift Ayam Bang Jungko
// Shift 1: Makan Siang (11:00 - 14:59)
$lunchStmt = $pdo->prepare("SELECT COALESCE(SUM(count_value), 0) FROM counting_events WHERE $dateExpr = :d AND $hourExpr BETWEEN 11 AND 14");
$lunchStmt->execute([':d' => $selectedDate]);
$lunchCount = (int)$lunchStmt->fetchColumn();
$lunchPct = ($totalDay > 0) ? round(($lunchCount / $totalDay) * 100, 1) : 0;

// Shift 2: Sore Hari (15:00 - 17:59)
$afternoonStmt = $pdo->prepare("SELECT COALESCE(SUM(count_value), 0) FROM counting_events WHERE $dateExpr = :d AND $hourExpr BETWEEN 15 AND 17");
$afternoonStmt->execute([':d' => $selectedDate]);
$afternoonCount = (int)$afternoonStmt->fetchColumn();
$afternoonPct = ($totalDay > 0) ? round(($afternoonCount / $totalDay) * 100, 1) : 0;

// Shift 3: Makan Malam (18:00 - 21:00)
$dinnerStmt = $pdo->prepare("SELECT COALESCE(SUM(count_value), 0) FROM counting_events WHERE $dateExpr = :d AND $hourExpr BETWEEN 18 AND 21");
$dinnerStmt->execute([':d' => $selectedDate]);
$dinnerCount = (int)$dinnerStmt->fetchColumn();
$dinnerPct = ($totalDay > 0) ? round(($dinnerCount / $totalDay) * 100, 1) : 0;

include __DIR__ . '/includes/header.php';
?>

<!-- Title & Date Filter -->
<div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
    <div>
        <h2 class="text-2xl font-bold tracking-tight text-slate-900">Waktu Ramai Pengunjung</h2>
        <p class="text-xs text-slate-500 mt-1">Melihat jam berapa restoran paling ramai agar staf dan meja makan bisa disiapkan lebih awal.</p>
    </div>

    <!-- Date Picker Form -->
    <form method="GET" action="analytics.php" class="flex items-center gap-3 bg-white p-2 rounded-2xl border border-slate-200 shadow-sm">
        <div class="flex items-center gap-2 pl-2 text-xs font-semibold text-slate-500 uppercase">
            <i data-lucide="calendar" class="w-3.5 h-3.5 text-slate-400"></i>
            <span>Pilih Tanggal:</span>
        </div>
        <input type="date" name="date" value="<?= htmlspecialchars($selectedDate) ?>" onchange="this.form.submit()"
               class="text-xs font-medium bg-slate-50 border border-slate-200 rounded-xl px-3 py-1.5 text-slate-700 focus:outline-none focus:border-orange-500">
        <button type="submit" class="text-xs font-semibold bg-orange-500 hover:bg-orange-600 text-white px-3 py-1.5 rounded-xl transition-all">
            Terapkan
        </button>
    </form>
</div>

<!-- 3 Shift Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-5">
    <!-- Makan Siang -->
    <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-bold text-amber-600 bg-amber-50 px-2.5 py-1 rounded-lg">Shift Makan Siang</span>
            <span class="text-[11px] text-slate-400">11:00 - 14:00 WIB</span>
        </div>
        <div class="text-3xl font-bold text-slate-900 my-2"><?= number_format($lunchCount) ?> <span class="text-xs text-slate-400 font-normal">pengunjung</span></div>
        <div class="w-full bg-slate-100 rounded-full h-2 mt-3 overflow-hidden">
            <div class="bg-amber-500 h-2 rounded-full" style="width: <?= $lunchPct ?>%"></div>
        </div>
        <div class="text-[11px] text-slate-500 mt-2 flex justify-between">
            <span>Kontribusi:</span>
            <span class="font-bold text-slate-700"><?= $lunchPct ?>% dari total harian</span>
        </div>
    </div>

    <!-- Sore Hari -->
    <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-bold text-slate-600 bg-slate-100 px-2.5 py-1 rounded-lg">Shift Sore Hari</span>
            <span class="text-[11px] text-slate-400">15:00 - 17:00 WIB</span>
        </div>
        <div class="text-3xl font-bold text-slate-900 my-2"><?= number_format($afternoonCount) ?> <span class="text-xs text-slate-400 font-normal">pengunjung</span></div>
        <div class="w-full bg-slate-100 rounded-full h-2 mt-3 overflow-hidden">
            <div class="bg-slate-400 h-2 rounded-full" style="width: <?= $afternoonPct ?>%"></div>
        </div>
        <div class="text-[11px] text-slate-500 mt-2 flex justify-between">
            <span>Kontribusi:</span>
            <span class="font-bold text-slate-700"><?= $afternoonPct ?>% dari total harian</span>
        </div>
    </div>

    <!-- Makan Malam (Peak) -->
    <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-bold text-orange-600 bg-orange-50 px-2.5 py-1 rounded-lg">Shift Makan Malam (Puncak)</span>
            <span class="text-[11px] text-slate-400">18:00 - 21:00 WIB</span>
        </div>
        <div class="text-3xl font-bold text-slate-900 my-2"><?= number_format($dinnerCount) ?> <span class="text-xs text-slate-400 font-normal">pengunjung</span></div>
        <div class="w-full bg-slate-100 rounded-full h-2 mt-3 overflow-hidden">
            <div class="bg-orange-500 h-2 rounded-full" style="width: <?= $dinnerPct ?>%"></div>
        </div>
        <div class="text-[11px] text-slate-500 mt-2 flex justify-between">
            <span>Kontribusi:</span>
            <span class="font-bold text-slate-700"><?= $dinnerPct ?>% dari total harian</span>
        </div>
    </div>
</div>

<!-- Peak Hours Classification Table & Chart -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Peak Hours List (1 Col) -->
    <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-sm">
        <h3 class="text-sm font-bold text-slate-900 mb-1">Peringkat Jam Tersibuk (Peak Hours)</h3>
        <p class="text-xs text-slate-500 mb-4">Klasifikasi tingkat kepadatan berdasarkan persentase pengunjung.</p>

        <div class="space-y-3">
            <?php if (empty($peakRows)): ?>
                <div class="py-8 text-center text-xs text-slate-400">Tidak ada data penyeberangan pada tanggal ini.</div>
            <?php else: ?>
                <?php foreach ($peakRows as $idx => $r): 
                    $h = (int)$r['h'];
                    $cnt = (int)$r['cnt'];
                    $pct = round(($cnt / $totalDay) * 100, 1);
                    $shift = ($h >= 11 && $h <= 14) ? "Makan Siang" : (($h >= 18 && $h <= 20) ? "Makan Malam" : "Sore Hari");
                    
                    if ($pct >= 20.0) {
                        $statusBadge = '<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">PADAT</span>';
                    } elseif ($pct >= 10.0) {
                        $statusBadge = '<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">SEDANG</span>';
                    } else {
                        $statusBadge = '<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">LENGANG</span>';
                    }
                ?>
                    <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-between">
                        <div>
                            <div class="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                                <span>#<?= $idx + 1 ?> &bull; <?= sprintf("%02d:00 - %02d:00", $h, $h + 1) ?></span>
                                <span class="text-[10px] font-medium text-slate-500">(<?= $shift ?>)</span>
                            </div>
                            <div class="text-[11px] text-slate-500 mt-1">
                                <strong><?= number_format($cnt) ?></strong> melintas &bull; <strong><?= $pct ?>%</strong> dari total
                            </div>
                        </div>
                        <div>
                            <?= $statusBadge ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="mt-5 p-3.5 rounded-2xl bg-orange-50/60 border border-orange-200/60 text-[11px] text-orange-800">
            <strong>Saran untuk Restoran:</strong><br>
            Siapkan tambahan pelayan saat kondisi <span class="font-bold text-rose-600">PADAT</span> agar meja cepat bersih dan pelanggan tidak lama menunggu.
        </div>
    </div>

    <!-- Chart Hourly Profile (2 Cols) -->
    <div class="lg:col-span-2 bg-white rounded-3xl p-6 border border-slate-200/80 shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-sm font-bold text-slate-900">Kurva Distribusi Kepadatan (<?= date('d M Y', strtotime($selectedDate)) ?>)</h3>
                <p class="text-xs text-slate-500 mt-0.5">Visualisasi lonjakan jam makan siang dan makan malam</p>
            </div>
        </div>
        <div class="h-80 w-full">
            <canvas id="analyticsHourlyChart"></canvas>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async function() {
    try {
        const dateParam = '<?= $selectedDate ?>';
        const res = await fetch(`api/analytics.php?type=hourly&date=${dateParam}`);
        const data = await res.json();

        const labels = data.map(d => d.hour);
        const totals = data.map(d => d.total);
        const ins = data.map(d => d.count_in);

        const ctx = document.getElementById('analyticsHourlyChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Total Lalu Lintas (IN + OUT)',
                        data: totals,
                        borderColor: '#f97316',
                        backgroundColor: 'rgba(249, 115, 22, 0.1)',
                        fill: true,
                        tension: 0.4,
                        borderWidth: 2.5,
                        pointBackgroundColor: '#ea580c',
                        pointRadius: 4
                    },
                    {
                        label: 'Pengunjung Masuk (IN)',
                        data: ins,
                        borderColor: '#10b981',
                        borderDash: [5, 5],
                        borderWidth: 2,
                        fill: false,
                        tension: 0.3,
                        pointRadius: 3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { boxWidth: 12, font: { size: 11 } }
                    }
                },
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true, grid: { color: '#f1f5f9' } }
                }
            }
        });
    } catch (e) {
        console.error(e);
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
