with open('cameras.php', 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Add Edit button
old_btn = 'onclick="deleteCamera(\'<?= $activeCam[\'id\'] ?>\''
new_btn = '''onclick="openEditCamModal()" 
                        class="w-full py-2 px-3 text-[11px] font-semibold text-slate-700 hover:text-orange-600 hover:bg-orange-50 rounded-xl border border-slate-200 transition-all flex items-center justify-center gap-1.5 mb-2">
                    <i data-lucide="key-round" class="w-3.5 h-3.5"></i>
                    <span>Ubah URL RTSP / Kata Sandi</span>
                </button>

                <button type="button" onclick="deleteCamera('<?= $activeCam['id'] ?>' '''

if old_btn in content:
    content = content.replace('onclick="deleteCamera(\'<?= $activeCam[\'id\'] ?>\'', new_btn, 1)
    print('Button added successfully')
else:
    print('old_btn not found')

# 2. Add Modal
old_script_tag = '<script>\nlet currentPresetKey'
modal_html = '''<!-- Modal Edit Kamera (Ubah Password / RTSP Langsung) -->
<div id="modalEditCam" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-100 space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-xl bg-orange-50 text-orange-600 flex items-center justify-center">
                    <i data-lucide="key-round" class="w-4 h-4"></i>
                </div>
                <h3 class="text-sm font-bold text-slate-900">Ubah URL RTSP / Kata Sandi CCTV</h3>
            </div>
            <button type="button" onclick="document.getElementById('modalEditCam').classList.add('hidden')" class="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
        <form id="formEditCam" onsubmit="saveCameraEdit(event)" class="space-y-3.5">
            <div>
                <label class="block text-[11px] font-bold text-slate-700 mb-1">Nama Kamera</label>
                <input type="text" id="edit_cam_name" value="<?= htmlspecialchars($activeCam['name']) ?>" required class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800 focus:outline-none focus:border-orange-500">
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-700 mb-1">URL Sumber Video (RTSP Stream)</label>
                <input type="text" id="edit_cam_uri" value="<?= htmlspecialchars($activeCam['source_uri']) ?>" required class="w-full text-xs font-mono bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800 focus:outline-none focus:border-orange-500">
                <p class="text-[10px] text-slate-500 mt-1">Ganti <code class="bg-slate-100 px-1 py-0.5 rounded text-orange-600 font-mono">admin123</code> dengan kata sandi asli CCTV toko Anda.</p>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('modalEditCam').classList.add('hidden')" class="px-3 py-2 rounded-xl text-xs font-semibold text-slate-500 hover:bg-slate-100 transition-colors">Batal</button>
                <button type="submit" id="btnSaveEditCam" class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-orange-500 hover:bg-orange-600 shadow-sm transition-all flex items-center gap-1.5">
                    <i data-lucide="check" class="w-4 h-4"></i>
                    <span>Simpan Perubahan</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditCamModal() {
    document.getElementById('modalEditCam').classList.remove('hidden');
    if (window.lucide) lucide.createIcons();
}
async function saveCameraEdit(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSaveEditCam');
    btn.disabled = true;
    btn.innerText = 'Menyimpan...';
    const name = document.getElementById('edit_cam_name').value.trim();
    const uri = document.getElementById('edit_cam_uri').value.trim();
    try {
        const res = await fetch('api/cameras.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'update_camera',
                camera_id: '<?= $activeCam["id"] ?>',
                name: name,
                source_uri: uri
            })
        });
        const data = await res.json();
        if (data.success) {
            window.location.reload();
        } else {
            alert('Gagal: ' + (data.error || 'Terjadi kesalahan'));
            btn.disabled = false;
            btn.innerText = 'Simpan Perubahan';
        }
    } catch(err) {
        alert('Gagal menghubungi server: ' + err.message);
        btn.disabled = false;
        btn.innerText = 'Simpan Perubahan';
    }
}
'''

if '<script>' in content:
    # replace first <script> occurrence after line 1300
    pos = content.find('let currentPresetKey')
    if pos != -1:
        script_pos = content.rfind('<script>', 0, pos)
        content = content[:script_pos] + modal_html + content[script_pos:]
        print('Modal added successfully')
    else:
        print('currentPresetKey not found')

with open('cameras.php', 'w', encoding='utf-8') as f:
    f.write(content)
print('Done!')
