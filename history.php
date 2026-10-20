<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/database.php';

$pageTitle = 'Riwayat Penyeberangan Garis Virtual';
$pdo = getDBConnection();

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$pageSize = 15;
$direction = isset($_GET['direction']) ? strtoupper(trim($_GET['direction'])) : '';
$cameraId = isset($_GET['camera_id']) && $_GET['camera_id'] !== 'all' ? trim($_GET['camera_id']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$cameras = $pdo->query("SELECT id, name FROM cameras ORDER BY name ASC")->fetchAll();

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

// Count Total
$countSql = "SELECT COUNT(*) FROM counting_events e LEFT JOIN cameras c ON e.camera_id = c.id $whereSql";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($total / $pageSize));

// Paginated Records
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

// URL Export
$exportParams = [
    'direction' => $direction,
    'camera_id' => $cameraId,
    'search' => $search
];
$excelUrl = "api/export_excel.php?" . http_build_query($exportParams);
$pdfUrl = "print_report.php?" . http_build_query($exportParams);
$csvUrl = "api/export_csv.php?" . http_build_query($exportParams);

include __DIR__ . '/includes/header.php';
?>

<!-- Title & Action Buttons -->
<div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
    <div>
        <h2 class="text-2xl font-bold tracking-tight text-slate-900">Catatan Pengunjung Masuk & Keluar</h2>
        <p class="text-xs text-slate-500 mt-1">Daftar setiap pengunjung yang lewat melalui pintu yang dipantau kamera CCTV.</p>
    </div>

    <!-- Export Action Buttons -->
    <div class="flex flex-wrap items-center gap-2.5">
        <!-- Unduh Excel -->
        <a href="<?= htmlspecialchars($excelUrl) ?>" class="inline-flex items-center gap-2 px-3.5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-sm transition-all" title="Unduh format spreadsheet Microsoft Excel">
            <i data-lucide="sheet" class="w-4 h-4"></i>
            <span>Unduh Excel (.xls)</span>
        </a>

        <!-- Cetak / Unduh PDF -->
        <a href="<?= htmlspecialchars($pdfUrl) ?>" target="_blank" class="inline-flex items-center gap-2 px-3.5 py-2.5 rounded-xl bg-orange-500 hover:bg-orange-600 text-white text-xs font-semibold shadow-sm shadow-orange-500/20 transition-all" title="Buka pratinjau dokumen laporan resmi & simpan ke PDF">
            <i data-lucide="printer" class="w-4 h-4"></i>
            <span>Cetak / PDF</span>
        </a>

        <!-- CSV (Opsional) -->
        <a href="<?= htmlspecialchars($csvUrl) ?>" class="inline-flex items-center gap-1.5 px-3 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold transition-all" title="Unduh berkas CSV mentah">
            <i data-lucide="file-text" class="w-3.5 h-3.5"></i>
            <span>CSV</span>
        </a>
    </div>
</div>

<!-- Filters Bar -->
<div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm">
    <form method="GET" action="history.php" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <!-- Filter Arah -->
        <div>
            <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">Arah Melintas</label>
            <select name="direction" onchange="this.form.submit()" class="w-full text-xs font-medium bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-700 focus:outline-none focus:border-orange-500">
                <option value="">Semua Arah (IN & OUT)</option>
                <option value="IN" <?= $direction === 'IN' ? 'selected' : '' ?>>Masuk (IN)</option>
                <option value="OUT" <?= $direction === 'OUT' ? 'selected' : '' ?>>Keluar (OUT)</option>
            </select>
        </div>

        <!-- Filter Kamera -->
        <div>
            <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">Kamera CCTV</label>
            <select name="camera_id" onchange="this.form.submit()" class="w-full text-xs font-medium bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-700 focus:outline-none focus:border-orange-500">
                <option value="all">Semua Kamera</option>
                <?php foreach ($cameras as $cam): ?>
                    <option value="<?= htmlspecialchars($cam['id']) ?>" <?= $cameraId === $cam['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cam['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Pencarian Kata Kunci -->
        <div>
            <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">Pencarian (Track ID / Lokasi)</label>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Misal: 12 atau Lantai 2"
                   class="w-full text-xs font-medium bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-700 focus:outline-none focus:border-orange-500">
        </div>

        <!-- Tombol Filter -->
        <div class="flex items-end gap-2">
            <button type="submit" class="flex-1 py-2 px-3 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-xs font-semibold transition-all">
                Cari Data
            </button>
            <a href="history.php" class="py-2 px-3 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-semibold transition-all" title="Reset Filter">
                Reset
            </a>
        </div>
    </form>
</div>

<!-- Table Card -->
<div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm overflow-hidden">
    <div class="p-4 border-b border-slate-100 flex items-center justify-between text-xs text-slate-500">
        <span>Menampilkan <strong><?= number_format(count($records)) ?></strong> dari <strong><?= number_format($total) ?></strong> total kejadian</span>
        <span>Halaman <strong><?= $page ?></strong> dari <strong><?= $totalPages ?></strong></span>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead>
                <tr class="bg-slate-50/80 border-b border-slate-100 text-slate-500 uppercase font-semibold text-[10px]">
                    <th class="py-3 px-4">Waktu (WIB)</th>
                    <th class="py-3 px-4">Nama CCTV</th>
                    <th class="py-3 px-4">Arah Melintas</th>
                    <th class="py-3 px-4 text-center">Track ID</th>
                    <th class="py-3 px-4 text-center">Confidence</th>
                    <th class="py-3 px-4 text-center">Mode</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-slate-700">
                <?php if (empty($records)): ?>
                    <tr>
                        <td colspan="6" class="py-12 text-center text-slate-400">
                            <i data-lucide="inbox" class="w-8 h-8 mx-auto text-slate-300 mb-2"></i>
                            Tidak ada data penyeberangan yang sesuai dengan filter.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($records as $r): ?>
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            <td class="py-3 px-4 font-medium text-slate-900">
                                <?= date('d M Y, H:i:s', strtotime($r['event_timestamp'])) ?>
                            </td>
                            <td class="py-3 px-4 text-slate-600 font-medium">
                                <?= htmlspecialchars($r['camera_name'] ?: 'CCTV Pintu Masuk') ?>
                            </td>
                            <td class="py-3 px-4">
                                <?php if ($r['direction'] === 'IN'): ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <i data-lucide="arrow-down-left" class="w-3.5 h-3.5"></i> MASUK (IN)
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                                        <i data-lucide="arrow-up-right" class="w-3.5 h-3.5"></i> KELUAR (OUT)
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4 text-center font-mono text-slate-600 font-semibold">
                                #<?= htmlspecialchars($r['track_id'] ?: '-') ?>
                            </td>
                            <td class="py-3 px-4 text-center font-medium">
                                <span class="bg-slate-100 px-2 py-0.5 rounded text-[11px] text-slate-700">
                                    <?= number_format($r['confidence'] * 100, 1) ?>%
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="text-[10px] font-semibold text-slate-500 uppercase bg-slate-50 px-2 py-0.5 rounded border border-slate-200">
                                    <?= htmlspecialchars($r['source_mode']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination Controls -->
    <?php if ($totalPages > 1): ?>
        <div class="p-4 border-t border-slate-100 flex items-center justify-between text-xs">
            <?php
            $prevUrl = "history.php?" . http_build_query(array_merge($_GET, ['page' => max(1, $page - 1)]));
            $nextUrl = "history.php?" . http_build_query(array_merge($_GET, ['page' => min($totalPages, $page + 1)]));
            ?>
            <a href="<?= $page > 1 ? htmlspecialchars($prevUrl) : '#' ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 <?= $page > 1 ? 'hover:bg-slate-50 text-slate-700' : 'text-slate-300 pointer-events-none' ?>">
                &larr; Sebelumnya
            </a>

            <div class="flex items-center gap-1">
                <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                    <?php $pUrl = "history.php?" . http_build_query(array_merge($_GET, ['page' => $p])); ?>
                    <a href="<?= htmlspecialchars($pUrl) ?>" class="w-7 h-7 rounded-lg flex items-center justify-center font-medium <?= $p === $page ? 'bg-orange-500 text-white' : 'hover:bg-slate-100 text-slate-700' ?>">
                        <?= $p ?>
                    </a>
                <?php endfor; ?>
            </div>

            <a href="<?= $page < $totalPages ? htmlspecialchars($nextUrl) : '#' ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 <?= $page < $totalPages ? 'hover:bg-slate-50 text-slate-700' : 'text-slate-300 pointer-events-none' ?>">
                Selanjutnya &rarr;
            </a>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
