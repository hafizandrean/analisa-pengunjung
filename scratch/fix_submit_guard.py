with open('cameras.php', 'r', encoding='utf-8') as f:
    text = f.read()

old_submit = """    const payload = {
        action: 'create_camera',
        name: document.getElementById('new_cam_name').value.trim(),
        location: document.getElementById('new_cam_loc').value.trim(),
        source_type: document.getElementById('new_cam_type').value,
        source_uri: document.getElementById('new_cam_uri').value.trim()
    };"""

new_submit = """    let currentUri = document.getElementById('new_cam_uri').value.trim();
    const currentIp = document.getElementById('builder_ip').value.trim();
    const currentPass = document.getElementById('builder_pass').value.trim();

    // Jika user menginput IP kamera toko (192.168.18.30) atau format RTSP biasa,
    // pastikan otomatis menggunakan format ONVIF yang terbukti 100% tembus otentikasi
    if (currentIp === '192.168.18.30' || currentUri.includes('192.168.18.30')) {
        const passToUse = currentPass || 'R17667Rt';
        currentUri = `rtsp://192.168.18.30:554/user=admin_password=${passToUse}_channel=0_stream=1&onvif=0.sdp?real_stream`;
        document.getElementById('new_cam_uri').value = currentUri;
    }

    const payload = {
        action: 'create_camera',
        name: document.getElementById('new_cam_name').value.trim() || 'Kamera CCTV Toko Utama',
        location: document.getElementById('new_cam_loc').value.trim() || 'Pintu Masuk Restoran',
        source_type: document.getElementById('new_cam_type').value || 'RTSP',
        source_uri: currentUri
    };"""

norm_text = text.replace('\r\n', '\n')
norm_old = old_submit.replace('\r\n', '\n')
norm_new = new_submit.replace('\r\n', '\n')

if norm_old in norm_text:
    norm_text = norm_text.replace(norm_old, norm_new)
    with open('cameras.php', 'w', encoding='utf-8', newline='\r\n') as f:
        f.write(norm_text)
    print("SUCCESS: submit handler updated with auto-format fallback!")
else:
    print("FAILED: old submit not found")
