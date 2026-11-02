with open('cameras.php', 'r', encoding='utf-8') as f:
    content = f.read()

target = """                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'w-full text-left p-1.5 rounded-lg bg-white border border-emerald-300 hover:bg-emerald-100 flex items-center justify-between text-xs text-slate-800';
                btn.innerHTML = `<span><strong>${d.ip}</strong> (${d.service} - Port ${d.port})</span> <span class="text-[10px] bg-emerald-600 text-white px-2 py-0.5 rounded font-bold">Pilih IP</span>`;
                btn.onclick = function() {
                    document.getElementById('builder_ip').value = d.ip;
                    document.getElementById('builder_port').value = d.port;
                    rebuildUri();
                    box.classList.add('hidden');
                };
                list.appendChild(btn);"""

replacement = """                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'w-full text-left p-2 rounded-xl bg-white border border-emerald-300 hover:bg-emerald-50 hover:border-emerald-400 flex items-center justify-between text-xs text-slate-800 transition-all shadow-xs';
                btn.innerHTML = `<div class="flex flex-col"><span class="font-bold text-slate-800">${d.brand_guess || d.ip}</span><span class="text-[10px] text-slate-500">${d.ip}:${d.port} • ${d.service}</span></div> <span class="text-[10px] bg-emerald-600 hover:bg-emerald-700 text-white px-2.5 py-1 rounded-lg font-bold transition-all shadow-xs">Gunakan Kamera</span>`;
                btn.onclick = function() {
                    if (d.default_uri) {
                        document.getElementById('new_cam_uri').value = d.default_uri;
                        document.getElementById('new_cam_name').value = d.brand_guess || 'Kamera CCTV Toko Utama';
                        document.getElementById('new_cam_type').value = 'RTSP';
                    }
                    document.getElementById('builder_ip').value = d.ip;
                    document.getElementById('builder_port').value = d.port;
                    box.classList.add('hidden');
                    if (typeof testConnection === 'function') {
                        testConnection();
                    }
                };
                list.appendChild(btn);"""

# Normalize line endings for replacement
norm_content = content.replace('\r\n', '\n')
norm_target = target.replace('\r\n', '\n')
norm_replacement = replacement.replace('\r\n', '\n')

if norm_target in norm_content:
    norm_content = norm_content.replace(norm_target, norm_replacement)
    with open('cameras.php', 'w', encoding='utf-8', newline='\r\n') as f:
        f.write(norm_content)
    print("SUCCESS: cameras.php updated!")
else:
    print("FAILED: target not found in cameras.php")

# Also update api/cameras.php delete_camera to stop stream
with open('api/cameras.php', 'r', encoding='utf-8') as f:
    api_content = f.read()

target_api = """        if ($action === 'delete_camera') {
            $cameraId = $input['camera_id'] ?? null;
            if (!$cameraId) {
                http_response_code(422);
                echo json_encode(['error' => 'camera_id required']);
                exit;
            }
            $stmt = $pdo->prepare("DELETE FROM cameras WHERE id = ?");
            $stmt->execute([$cameraId]);
            echo json_encode(['success' => true, 'message' => 'Kamera berhasil dihapus']);
            exit;
        }"""

replacement_api = """        if ($action === 'delete_camera') {
            $cameraId = $input['camera_id'] ?? null;
            if (!$cameraId) {
                http_response_code(422);
                echo json_encode(['error' => 'camera_id required']);
                exit;
            }
            // Hentikan worker jika kamera yang dihapus sedang aktif
            $ch = curl_init("http://127.0.0.1:8080/shutdown");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 1);
            @curl_exec($ch);
            curl_close($ch);

            $stmt = $pdo->prepare("DELETE FROM cameras WHERE id = ?");
            $stmt->execute([$cameraId]);
            echo json_encode(['success' => true, 'message' => 'Kamera berhasil dihapus']);
            exit;
        }"""

norm_api = api_content.replace('\r\n', '\n')
norm_target_api = target_api.replace('\r\n', '\n')
norm_replacement_api = replacement_api.replace('\r\n', '\n')

if norm_target_api in norm_api:
    norm_api = norm_api.replace(norm_target_api, norm_replacement_api)
    with open('api/cameras.php', 'w', encoding='utf-8', newline='\r\n') as f:
        f.write(norm_api)
    print("SUCCESS: api/cameras.php updated!")
else:
    print("FAILED: target not found in api/cameras.php")
