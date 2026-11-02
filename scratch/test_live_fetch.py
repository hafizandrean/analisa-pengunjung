import urllib.request
import cv2

print("=== 1. Testing HTTP Snapshot with ONVIF credentials ===")
snapshot_url = "http://192.168.18.30/webcapture.jpg?command=snap&channel=0&user=admin&password=R17667Rt"
try:
    with urllib.request.urlopen(snapshot_url, timeout=4.0) as resp:
        data = resp.read()
        print(f"Snapshot SUCCESS! Length: {len(data)} bytes. Header: {resp.headers.get('Content-Type')}")
        with open("scratch/cctv_live_test.jpg", "wb") as f:
            f.write(data)
        print("Live snapshot saved to scratch/cctv_live_test.jpg!")
except Exception as e:
    print(f"Snapshot Error: {e}")

print("\n=== 2. Testing RTSP Sub-stream with OpenCV ===")
rtsp_url = "rtsp://192.168.18.30:554/user=admin_password=R17667Rt_channel=0_stream=1&onvif=0.sdp?real_stream"
cap = cv2.VideoCapture(rtsp_url)
print(f"cap.isOpened(): {cap.isOpened()}")
if cap.isOpened():
    ret, frame = cap.read()
    print(f"Frame read ret: {ret}")
    if ret:
        print(f"Frame shape: {frame.shape}")
        cv2.imwrite("scratch/cctv_rtsp_test.jpg", frame)
        print("RTSP frame saved to scratch/cctv_rtsp_test.jpg!")
    cap.release()
else:
    print("Failed to open RTSP with OpenCV.")
