import subprocess
import time

print("Starting worker and capturing stdout/stderr...")
proc = subprocess.Popen(
    ["C:\\laragon\\bin\\python\\python-3.10\\python.exe", "vision/worker_php.py", "--camera-id=cam-6ae30b77d51af"],
    stdout=subprocess.PIPE,
    stderr=subprocess.PIPE,
    text=True
)

time.sleep(6)
poll = proc.poll()
print("Process poll after 6s:", poll)

if poll is not None:
    stdout, stderr = proc.communicate()
    print("STDOUT:\n", stdout)
    print("STDERR:\n", stderr)
else:
    print("Process is still running strong! Terminating test...")
    proc.terminate()
