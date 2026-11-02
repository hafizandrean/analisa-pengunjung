import urllib.request
import urllib.error

TARGET_IP = "192.168.18.30"
url = f"http://{TARGET_IP}:8899/onvif/device_service"

soap_inquiry = """<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:tds="http://www.onvif.org/ver10/device/wsdl">
  <soap:Body>
    <tds:GetDeviceInformation />
  </soap:Body>
</soap:Envelope>"""

req = urllib.request.Request(
    url, 
    data=soap_inquiry.encode('utf-8'),
    headers={"Content-Type": "application/soap+xml; charset=utf-8"}
)

try:
    with urllib.request.urlopen(req, timeout=3.0) as resp:
        print("ONVIF 8899 Status:", resp.status)
        print("Body:", resp.read().decode('utf-8', errors='ignore'))
except urllib.error.HTTPError as e:
    print("ONVIF HTTP Error:", e.code, e.read().decode('utf-8', errors='ignore'))
except Exception as e:
    print("ONVIF Error:", e)
