import socket

TARGET_IP = "192.168.18.30"
ports_to_check = [
    21, 22, 23, 80, 81, 88, 443, 554, 555, 1935, 2000, 3702, 5000, 
    8000, 8080, 8554, 8899, 9000, 9527, 9530, 10554, 33051, 34567, 37777
]

open_ports = []
for p in ports_to_check:
    s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    s.settimeout(0.5)
    res = s.connect_ex((TARGET_IP, p))
    if res == 0:
        open_ports.append(p)
    s.close()

print(f"Open ports on {TARGET_IP}: {open_ports}")
