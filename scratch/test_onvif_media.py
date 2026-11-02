import urllib.request
import urllib.error

TARGET_IP = "192.168.18.30"
url = f"http://{TARGET_IP}:8899/onvif/device_service"

# Query Capabilities
soap_cap = """<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:tds="http://www.onvif.org/ver10/device/wsdl">
  <soap:Body>
    <tds:GetCapabilities>
      <tds:Category>All</tds:Category>
    </tds:GetCapabilities>
  </soap:Body>
</soap:Envelope>"""

req = urllib.request.Request(
    url, 
    data=soap_cap.encode('utf-8'),
    headers={"Content-Type": "application/soap+xml; charset=utf-8"}
)

try:
    with urllib.request.urlopen(req, timeout=3.0) as resp:
        print("Capabilities Status:", resp.status)
        body = resp.read().decode('utf-8', errors='ignore')
        print("Body preview:", body[:1000])
        with open("scratch/onvif_capabilities.xml", "w", encoding="utf-8") as f:
            f.write(body)
except Exception as e:
    print("Cap err:", e)
