import urllib.request
import json
import time

# 1. Start Camera
print("=== Starting camera via stream_control.php ===")
data = json.dumps({
    "action": "start",
    "camera_id": "cam-6ae30b77d51af"
}).encode('utf-8')

req = urllib.request.Request("http://127.0.0.1:8000/api/stream_control.php", data=data, headers={"Content-Type": "application/json"})
with urllib.request.urlopen(req) as resp:
    print("Start resp:", resp.read().decode('utf-8'))

time.sleep(2)

# 2. Check Stream Status
print("\n=== Checking stream status ===")
req_status = urllib.request.Request("http://127.0.0.1:8000/api/stream_control.php?action=status")
with urllib.request.urlopen(req_status) as resp:
    print("Status resp:", resp.read().decode('utf-8'))

# 3. Test reading a frame from http://127.0.0.1:8080/snapshot
print("\n=== Testing snapshot from live stream server ===")
try:
    with urllib.request.urlopen("http://127.0.0.1:8080/snapshot", timeout=3) as resp:
        body = resp.read()
        print(f"Snapshot received: {len(body)} bytes! Content-Type: {resp.headers.get('Content-Type')}")
except Exception as e:
    print("Snapshot error:", e)
