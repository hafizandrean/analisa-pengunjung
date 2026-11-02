with open('cameras.php', 'r', encoding='utf-8') as f:
    text = f.read()

old_block = """    } else if (key === 'XIONGMAI') {
        typeInp.value = 'RTSP';
        portInp.value = '554';
        help.innerText = 'XiongMai / XMeye / Topsvision / SPC: Standar DVR/NVR menggunakan format jalur user-password sdp.';"""

new_block = """    } else if (key === 'XIONGMAI') {
        typeInp.value = 'RTSP';
        portInp.value = '554';
        ipInp.value = '192.168.18.30';
        passInp.value = 'R17667Rt';
        userInp.value = 'admin';
        chInp.value = '0';
        help.innerText = 'CCTV Toko (XiongMai / XMeye / Topsvision): Menggunakan jalur ONVIF Sub-stream terotentikasi real-time.';"""

norm_text = text.replace('\r\n', '\n')
norm_old = old_block.replace('\r\n', '\n')
norm_new = new_block.replace('\r\n', '\n')

if norm_old in norm_text:
    norm_text = norm_text.replace(norm_old, norm_new)
    with open('cameras.php', 'w', encoding='utf-8', newline='\r\n') as f:
        f.write(norm_text)
    print("SUCCESS: applyCctvPreset updated for XIONGMAI!")
else:
    print("FAILED: old block not found in cameras.php")
