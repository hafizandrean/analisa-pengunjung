<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/database.php';

$pdo = getDBConnection();

$direction = isset($_GET['direction']) ? strtoupper(trim($_GET['direction'])) : '';
$cameraId = isset($_GET['camera_id']) && $_GET['camera_id'] !== 'all' ? trim($_GET['camera_id']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

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

$sql = "SELECT e.*, c.name as camera_name 
        FROM counting_events e 
        LEFT JOIN cameras c ON e.camera_id = c.id 
        $whereSql 
        ORDER BY e.event_timestamp DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

// Statistik ringkasan
$totalCount = count($records);
$totalIn = 0;
$totalOut = 0;
foreach ($records as $r) {
    if ($r['direction'] === 'IN') $totalIn++;
    else $totalOut++;
}

// Nama kamera terpilih
$cameraName = 'Semua Kamera';
if ($cameraId) {
    $cStmt = $pdo->prepare("SELECT name FROM cameras WHERE id = ?");
    $cStmt->execute([$cameraId]);
    $cameraName = $cStmt->fetchColumn() ?: $cameraId;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Riwayat Pengunjung — Ayam Bang Jungko</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; color: black !important; padding: 0 !important; }
            @page { margin: 1.5cm; }
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 p-4 sm:p-8 min-h-screen">

    <!-- Top Action Bar (Hidden on Print) -->
    <div class="no-print max-w-5xl mx-auto mb-6 bg-white p-4 rounded-2xl shadow-sm border border-slate-200 flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="history.php" class="p-2 text-slate-600 hover:text-slate-900 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
            </a>
            <div>
                <h1 class="text-sm font-bold text-slate-900">Pratinjau Dokumen Laporan Pengunjung</h1>
                <p class="text-xs text-slate-500">Gunakan tombol cetak untuk menyimpan sebagai PDF atau print fisik.</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="api/export_excel.php?<?= http_build_query($_GET) ?>" class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-sm transition-all">
                <i data-lucide="sheet" class="w-4 h-4"></i>
                <span>Unduh Excel (.xls)</span>
            </a>
            <button onclick="window.print()" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-orange-500 hover:bg-orange-600 text-white text-xs font-semibold shadow-md shadow-orange-500/25 transition-all">
                <i data-lucide="printer" class="w-4 h-4"></i>
                <span>Cetak / Simpan PDF</span>
            </button>
        </div>
    </div>

    <!-- Printable Paper Sheet -->
    <div class="max-w-5xl mx-auto bg-white p-8 sm:p-12 rounded-3xl shadow-lg border border-slate-200/80 print:border-none print:shadow-none print:p-0">
        <!-- Letterhead Header -->
        <div class="border-b-2 border-slate-900 pb-6 mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="h-14 w-14 rounded-2xl bg-orange-500 text-white flex items-center justify-center font-bold text-2xl shadow-md">
                    🍗
                </div>
                <div>
                    <h2 class="text-2xl font-black text-slate-900 tracking-tight">AYAM BANG JUNGKO</h2>
                    <p class="text-xs font-bold text-orange-600 uppercase tracking-widest mt-0.5">Laporan Jumlah Pengunjung Restoran</p>
                    <p class="text-[11px] text-slate-400 mt-1">Catatan Pengunjung Masuk dan Keluar</p>
                </div>
            </div>

            <div class="text-right text-xs space-y-1">
                <div class="font-bold text-slate-800">Tanggal Cetak:</div>
                <div class="text-slate-600 font-mono"><?= date('d F Y, H:i') ?> WIB</div>
                <div class="text-[11px] text-slate-400">Dicetak oleh: <?= htmlspecialchars($_SESSION['user']['full_name'] ?? 'Manajer Operasional') ?></div>
            </div>
        </div>

        <!-- Filter & KPI Summary Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
            <div class="bg-slate-50 border border-slate-200/80 p-3.5 rounded-xl">
                <div class="text-[10px] font-bold uppercase text-slate-400">Kamera Terpilih</div>
                <div class="text-xs font-bold text-slate-800 mt-1 truncate"><?= htmlspecialchars($cameraName) ?></div>
            </div>
            <div class="bg-slate-50 border border-slate-200/80 p-3.5 rounded-xl">
                <div class="text-[10px] font-bold uppercase text-slate-400">Total Lalu Lalang</div>
                <div class="text-base font-bold text-slate-900 mt-1"><?= number_format($totalCount) ?> kali melintas</div>
            </div>
            <div class="bg-emerald-50 border border-emerald-200 p-3.5 rounded-xl">
                <div class="text-[10px] font-bold uppercase text-emerald-700">Total Masuk (IN)</div>
                <div class="text-base font-bold text-emerald-800 mt-1"><?= number_format($totalIn) ?> orang</div>
            </div>
            <div class="bg-blue-50 border border-blue-200 p-3.5 rounded-xl">
                <div class="text-[10px] font-bold uppercase text-blue-700">Total Keluar (OUT)</div>
                <div class="text-base font-bold text-blue-800 mt-1"><?= number_format($totalOut) ?> orang</div>
            </div>
        </div>

        <!-- Data Table -->
        <table class="w-full text-left text-xs border border-slate-200">
            <thead>
                <tr class="bg-slate-900 text-white font-semibold text-[11px]">
                    <th class="py-2.5 px-3 border border-slate-700 text-center w-12">No</th>
                    <th class="py-2.5 px-3 border border-slate-700">Waktu Kunjungan (WIB)</th>
                    <th class="py-2.5 px-3 border border-slate-700">Kamera CCTV</th>
                    <th class="py-2.5 px-3 border border-slate-700 text-center">Arah</th>
                    <th class="py-2.5 px-3 border border-slate-700 text-center">ID Tamu</th>
                    <th class="py-2.5 px-3 border border-slate-700 text-center">Akurasi AI</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                <?php if (empty($records)): ?>
                    <tr>
                        <td colspan="6" class="py-8 text-center text-slate-400">Tidak ada catatan data pengunjung.</td>
                    </tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($records as $r): ?>
                        <tr class="<?= ($no % 2 === 0) ? 'bg-slate-50/50' : 'bg-white' ?>">
                            <td class="py-2 px-3 border border-slate-200 text-center font-mono text-slate-500"><?= $no++ ?></td>
                            <td class="py-2 px-3 border border-slate-200 font-medium text-slate-900">
                                <?= date('d/m/Y, H:i:s', strtotime($r['event_timestamp'])) ?>
                            </td>
                            <td class="py-2 px-3 border border-slate-200 text-slate-700">
                                <?= htmlspecialchars($r['camera_name'] ?: 'CCTV Pintu Masuk') ?>
                            </td>
                            <td class="py-2 px-3 border border-slate-200 text-center font-bold">
                                <?php if ($r['direction'] === 'IN'): ?>
                                    <span class="text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">MASUK (IN)</span>
                                <?php else: ?>
                                    <span class="text-blue-700 bg-blue-50 px-2 py-0.5 rounded border border-blue-200">KELUAR (OUT)</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-2 px-3 border border-slate-200 text-center font-mono text-slate-600">
                                #<?= htmlspecialchars($r['track_id'] ?: '-') ?>
                            </td>
                            <td class="py-2 px-3 border border-slate-200 text-center font-medium text-slate-700">
                                <?= number_format($r['confidence'] * 100, 1) ?>%
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Signatures / Footer Note -->
        <div class="mt-12 pt-6 border-t border-slate-200 flex justify-between items-end text-xs text-slate-500">
            <div>
                <p class="font-bold text-slate-700">Catatan Privasi:</p>
                <p class="text-[11px] text-slate-400">Sistem ini hanya menghitung jumlah orang yang lewat di depan kamera. Sistem tidak mengambil foto ataupun menyimpan wajah pengunjung.</p>
            </div>
            <div class="text-center w-48">
                <div class="text-[11px] text-slate-400 mb-12">Mengetahui,</div>
                <div class="border-b border-slate-400 pb-1 font-bold text-slate-800"><?= htmlspecialchars($_SESSION['user']['full_name'] ?? 'Manajer Operasional') ?></div>
                <div class="text-[10px] text-slate-400 mt-0.5">Operasional Ayam Bang Jungko</div>
            </div>
        </div>
    </div>

    <script>lucide.createIcons();</script>
</body>
</html>
