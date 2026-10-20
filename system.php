<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/database.php';

$pageTitle = 'Status Sistem & Server Health';
$pdo = getDBConnection();

// Status database
$dbDriver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
$totalEvents = (int)$pdo->query("SELECT COUNT(*) FROM counting_events")->fetchColumn();
$totalCameras = (int)$pdo->query("SELECT COUNT(*) FROM cameras")->fetchColumn();

// Ambil log sistem
$logs = $pdo->query("SELECT * FROM system_logs ORDER BY created_at DESC LIMIT 20")->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<!-- Title -->
<div>
    <h2 class="text-2xl font-bold tracking-tight text-slate-900">Kondisi & Kesehatan Sistem</h2>
    <p class="text-xs text-slate-500 mt-1">Melihat status web, penyimpanan data, dan koneksi kamera CCTV.</p>
</div>

<!-- Diagnostics Grid -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    <!-- Server Web / PHP -->
    <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-semibold text-slate-500 uppercase">Aplikasi Web</span>
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
        </div>
        <div class="text-2xl font-bold text-slate-900">Aktif Normal</div>
        <div class="text-[11px] text-slate-500 mt-2">
            Status: <span class="font-semibold text-emerald-600">Berjalan Lancar</span>
        </div>
    </div>

    <!-- Database -->
    <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-semibold text-slate-500 uppercase">Penyimpanan Data</span>
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
        </div>
        <div class="text-2xl font-bold text-slate-900">Tersambung</div>
        <div class="text-[11px] text-slate-500 mt-2">
            Status: <span class="font-semibold text-emerald-600">Database Siap</span>
        </div>
    </div>

    <!-- Total Records -->
    <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-semibold text-slate-500 uppercase">Catatan Orang Lewat</span>
            <i data-lucide="users" class="w-4 h-4 text-orange-500"></i>
        </div>
        <div class="text-2xl font-bold text-slate-900"><?= number_format($totalEvents) ?></div>
        <div class="text-[11px] text-slate-500 mt-2">
            Dari <span class="font-semibold text-slate-700"><?= $totalCameras ?> kamera</span> terdaftar
        </div>
    </div>

    <!-- AI Vision Worker Integration -->
    <?php
    $streamActive = false;
    $streamFps = 0.0;
    $ch = curl_init("http://127.0.0.1:8080/status");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT_MS, 500);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code === 200 && $resp) {
        $st = json_decode($resp, true);
        if ($st && !empty($st['is_active'])) {
            $streamActive = true;
            $streamFps = (float)($st['fps'] ?? 0.0);
        }
    }
    ?>
    <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-semibold text-slate-500 uppercase">Pendeteksi Kamera</span>
            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold <?= $streamActive ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' ?>">
                <?= $streamActive ? 'ONLINE' : 'STANDBY' ?>
            </span>
        </div>
        <div class="text-xl font-bold text-slate-900"><?= $streamActive ? 'Sedang Mendeteksi' : 'Hitung Otomatis' ?></div>
        <div class="text-[11px] text-slate-500 mt-2">
            Kondisi: <span class="font-semibold <?= $streamActive ? 'text-emerald-600' : 'text-slate-600' ?>"><?= $streamActive ? "Live Streaming ($streamFps FPS)" : 'Siaga / Standby' ?></span>
        </div>
    </div>
</div>

<!-- Integration Information Card -->
<div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-sm">
    <h3 class="text-sm font-bold text-slate-900 mb-2 flex items-center gap-2">
        <i data-lucide="video" class="w-4 h-4 text-orange-500"></i>
        <span>Cara Menghubungkan Kamera CCTV ke Sistem</span>
    </h3>
    <p class="text-xs text-slate-600 mb-4">
        Jalankan perintah ini di terminal komputer untuk mulai menghitung pengunjung dari CCTV secara otomatis:
    </p>

    <div class="bg-slate-900 text-slate-100 p-4 rounded-2xl font-mono text-xs overflow-x-auto space-y-2">
        <div class="text-slate-400"># Jalankan pendeteksi kamera CCTV:</div>
        <div class="text-emerald-400">python vision/worker_php.py --video "samples/video_sample.mp4" --api "http://127.0.0.1:8000/api/events.php"</div>
    </div>
</div>

<!-- System Activity Logs -->
<div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-sm">
    <h3 class="text-sm font-bold text-slate-900 mb-4 flex items-center gap-2">
        <i data-lucide="file-text" class="w-4 h-4 text-slate-400"></i>
        <span>Log Aktivitas Sistem</span>
    </h3>

    <div class="space-y-2 font-mono text-xs">
        <?php if (empty($logs)): ?>
            <div class="text-slate-400 py-4 text-center">Belum ada catatan log sistem.</div>
        <?php else: ?>
            <?php foreach ($logs as $l): ?>
                <div class="p-3 rounded-xl bg-slate-50 border border-slate-100 flex items-start justify-between gap-3">
                    <div class="flex items-start gap-2.5">
                        <span class="px-1.5 py-0.5 rounded text-[10px] font-bold <?= $l['level'] === 'ERROR' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700' ?>">
                            <?= htmlspecialchars($l['level']) ?>
                        </span>
                        <span class="text-slate-800"><?= htmlspecialchars($l['message']) ?></span>
                    </div>
                    <span class="text-[11px] text-slate-400 shrink-0"><?= $l['created_at'] ?></span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
