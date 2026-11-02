<?php
$rootDir = realpath(__DIR__ . '/..');
$script = $rootDir . '\\vision\\worker_php.py';
$pyPath = 'C:\\laragon\\bin\\python\\python-3.10\\python.exe';
$cameraId = 'cam-6ae30b77d51af';

$cmd = "start /B \"\" \"{$pyPath}\" \"{$script}\" --camera-id={$cameraId}";
echo "Running cmd: $cmd\n";
pclose(popen($cmd, "r"));
echo "Pclose done. Sleeping 2s...\n";
sleep(2);

$fp = @fsockopen("127.0.0.1", 8080, $errno, $errstr, 2);
if ($fp) {
    echo "SUCCESS! Port 8080 is OPEN!\n";
    fclose($fp);
} else {
    echo "FAILED! Port 8080 still closed: $errstr\n";
}
