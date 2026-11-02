import urllib.request
import json

TARGET_IP = "192.168.18.30"

# Common Xiongmai HTTP RPC payloads
endpoints = [
    "/cgi-bin/snapshot.cgi",
    "/cgi-bin/netsdk.cgi",
    "/cgi-bin/magicBox.cgi"
]

for ep in endpoints:
    url = f"http://{TARGET_IP}{ep}"
    data = json.dumps({"Name": "SystemInfo", "SessionID": "0x00000000"}).encode('utf-8')
    req = urllib.request.Request(url, data=data, headers={"Content-Type": "application/json"})
    try:
        with urllib.request.urlopen(req, timeout=3.0) as resp:
            body = resp.read().decode('utf-8', errors='ignore')
            print(f"[SUCCESS] {ep} -> {resp.status}: {body[:200]}")
    except Exception as e:
        print(f"[ERR] {ep} -> {e}")
