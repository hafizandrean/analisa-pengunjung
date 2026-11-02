<?php
$rootDir = realpath(__DIR__ . '/..');
$script = $rootDir . '\\vision\\worker_php.py';
$pyPath = 'C:\\laragon\\bin\\python\\python-3.10\\python.exe';
$cameraId = 'cam-6ae30b77d51af';

$cmd = "start \"AI_Worker\" /MIN \"{$pyPath}\" \"{$script}\" --camera-id={$cameraId}";
echo "Running cmd: $cmd\n";
pclose(popen($cmd, "r"));
echo "Pclose finished immediately!\n";

for ($i = 1; $i <= 8; $i++) {
    sleep(1);
    $fp = @fsockopen("127.0.0.1", 8080, $errno, $errstr, 0.5);
    if ($fp) {
        echo "SUCCESS! Port 8080 is listening after {$i}s!\n";
        fclose($fp);
        break;
    } else {
        echo "Waiting {$i}s...\n";
    }
}
