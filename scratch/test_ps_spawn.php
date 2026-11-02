<?php
$rootDir = realpath(__DIR__ . '/..');
$script = $rootDir . '\\vision\\worker_php.py';
$pyPath = 'C:\\laragon\\bin\\python\\python-3.10\\python.exe';
$cameraId = 'cam-6ae30b77d51af';

$psCmd = "powershell -WindowStyle Hidden -Command \"Start-Process '{$pyPath}' -ArgumentList 'vision/worker_php.py --camera-id={$cameraId}' -WorkingDirectory '{$rootDir}' -WindowStyle Hidden\"";
echo "Executing: $psCmd\n";
exec($psCmd, $out, $ret);
echo "Exec return code: $ret\n";

for ($i = 1; $i <= 6; $i++) {
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
