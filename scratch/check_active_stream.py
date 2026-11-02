import urllib.request
import json

try:
    with urllib.request.urlopen("http://127.0.0.1:8080/status", timeout=2) as resp:
        print("Status output:", resp.read().decode('utf-8'))
except Exception as e:
    print("Status error:", e)

try:
    with urllib.request.urlopen("http://127.0.0.1:8080/snapshot", timeout=2) as resp:
        data = resp.read()
        print("Snapshot size:", len(data), "bytes")
        with open("scratch/live_cctv_frame_received.jpg", "wb") as f:
            f.write(data)
        print("SUCCESS! Saved frame to scratch/live_cctv_frame_received.jpg")
except Exception as e:
    print("Snapshot error:", e)
