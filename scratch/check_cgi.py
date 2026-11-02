import urllib.request

url = "http://192.168.18.30/cgi-bin/snapshot.cgi?channel=1"
try:
    with urllib.request.urlopen(url, timeout=3.0) as resp:
        print("Status:", resp.status)
        print("Headers:", resp.headers)
        print("Body:", resp.read())
except Exception as e:
    print("Error:", e)
