<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/database.php';

$pageTitle = 'Kamera CCTV & Editor Garis Virtual';
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
    <h2 class="text-2xl font-bold tracking-tight text-slate-900">Pengaturan Kamera & Garis Pintu Masuk</h2>
    <p class="text-xs text-slate-500 mt-1">Atur garis batas di depan pintu. Setiap orang yang melewati garis ini akan otomatis dihitung oleh kamera.</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Camera List & Quick Info (1 Col) -->
    <div class="space-y-4">
        <div class="bg-white rounded-3xl p-5 border border-slate-200/80 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-sm font-bold text-slate-900">Daftar Kamera</h3>
                    <span class="text-[11px] text-slate-400 font-medium"><?= count($cameras) ?> Kamera Terhubung</span>
                </div>
                <button type="button" onclick="document.getElementById('modalAddCam').classList.remove('hidden')" 
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-orange-500 hover:bg-orange-600 text-white rounded-xl text-xs font-semibold shadow-sm transition-all">
                    <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                    <span>Tambah CCTV</span>
                </button>
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
                            Lokasi: <?= htmlspecialchars($cam['location']) ?>
                        </div>
                        <div class="text-[10px] text-slate-400 mt-1 font-mono">
                            <?= htmlspecialchars($cam['source_type']) ?> &bull; <?= $cam['resolution_width'] ?>x<?= $cam['resolution_height'] ?> @ <?= $cam['fps'] ?>fps
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Camera Information Card -->
        <div class="bg-white rounded-3xl p-5 border border-slate-200/80 shadow-sm">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">Detail Kamera Aktif</h3>
            <div class="space-y-2.5 text-xs">
                <div class="flex justify-between py-1 border-b border-slate-100">
                    <span class="text-slate-500">ID Kamera:</span>
                    <span class="font-mono text-slate-700"><?= htmlspecialchars($activeCam['id']) ?></span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-100">
                    <span class="text-slate-500">Nama:</span>
                    <span class="font-semibold text-slate-800"><?= htmlspecialchars($activeCam['name']) ?></span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-100">
                    <span class="text-slate-500">Tipe Input:</span>
                    <span class="font-semibold text-orange-600"><?= htmlspecialchars($activeCam['source_type']) ?></span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-100">
                    <span class="text-slate-500">URI Sumber:</span>
                    <span class="font-mono text-[11px] text-slate-600 truncate max-w-[150px]"><?= htmlspecialchars($activeCam['source_uri']) ?></span>
                </div>
                <div class="flex justify-between py-1">
                    <span class="text-slate-500">Status Koneksi:</span>
                    <?php if ($activeCam['is_active']): ?>
                        <span class="text-emerald-700 font-bold bg-emerald-50 px-2 py-0.5 rounded text-[11px]">TERHUBUNG (AKTIF)</span>
                    <?php else: ?>
                        <span class="text-slate-500 font-bold bg-slate-100 px-2 py-0.5 rounded text-[11px]">TERPUTUS (NONAKTIF)</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tombol Kontrol Nyalakan Kamera, Putus & Hapus -->
            <div class="pt-4 mt-4 border-t border-slate-100 space-y-2">
                <button type="button" id="btnToggleStream" onclick="toggleStreamProcess('<?= $activeCam['id'] ?>')" 
                        class="w-full py-2.5 px-3 rounded-xl bg-orange-500 hover:bg-orange-600 active:bg-orange-700 text-white text-xs font-bold shadow-md shadow-orange-500/20 transition-all flex items-center justify-center gap-2">
                    <i data-lucide="play" class="w-3.5 h-3.5" id="streamBtnIcon"></i>
                    <span id="streamBtnText">Nyalakan Kamera (Live AI)</span>
                </button>

                <button type="button" onclick="toggleCameraStatus('<?= $activeCam['id'] ?>')" 
                        class="w-full py-2 px-3 rounded-xl border border-slate-200 hover:bg-slate-50 text-xs font-semibold text-slate-700 transition-colors flex items-center justify-center gap-1.5">
                    <i data-lucide="<?= $activeCam['is_active'] ? 'power-off' : 'power' ?>" class="w-3.5 h-3.5 text-slate-500"></i>
                    <span><?= $activeCam['is_active'] ? 'Putus Sementara Koneksi' : 'Sambungkan Kembali' ?></span>
                </button>

                <button type="button" onclick="deleteCamera('<?= $activeCam['id'] ?>', '<?= htmlspecialchars(addslashes($activeCam['name'])) ?>')" 
                        class="w-full py-2 px-3 rounded-xl border border-rose-200 hover:bg-rose-50 text-xs font-semibold text-rose-600 transition-colors flex items-center justify-center gap-1.5">
                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                    <span>Hapus Kamera Ini</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Virtual Line Canvas Editor (2 Cols) -->
    <div class="lg:col-span-2 bg-white rounded-3xl p-6 border border-slate-200/80 shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
                <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                    <i data-lucide="sliders" class="w-4 h-4 text-orange-500"></i>
                    <span>Atur Garis Batas Pintu</span>
                </h3>
                <p class="text-xs text-slate-500 mt-0.5">Geser titik hijau (P1) dan titik biru (P2) pada gambar di bawah agar pas di depan pintu masuk restoran.</p>
            </div>
            <div id="saveStatus" class="hidden text-xs font-semibold px-3 py-1 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200">
                Tersimpan!
            </div>
        </div>

        <!-- Canvas & Live Video Stream Container -->
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
            
            <!-- Badges -->
            <div class="absolute top-3 left-3 bg-black/75 backdrop-blur-md px-3 py-1.5 rounded-xl border border-white/10 text-[11px] text-white space-y-0.5 pointer-events-none z-20">
                <div class="font-bold flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                    <span>P1 (<?= $lineCoords['x1'] ?>, <?= $lineCoords['y1'] ?>) &bull; P2 (<?= $lineCoords['x2'] ?>, <?= $lineCoords['y2'] ?>)</span>
                </div>
                <div class="text-[10px] text-slate-400">Arah Masuk (IN): <span class="text-orange-400 uppercase font-bold" id="dirLabel"><?= $lineCoords['direction_in'] ?? 'down' ?></span></div>
            </div>

            <!-- Live Status Indicator -->
            <div id="liveStreamBadge" class="absolute top-3 right-3 bg-emerald-500/20 border border-emerald-500/30 text-emerald-400 backdrop-blur-md px-3 py-1 rounded-xl text-[11px] font-bold flex items-center gap-1.5 z-20 hidden">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <span>LIVE CCTV AKTIF</span>
            </div>
        </div>

        <!-- Coordinates Form -->
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 pt-2">
            <div>
                <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">P1 - X</label>
                <input type="number" id="inp_x1" value="<?= $lineCoords['x1'] ?>" class="w-full text-xs font-mono bg-slate-50 border border-slate-200 rounded-xl px-3 py-1.5 text-slate-800 focus:outline-none focus:border-orange-500">
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">P1 - Y</label>
                <input type="number" id="inp_y1" value="<?= $lineCoords['y1'] ?>" class="w-full text-xs font-mono bg-slate-50 border border-slate-200 rounded-xl px-3 py-1.5 text-slate-800 focus:outline-none focus:border-orange-500">
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">P2 - X</label>
                <input type="number" id="inp_x2" value="<?= $lineCoords['x2'] ?>" class="w-full text-xs font-mono bg-slate-50 border border-slate-200 rounded-xl px-3 py-1.5 text-slate-800 focus:outline-none focus:border-orange-500">
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">P2 - Y</label>
                <input type="number" id="inp_y2" value="<?= $lineCoords['y2'] ?>" class="w-full text-xs font-mono bg-slate-50 border border-slate-200 rounded-xl px-3 py-1.5 text-slate-800 focus:outline-none focus:border-orange-500">
            </div>
            <div class="col-span-2 sm:col-span-1">
                <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">Arah Masuk</label>
                <select id="inp_dir" class="w-full text-xs font-medium bg-slate-50 border border-slate-200 rounded-xl px-2 py-1.5 text-slate-800 focus:outline-none focus:border-orange-500">
                    <option value="down" <?= ($lineCoords['direction_in'] ?? '') === 'down' ? 'selected' : '' ?>>Bawah (&darr;)</option>
                    <option value="up" <?= ($lineCoords['direction_in'] ?? '') === 'up' ? 'selected' : '' ?>>Atas (&uarr;)</option>
                    <option value="left" <?= ($lineCoords['direction_in'] ?? '') === 'left' ? 'selected' : '' ?>>Kiri (&larr;)</option>
                    <option value="right" <?= ($lineCoords['direction_in'] ?? '') === 'right' ? 'selected' : '' ?>>Kanan (&rarr;)</option>
                </select>
            </div>
        </div>

        <div class="flex items-center justify-between pt-3 border-t border-slate-100">
            <span class="text-xs text-slate-400">Dimensi Resolusi Acuan: 1280 &times; 720</span>
            <button type="button" id="btnSaveLine" class="px-5 py-2.5 rounded-xl bg-orange-500 hover:bg-orange-600 active:bg-orange-700 text-white text-xs font-bold shadow-lg shadow-orange-500/25 transition-all flex items-center gap-2">
                <i data-lucide="check" class="w-4 h-4"></i>
                <span>Simpan Koordinat Garis</span>
            </button>
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
    });

    window.addEventListener('mouseup', function() {
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
        });
    });

    document.getElementById('inp_dir').addEventListener('change', function() {
        coords.direction_in = this.value;
        document.getElementById('dirLabel').innerText = this.value;
        draw();
    });

    // Save Button AJAX
    document.getElementById('btnSaveLine').addEventListener('click', async function() {
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<span>Menyimpan...</span>';

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
                const badge = document.getElementById('saveStatus');
                badge.classList.remove('hidden');
                setTimeout(() => badge.classList.add('hidden'), 3000);
            } else {
                alert('Gagal menyimpan koordinat: ' + (data.error || 'Unknown error'));
            }
        } catch (e) {
            alert('Error koneksi: ' + e.message);
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i data-lucide="check" class="w-4 h-4"></i><span>Simpan Koordinat Garis</span>';
            lucide.createIcons();
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

async function checkStreamStatus() {
    try {
        const res = await fetch('api/stream_control.php');
        const data = await res.json();
        updateStreamUI(data.running);
    } catch(e) {
        updateStreamUI(false);
    }
}

function updateStreamUI(running) {
    isRunningWorker = running;
    window.setStreamLiveState(running);

    const img = document.getElementById('cameraStreamImg');
    const notice = document.getElementById('streamOfflineNotice');
    const badge = document.getElementById('liveStreamBadge');
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
        if (badge) badge.classList.remove('hidden');

        btn.classList.remove('bg-orange-500', 'hover:bg-orange-600', 'shadow-orange-500/20');
        btn.classList.add('bg-rose-500', 'hover:bg-rose-600', 'shadow-rose-500/20');
        btnText.innerText = 'Matikan Kamera (Stop)';
        btnIcon.setAttribute('data-lucide', 'square');
    } else {
        img.classList.add('hidden');
        if (notice) notice.classList.remove('hidden');
        if (badge) badge.classList.add('hidden');

        btn.classList.remove('bg-rose-500', 'hover:bg-rose-600', 'shadow-rose-500/20');
        btn.classList.add('bg-orange-500', 'hover:bg-orange-600', 'shadow-orange-500/20');
        btnText.innerText = 'Nyalakan Kamera (Live AI)';
        btnIcon.setAttribute('data-lucide', 'play');
    }
    lucide.createIcons();
}

async function toggleStreamProcess(camId) {
    const btn = document.getElementById('btnToggleStream');
    const btnText = document.getElementById('streamBtnText');
    btn.disabled = true;
    const oldText = btnText.innerText;
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
            // Tampilkan status cepat
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
        btnText.innerText = oldText;
        await checkStreamStatus();
    }
}

// Cek status stream saat pertama kali halaman dimuat dan setiap 4 detik
document.addEventListener('DOMContentLoaded', function() {
    checkStreamStatus();
    setInterval(checkStreamStatus, 4000);
});
</script>

<!-- Modal Tambah CCTV Baru -->
<div id="modalAddCam" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl border border-slate-100 space-y-5 animate-in fade-in duration-200">
        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-orange-50 text-orange-600 flex items-center justify-center">
                    <i data-lucide="video" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-slate-900">Hubungkan CCTV Baru</h3>
                    <p class="text-xs text-slate-500">Tambahkan kamera untuk memantau area pintu restoran.</p>
                </div>
            </div>
            <button type="button" onclick="document.getElementById('modalAddCam').classList.add('hidden')" class="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form id="formAddCam" class="space-y-4">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Kamera</label>
                <input type="text" id="new_cam_name" placeholder="Misal: CCTV Pintu Masuk Utama" required
                       class="w-full text-xs font-medium bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800 focus:outline-none focus:border-orange-500">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Lokasi Pemasangan</label>
                <input type="text" id="new_cam_loc" placeholder="Misal: Pintu Masuk Depan" required
                       class="w-full text-xs font-medium bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800 focus:outline-none focus:border-orange-500">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Jenis Kamera</label>
                <select id="new_cam_type" onchange="updateUriPlaceholder(this.value)" class="w-full text-xs font-medium bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800 focus:outline-none focus:border-orange-500">
                    <option value="RTSP">Kamera IP / CCTV (RTSP Stream)</option>
                    <option value="WEBCAM">Webcam USB / Kamera Laptop (Angka 0)</option>
                    <option value="VIDEO_FILE">Berkas Rekaman Video (MP4)</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Alamat Streaming / Kamera</label>
                <input type="text" id="new_cam_uri" placeholder="rtsp://admin:admin123@192.168.1.100:554/live" required
                       class="w-full text-xs font-mono bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800 focus:outline-none focus:border-orange-500">
                <p id="uriHelp" class="text-[11px] text-slate-400 mt-1">
                    Masukkan alamat RTSP dari kamera CCTV IP Anda (contoh: rtsp://username:password@IP_KAMERA:554/...).
                </p>
            </div>

            <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                <button type="button" onclick="document.getElementById('modalAddCam').classList.add('hidden')" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 transition-colors">
                    Batal
                </button>
                <button type="submit" id="btnSubmitNewCam" class="px-5 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-xl text-xs font-bold shadow-md shadow-orange-500/20 transition-all flex items-center gap-1.5">
                    <i data-lucide="check" class="w-4 h-4"></i>
                    <span>Simpan Kamera</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function updateUriPlaceholder(type) {
    const inp = document.getElementById('new_cam_uri');
    const help = document.getElementById('uriHelp');
    if (type === 'RTSP') {
        inp.placeholder = 'rtsp://admin:admin123@192.168.1.100:554/live';
        help.innerText = 'Masukkan alamat RTSP dari kamera CCTV IP Anda.';
    } else if (type === 'WEBCAM') {
        inp.placeholder = '0';
        inp.value = '0';
        help.innerText = 'Gunakan angka 0 untuk webcam bawaan laptop atau kamera USB.';
    } else {
        inp.placeholder = 'samples/video_sample.mp4';
        help.innerText = 'Masukkan path lokasi berkas video mp4 di komputer.';
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
            btn.innerText = 'Simpan Kamera';
        }
    } catch (err) {
        alert('Error: ' + err.message);
        btn.disabled = false;
        btn.innerText = 'Simpan Kamera';
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
