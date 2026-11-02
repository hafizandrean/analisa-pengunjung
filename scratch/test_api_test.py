import urllib.request
import json

data = json.dumps({
    "action": "test",
    "source_type": "RTSP",
    "source_uri": "rtsp://192.168.18.30:554/user=admin_password=R17667Rt_channel=0_stream=1&onvif=0.sdp?real_stream"
}).encode('utf-8')

req = urllib.request.Request("http://127.0.0.1:8000/api/cctv_scanner.php", data=data, headers={"Content-Type": "application/json"})
with urllib.request.urlopen(req) as resp:
    print(resp.read().decode('utf-8'))
