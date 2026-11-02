with open('cameras.php', 'r', encoding='utf-8') as f:
    code = f.read()

# 1. Fix default activeCam when empty
target_init = """if (!$activeCam && !empty($cameras)) {
    $activeCam = $cameras[0];
}"""

replacement_init = """if (!$activeCam && !empty($cameras)) {
    $activeCam = $cameras[0];
}
if (!$activeCam) {
    $activeCam = [
        'id' => '',
        'name' => 'Belum Ada Kamera Terhubung',
        'location' => 'Pintu Masuk Restoran',
        'source_type' => 'RTSP',
        'source_uri' => '',
        'resolution_width' => 1280,
        'resolution_height' => 720,
        'fps' => 25,
        'line_coords' => json_encode(['x1' => 120, 'y1' => 380, 'x2' => 1160, 'y2' => 380, 'direction_in' => 'down', 'offset' => 30]),
        'is_active' => 0
    ];
}"""

# 2. Fix camera list empty state
target_cam_list = """            <div class="space-y-2">
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
            </div>"""

replacement_cam_list = """            <div class="space-y-2">
                <?php if (empty($cameras)): ?>
                    <div class="p-5 rounded-2xl bg-slate-50 border border-dashed border-slate-200 text-center space-y-2">
                        <div class="w-10 h-10 mx-auto rounded-xl bg-orange-50 text-orange-500 flex items-center justify-center">
                            <i data-lucide="video-off" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-slate-800">0 Kamera Terhubung</p>
                            <p class="text-[11px] text-slate-500 mt-0.5">Klik tombol di bawah untuk mendeteksi CCTV secara otomatis.</p>
                        </div>
                        <button type="button" onclick="openRadarDiscoveryModal()" 
                                class="w-full mt-2 py-2.5 px-3 bg-orange-500 hover:bg-orange-600 text-white rounded-xl text-xs font-bold shadow-sm shadow-orange-500/20 transition-all flex items-center justify-center gap-1.5">
                            <i data-lucide="radar" class="w-3.5 h-3.5 animate-pulse"></i>
                            <span>Pindai CCTV Wi-Fi (Scan)</span>
                        </button>
                    </div>
                <?php else: ?>
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
                <?php endif; ?>
            </div>"""

# 3. Fix action buttons when empty
target_actions = """            <!-- Satu Tombol Saklar Utama: Nyalakan / Matikan Kamera -->
            <div class="pt-4 mt-3 border-t border-slate-100 space-y-2">
                <button type="button" id="btnToggleStream" onclick="toggleStreamProcess('<?= $activeCam['id'] ?>')" 
                        class="w-full py-3 px-3 rounded-2xl bg-orange-500 hover:bg-orange-600 active:bg-orange-700 text-white text-xs font-bold shadow-md shadow-orange-500/20 transition-all flex items-center justify-center gap-2">
                    <i data-lucide="play" class="w-4 h-4" id="streamBtnIcon"></i>
                    <span id="streamBtnText"><?= __('btn_turn_on_cam') ?></span>
                </button>

                <button type="button" onclick="openEditCamModal()" 
                        class="w-full py-2 px-3 text-[11px] font-semibold text-slate-700 hover:text-orange-600 hover:bg-orange-50 rounded-xl border border-slate-200 transition-all flex items-center justify-center gap-1.5 mb-2">
                    <i data-lucide="key-round" class="w-3.5 h-3.5"></i>
                    <span>Ubah URL RTSP / Kata Sandi</span>
                </button>

                <button type="button" onclick="deleteCamera('<?= $activeCam['id'] ?>' , '<?= htmlspecialchars(addslashes($activeCam['name'])) ?>')" 
                        class="w-full py-2 px-3 text-[11px] font-semibold text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-xl transition-all flex items-center justify-center gap-1.5">
                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                    <span><?= __('btn_delete_camera') ?></span>
                </button>
            </div>"""

replacement_actions = """            <!-- Tombol Aksi Kamera -->
            <div class="pt-4 mt-3 border-t border-slate-100 space-y-2">
                <?php if (empty($cameras)): ?>
                    <button type="button" onclick="openRadarDiscoveryModal()" 
                            class="w-full py-3 px-3 rounded-2xl bg-orange-500 hover:bg-orange-600 active:bg-orange-700 text-white text-xs font-bold shadow-md shadow-orange-500/20 transition-all flex items-center justify-center gap-2">
                        <i data-lucide="plus-circle" class="w-4 h-4"></i>
                        <span>Hubungkan Kamera CCTV Baru</span>
                    </button>
                <?php else: ?>
                    <button type="button" id="btnToggleStream" onclick="toggleStreamProcess('<?= $activeCam['id'] ?>')" 
                            class="w-full py-3 px-3 rounded-2xl bg-orange-500 hover:bg-orange-600 active:bg-orange-700 text-white text-xs font-bold shadow-md shadow-orange-500/20 transition-all flex items-center justify-center gap-2">
                        <i data-lucide="play" class="w-4 h-4" id="streamBtnIcon"></i>
                        <span id="streamBtnText"><?= __('btn_turn_on_cam') ?></span>
                    </button>

                    <button type="button" onclick="openEditCamModal()" 
                            class="w-full py-2 px-3 text-[11px] font-semibold text-slate-700 hover:text-orange-600 hover:bg-orange-50 rounded-xl border border-slate-200 transition-all flex items-center justify-center gap-1.5 mb-2">
                        <i data-lucide="key-round" class="w-3.5 h-3.5"></i>
                        <span>Ubah URL RTSP / Kata Sandi</span>
                    </button>

                    <button type="button" onclick="deleteCamera('<?= $activeCam['id'] ?>' , '<?= htmlspecialchars(addslashes($activeCam['name'])) ?>')" 
                            class="w-full py-2 px-3 text-[11px] font-semibold text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-xl transition-all flex items-center justify-center gap-1.5">
                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                        <span><?= __('btn_delete_camera') ?></span>
                    </button>
                <?php endif; ?>
            </div>"""

# 4. Fix connectDiscoveredDevice to use default_uri with high priority
target_connect = """// Hubungkan Otomatis Perangkat yang Terdeteksi Radar
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
}"""

replacement_connect = """// Hubungkan Otomatis Perangkat yang Terdeteksi Radar
function connectDiscoveredDevice(dev) {
    closeRadarDiscoveryModal();
    document.getElementById('modalAddCam').classList.remove('hidden');

    if (dev.default_uri) {
        document.getElementById('cam_preset_select').value = 'MANUAL_RTSP';
        applyCctvPreset('MANUAL_RTSP');
        document.getElementById('new_cam_uri').value = dev.default_uri;
        document.getElementById('new_cam_name').value = dev.brand_guess || 'Kamera CCTV Toko Utama';
        document.getElementById('new_cam_loc').value = 'Pintu Masuk';
        document.getElementById('new_cam_type').value = 'RTSP';
        document.getElementById('builder_ip').value = dev.ip;
        document.getElementById('builder_port').value = dev.port;
        testCctvConnection();
        return;
    }

    let targetPreset = 'HIKVISION';
    if (dev.type === 'DROIDCAM') targetPreset = 'DROIDCAM';
    else if (dev.type === 'IP_WEBCAM_HP') targetPreset = 'IP_WEBCAM_HP';
    else if (dev.brand_guess && dev.brand_guess.includes('Dahua')) targetPreset = 'DAHUA';
    else if (dev.brand_guess && dev.brand_guess.includes('Xiong')) targetPreset = 'XIONGMAI';

    document.getElementById('cam_preset_select').value = targetPreset;
    applyCctvPreset(targetPreset);

    document.getElementById('builder_ip').value = dev.ip;
    document.getElementById('builder_port').value = dev.port;
    document.getElementById('new_cam_name').value = (dev.brand_guess || 'CCTV Toko') + ' (' + dev.ip + ')';

    rebuildUri();
    testCctvConnection();
}"""

norm_code = code.replace('\r\n', '\n')
for t, r, name in [
    (target_init, replacement_init, "init"),
    (target_cam_list, replacement_cam_list, "cam_list"),
    (target_actions, replacement_actions, "actions"),
    (target_connect, replacement_connect, "connect")
]:
    t_norm = t.replace('\r\n', '\n')
    r_norm = r.replace('\r\n', '\n')
    if t_norm in norm_code:
        norm_code = norm_code.replace(t_norm, r_norm)
        print(f"SUCCESS: {name} replaced!")
    else:
        print(f"FAILED: {name} not found!")

with open('cameras.php', 'w', encoding='utf-8', newline='\r\n') as f:
    f.write(norm_code)
print("Updated cameras.php successfully!")
