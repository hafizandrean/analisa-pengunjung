import socket
import urllib.request
import urllib.error
import json
import struct
import time

TARGET_IP = "192.168.18.30"

print(f"=== PROBING SCHEMES FOR {TARGET_IP} ===")

# 1. Test Sofia Port 34567
print("\n[1] Testing Sofia / NetSDK Port 34567...")
try:
    s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    s.settimeout(2.0)
    s.connect((TARGET_IP, 34567))
    print("  -> Port 34567 is OPEN!")
    
    # Send a simple SystemInfo inquiry (OPMonitor / SysInfo)
    # Header format: 0xff, 0, 0, 0, SessionID(4B), Seq(4B), Total(1B), Cur(1B), MsgId(2B), DataLen(4B)
    payload = json.dumps({"Name": "SystemInfo", "SessionID": "0x00000000"}).encode('utf-8')
    hdr = struct.pack("<4BII2BHI", 0xff, 0, 0, 0, 0, 0, 0, 0, 1020, len(payload))
    s.sendall(hdr + payload)
    resp = s.recv(1024)
    print(f"  -> Sofia response length: {len(resp)} bytes")
    if len(resp) > 20:
        try:
            body = resp[20:].decode('utf-8', errors='ignore')
            print(f"  -> Sofia response body: {body[:200]}")
        except Exception as e:
            print(f"  -> Decode err: {e}")
    s.close()
except Exception as e:
    print(f"  -> Sofia Port 34567 error: {e}")

# 2. Test HTTP Snapshot / CGI on Port 80
print("\n[2] Testing HTTP Snapshot / CGI endpoints on Port 80...")
passwords = ["", "admin", "admin123", "12345", "123456", "888888"]
endpoints = [
    "/webcapture.jpg?command=snap&channel=1",
    "/cgi-bin/snapshot.cgi?channel=1",
    "/onvif/snapshot",
    "/snapshot.jpg"
]

for ep in endpoints:
    url = f"http://{TARGET_IP}{ep}"
    req = urllib.request.Request(url)
    try:
        with urllib.request.urlopen(req, timeout=2.0) as resp:
            data = resp.read()
            print(f"  -> [SUCCESS!] {url} returned {len(data)} bytes (Content-Type: {resp.headers.get('Content-Type')})")
            break
    except urllib.error.HTTPError as e:
        print(f"  -> {ep}: HTTP {e.code} ({e.reason})")
    except Exception as e:
        print(f"  -> {ep}: {e}")

# 3. Test RTSP Describe on different paths
print("\n[3] Testing RTSP DESCRIBE paths on Port 554...")
rtsp_paths = [
    "/H264/ch1/main/av_stream",
    "/H264/ch1/sub/av_stream",
    "/user=admin&password=&channel=1&stream=1.sdp?",
    "/user=admin&password=admin123&channel=1&stream=1.sdp?",
    "/live/ch01_0",
    "/onvif1"
]

for p in rtsp_paths:
    try:
        s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        s.settimeout(2.0)
        s.connect((TARGET_IP, 554))
        req = f"DESCRIBE rtsp://{TARGET_IP}:554{p} RTSP/1.0\r\nCSeq: 1\r\nUser-Agent: Antigravity\r\n\r\n"
        s.sendall(req.encode())
        res = s.recv(1024).decode('utf-8', errors='ignore')
        first_line = res.split('\r\n')[0] if res else "No response"
        auth_hdr = [l for l in res.split('\r\n') if l.lower().startswith('www-authenticate')]
        print(f"  -> Path '{p}': {first_line}")
        if auth_hdr:
            print(f"     Header: {auth_hdr[0]}")
        s.close()
    except Exception as e:
        print(f"  -> Path '{p}' error: {e}")

print("\n=== PROBING COMPLETE ===")
