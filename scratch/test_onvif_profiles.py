import urllib.request
import urllib.error

TARGET_IP = "192.168.18.30"
url = f"http://{TARGET_IP}:8899/onvif/media_service"

soap_profiles = """<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:trt="http://www.onvif.org/ver10/media/wsdl">
  <soap:Body>
    <trt:GetProfiles />
  </soap:Body>
</soap:Envelope>"""

req = urllib.request.Request(
    url, 
    data=soap_profiles.encode('utf-8'),
    headers={"Content-Type": "application/soap+xml; charset=utf-8"}
)

try:
    with urllib.request.urlopen(req, timeout=3.0) as resp:
        print("Profiles Status:", resp.status)
        body = resp.read().decode('utf-8', errors='ignore')
        with open("scratch/onvif_profiles.xml", "w", encoding="utf-8") as f:
            f.write(body)
        print("Profiles written to scratch/onvif_profiles.xml")
except urllib.error.HTTPError as e:
    print("Profiles HTTP Error:", e.code, e.read().decode('utf-8', errors='ignore'))
except Exception as e:
    print("Profiles err:", e)
