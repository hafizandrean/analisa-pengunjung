import urllib.request
import urllib.error

TARGET_IP = "192.168.18.30"
url = f"http://{TARGET_IP}:8899/onvif/media_service"

def get_stream_uri(token):
    soap = f"""<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:trt="http://www.onvif.org/ver10/media/wsdl" xmlns:tt="http://www.onvif.org/ver10/schema">
  <soap:Body>
    <trt:GetStreamUri>
      <trt:StreamSetup>
        <tt:Stream>RTP-Unicast</tt:Stream>
        <tt:Transport>
          <tt:Protocol>RTSP</tt:Protocol>
        </tt:Transport>
      </trt:StreamSetup>
      <trt:ProfileToken>{token}</trt:ProfileToken>
    </trt:GetStreamUri>
  </soap:Body>
</soap:Envelope>"""
    req = urllib.request.Request(url, data=soap.encode('utf-8'), headers={"Content-Type": "application/soap+xml; charset=utf-8"})
    try:
        with urllib.request.urlopen(req, timeout=3.0) as resp:
            return resp.read().decode('utf-8', errors='ignore')
    except Exception as e:
        return f"Error: {e}"

def get_snapshot_uri(token):
    soap = f"""<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:trt="http://www.onvif.org/ver10/media/wsdl">
  <soap:Body>
    <trt:GetSnapshotUri>
      <trt:ProfileToken>{token}</trt:ProfileToken>
    </trt:GetSnapshotUri>
  </soap:Body>
</soap:Envelope>"""
    req = urllib.request.Request(url, data=soap.encode('utf-8'), headers={"Content-Type": "application/soap+xml; charset=utf-8"})
    try:
        with urllib.request.urlopen(req, timeout=3.0) as resp:
            return resp.read().decode('utf-8', errors='ignore')
    except Exception as e:
        return f"Error: {e}"

print("=== Stream URI for mainStream (000) ===")
print(get_stream_uri("000"))

print("\n=== Stream URI for subStream (001) ===")
print(get_stream_uri("001"))

print("\n=== Snapshot URI for snapStream (002) ===")
print(get_snapshot_uri("002"))
