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
        <h2 class="text-2xl font-bold tracking-tight text-slate-900"><?= __('hist_title') ?></h2>
        <p class="text-xs text-slate-500 mt-1"><?= __('hist_subtitle') ?></p>
    </div>

    <!-- Export Action Buttons -->
    <div class="flex flex-wrap items-center gap-2.5">
        <!-- Unduh Excel -->
        <a href="<?= htmlspecialchars($excelUrl) ?>" class="inline-flex items-center gap-2 px-3.5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-sm transition-all" title="Unduh format spreadsheet Microsoft Excel">
            <i data-lucide="sheet" class="w-4 h-4"></i>
            <span><?= __('btn_download_excel') ?></span>
        </a>

        <!-- Cetak / Unduh PDF -->
        <a href="<?= htmlspecialchars($pdfUrl) ?>" target="_blank" class="inline-flex items-center gap-2 px-3.5 py-2.5 rounded-xl bg-orange-500 hover:bg-orange-600 text-white text-xs font-semibold shadow-sm shadow-orange-500/20 transition-all" title="Buka pratinjau dokumen laporan resmi & simpan ke PDF">
            <i data-lucide="printer" class="w-4 h-4"></i>
            <span><?= __('btn_print_pdf') ?></span>
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
            <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1"><?= __('filter_direction') ?></label>
            <select name="direction" onchange="this.form.submit()" class="w-full text-xs font-medium bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-700 focus:outline-none focus:border-orange-500">
                <option value=""><?= __('all_directions') ?></option>
                <option value="IN" <?= $direction === 'IN' ? 'selected' : '' ?>><?= __('dir_in_only') ?></option>
                <option value="OUT" <?= $direction === 'OUT' ? 'selected' : '' ?>><?= __('dir_out_only') ?></option>
            </select>
        </div>

        <!-- Filter Kamera -->
        <div>
            <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1"><?= __('filter_cctv_cam') ?></label>
            <select name="camera_id" onchange="this.form.submit()" class="w-full text-xs font-medium bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-700 focus:outline-none focus:border-orange-500">
                <option value="all"><?= __('all_cameras') ?></option>
                <?php foreach ($cameras as $cam): ?>
                    <option value="<?= htmlspecialchars($cam['id']) ?>" <?= $cameraId === $cam['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cam['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Pencarian Kata Kunci -->
        <div>
            <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1"><?= __('filter_search') ?></label>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="<?= __('search_placeholder') ?>"
                   class="w-full text-xs font-medium bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-700 focus:outline-none focus:border-orange-500">
        </div>

        <!-- Tombol Filter -->
        <div class="flex items-end gap-2">
            <button type="submit" class="flex-1 py-2 px-3 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-xs font-semibold transition-all">
                <?= __('btn_search_data') ?>
            </button>
            <a href="history.php" class="py-2 px-3 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-semibold transition-all" title="Reset Filter">
                <?= __('btn_reset') ?>
            </a>
        </div>
    </form>
</div>

<!-- Table Card -->
<div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm overflow-hidden">
    <div class="p-4 border-b border-slate-100 flex items-center justify-between text-xs text-slate-500">
        <span><?= __('showing_records') ?> <strong><?= number_format(count($records)) ?></strong> <?= __('of_records') ?> <strong><?= number_format($total) ?></strong> <?= __('total_guest_records') ?></span>
        <span><?= __('page_label') ?> <strong><?= $page ?></strong> <?= __('of_records') ?> <strong><?= $totalPages ?></strong></span>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead>
                <tr class="bg-slate-50/80 border-b border-slate-100 text-slate-500 uppercase font-semibold text-[10px]">
                    <th class="py-3 px-4 text-center"><?= __('th_evidence') ?></th>
                    <th class="py-3 px-4"><?= __('th_visit_time') ?></th>
                    <th class="py-3 px-4"><?= __('th_cctv_name') ?></th>
                    <th class="py-3 px-4"><?= __('th_cross_direction') ?></th>
                    <th class="py-3 px-4 text-center"><?= __('th_guest_id') ?></th>
                    <th class="py-3 px-4 text-center"><?= __('th_ai_accuracy') ?></th>
                    <th class="py-3 px-4 text-center"><?= __('th_mode') ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-slate-700">
                <?php if (empty($records)): ?>
                    <tr>
                        <td colspan="7" class="py-12 text-center text-slate-400">
                            <i data-lucide="inbox" class="w-8 h-8 mx-auto text-slate-300 mb-2"></i>
                            <?= __('no_data_history') ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($records as $r): ?>
                        <?php 
                        $hasR2 = (!empty($r['metadata_json']) && strpos($r['metadata_json'], 'r2_url') !== false);
                        $imgSrc = '';
                        if (!empty($r['snapshot_path'])) {
                            if (strpos($r['snapshot_path'], 'uploads/') === 0 && file_exists(__DIR__ . '/' . $r['snapshot_path'])) {
                                $imgSrc = htmlspecialchars($r['snapshot_path']);
                            } else {
                                $imgSrc = 'api/evidence.php?id=' . urlencode($r['id']);
                            }
                        }
                        ?>
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            <td class="py-2.5 px-4 text-center">
                                <?php if (!empty($imgSrc)): ?>
                                    <button type="button" 
                                            onclick="openEvidenceModal('<?= $imgSrc ?>', '<?= htmlspecialchars($r['id']) ?>', '<?= date('d M Y, H:i:s', strtotime($r['event_timestamp'])) ?>', '<?= $r['direction'] ?>', '<?= htmlspecialchars($r['track_id'] ?: '-') ?>', '<?= number_format($r['confidence'] * 100, 1) ?>%', '<?= htmlspecialchars($r['camera_name'] ?: 'CCTV') ?>', true)"
                                            class="relative group cursor-pointer inline-block focus:outline-none">
                                        <img src="<?= $imgSrc ?>" alt="Evidence" 
                                             class="w-11 h-11 object-cover rounded-xl border border-slate-200/80 shadow-sm group-hover:scale-105 group-hover:shadow-md group-hover:border-orange-500 transition-all duration-200" 
                                             loading="lazy"
                                             onerror="if(!this.dataset.retried){this.dataset.retried='1';this.src='api/evidence.php?id=<?= urlencode($r['id']) ?>';}else{this.parentElement.innerHTML='<span class=\'text-[10px] text-slate-400 bg-slate-100 px-2 py-1 rounded\'>Foto Error</span>';}">
                                        <span class="absolute -bottom-1 -right-1 w-4 h-4 rounded-full flex items-center justify-center bg-orange-500 text-white text-[9px] shadow group-hover:bg-orange-600 transition-colors">
                                            <i data-lucide="zoom-in" class="w-2.5 h-2.5"></i>
                                        </span>
                                    </button>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 text-[11px] text-slate-400 bg-slate-50 px-2 py-1 rounded-lg border border-slate-100" title="<?= __('no_snapshot_desc') ?>">
                                        <i data-lucide="image-off" class="w-3.5 h-3.5 text-slate-300"></i> -
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4 font-medium text-slate-900">
                                <?= date('d M Y, H:i:s', strtotime($r['event_timestamp'])) ?>
                            </td>
                            <td class="py-3 px-4 text-slate-600 font-medium">
                                <?= htmlspecialchars($r['camera_name'] ?: 'CCTV') ?>
                            </td>
                            <td class="py-3 px-4">
                                <?php if ($r['direction'] === 'IN'): ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <i data-lucide="arrow-down-left" class="w-3.5 h-3.5"></i> <?= __('badge_in') ?> (IN)
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                                        <i data-lucide="arrow-up-right" class="w-3.5 h-3.5"></i> <?= __('badge_out') ?> (OUT)
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
                &larr; <?= __('btn_prev') ?>
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
                <?= __('btn_next') ?> &rarr;
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- Modal Pop-up Bukti Visual (Evidence Lightbox Modal) -->
<div id="evidenceModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/70 backdrop-blur-sm p-4 transition-all duration-300">
    <div class="relative w-full max-w-lg bg-white rounded-3xl shadow-2xl border border-slate-100 overflow-hidden transform transition-all scale-95 opacity-0 duration-300" id="evidenceModalCard">
        <!-- Header Modal -->
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <div>
                <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                    <i data-lucide="camera" class="w-4 h-4 text-orange-500"></i>
                    <span><?= __('evidence_modal_title') ?></span>
                </h3>
                <p class="text-[11px] text-slate-500 mt-0.5"><?= __('evidence_modal_subtitle') ?></p>
            </div>
            <button type="button" onclick="closeEvidenceModal()" class="w-8 h-8 rounded-full flex items-center justify-center text-slate-400 hover:text-slate-700 hover:bg-slate-200/60 transition-colors">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>

        <!-- Body Modal: Gambar HD -->
        <div class="p-6 flex flex-col items-center">
            <div class="relative w-full max-h-[380px] bg-slate-950/90 rounded-2xl overflow-hidden flex items-center justify-center border border-slate-200/80 shadow-inner group">
                <img id="modalEvidenceImg" src="" alt="Guest Snapshot" class="max-h-[360px] w-auto max-w-full object-contain transition-transform duration-300 group-hover:scale-105">
                
                <!-- Badge Sumber Penyimpanan (Cloudflare R2 vs Lokal) -->
                <div id="modalStorageBadge" class="absolute top-3 right-3 px-2.5 py-1 rounded-full text-[10px] font-semibold flex items-center gap-1.5 shadow-sm backdrop-blur-md">
                    <!-- Populated via JS -->
                </div>
            </div>

            <!-- Meta Data Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 w-full mt-4">
                <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-100 text-center">
                    <span class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider"><?= __('th_cross_direction') ?></span>
                    <span id="modalDirectionBadge" class="inline-block mt-1"></span>
                </div>
                <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-100 text-center">
                    <span class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider"><?= __('th_guest_id') ?></span>
                    <span id="modalGuestId" class="block mt-1 font-mono font-bold text-xs text-slate-800"></span>
                </div>
                <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-100 text-center">
                    <span class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider"><?= __('th_ai_accuracy') ?></span>
                    <span id="modalConfidence" class="block mt-1 font-bold text-xs text-emerald-600"></span>
                </div>
                <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-100 text-center">
                    <span class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider"><?= __('th_cctv_name') ?></span>
                    <span id="modalCameraName" class="block mt-1 font-medium text-xs text-slate-700 truncate"></span>
                </div>
            </div>

            <div class="w-full mt-2 text-center">
                <span class="text-[11px] text-slate-400">
                    <i data-lucide="clock" class="w-3 h-3 inline mr-1"></i>
                    <span id="modalTimestamp"></span>
                </span>
            </div>
        </div>

        <!-- Footer Modal -->
        <div class="px-6 py-3.5 bg-slate-50 border-t border-slate-100 flex items-center justify-between">
            <a id="modalDownloadBtn" href="#" download class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold shadow-sm transition-all">
                <i data-lucide="download" class="w-3.5 h-3.5"></i>
                <span><?= __('btn_download_photo') ?></span>
            </a>
            <button type="button" onclick="closeEvidenceModal()" class="px-4 py-2 rounded-xl bg-slate-200/80 hover:bg-slate-300 text-slate-700 text-xs font-semibold transition-all">
                <?= __('btn_close') ?>
            </button>
        </div>
    </div>
</div>

<script>
function openEvidenceModal(imgSrc, eventId, timestamp, direction, trackId, confidence, cameraName, hasR2 = false) {
    const modal = document.getElementById('evidenceModal');
    const card = document.getElementById('evidenceModalCard');
    const img = document.getElementById('modalEvidenceImg');
    const badge = document.getElementById('modalStorageBadge');
    const dirBadge = document.getElementById('modalDirectionBadge');
    const guestIdEl = document.getElementById('modalGuestId');
    const confEl = document.getElementById('modalConfidence');
    const camEl = document.getElementById('modalCameraName');
    const timeEl = document.getElementById('modalTimestamp');
    const downloadBtn = document.getElementById('modalDownloadBtn');

    img.src = imgSrc;
    downloadBtn.href = imgSrc;
    guestIdEl.textContent = '#' + trackId;
    confEl.textContent = confidence;
    camEl.textContent = cameraName;
    timeEl.textContent = timestamp + ' (' + eventId + ')';

    // Badge Arah
    if (direction === 'IN') {
        dirBadge.innerHTML = '<span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800">MASUK (IN)</span>';
    } else {
        dirBadge.innerHTML = '<span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-blue-100 text-blue-800">KELUAR (OUT)</span>';
    }

    // Badge Sumber Penyimpanan
    if (hasR2 || imgSrc.startsWith('http://') || imgSrc.startsWith('https://')) {
        badge.className = 'absolute top-3 right-3 px-2.5 py-1 rounded-full text-[10px] font-semibold flex items-center gap-1.5 shadow-sm bg-orange-500 text-white backdrop-blur-md';
        badge.innerHTML = '<i data-lucide="cloud" class="w-3 h-3"></i> <?= __("evidence_storage_badge_r2") ?>';
    } else {
        badge.className = 'absolute top-3 right-3 px-2.5 py-1 rounded-full text-[10px] font-semibold flex items-center gap-1.5 shadow-sm bg-slate-800/90 text-slate-200 backdrop-blur-md';
        badge.innerHTML = '<i data-lucide="hard-drive" class="w-3 h-3"></i> <?= __("evidence_storage_badge_local") ?>';
    }

    modal.classList.remove('hidden');
    modal.classList.add('flex');
    setTimeout(() => {
        card.classList.remove('scale-95', 'opacity-0');
        card.classList.add('scale-100', 'opacity-100');
    }, 10);

    if (window.lucide) {
        lucide.createIcons();
    }
}

function closeEvidenceModal() {
    const modal = document.getElementById('evidenceModal');
    const card = document.getElementById('evidenceModalCard');
    card.classList.remove('scale-100', 'opacity-100');
    card.classList.add('scale-95', 'opacity-0');
    setTimeout(() => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }, 200);
}

// Tutup modal jika tombol ESC ditekan atau klik backdrop luar
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeEvidenceModal();
});
document.getElementById('evidenceModal').addEventListener('click', function(e) {
    if (e.target === this) closeEvidenceModal();
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
