<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/lang.php';

$pageTitle = __('cam_title');
$pdo = getDBConnection();

// Ambil daftar kamera
$cameras = $pdo->query("SELECT * FROM cameras ORDER BY created_at ASC")->fetchAll();

$selectedCamId = $_GET['id'] ?? ($cameras[0]['id'] ?? 'cam-1');
$activeCam = null;
foreach ($cameras as $c) {
    if ($c['id'] === $selectedCamId) {
        $activeCam = $c;
        break;
    }
}
if (!$activeCam && !empty($cameras)) {
    $activeCam = $cameras[0];
}

$lineCoords = json_decode($activeCam['line_coords'] ?? '{}', true) ?: [
    'x1' => 120, 'y1' => 380, 'x2' => 1160, 'y2' => 380,
    'direction_in' => 'down', 'offset' => 30
];

include __DIR__ . '/includes/header.php';
?>

<!-- Title -->
<div>
    <h2 class="text-2xl font-bold tracking-tight text-slate-900"><?= __('cam_title') ?></h2>
    <p class="text-xs text-slate-500 mt-1"><?= __('cam_subtitle') ?></p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Camera List & Quick Info (1 Col) -->
    <div class="space-y-4">
        <div class="bg-white rounded-3xl p-5 border border-slate-200/80 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-sm font-bold text-slate-900"><?= __('cam_list') ?></h3>
                    <span class="text-[11px] text-slate-400 font-medium"><?= count($cameras) ?> <?= __('cameras_connected') ?></span>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="openRadarDiscoveryModal()" 
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-orange-500 hover:bg-orange-600 active:bg-orange-700 text-white rounded-xl text-xs font-semibold shadow-sm shadow-orange-500/20 transition-all">
                        <i data-lucide="radar" class="w-3.5 h-3.5 animate-pulse"></i>
                        <span><?= __('btn_scan_network') ?></span>
                    </button>
                    <button type="button" onclick="openQrScannerModal()" title="Pindai QR / Barcode CCTV"
                            class="p-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl transition-all border border-slate-200">
                        <i data-lucide="qr-code" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>

            <div class="space-y-2">
                <?php foreach ($cameras as $cam): ?>
                    <a href="cameras.php?id=<?= urlencode($cam['id']) ?>" 
                       class="block p-3.5 rounded-2xl border transition-all <?= $cam['id'] === $activeCam['id'] ? 'border-orange-500 bg-orange-50/50 shadow-sm' : 'border-slate-100 bg-slate-50 hover:bg-slate-100/70' ?>">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-bold text-slate-800"><?= htmlspecialchars($cam['name']) ?></span>
                            <span class="w-2 h-2 rounded-full <?= $cam['is_active'] ? 'bg-emerald-500' : 'bg-slate-300' ?>"></span>
                        </div>
                        <div class="text-[11px] text-slate-500">
                            <?= __('field_cam_position') ?> <?= htmlspecialchars($cam['location']) ?>
                        </div>
                        <div class="text-[10px] text-slate-400 mt-1 font-mono">
                            <?= htmlspecialchars($cam['source_type']) ?> &bull; <?= $cam['resolution_width'] ?>x<?= $cam['resolution_height'] ?> @ <?= $cam['fps'] ?>fps
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Detail Kamera Sederhana -->
        <div class="bg-white rounded-3xl p-5 border border-slate-200/80 shadow-sm">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3"><?= __('info_camera_header') ?></h3>
            <div class="space-y-2.5 text-xs">
                <div class="flex justify-between py-1 border-b border-slate-100">
                    <span class="text-slate-500"><?= __('field_cam_name') ?></span>
                    <span class="font-bold text-slate-800"><?= htmlspecialchars($activeCam['name']) ?></span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-100">
                    <span class="text-slate-500"><?= __('field_cam_position') ?></span>
                    <span class="font-semibold text-slate-700"><?= htmlspecialchars($activeCam['location']) ?></span>
                </div>
                <div class="flex justify-between py-1">
                    <span class="text-slate-500"><?= __('field_cam_condition') ?></span>
                    <span class="text-emerald-700 font-bold bg-emerald-50 px-2 py-0.5 rounded text-[11px] flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> <?= __('cam_status_ready') ?>
                    </span>
                </div>
            </div>

            <!-- Satu Tombol Saklar Utama: Nyalakan / Matikan Kamera -->
            <div class="pt-4 mt-3 border-t border-slate-100 space-y-2">
                <button type="button" id="btnToggleStream" onclick="toggleStreamProcess('<?= $activeCam['id'] ?>')" 
                        class="w-full py-3 px-3 rounded-2xl bg-orange-500 hover:bg-orange-600 active:bg-orange-700 text-white text-xs font-bold shadow-md shadow-orange-500/20 transition-all flex items-center justify-center gap-2">
                    <i data-lucide="play" class="w-4 h-4" id="streamBtnIcon"></i>
                    <span id="streamBtnText"><?= __('btn_turn_on_cam') ?></span>
                </button>

                <button type="button" onclick="deleteCamera('<?= $activeCam['id'] ?>', '<?= htmlspecialchars(addslashes($activeCam['name'])) ?>')" 
                        class="w-full py-2 px-3 text-[11px] font-semibold text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-xl transition-all flex items-center justify-center gap-1.5">
                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                    <span><?= __('btn_delete_camera') ?></span>
                </button>
            </div>
        </div>
    </div>

    <!-- Virtual Line Canvas Editor (2 Cols) -->
    <div class="lg:col-span-2 bg-white rounded-3xl p-6 border border-slate-200/80 shadow-sm space-y-4">
        <!-- Header Atur Garis & Live Status (DI LUAR VIDEO) -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
                <div class="flex items-center gap-2.5">
                    <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                        <i data-lucide="sliders" class="w-4 h-4 text-orange-500"></i>
                        <span><?= __('line_title') ?></span>
                    </h3>
                    <!-- Live Status Indicator (Di Luar Video Kamera) -->
                    <div id="liveStreamBadge" class="hidden items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span><?= __('badge_live_active') ?></span>
                    </div>
                </div>
                <p class="text-xs text-slate-500 mt-0.5"><?= __('drag_points_desc') ?></p>
            </div>
            <div id="saveStatus" class="hidden text-xs font-semibold px-3 py-1 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200">
                <?= __('saved_badge') ?>
            </div>
        </div>

        <!-- Kartu Bar Perhitungan Pengunjung (DI LUAR VIDEO - Tepat di Atas Layar Kamera) -->
        <div id="liveCounterPopUp" class="hidden bg-slate-900 text-white rounded-2xl px-4 sm:px-6 py-3 border border-slate-800 shadow-md">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-4 sm:gap-7">
                    <!-- Masuk -->
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span class="text-xs font-bold text-slate-400"><?= __('val_in') ?></span>
                        <span class="text-lg sm:text-xl font-black text-emerald-400 font-mono tracking-tight" id="popValIn">0</span>
                    </div>
                    <div class="h-6 w-px bg-slate-700 hidden sm:block"></div>
                    <!-- Keluar -->
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-blue-400"></span>
                        <span class="text-xs font-bold text-slate-400"><?= __('val_out') ?></span>
                        <span class="text-lg sm:text-xl font-black text-blue-400 font-mono tracking-tight" id="popValOut">0</span>
                    </div>
                    <div class="h-6 w-px bg-slate-700 hidden sm:block"></div>
                    <!-- Di Dalam -->
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-amber-400"></span>
                        <span class="text-xs font-bold text-slate-400"><?= __('val_inside') ?></span>
                        <span class="text-lg sm:text-xl font-black text-amber-400 font-mono tracking-tight" id="popValCurrent">0</span>
                    </div>
                </div>

                <!-- Status Jam Operasional & AI & FPS & Tombol Buka/Tutup -->
                <div class="flex items-center gap-2 sm:gap-3">
                    <!-- Badge Status Buka / Tutup Operasional Restoran -->
                    <div id="operatingStatusBadge" class="hidden items-center gap-1.5 px-2.5 py-1 rounded-xl text-xs font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30">
                        <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
                        <span id="operatingStatusText">Tutup &bull; Standby</span>
                    </div>

                    <span class="text-xs font-mono font-bold text-slate-300 bg-slate-800 px-2.5 py-1 rounded-lg border border-slate-700" id="popFps">20 FPS</span>

                    <!-- Tombol Buka / Tutup Restoran (Manual & Otomatis) -->
                    <button type="button" id="btnToggleStoreStatus" data-is-open="0" onclick="toggleStoreStatus()" class="px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 active:bg-emerald-700 text-white text-xs font-bold transition-all shadow-md shadow-emerald-600/30 flex items-center gap-1.5" title="Klik untuk membuka/menutup restoran">
                        <i data-lucide="play" class="w-3.5 h-3.5" id="storeStatusBtnIcon"></i>
                        <span id="storeStatusBtnText"><?= __('btn_open_store') ?></span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Canvas & Live Video Stream Container (100% BERSIH - TANPA OVERLAY YANG MENGHALANGI) -->
        <div class="relative bg-slate-950 rounded-2xl overflow-hidden shadow-inner border border-slate-800 aspect-video flex items-center justify-center">
            <!-- MJPEG Live Video Feed from AI Worker -->
            <img id="cameraStreamImg" 
                 src="http://127.0.0.1:8080/video_feed" 
                 alt="Live CCTV" 
                 class="absolute inset-0 w-full h-full object-cover select-none pointer-events-none hidden" />

            <!-- Fallback Overlay saat kamera belum dinyalakan -->
            <div id="streamOfflineNotice" class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none z-0">
                <div class="text-center p-4 bg-slate-900/80 backdrop-blur-md rounded-2xl border border-slate-700/60 max-w-xs shadow-xl">
                    <div class="w-10 h-10 mx-auto rounded-xl bg-orange-500/10 text-orange-400 flex items-center justify-center mb-2">
                        <i data-lucide="video" class="w-5 h-5"></i>
                    </div>
                    <div class="text-xs font-bold text-slate-200">Kamera Belum Aktif</div>
                    <div class="text-[11px] text-slate-400 mt-1">Klik tombol <strong>"Nyalakan Kamera (Live AI)"</strong> di samping untuk melihat video live streaming.</div>
                </div>
            </div>

            <!-- Transparent Interactive Canvas for Line Adjustment -->
            <canvas id="virtualLineCanvas" width="640" height="360" class="absolute inset-0 w-full h-full cursor-crosshair z-10"></canvas>
        </div>

        <!-- Pengaturan Garis yang Sederhana & Posisi Titik (DI LUAR VIDEO) -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-2">
            <div class="flex flex-wrap items-center gap-2">
                <label class="text-xs font-bold text-slate-700 shrink-0"><?= __('direction_in_label') ?></label>
                <select id="inp_dir" class="text-xs font-semibold bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800 focus:outline-none focus:border-orange-500">
                    <option value="down" <?= ($lineCoords['direction_in'] ?? '') === 'down' ? 'selected' : '' ?>><?= __('dir_down') ?></option>
                    <option value="up" <?= ($lineCoords['direction_in'] ?? '') === 'up' ? 'selected' : '' ?>><?= __('dir_up') ?></option>
                    <option value="left" <?= ($lineCoords['direction_in'] ?? '') === 'left' ? 'selected' : '' ?>><?= __('dir_left') ?></option>
                    <option value="right" <?= ($lineCoords['direction_in'] ?? '') === 'right' ? 'selected' : '' ?>><?= __('dir_right') ?></option>
                </select>

                <!-- Badge Koordinat P1 & P2 di luar layar video -->
                <div class="flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    <span id="coordBadgeText">P1 (<?= $lineCoords['x1'] ?>, <?= $lineCoords['y1'] ?>) &bull; P2 (<?= $lineCoords['x2'] ?>, <?= $lineCoords['y2'] ?>)</span>
                </div>

                <!-- Tanda Belum Disimpan (Muncul otomatis saat garis digeser) -->
                <span id="unsavedLineNotice" class="hidden items-center gap-1.5 px-2.5 py-1 rounded-xl text-[11px] font-bold bg-rose-100 text-rose-700 border border-rose-200">
                    <span class="w-2 h-2 rounded-full bg-rose-500 animate-ping"></span>
                    <span><?= __('unsaved_line') ?></span>
                </span>

                <button type="button" onclick="document.getElementById('advancedCoordsBox').classList.toggle('hidden')" class="text-[11px] text-slate-400 hover:text-slate-600 underline ml-1">
                    <?= __('btn_manual_coords') ?>
                </button>
            </div>

            <div class="flex items-center gap-2">
                <!-- Tombol Batal/Kembalikan (Hanya muncul jika garis digeser) -->
                <button type="button" id="btnCancelLine" class="hidden px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 active:bg-slate-300 text-slate-700 text-xs font-bold transition-all items-center justify-center gap-1.5 border border-slate-200 shadow-sm" title="Kembalikan posisi garis ke semula">
                    <i data-lucide="rotate-ccw" class="w-3.5 h-3.5 text-slate-500"></i>
                    <span><?= __('btn_cancel') ?></span>
                </button>

                <!-- Tombol Simpan Posisi Garis (Akan berubah merah menyala jika belum disimpan) -->
                <button type="button" id="btnSaveLine" class="px-5 py-2.5 rounded-xl bg-orange-500 hover:bg-orange-600 active:bg-orange-700 text-white text-xs font-bold shadow-md shadow-orange-500/25 transition-all flex items-center justify-center gap-2">
                    <i data-lucide="check" class="w-4 h-4" id="saveLineIcon"></i>
                    <span id="saveLineText"><?= __('btn_save_line') ?></span>
                </button>
            </div>
        </div>

        <!-- Kolom Angka (Disembunyikan agar tidak membingungkan, hanya dibuka jika perlu) -->
        <div id="advancedCoordsBox" class="hidden p-3 bg-slate-50 rounded-2xl border border-slate-200 grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs">
            <div>
                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Titik 1 (X)</label>
                <input type="number" id="inp_x1" value="<?= $lineCoords['x1'] ?>" class="w-full text-xs font-mono bg-white border border-slate-200 rounded-lg px-2.5 py-1 text-slate-800">
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Titik 1 (Y)</label>
                <input type="number" id="inp_y1" value="<?= $lineCoords['y1'] ?>" class="w-full text-xs font-mono bg-white border border-slate-200 rounded-lg px-2.5 py-1 text-slate-800">
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Titik 2 (X)</label>
                <input type="number" id="inp_x2" value="<?= $lineCoords['x2'] ?>" class="w-full text-xs font-mono bg-white border border-slate-200 rounded-lg px-2.5 py-1 text-slate-800">
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Titik 2 (Y)</label>
                <input type="number" id="inp_y2" value="<?= $lineCoords['y2'] ?>" class="w-full text-xs font-mono bg-white border border-slate-200 rounded-lg px-2.5 py-1 text-slate-800">
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const canvas = document.getElementById('virtualLineCanvas');
    const ctx = canvas.getContext('2d');
    const camId = '<?= htmlspecialchars($activeCam['id']) ?>';

    // Nilai skala dari 1280x720 ke resolusi canvas 640x360
    const scaleX = canvas.width / 1280;
    const scaleY = canvas.height / 720;

    let coords = {
        x1: parseInt(document.getElementById('inp_x1').value) || 120,
        y1: parseInt(document.getElementById('inp_y1').value) || 380,
        x2: parseInt(document.getElementById('inp_x2').value) || 1160,
        y2: parseInt(document.getElementById('inp_y2').value) || 380,
        direction_in: document.getElementById('inp_dir').value || 'down'
    };

    let draggingPoint = null;
    let isStreamLive = false;

    window.setStreamLiveState = function(state) {
        isStreamLive = state;
        draw();
    };

    function draw() {
        if (isStreamLive) {
            // Mode transparan: biarkan video live stream terlihat jernih di bawah canvas
            ctx.clearRect(0, 0, canvas.width, canvas.height);
        } else {
            // Mode panduan visual: gambar grid & perspektif lantai pintu restoran
            ctx.fillStyle = '#0f172a';
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            ctx.strokeStyle = '#1e293b';
            ctx.lineWidth = 1;
            for (let x = 0; x < canvas.width; x += 40) {
                ctx.beginPath();
                ctx.moveTo(x, 0);
                ctx.lineTo(x, canvas.height);
                ctx.stroke();
            }
            for (let y = 0; y < canvas.height; y += 40) {
                ctx.beginPath();
                ctx.moveTo(0, y);
                ctx.lineTo(canvas.width, y);
                ctx.stroke();
            }

            ctx.strokeStyle = '#334155';
            ctx.beginPath();
            ctx.moveTo(80, 360);
            ctx.lineTo(240, 100);
            ctx.lineTo(400, 100);
            ctx.lineTo(560, 360);
            ctx.stroke();
        }

        // Convert coords to canvas scale
        const cx1 = coords.x1 * scaleX;
        const cy1 = coords.y1 * scaleY;
        const cx2 = coords.x2 * scaleX;
        const cy2 = coords.y2 * scaleY;

        // Draw Virtual Line
        ctx.shadowColor = '#f97316';
        ctx.shadowBlur = 10;
        ctx.strokeStyle = '#f97316';
        ctx.lineWidth = 3;
        ctx.beginPath();
        ctx.moveTo(cx1, cy1);
        ctx.lineTo(cx2, cy2);
        ctx.stroke();
        ctx.shadowBlur = 0;

        // Draw P1 Handle
        ctx.fillStyle = '#10b981';
        ctx.beginPath();
        ctx.arc(cx1, cy1, 8, 0, Math.PI * 2);
        ctx.fill();
        ctx.strokeStyle = '#ffffff';
        ctx.lineWidth = 2;
        ctx.stroke();

        // Draw P2 Handle
        ctx.fillStyle = '#3b82f6';
        ctx.beginPath();
        ctx.arc(cx2, cy2, 8, 0, Math.PI * 2);
        ctx.fill();
        ctx.stroke();

        // Labels P1 and P2
        ctx.fillStyle = '#ffffff';
        ctx.font = 'bold 10px Plus Jakarta Sans, sans-serif';
        ctx.fillText('P1', cx1 - 6, cy1 - 12);
        ctx.fillText('P2', cx2 - 6, cy2 - 12);

        // Draw Direction Arrow
        const midX = (cx1 + cx2) / 2;
        const midY = (cy1 + cy2) / 2;
        drawArrow(ctx, midX, midY, coords.direction_in);

        // Update teks koordinat di badge luar video
        const coordBadge = document.getElementById('coordBadgeText');
        if (coordBadge) {
            coordBadge.innerHTML = 'P1 (' + coords.x1 + ', ' + coords.y1 + ') &bull; P2 (' + coords.x2 + ', ' + coords.y2 + ')';
        }
    }

    function drawArrow(context, x, y, dir) {
        context.strokeStyle = '#fbbf24';
        context.fillStyle = '#fbbf24';
        context.lineWidth = 2;

        let dx = 0, dy = 0;
        if (dir === 'down') dy = 25;
        else if (dir === 'up') dy = -25;
        else if (dir === 'left') dx = -25;
        else if (dir === 'right') dx = 25;

        context.beginPath();
        context.moveTo(x, y);
        context.lineTo(x + dx, y + dy);
        context.stroke();

        context.beginPath();
        context.arc(x + dx, y + dy, 4, 0, Math.PI * 2);
        context.fill();
    }

    draw();

    // Mouse Dragging on Canvas
    function getCanvasPos(e) {
        const rect = canvas.getBoundingClientRect();
        return {
            x: (e.clientX - rect.left) * (canvas.width / rect.width),
            y: (e.clientY - rect.top) * (canvas.height / rect.height)
        };
    }

    canvas.addEventListener('mousedown', function(e) {
        const pos = getCanvasPos(e);
        const p1 = { x: coords.x1 * scaleX, y: coords.y1 * scaleY };
        const p2 = { x: coords.x2 * scaleX, y: coords.y2 * scaleY };

        if (Math.hypot(pos.x - p1.x, pos.y - p1.y) < 15) {
            draggingPoint = 'P1';
        } else if (Math.hypot(pos.x - p2.x, pos.y - p2.y) < 15) {
            draggingPoint = 'P2';
        }
    });

    let savedCoords = JSON.parse(JSON.stringify(coords));
    let isDirty = false;

    function setDirtyState(dirty) {
        isDirty = dirty;
        const btnSave = document.getElementById('btnSaveLine');
        const saveText = document.getElementById('saveLineText');
        const saveIcon = document.getElementById('saveLineIcon');
        const btnCancel = document.getElementById('btnCancelLine');
        const unsavedNotice = document.getElementById('unsavedLineNotice');

        if (dirty) {
            if (unsavedNotice) {
                unsavedNotice.classList.remove('hidden');
                unsavedNotice.classList.add('inline-flex');
            }
            if (btnCancel) {
                btnCancel.classList.remove('hidden');
                btnCancel.classList.add('inline-flex');
            }
            if (btnSave) {
                btnSave.classList.remove('bg-orange-500', 'hover:bg-orange-600', 'shadow-orange-500/25', 'bg-emerald-600', 'hover:bg-emerald-700');
                btnSave.classList.add('bg-rose-500', 'hover:bg-rose-600', 'shadow-rose-500/30', 'animate-pulse');
            }
            if (saveText) saveText.innerText = '<?= __('save_line_dirty') ?>';
            if (saveIcon) saveIcon.setAttribute('data-lucide', 'save');
        } else {
            if (unsavedNotice) {
                unsavedNotice.classList.add('hidden');
                unsavedNotice.classList.remove('inline-flex');
            }
            if (btnCancel) {
                btnCancel.classList.add('hidden');
                btnCancel.classList.remove('inline-flex');
            }
            if (btnSave) {
                btnSave.classList.remove('bg-rose-500', 'hover:bg-rose-600', 'shadow-rose-500/30', 'animate-pulse', 'bg-emerald-600', 'hover:bg-emerald-700');
                btnSave.classList.add('bg-orange-500', 'hover:bg-orange-600', 'shadow-orange-500/25');
            }
            if (saveText) saveText.innerText = '<?= __('btn_save_line') ?>';
            if (saveIcon) saveIcon.setAttribute('data-lucide', 'check');
        }
        lucide.createIcons();
    }

    function checkDirtyState() {
        const changed = (
            coords.x1 !== savedCoords.x1 ||
            coords.y1 !== savedCoords.y1 ||
            coords.x2 !== savedCoords.x2 ||
            coords.y2 !== savedCoords.y2 ||
            coords.direction_in !== savedCoords.direction_in
        );
        setDirtyState(changed);
    }

    window.addEventListener('mousemove', function(e) {
        if (!draggingPoint) return;
        const pos = getCanvasPos(e);

        if (draggingPoint === 'P1') {
            coords.x1 = Math.round(Math.max(0, Math.min(1280, pos.x / scaleX)));
            coords.y1 = Math.round(Math.max(0, Math.min(720, pos.y / scaleY)));
            document.getElementById('inp_x1').value = coords.x1;
            document.getElementById('inp_y1').value = coords.y1;
        } else if (draggingPoint === 'P2') {
            coords.x2 = Math.round(Math.max(0, Math.min(1280, pos.x / scaleX)));
            coords.y2 = Math.round(Math.max(0, Math.min(720, pos.y / scaleY)));
            document.getElementById('inp_x2').value = coords.x2;
            document.getElementById('inp_y2').value = coords.y2;
        }
        draw();
        checkDirtyState();
    });

    window.addEventListener('mouseup', function() {
        if (draggingPoint) {
            checkDirtyState();
        }
        draggingPoint = null;
    });

    // Inputs change listener
    ['inp_x1', 'inp_y1', 'inp_x2', 'inp_y2'].forEach(id => {
        document.getElementById(id).addEventListener('input', function() {
            coords.x1 = parseInt(document.getElementById('inp_x1').value) || 0;
            coords.y1 = parseInt(document.getElementById('inp_y1').value) || 0;
            coords.x2 = parseInt(document.getElementById('inp_x2').value) || 0;
            coords.y2 = parseInt(document.getElementById('inp_y2').value) || 0;
            draw();
            checkDirtyState();
        });
    });

    document.getElementById('inp_dir').addEventListener('change', function() {
        coords.direction_in = this.value;
        const dl = document.getElementById('dirLabel');
        if (dl) dl.innerText = this.value;
        draw();
        checkDirtyState();
    });

    // Tombol Batal/Reset Posisi Garis
    const btnCancel = document.getElementById('btnCancelLine');
    if (btnCancel) {
        btnCancel.addEventListener('click', function() {
            coords = JSON.parse(JSON.stringify(savedCoords));
            document.getElementById('inp_x1').value = coords.x1;
            document.getElementById('inp_y1').value = coords.y1;
            document.getElementById('inp_x2').value = coords.x2;
            document.getElementById('inp_y2').value = coords.y2;
            document.getElementById('inp_dir').value = coords.direction_in;
            const dl = document.getElementById('dirLabel');
            if (dl) dl.innerText = coords.direction_in;
            draw();
            setDirtyState(false);
        });
    }

    // Save Button AJAX
    document.getElementById('btnSaveLine').addEventListener('click', async function() {
        const btn = this;
        const saveText = document.getElementById('saveLineText');
        btn.disabled = true;
        if (saveText) saveText.innerText = 'Menyimpan...';

        try {
            const res = await fetch('api/cameras.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'update_line',
                    camera_id: camId,
                    line_coords: coords
                })
            });
            const data = await res.json();
            if (data.success) {
                savedCoords = JSON.parse(JSON.stringify(coords));
                setDirtyState(false);

                // Efek visual sukses hijau sejuk
                btn.classList.remove('bg-orange-500', 'hover:bg-orange-600', 'bg-rose-500', 'hover:bg-rose-600', 'animate-pulse');
                btn.classList.add('bg-emerald-600', 'hover:bg-emerald-700');
                if (saveText) saveText.innerText = '✓ Berhasil Disimpan!';
                setTimeout(() => {
                    btn.classList.remove('bg-emerald-600', 'hover:bg-emerald-700');
                    btn.classList.add('bg-orange-500', 'hover:bg-orange-600');
                    if (saveText) saveText.innerText = 'Simpan Posisi Garis';
                    lucide.createIcons();
                }, 2500);

                const badge = document.getElementById('saveStatus');
                if (badge) {
                    badge.classList.remove('hidden');
                    setTimeout(() => badge.classList.add('hidden'), 3000);
                }
            } else {
                alert('Gagal menyimpan koordinat: ' + (data.error || 'Terjadi kesalahan'));
                checkDirtyState();
            }
        } catch (e) {
            alert('Error koneksi: ' + e.message);
            checkDirtyState();
        } finally {
            btn.disabled = false;
            lucide.createIcons();
        }
    });

    window.addEventListener('beforeunload', function(e) {
        if (isDirty) {
            e.preventDefault();
            e.returnValue = 'Ada perubahan posisi garis yang belum disimpan. Yakin ingin keluar?';
            return e.returnValue;
        }
    });
});

async function toggleCameraStatus(camId) {
    if (!confirm('Ubah status koneksi kamera ini?')) return;
    try {
        const res = await fetch('api/cameras.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'toggle_camera', camera_id: camId })
        });
        const data = await res.json();
        if (data.success) {
            window.location.reload();
        } else {
            alert('Gagal mengubah status: ' + (data.error || 'Terjadi kesalahan'));
        }
    } catch (e) {
        alert('Error: ' + e.message);
    }
}

async function deleteCamera(camId, camName) {
    if (!confirm('Yakin ingin menghapus kamera "' + camName + '"? Kamera ini akan dilepas dari sistem.')) return;
    try {
        const res = await fetch('api/cameras.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete_camera', camera_id: camId })
        });
        const data = await res.json();
        if (data.success) {
            alert('Kamera berhasil dihapus.');
            window.location.href = 'cameras.php';
        } else {
            alert('Gagal menghapus kamera: ' + (data.error || 'Terjadi kesalahan'));
        }
    } catch (e) {
        alert('Error: ' + e.message);
    }
}

// Live Video Stream Management
let isRunningWorker = false;
let previousTotalIn = null;
const DETECTION_SOUND = <?= (getSetting('detection_sound', '0') === '1') ? 'true' : 'false' ?>;

// Web Audio API Synthesized Bell Chime (Ding-Dong)
function playChimeSound() {
    try {
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        if (!AudioContext) return;
        const ctx = new AudioContext();

        // Nada Ding (880Hz - A5)
        const osc1 = ctx.createOscillator();
        const gain1 = ctx.createGain();
        osc1.type = 'sine';
        osc1.frequency.setValueAtTime(880, ctx.currentTime);
        gain1.gain.setValueAtTime(0.3, ctx.currentTime);
        gain1.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.6);
        osc1.connect(gain1);
        gain1.connect(ctx.destination);
        osc1.start(ctx.currentTime);
        osc1.stop(ctx.currentTime + 0.6);

        // Nada Dong (587.33Hz - D5)
        const osc2 = ctx.createOscillator();
        const gain2 = ctx.createGain();
        osc2.type = 'sine';
        osc2.frequency.setValueAtTime(587.33, ctx.currentTime + 0.25);
        gain2.gain.setValueAtTime(0.35, ctx.currentTime + 0.25);
        gain2.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 1.1);
        osc2.connect(gain2);
        gain2.connect(ctx.destination);
        osc2.start(ctx.currentTime + 0.25);
        osc2.stop(ctx.currentTime + 1.1);
    } catch (e) {
        console.warn('Audio chime not allowed or supported:', e);
    }
}

function updateCounterNumbers(stats) {
    if (!stats) return;
    const inVal = parseInt(stats.total_in) || 0;
    const outVal = parseInt(stats.total_out) || 0;
    const currentVal = Math.max(0, inVal - outVal);
    const fpsVal = stats.fps !== undefined ? Math.round(stats.fps) : 0;

    // Bunyikan lonceng jika ada pengunjung baru masuk dan fitur aktif
    if (previousTotalIn !== null && inVal > previousTotalIn && DETECTION_SOUND) {
        playChimeSound();
    }
    previousTotalIn = inVal;

    const elIn = document.getElementById('popValIn');
    const elOut = document.getElementById('popValOut');
    const elCurrent = document.getElementById('popValCurrent');
    const elFps = document.getElementById('popFps');

    if (elIn) elIn.innerText = inVal;
    if (elOut) elOut.innerText = outVal;
    if (elCurrent) elCurrent.innerText = currentVal;
    if (elFps) elFps.innerText = fpsVal + ' FPS';

    // Update status jam operasional restoran
    const opBadge = document.getElementById('operatingStatusBadge');
    const opText = document.getElementById('operatingStatusText');
    if (opBadge && opText && stats.operating_status !== undefined) {
        opBadge.classList.remove('hidden');
        opBadge.classList.add('inline-flex');

        if (stats.is_open === false) {
            opBadge.className = 'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl text-xs font-bold bg-amber-500/20 text-amber-300 border border-amber-500/40';
            opText.innerHTML = '🌙 Tutup &bull; Standby (' + (stats.operating_status || ('Buka ' + (stats.opening_time || '10:00'))) + ')';
        } else {
            opBadge.className = 'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl text-xs font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30';
            opText.innerText = '🟢 ' + (stats.operating_status || 'Sedang Menghitung');
        }
    }

    // Update tampilan tombol Buka / Tutup Restoran
    const btnStore = document.getElementById('btnToggleStoreStatus');
    const storeText = document.getElementById('storeStatusBtnText');
    const storeIcon = document.getElementById('storeStatusBtnIcon');
    if (btnStore && storeText && stats.is_open !== undefined) {
        btnStore.setAttribute('data-is-open', stats.is_open ? '1' : '0');
        if (stats.is_open) {
            btnStore.className = 'px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-rose-500/20 text-slate-300 hover:text-rose-400 border border-slate-700 hover:border-rose-500/40 text-xs font-bold transition-all flex items-center gap-1.5 shadow-sm';
            storeText.innerText = '<?= __('btn_close_store') ?>';
            if (storeIcon) storeIcon.setAttribute('data-lucide', 'square');
        } else {
            btnStore.className = 'px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 active:bg-emerald-700 text-white text-xs font-bold transition-all shadow-md shadow-emerald-600/30 flex items-center gap-1.5';
            storeText.innerText = '<?= __('btn_open_store') ?>';
            if (storeIcon) storeIcon.setAttribute('data-lucide', 'play');
        }
        lucide.createIcons();
    }
}

async function toggleStoreStatus() {
    const btn = document.getElementById('btnToggleStoreStatus');
    const currentlyOpen = btn && btn.getAttribute('data-is-open') === '1';
    const targetStatus = currentlyOpen ? 'closed' : 'open';

    let confirmMsg = '';
    if (currentlyOpen) {
        const currentGuests = parseInt(document.getElementById('popValCurrent')?.innerText) || 0;
        if (currentGuests > 0) {
            confirmMsg = '⚠️ PERHATIAN: Masih tercatat ' + currentGuests + ' orang di dalam restoran.\n\n' +
                         'Jika restoran ditutup sekarang, kamera akan beralih ke mode standby (jeda menghitung) dan tamu yang keluar tidak akan tercatat lagi.\n\n' +
                         'Yakin ingin menutup restoran sekarang?';
        } else {
            confirmMsg = 'Tutup restoran sekarang? Kamera CCTV akan beralih ke mode standby (jeda menghitung pengunjung).';
        }
    } else {
        // Cek apakah saat ini di luar jam operasional (sebelum buka atau lewat jam tutup)
        const openingTime = '<?= htmlspecialchars($settings['opening_time'] ?? '10:00') ?>';
        const closingTime = '<?= htmlspecialchars($settings['closing_time'] ?? '22:00') ?>';
        
        const now = new Date();
        const currentHM = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');

        if (currentHM < openingTime) {
            confirmMsg = '🌅 BUKA LEBIH AWAL (SEBELUM PK. ' + openingTime + '):\n' +
                         'Jadwal resmi restoran buka pk. ' + openingTime + ' (saat ini pk. ' + currentHM + ').\n\n' +
                         'Jika restoran memang buka lebih awal (misal kejar target penjualan atau rombongan sarapan pagi), ' +
                         'tamu yang masuk akan langsung dihitung ke dalam analitik hari ini.\n\n' +
                         '⚠️ Catatan: Pastikan kru dapur/staf tidak lagi mondar-mandir di pintu agar tidak salah terhitung.\n\n' +
                         'Buka restoran sekarang?';
        } else if (currentHM >= closingTime) {
            confirmMsg = '🌙 BUKA LEMBUR / TUTUP LEBIH LARUT (LEWAT PK. ' + closingTime + '):\n' +
                         'Jadwal resmi restoran tutup pk. ' + closingTime + ' (saat ini pk. ' + currentHM + ').\n\n' +
                         'Jika restoran masih ramai pelanggan dan ingin melayani lebih lama, ' +
                         'tamu yang melintas akan tetap terhitung dan tercatat di analitik hari ini.\n\n' +
                         'Lanjutkan buka restoran sekarang?';
        } else {
            confirmMsg = 'Buka restoran sekarang? Kamera CCTV akan langsung aktif menghitung tamu yang datang.';
        }
    }

    if (!confirm(confirmMsg)) return;

    if (btn) btn.disabled = true;

    try {
        const res = await fetch('api/stream_control.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'toggle_store_status',
                status: targetStatus,
                camera_id: '<?= htmlspecialchars($activeCam['id']) ?>'
            })
        });
        const data = await res.json();
        if (data.success) {
            const badge = document.getElementById('saveStatus');
            if (badge) {
                badge.innerText = data.message;
                badge.classList.remove('hidden');
                setTimeout(() => { badge.classList.add('hidden'); badge.innerText = 'Tersimpan!'; }, 3500);
            }
            await pollLiveCounters();
        } else {
            alert('Gagal mengubah status: ' + (data.error || 'Terjadi kesalahan'));
        }
    } catch(e) {
        alert('Gagal menghubungi server: ' + e.message);
    } finally {
        if (btn) btn.disabled = false;
        lucide.createIcons();
    }
}

async function pollLiveCounters() {
    if (!isRunningWorker) return;
    try {
        const res = await fetch('http://127.0.0.1:8080/status', { cache: 'no-store' });
        if (res.ok) {
            const data = await res.json();
            if (data && data.is_active) {
                updateCounterNumbers(data);
            }
        }
    } catch (e) {
        // Fallback hening jika server belum siap
    }
}

async function checkStreamStatus() {
    try {
        const res = await fetch('api/stream_control.php');
        const data = await res.json();
        const activeCamId = '<?= htmlspecialchars($activeCam['id']) ?>';
        
        // Periksa apakah stream yang aktif memang benar untuk kamera yang sedang dibuka
        const isRunningForThisCam = data.running && (!data.stats || data.stats.camera_id === activeCamId);
        updateStreamUI(isRunningForThisCam, data.stats);
    } catch(e) {
        updateStreamUI(false, null);
    }
}

function updateStreamUI(running, stats) {
    isRunningWorker = running;
    window.setStreamLiveState(running);

    const img = document.getElementById('cameraStreamImg');
    const notice = document.getElementById('streamOfflineNotice');
    const badge = document.getElementById('liveStreamBadge');
    const counterPopUp = document.getElementById('liveCounterPopUp');
    const btn = document.getElementById('btnToggleStream');
    const btnText = document.getElementById('streamBtnText');
    const btnIcon = document.getElementById('streamBtnIcon');

    if (!btn || !img) return;

    if (running) {
        if (img.classList.contains('hidden')) {
            img.src = 'http://127.0.0.1:8080/video_feed?' + new Date().getTime();
            img.classList.remove('hidden');
        }
        if (notice) notice.classList.add('hidden');
        if (badge) {
            badge.classList.remove('hidden');
            badge.classList.add('inline-flex');
        }
        if (counterPopUp) {
            counterPopUp.classList.remove('hidden');
            if (stats) updateCounterNumbers(stats);
        }

        btn.classList.remove('bg-orange-500', 'hover:bg-orange-600', 'shadow-orange-500/20');
        btn.classList.add('bg-rose-500', 'hover:bg-rose-600', 'shadow-rose-500/20');
        btnText.innerText = '<?= __('btn_turn_off_cam') ?>';
        btnIcon.setAttribute('data-lucide', 'square');
    } else {
        img.classList.add('hidden');
        img.src = '';
        if (notice) notice.classList.remove('hidden');
        if (badge) {
            badge.classList.add('hidden');
            badge.classList.remove('inline-flex');
        }
        if (counterPopUp) counterPopUp.classList.add('hidden');

        btn.classList.remove('bg-rose-500', 'hover:bg-rose-600', 'shadow-rose-500/20');
        btn.classList.add('bg-orange-500', 'hover:bg-orange-600', 'shadow-orange-500/20');
        btnText.innerText = '<?= __('btn_turn_on_cam') ?>';
        btnIcon.setAttribute('data-lucide', 'play');
    }
    lucide.createIcons();
}

async function toggleStreamProcess(camId) {
    const btn = document.getElementById('btnToggleStream');
    const btnText = document.getElementById('streamBtnText');
    btn.disabled = true;
    btnText.innerText = 'Memproses...';

    const action = isRunningWorker ? 'stop' : 'start';

    try {
        const res = await fetch('api/stream_control.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: action, camera_id: camId })
        });
        const data = await res.json();
        if (data.message) {
            const badge = document.getElementById('saveStatus');
            if (badge) {
                badge.innerText = data.message;
                badge.classList.remove('hidden');
                setTimeout(() => { badge.classList.add('hidden'); badge.innerText = 'Tersimpan!'; }, 3500);
            }
        }
        await checkStreamStatus();
    } catch(e) {
        alert('Gagal menghubungi kontrol kamera: ' + e.message);
    } finally {
        btn.disabled = false;
        await checkStreamStatus();
    }
}

// Cek status stream saat pertama kali halaman dimuat dan polling berkala
document.addEventListener('DOMContentLoaded', function() {
    checkStreamStatus();
    setInterval(checkStreamStatus, 3000);
    setInterval(pollLiveCounters, 800);
});
</script>

<!-- Style Khusus Animasi Radar (Foto 2) & Laser Scanner (Foto 3) -->
<style>
@keyframes radar-rotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
@keyframes radar-ripple {
    0% { transform: scale(0.65); opacity: 0.85; }
    50% { transform: scale(1.15); opacity: 0.35; }
    100% { transform: scale(1.6); opacity: 0; }
}
@keyframes laser-sweep {
    0% { top: 6%; opacity: 0.95; }
    50% { top: 92%; opacity: 0.95; }
    100% { top: 6%; opacity: 0.95; }
}
.radar-sweep-beam {
    background: conic-gradient(from 0deg, rgba(14, 165, 233, 0.45) 0deg, rgba(14, 165, 233, 0) 75deg);
    animation: radar-rotate 2.8s linear infinite;
}
.radar-ring-pulse-1 {
    animation: radar-ripple 3.2s cubic-bezier(0, 0.2, 0.8, 1) infinite;
}
.radar-ring-pulse-2 {
    animation: radar-ripple 3.2s cubic-bezier(0, 0.2, 0.8, 1) infinite 1.1s;
}
.radar-ring-pulse-3 {
    animation: radar-ripple 3.2s cubic-bezier(0, 0.2, 0.8, 1) infinite 2.2s;
}
.laser-beam-anim {
    animation: laser-sweep 2.2s ease-in-out infinite;
}
</style>

<!-- MODAL 1: PENCARIAN JARINGAN OTOMATIS / RADAR DISCOVERY (Persis Foto 2) -->
<div id="modalRadarDiscovery" class="hidden fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-7 shadow-2xl border border-slate-100 space-y-6 animate-in fade-in zoom-in-95 duration-200 my-8">
        <!-- Header -->
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <button type="button" onclick="closeRadarDiscoveryModal()" class="p-1.5 text-slate-400 hover:text-slate-600 rounded-xl hover:bg-slate-100 transition-colors">
                <i data-lucide="chevron-left" class="w-5 h-5"></i>
            </button>
            <h3 class="text-sm font-bold text-slate-800">Cari Kamera Otomatis</h3>
            <div class="flex items-center gap-1">
                <button type="button" onclick="openQrScannerModal()" title="Pindai Barcode / QR CCTV" class="p-2 text-slate-600 hover:text-orange-600 hover:bg-orange-50 rounded-xl transition-all">
                    <i data-lucide="scan" class="w-5 h-5"></i>
                </button>
                <button type="button" onclick="closeRadarDiscoveryModal()" class="p-2 text-slate-400 hover:text-slate-600 rounded-xl hover:bg-slate-100 transition-colors">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>
        </div>

        <!-- Section Pencarian Radar -->
        <div class="text-center space-y-1.5">
            <h4 class="text-lg font-extrabold text-slate-900 tracking-tight">Mencari Kamera di Sekitar...</h4>
            <p class="text-xs text-slate-500 max-w-sm mx-auto">
                Pastikan kamera CCTV atau HP Anda sudah menyala dan terhubung ke Wi-Fi restoran yang sama.
            </p>
        </div>

        <!-- Radar Animation Container -->
        <div class="relative w-52 h-52 mx-auto flex items-center justify-center my-3">
            <!-- Ripple Wave Rings -->
            <div class="absolute inset-0 rounded-full border border-sky-300/40 radar-ring-pulse-1 pointer-events-none"></div>
            <div class="absolute inset-0 rounded-full border border-sky-400/35 radar-ring-pulse-2 pointer-events-none"></div>
            <div class="absolute inset-0 rounded-full border border-sky-400/25 radar-ring-pulse-3 pointer-events-none"></div>

            <!-- Concentric Grid Circles -->
            <div class="absolute w-44 h-44 rounded-full border border-sky-100 bg-sky-50/20"></div>
            <div class="absolute w-28 h-28 rounded-full border border-sky-200/60"></div>
            <div class="absolute w-14 h-14 rounded-full border border-sky-300/70"></div>

            <!-- Rotating Radar Sweep Beam -->
            <div class="absolute w-44 h-44 rounded-full overflow-hidden pointer-events-none">
                <div class="w-full h-full radar-sweep-beam"></div>
            </div>

            <!-- Center Center Point -->
            <div class="relative z-10 w-4 h-4 rounded-full bg-sky-500 border-2 border-white shadow-md flex items-center justify-center">
                <span class="w-2 h-2 rounded-full bg-white animate-ping"></span>
            </div>
        </div>

        <!-- Status & Hasil Pemindaian -->
        <div class="space-y-3">
            <div id="radarScanStatusText" class="text-center text-xs font-medium text-slate-500 flex items-center justify-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-sky-500 animate-pulse"></span>
                <span>Sedang mencari kamera aktif di Wi-Fi...</span>
            </div>

            <!-- Discovered Devices Container -->
            <div id="radarDiscoveredContainer" class="hidden space-y-2 max-h-48 overflow-y-auto pr-1"></div>

            <!-- Scan Action Buttons -->
            <div class="flex items-center justify-center gap-2 pt-1">
                <button type="button" onclick="startRadarDiscoveryScan()" id="btnRadarRescan" 
                        class="px-4 py-2 rounded-xl bg-sky-50 hover:bg-sky-100 text-sky-700 text-xs font-bold transition-all flex items-center gap-1.5">
                    <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
                    <span>Cari Ulang</span>
                </button>
            </div>
        </div>

        <!-- Section: Tambahkan Secara Manual (Persis Foto 2) -->
        <div class="pt-4 border-t border-slate-100 space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-800">Pilih Jenis Kamera Langsung</span>
                <span class="text-[11px] text-slate-400">Jika tidak ketemu otomatis</span>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                <!-- IP Camera Indoor / Statis -->
                <button type="button" onclick="openManualAddWithPreset('HIKVISION')" 
                        class="group p-3 rounded-2xl border border-slate-200/80 hover:border-orange-500 hover:bg-orange-50/40 transition-all text-center flex flex-col items-center justify-center gap-2 shadow-xs">
                    <div class="w-10 h-10 rounded-xl bg-slate-100 group-hover:bg-orange-500 text-slate-600 group-hover:text-white flex items-center justify-center transition-colors">
                        <i data-lucide="camera" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <div class="text-[11px] font-bold text-slate-800 group-hover:text-orange-600">CCTV Toko</div>
                        <div class="text-[9px] text-slate-400">Hikvision / Dahua</div>
                    </div>
                </button>

                <!-- IP Camera PTZ 360 -->
                <button type="button" onclick="openManualAddWithPreset('TAPO')" 
                        class="group p-3 rounded-2xl border border-slate-200/80 hover:border-orange-500 hover:bg-orange-50/40 transition-all text-center flex flex-col items-center justify-center gap-2 shadow-xs">
                    <div class="w-10 h-10 rounded-xl bg-slate-100 group-hover:bg-orange-500 text-slate-600 group-hover:text-white flex items-center justify-center transition-colors">
                        <i data-lucide="aperture" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <div class="text-[11px] font-bold text-slate-800 group-hover:text-orange-600">CCTV Putar</div>
                        <div class="text-[9px] text-slate-400">Tapo / Bardi / Imou</div>
                    </div>
                </button>

                <!-- HP Smartphone (DroidCam / IP Webcam) -->
                <button type="button" onclick="openManualAddWithPreset('DROIDCAM')" 
                        class="group p-3 rounded-2xl border border-slate-200/80 hover:border-orange-500 hover:bg-orange-50/40 transition-all text-center flex flex-col items-center justify-center gap-2 shadow-xs">
                    <div class="w-10 h-10 rounded-xl bg-slate-100 group-hover:bg-orange-500 text-slate-600 group-hover:text-white flex items-center justify-center transition-colors">
                        <i data-lucide="smartphone" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <div class="text-[11px] font-bold text-slate-800 group-hover:text-orange-600">Kamera HP</div>
                        <div class="text-[9px] text-slate-400">Android / iPhone</div>
                    </div>
                </button>

                <!-- Webcam USB -->
                <button type="button" onclick="openManualAddWithPreset('WEBCAM_USB')" 
                        class="group p-3 rounded-2xl border border-slate-200/80 hover:border-orange-500 hover:bg-orange-50/40 transition-all text-center flex flex-col items-center justify-center gap-2 shadow-xs">
                    <div class="w-10 h-10 rounded-xl bg-slate-100 group-hover:bg-orange-500 text-slate-600 group-hover:text-white flex items-center justify-center transition-colors">
                        <i data-lucide="laptop" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <div class="text-[11px] font-bold text-slate-800 group-hover:text-orange-600">Kamera Laptop</div>
                        <div class="text-[9px] text-slate-400">Webcam Bawaan</div>
                    </div>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL 2: PEMINDAI QR CODE & BARCODE KAMERA (Persis Foto 3) -->
<div id="modalQrScanner" class="hidden fixed inset-0 z-50 bg-slate-950/90 backdrop-blur-md flex items-center justify-center p-4">
    <div class="bg-slate-900 rounded-3xl max-w-md w-full p-6 text-white border border-slate-800 shadow-2xl space-y-5 animate-in fade-in zoom-in-95 duration-200">
        <!-- Header -->
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
            <button type="button" onclick="closeQrScannerModal()" class="p-1.5 text-slate-400 hover:text-white rounded-xl hover:bg-slate-800 transition-colors">
                <i data-lucide="chevron-left" class="w-5 h-5"></i>
            </button>
            <h3 class="text-sm font-bold text-slate-200">Arahkan ke Barcode / QR</h3>
            <button type="button" onclick="closeQrScannerModal()" class="p-1.5 text-slate-400 hover:text-white rounded-xl hover:bg-slate-800 transition-colors">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <!-- Viewfinder Box dengan Target Reticle & Laser Sweep -->
        <div class="relative w-full aspect-square max-w-[320px] mx-auto bg-black rounded-3xl overflow-hidden border border-slate-800 flex items-center justify-center">
            <!-- Reader HTML5 element container -->
            <div id="qr-reader" class="w-full h-full object-cover"></div>

            <!-- Fallback Viewfinder Frame jika reader berjalan di canvas -->
            <div class="absolute inset-6 pointer-events-none flex flex-col justify-between">
                <div class="flex justify-between">
                    <div class="w-8 h-8 border-t-4 border-l-4 border-emerald-400 rounded-tl-xl"></div>
                    <div class="w-8 h-8 border-t-4 border-r-4 border-emerald-400 rounded-tr-xl"></div>
                </div>
                <!-- Laser Sweep Beam -->
                <div class="relative w-full h-0.5 bg-gradient-to-r from-transparent via-emerald-400 to-transparent laser-beam-anim shadow-lg shadow-emerald-400/50"></div>
                <div class="flex justify-between">
                    <div class="w-8 h-8 border-b-4 border-l-4 border-emerald-400 rounded-bl-xl"></div>
                    <div class="w-8 h-8 border-b-4 border-r-4 border-emerald-400 rounded-br-xl"></div>
                </div>
            </div>
        </div>

        <!-- Instruction Text -->
        <div class="text-center space-y-1">
            <p class="text-xs text-slate-300 font-medium">Arahkan stiker barcode di bodi CCTV atau layar HP ke dalam kotak.</p>
            <p class="text-[11px] text-slate-500">Mendukung kamera Ezviz, Tapo, Hikvision, dan HP DroidCam.</p>
        </div>

        <!-- Pill Link -->
        <div class="text-center">
            <button type="button" onclick="closeQrScannerModal(); openManualAddWithPreset('HIKVISION')" 
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full bg-slate-800/90 hover:bg-slate-800 text-xs text-slate-300 hover:text-white border border-slate-700/60 transition-all">
                <span>Mau masukkan manual saja? Klik di sini</span>
                <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
            </button>
        </div>

        <!-- Bottom Action Bar -->
        <div class="grid grid-cols-2 gap-3 pt-2 border-t border-slate-800">
            <button type="button" onclick="promptManualSetupCode()" 
                    class="py-2.5 px-3 rounded-2xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold transition-all flex items-center justify-center gap-2">
                <i data-lucide="keyboard" class="w-4 h-4 text-orange-400"></i>
                <span>Ketik Kode Kamera</span>
            </button>

            <button type="button" onclick="closeQrScannerModal(); openRadarDiscoveryModal()" 
                    class="py-2.5 px-3 rounded-2xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold transition-all flex items-center justify-center gap-2">
                <i data-lucide="radar" class="w-4 h-4 text-sky-400"></i>
                <span>Cari Lewat Wi-Fi</span>
            </button>
        </div>
    </div>
</div>

<!-- Modal Tambah CCTV Baru (Sederhana & Mudah Dipahami) -->
<div id="modalAddCam" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-7 shadow-2xl border border-slate-100 space-y-4 animate-in fade-in duration-200 my-8">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-orange-50 text-orange-600 flex items-center justify-center">
                    <i data-lucide="video" class="w-4 h-4"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-slate-900">Hubungkan Kamera Baru</h3>
                    <p class="text-[11px] text-slate-500">Mendukung CCTV toko, HP Android/iPhone, hingga laptop.</p>
                </div>
            </div>
            <div class="flex items-center gap-1">
                <button type="button" onclick="openRadarDiscoveryModal()" class="px-2.5 py-1.5 text-xs font-semibold text-sky-700 bg-sky-50 hover:bg-sky-100 rounded-xl flex items-center gap-1 transition-colors">
                    <i data-lucide="radar" class="w-3.5 h-3.5"></i>
                    <span>Cari Otomatis</span>
                </button>
                <button type="button" onclick="document.getElementById('modalAddCam').classList.add('hidden')" class="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>
        </div>

        <form id="formAddCam" class="space-y-3.5">
            <!-- Informasi Dasar -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 mb-1">Nama Kamera</label>
                    <input type="text" id="new_cam_name" placeholder="Contoh: Pintu Depan" required
                           class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800 focus:outline-none focus:border-orange-500">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 mb-1">Posisi / Tempat</label>
                    <input type="text" id="new_cam_loc" placeholder="Contoh: Depan Kasir" required
                           class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800 focus:outline-none focus:border-orange-500">
                </div>
            </div>

            <!-- Pilihan Merek / Tipe Perangkat -->
            <div>
                <label class="block text-[11px] font-bold text-slate-700 mb-1">Jenis / Merek Kamera</label>
                <select id="cam_preset_select" onchange="applyCctvPreset(this.value)" class="w-full text-xs font-semibold bg-orange-50/40 border border-orange-200 rounded-xl px-3 py-2 text-slate-900 focus:outline-none focus:border-orange-500">
                    <optgroup label="CCTV Toko & Wi-Fi">
                        <option value="HIKVISION">Hikvision / HiLook / Ezviz</option>
                        <option value="DAHUA">Dahua / Imou</option>
                        <option value="TAPO">TP-Link Tapo (C200 / C210)</option>
                        <option value="BARDI">Bardi / Tuya Smart Cam</option>
                        <option value="XIONGMAI">XiongMai / XMeye / SPC (DVR)</option>
                        <option value="MANUAL_RTSP">Kamera IP Lainnya</option>
                    </optgroup>
                    <optgroup label="Kamera HP Smartphone">
                        <option value="DROIDCAM">Kamera HP (DroidCam via Wi-Fi)</option>
                        <option value="IP_WEBCAM_HP">Kamera HP (IP Webcam via Wi-Fi)</option>
                    </optgroup>
                    <optgroup label="Kamera Laptop & Video">
                        <option value="WEBCAM_USB">Kamera Laptop Bawaan / USB</option>
                        <option value="VIDEO_FILE">Video Rekaman Contoh (Uji Coba)</option>
                    </optgroup>
                </select>
            </div>

            <!-- Parameter Form Pembantu (IP & Sandi) -->
            <div id="boxCctvParams" class="p-3 bg-slate-50 rounded-2xl border border-slate-200/80 space-y-2.5">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2" id="gridNetworkFields">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Alamat IP Kamera</label>
                        <input type="text" id="builder_ip" value="192.168.1.64" oninput="rebuildUri()" placeholder="Contoh: 192.168.1.50"
                               class="w-full text-xs font-mono bg-white border border-slate-200 rounded-lg px-2.5 py-1.5 text-slate-800 focus:outline-none focus:border-orange-500">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Kata Sandi Kamera</label>
                        <input type="password" id="builder_pass" value="admin123" oninput="rebuildUri()" placeholder="Kata Sandi CCTV"
                               class="w-full text-xs bg-white border border-slate-200 rounded-lg px-2.5 py-1.5 text-slate-800 focus:outline-none focus:border-orange-500">
                    </div>

                    <!-- Input Lanjutan (Port, Channel, User) disembunyikan agar rapi -->
                    <div id="advancedNetworkFields" class="hidden col-span-2 grid grid-cols-3 gap-2 pt-1 border-t border-slate-200/60">
                        <div>
                            <label class="block text-[9px] font-bold text-slate-400 uppercase">Username</label>
                            <input type="text" id="builder_user" value="admin" oninput="rebuildUri()" class="w-full text-xs bg-white border border-slate-200 rounded px-2 py-1">
                        </div>
                        <div>
                            <label class="block text-[9px] font-bold text-slate-400 uppercase">Port</label>
                            <input type="number" id="builder_port" value="554" oninput="rebuildUri()" class="w-full text-xs bg-white border border-slate-200 rounded px-2 py-1">
                        </div>
                        <div>
                            <label class="block text-[9px] font-bold text-slate-400 uppercase">Channel</label>
                            <input type="number" id="builder_channel" value="1" oninput="rebuildUri()" class="w-full text-xs bg-white border border-slate-200 rounded px-2 py-1">
                        </div>
                    </div>
                    <div class="col-span-2 text-right">
                        <button type="button" onclick="document.getElementById('advancedNetworkFields').classList.toggle('hidden')" class="text-[10px] text-slate-400 hover:text-slate-600 underline">
                            Atur Port / Username
                        </button>
                    </div>
                </div>

                <!-- Field khusus USB -->
                <div id="gridUsbFields" class="hidden">
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Pilih Kamera</label>
                    <select id="builder_usb_index" onchange="rebuildUri()" class="w-full text-xs bg-white border border-slate-200 rounded-lg px-2.5 py-1.5 text-slate-800">
                        <option value="0">Kamera Laptop Bawaan (Webcam Utama)</option>
                        <option value="1">Kamera USB Eksternal Port 2</option>
                    </select>
                </div>

                <!-- Field khusus Video File -->
                <div id="gridFileFields" class="hidden">
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">File Video Contoh</label>
                    <input type="text" id="builder_file_path" value="samples/video_sample.mp4" oninput="rebuildUri()"
                           class="w-full text-xs font-mono bg-white border border-slate-200 rounded-lg px-2.5 py-1.5 text-slate-800">
                </div>

                <!-- Petunjuk Ramah -->
                <p id="presetHelpText" class="text-[11px] text-slate-500 italic bg-white/70 p-2 rounded-lg border border-slate-200/60">
                    Hikvision / Ezviz: Gunakan password yang Anda buat saat pertama kali menyalakan kamera.
                </p>
            </div>

            <!-- URL Tersembunyi (Disimpan di latar belakang tanpa membingungkan user) -->
            <input type="hidden" id="new_cam_uri" value="rtsp://admin:admin123@192.168.1.64:554/Streaming/Channels/101">
            <input type="hidden" id="new_cam_type" value="RTSP">
            
            <!-- Status Uji Koneksi -->
            <div id="testConnStatus" class="hidden p-2 rounded-xl text-xs font-medium"></div>

            <!-- Footer Tombol -->
            <div class="pt-2 border-t border-slate-100 flex items-center justify-between">
                <button type="button" onclick="testCctvConnection()" id="btnTestConn" class="px-3.5 py-2 rounded-xl border border-slate-200 hover:bg-slate-50 text-xs font-bold text-slate-700 flex items-center gap-1.5 transition-colors">
                    <i data-lucide="radio" class="w-3.5 h-3.5 text-orange-500"></i>
                    <span>Tes Sambungan</span>
                </button>

                <div class="flex items-center gap-2">
                    <button type="button" onclick="document.getElementById('modalAddCam').classList.add('hidden')" class="px-3 py-2 rounded-xl text-xs font-semibold text-slate-500 hover:bg-slate-100 transition-colors">
                        Batal
                    </button>
                    <button type="submit" id="btnSubmitNewCam" class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-xl text-xs font-bold shadow-md shadow-orange-500/20 transition-all flex items-center gap-1.5">
                        <i data-lucide="check" class="w-4 h-4"></i>
                        <span>Simpan Kamera</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
let currentPresetKey = 'HIKVISION';

function applyCctvPreset(key) {
    currentPresetKey = key;
    const gridNet = document.getElementById('gridNetworkFields');
    const gridUsb = document.getElementById('gridUsbFields');
    const gridFile = document.getElementById('gridFileFields');
    const help = document.getElementById('presetHelpText');
    const typeInp = document.getElementById('new_cam_type');
    const portInp = document.getElementById('builder_port');

    // Sembunyikan semua sub-grid
    gridNet.classList.remove('hidden');
    gridUsb.classList.add('hidden');
    gridFile.classList.add('hidden');

    if (key === 'HIKVISION') {
        typeInp.value = 'RTSP';
        portInp.value = '554';
        help.innerText = 'Hikvision / HiLook / Ezviz: Menggunakan port RTSP 554. Jalur utama saluran 1 adalah 101.';
    } else if (key === 'DAHUA') {
        typeInp.value = 'RTSP';
        portInp.value = '554';
        help.innerText = 'Dahua / Imou: Menggunakan port 554 dan jalur /cam/realmonitor.';
    } else if (key === 'TAPO') {
        typeInp.value = 'RTSP';
        portInp.value = '554';
        help.innerText = 'TP-Link Tapo: Pastikan membuat "Camera Account" di aplikasi Tapo > Pengaturan Perangkat > Pengaturan Lanjutan.';
    } else if (key === 'BARDI') {
        typeInp.value = 'RTSP';
        portInp.value = '554';
        help.innerText = 'Bardi / Tuya Smart Cam: Menggunakan port 554 jalur /live/ch0.';
    } else if (key === 'XIONGMAI') {
        typeInp.value = 'RTSP';
        portInp.value = '554';
        help.innerText = 'XiongMai / XMeye / Topsvision / SPC: Standar DVR/NVR menggunakan format jalur user-password sdp.';
    } else if (key === 'UNIVIEW') {
        typeInp.value = 'RTSP';
        portInp.value = '554';
        help.innerText = 'Uniview (UNV): Format stream utama /unicast/c1/s0/live pada port 554.';
    } else if (key === 'REOLINK') {
        typeInp.value = 'RTSP';
        portInp.value = '554';
        help.innerText = 'Reolink: Pastikan opsi RTSP diaktifkan di Server Settings Reolink.';
    } else if (key === 'IP_WEBCAM_HP') {
        typeInp.value = 'RTSP';
        portInp.value = '8080';
        help.innerText = 'HP Smartphone (IP Webcam): Pasang aplikasi "IP Webcam" dari Play Store. Klik "Start Server", lalu masukkan IP HP & port 8080 di sini.';
    } else if (key === 'DROIDCAM') {
        typeInp.value = 'RTSP';
        portInp.value = '4747';
        help.innerText = 'HP Smartphone (DroidCam): Pasang aplikasi "DroidCam" di HP. Buka aplikasi, lalu masukkan IP WiFi & port 4747 di sini (Bisa via WiFi, Bluetooth, atau kabel USB).';
    } else if (key === 'WEBCAM_USB') {
        typeInp.value = 'WEBCAM';
        gridNet.classList.add('hidden');
        gridUsb.classList.remove('hidden');
        help.innerText = 'Kabel USB: Pilih index 0 untuk webcam internal atau 1 untuk webcam USB eksternal.';
    } else if (key === 'VIDEO_FILE') {
        typeInp.value = 'VIDEO_FILE';
        gridNet.classList.add('hidden');
        gridFile.classList.remove('hidden');
        help.innerText = 'File Video: Masukkan lokasi file MP4 rekaman di komputer.';
    } else {
        typeInp.value = 'RTSP';
        help.innerText = 'Masukkan URL RTSP lengkap dari kamera CCTV IP Anda.';
    }

    rebuildUri();
}

function rebuildUri() {
    const key = currentPresetKey;
    const uriInp = document.getElementById('new_cam_uri');
    const ip = document.getElementById('builder_ip').value.trim() || '192.168.1.64';
    const port = document.getElementById('builder_port').value.trim() || '554';
    const ch = document.getElementById('builder_channel').value.trim() || '1';
    const user = encodeURIComponent(document.getElementById('builder_user').value.trim() || 'admin');
    const pass = encodeURIComponent(document.getElementById('builder_pass').value.trim() || 'admin123');

    if (key === 'HIKVISION') {
        uriInp.value = `rtsp://${user}:${pass}@${ip}:${port}/Streaming/Channels/${ch}01`;
    } else if (key === 'DAHUA') {
        uriInp.value = `rtsp://${user}:${pass}@${ip}:${port}/cam/realmonitor?channel=${ch}&subtype=0`;
    } else if (key === 'TAPO') {
        uriInp.value = `rtsp://${user}:${pass}@${ip}:${port}/stream1`;
    } else if (key === 'BARDI') {
        uriInp.value = `rtsp://${user}:${pass}@${ip}:${port}/live/ch0`;
    } else if (key === 'XIONGMAI') {
        uriInp.value = `rtsp://${user}:${pass}@${ip}:${port}/user=${user}_password=${pass}_channel=${ch}_stream=0.sdp`;
    } else if (key === 'UNIVIEW') {
        uriInp.value = `rtsp://${user}:${pass}@${ip}:${port}/unicast/c${ch}/s0/live`;
    } else if (key === 'REOLINK') {
        const paddedCh = String(ch).padStart(2, '0');
        uriInp.value = `rtsp://${user}:${pass}@${ip}:${port}/h264Preview_${paddedCh}_main`;
    } else if (key === 'IP_WEBCAM_HP' || key === 'DROIDCAM') {
        uriInp.value = `http://${ip}:${port}/video`;
    } else if (key === 'WEBCAM_USB') {
        uriInp.value = document.getElementById('builder_usb_index').value;
    } else if (key === 'VIDEO_FILE') {
        uriInp.value = document.getElementById('builder_file_path').value.trim() || 'samples/video_sample.mp4';
    }
}

// Uji Sambungan Kamera (Ping / Socket Check)
async function testCctvConnection() {
    const btn = document.getElementById('btnTestConn');
    const statusBox = document.getElementById('testConnStatus');
    const uri = document.getElementById('new_cam_uri').value.trim();
    const type = document.getElementById('new_cam_type').value;

    btn.disabled = true;
    btn.innerHTML = '<span class="animate-spin">&bull;</span> Menguji...';
    statusBox.classList.remove('hidden', 'bg-emerald-50', 'text-emerald-800', 'border-emerald-200', 'bg-rose-50', 'text-rose-800', 'border-rose-200');
    statusBox.classList.add('bg-slate-100', 'text-slate-700', 'border', 'border-slate-200');
    statusBox.innerText = 'Menghubungi perangkat kamera...';

    try {
        const res = await fetch('api/cctv_scanner.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'test', source_type: type, source_uri: uri })
        });
        const data = await res.json();

        statusBox.classList.remove('bg-slate-100', 'text-slate-700', 'border-slate-200');
        if (data.success) {
            statusBox.classList.add('bg-emerald-50', 'text-emerald-800', 'border', 'border-emerald-200');
            statusBox.innerHTML = `<strong>Koneksi Berhasil!</strong> ${data.message}`;
        } else {
            statusBox.classList.add('bg-rose-50', 'text-rose-800', 'border', 'border-rose-200');
            statusBox.innerHTML = `<strong>Gagal Terhubung:</strong> ${data.error || 'Perangkat tidak merespons.'}`;
        }
    } catch (e) {
        statusBox.classList.add('bg-rose-50', 'text-rose-800', 'border', 'border-rose-200');
        statusBox.innerText = 'Error: ' + e.message;
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="radio" class="w-3.5 h-3.5"></i><span>Uji Sambungan Kamera</span>';
        lucide.createIcons();
    }
}

// Pindai Otomatis CCTV di Wi-Fi / LAN
async function scanNetworkCctv() {
    const box = document.getElementById('scanResultsBox');
    const list = document.getElementById('scanDevicesList');
    const title = document.getElementById('scanResultTitle');

    box.classList.remove('hidden');
    title.innerText = 'Sedang memindai jaringan Wi-Fi/LAN restoran...';
    list.innerHTML = '<div class="text-[11px] text-slate-500">Mencari port 554 (RTSP) dan 8000 di subnet lokal...</div>';

    try {
        const res = await fetch('api/cctv_scanner.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'scan' })
        });
        const data = await res.json();

        if (data.devices && data.devices.length > 0) {
            title.innerText = `Ditemukan ${data.devices.length} Perangkat CCTV Aktif:`;
            list.innerHTML = '';
            data.devices.forEach(d => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'w-full text-left p-1.5 rounded-lg bg-white border border-emerald-300 hover:bg-emerald-100 flex items-center justify-between text-xs text-slate-800';
                btn.innerHTML = `<span><strong>${d.ip}</strong> (${d.service} - Port ${d.port})</span> <span class="text-[10px] bg-emerald-600 text-white px-2 py-0.5 rounded font-bold">Pilih IP</span>`;
                btn.onclick = function() {
                    document.getElementById('builder_ip').value = d.ip;
                    document.getElementById('builder_port').value = d.port;
                    rebuildUri();
                    box.classList.add('hidden');
                };
                list.appendChild(btn);
            });
        } else {
            title.innerText = 'Pindai Selesai';
            list.innerHTML = `<div class="text-[11px] text-slate-600">${data.message}</div>`;
        }
    } catch (e) {
        title.innerText = 'Gagal Memindai';
        list.innerHTML = `<div class="text-[11px] text-rose-600">Error: ${e.message}</div>`;
    }
}

document.getElementById('formAddCam').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSubmitNewCam');
    btn.disabled = true;
    btn.innerText = 'Menyimpan...';

    const payload = {
        action: 'create_camera',
        name: document.getElementById('new_cam_name').value.trim(),
        location: document.getElementById('new_cam_loc').value.trim(),
        source_type: document.getElementById('new_cam_type').value,
        source_uri: document.getElementById('new_cam_uri').value.trim()
    };

    try {
        const res = await fetch('api/cameras.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            window.location.href = 'cameras.php?id=' + encodeURIComponent(data.id);
        } else {
            alert('Gagal menambahkan kamera: ' + (data.error || 'Terjadi kesalahan'));
            btn.disabled = false;
            btn.innerText = 'Simpan & Sambungkan Kamera';
        }
    } catch (err) {
        alert('Error: ' + err.message);
        btn.disabled = false;
        btn.innerText = 'Simpan & Sambungkan Kamera';
    }
});

// Library HTML5-QRCode untuk Scanner (Foto 3)
</script>
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
let html5QrScanner = null;

// Buka Modal Radar Pencarian Jaringan (Foto 2)
function openRadarDiscoveryModal() {
    document.getElementById('modalRadarDiscovery').classList.remove('hidden');
    document.getElementById('modalAddCam').classList.add('hidden');
    document.getElementById('modalQrScanner').classList.add('hidden');
    lucide.createIcons();
    startRadarDiscoveryScan();
}

function closeRadarDiscoveryModal() {
    document.getElementById('modalRadarDiscovery').classList.add('hidden');
}

// Buka Formulir Manual dengan Preset Tertentu
function openManualAddWithPreset(presetKey) {
    document.getElementById('modalRadarDiscovery').classList.add('hidden');
    document.getElementById('modalQrScanner').classList.add('hidden');
    document.getElementById('modalAddCam').classList.remove('hidden');
    document.getElementById('cam_preset_select').value = presetKey;
    applyCctvPreset(presetKey);
    lucide.createIcons();
}

// Pemindaian Radar Otomatis Menggunakan ARP & Multi-Port Socket Probe
async function startRadarDiscoveryScan() {
    const statusText = document.getElementById('radarScanStatusText');
    const container = document.getElementById('radarDiscoveredContainer');
    const btnRescan = document.getElementById('btnRadarRescan');

    btnRescan.disabled = true;
    btnRescan.innerHTML = '<span class="animate-spin">&bull;</span> Memindai...';
    statusText.innerHTML = '<span class="w-2 h-2 rounded-full bg-sky-500 animate-pulse"></span><span>Memindai port RTSP (554), DVR (8000), DroidCam (4747), & IP Webcam (8080)...</span>';
    container.classList.remove('hidden');
    container.innerHTML = '<div class="text-center py-4 text-xs text-slate-400">Menghubungkan ke pemindai jaringan...</div>';

    try {
        const res = await fetch('api/cctv_scanner.php?action=scan');
        const data = await res.json();

        if (data.devices && data.devices.length > 0) {
            statusText.innerHTML = `<span class="w-2 h-2 rounded-full bg-emerald-500"></span><strong class="text-emerald-700">Ditemukan ${data.devices.length} Perangkat Kamera di Wi-Fi / LAN!</strong>`;
            container.innerHTML = '';

            data.devices.forEach(dev => {
                const card = document.createElement('div');
                card.className = 'p-3 rounded-2xl bg-slate-50 border border-slate-200/80 hover:border-emerald-400 hover:bg-emerald-50/40 transition-all flex items-center justify-between gap-3 text-left animate-in fade-in slide-in-from-bottom-2';
                
                let icon = 'video';
                if (dev.type === 'DROIDCAM' || dev.type === 'IP_WEBCAM_HP') icon = 'smartphone';
                if (dev.type === 'HIKVISION') icon = 'camera';

                card.innerHTML = `
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-sky-500/10 text-sky-600 flex items-center justify-center shrink-0">
                            <i data-lucide="${icon}" class="w-4 h-4"></i>
                        </div>
                        <div>
                            <div class="text-xs font-bold text-slate-800">${dev.brand_guess || 'Perangkat CCTV'}</div>
                            <div class="text-[10px] text-slate-500 font-mono">${dev.ip}:${dev.port} &bull; ${dev.service}</div>
                        </div>
                    </div>
                    <button type="button" class="shrink-0 px-3.5 py-1.5 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-bold shadow-sm transition-all flex items-center gap-1">
                        <span>Hubungkan</span>
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </button>
                `;

                card.querySelector('button').onclick = function() {
                    connectDiscoveredDevice(dev);
                };

                container.appendChild(card);
            });
            lucide.createIcons();
        } else {
            statusText.innerHTML = `<span class="w-2 h-2 rounded-full bg-amber-500"></span><span class="text-amber-800">${data.message || 'Tidak ada port kamera terbuka otomatis.'}</span>`;
            container.innerHTML = `
                <div class="p-3 bg-slate-50 rounded-2xl border border-slate-200 text-center text-xs text-slate-500 space-y-1">
                    <p class="font-medium text-slate-700">CCTV belum terdeteksi otomatis?</p>
                    <p class="text-[11px]">Pastikan CCTV berada di jaringan Wi-Fi yang sama, atau pilih salah satu menu <strong>Tambahkan Secara Manual</strong> di bawah.</p>
                </div>
            `;
        }
    } catch(err) {
        statusText.innerHTML = `<span class="w-2 h-2 rounded-full bg-rose-500"></span><span class="text-rose-700">Gagal memindai: ${err.message}</span>`;
        container.innerHTML = `<div class="text-center py-2 text-xs text-rose-500">Error menghubungi pemindai.</div>`;
    } finally {
        btnRescan.disabled = false;
        btnRescan.innerHTML = '<i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i><span>Pindai Ulang Jaringan</span>';
        lucide.createIcons();
    }
}

// Hubungkan Otomatis Perangkat yang Terdeteksi Radar
function connectDiscoveredDevice(dev) {
    closeRadarDiscoveryModal();
    document.getElementById('modalAddCam').classList.remove('hidden');

    let targetPreset = 'HIKVISION';
    if (dev.type === 'DROIDCAM') targetPreset = 'DROIDCAM';
    else if (dev.type === 'IP_WEBCAM_HP') targetPreset = 'IP_WEBCAM_HP';
    else if (dev.brand_guess && dev.brand_guess.includes('Dahua')) targetPreset = 'DAHUA';

    document.getElementById('cam_preset_select').value = targetPreset;
    applyCctvPreset(targetPreset);

    document.getElementById('builder_ip').value = dev.ip;
    document.getElementById('builder_port').value = dev.port;
    document.getElementById('new_cam_name').value = (dev.brand_guess || 'CCTV') + ' (' + dev.ip + ')';

    rebuildUri();
    testCctvConnection();
}

// Kontrol Pemindai QR Code & Barcode (Foto 3)
function openQrScannerModal() {
    document.getElementById('modalRadarDiscovery').classList.add('hidden');
    document.getElementById('modalAddCam').classList.add('hidden');
    document.getElementById('modalQrScanner').classList.remove('hidden');
    lucide.createIcons();

    if (typeof Html5Qrcode !== 'undefined') {
        try {
            if (html5QrScanner) {
                html5QrScanner.stop().catch(() => {}).then(() => startQrReader());
            } else {
                startQrReader();
            }
        } catch(e) {
            console.error('QR scanner init error', e);
        }
    }
}

function startQrReader() {
    const readerDiv = document.getElementById('qr-reader');
    readerDiv.innerHTML = '';
    html5QrScanner = new Html5Qrcode('qr-reader');
    html5QrScanner.start(
        { facingMode: 'environment' },
        { fps: 15, qrbox: { width: 220, height: 220 } },
        (decodedText, decodedResult) => {
            handleQrCodeDetected(decodedText);
        },
        (errorMessage) => {
            // Frame tidak berisi barcode, biarkan loop lanjut
        }
    ).catch(err => {
        readerDiv.innerHTML = `
            <div class="p-6 text-center text-xs text-slate-400 space-y-3">
                <div class="w-10 h-10 mx-auto rounded-xl bg-slate-800 text-slate-400 flex items-center justify-center">
                    <i data-lucide="camera-off" class="w-5 h-5"></i>
                </div>
                <p>Kamera tidak dapat diakses atau izin browser dinonaktifkan.</p>
                <button type="button" onclick="promptManualSetupCode()" class="px-4 py-2 bg-orange-500 text-white rounded-xl text-xs font-bold shadow-md">
                    Ketik Setup Code Manual
                </button>
            </div>
        `;
        lucide.createIcons();
    });
}

function closeQrScannerModal() {
    if (html5QrScanner) {
        try { html5QrScanner.stop().catch(() => {}); } catch(e) {}
    }
    document.getElementById('modalQrScanner').classList.add('hidden');
}

// Menangani Hasil Deteksi QR / Barcode
function handleQrCodeDetected(text) {
    closeQrScannerModal();
    
    // Suara beep notifikasi jika didukung audio browser
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        osc.connect(audioCtx.destination);
        osc.frequency.value = 880;
        osc.start();
        osc.stop(audioCtx.currentTime + 0.1);
    } catch(e) {}

    let ip = '';
    let port = '554';
    let preset = 'HIKVISION';

    const urlMatch = text.match(/(?:https?|rtsp):\/\/([^\/:]+)(?::(\d+))?/i);
    const ipMatch = text.match(/\b(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\b/);

    if (urlMatch) {
        ip = urlMatch[1];
        if (urlMatch[2]) port = urlMatch[2];
        if (port === '4747') preset = 'DROIDCAM';
        else if (port === '8080') preset = 'IP_WEBCAM_HP';
    } else if (ipMatch) {
        ip = ipMatch[1];
    } else {
        ip = '192.168.1.64';
    }

    document.getElementById('modalAddCam').classList.remove('hidden');
    document.getElementById('cam_preset_select').value = preset;
    applyCctvPreset(preset);

    if (ip) document.getElementById('builder_ip').value = ip;
    if (port) document.getElementById('builder_port').value = port;
    document.getElementById('new_cam_name').value = 'CCTV QR ' + (text.substring(0, 14));

    rebuildUri();
    alert('QR Code / Barcode Berhasil Dikenali!\nNilai: ' + text);
    testCctvConnection();
}

// Masukkan Setup Code Manual
function promptManualSetupCode() {
    const code = prompt('Masukkan Setup Code, IP Kamera, atau URL RTSP (Misal: 192.168.1.64 atau http://192.168.1.20:8080):');
    if (code && code.trim()) {
        handleQrCodeDetected(code.trim());
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
