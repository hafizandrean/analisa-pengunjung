<?php
$rootDir = realpath(__DIR__ . '/..');
$script = $rootDir . '\\vision\\worker_php.py';
$pyPath = 'C:\\laragon\\bin\\python\\python-3.10\\python.exe';
$cameraId = 'cam-6ae30b77d51af';

$cmd = "\"{$pyPath}\" \"{$script}\" --camera-id={$cameraId}";
echo "Testing WScript.Shell:\n";
try {
    $wsh = new COM("WScript.Shell");
    $wsh->Run($cmd, 0, false);
    echo "Spawned via COM!\n";
} catch (Exception $e) {
    echo "COM error: " . $e->getMessage() . "\n";
    echo "Trying proc_open...\n";
    $p = proc_open("start /B \"\" " . $cmd, [
        0 => ["pipe", "r"],
        1 => ["pipe", "w"],
        2 => ["pipe", "w"]
    ], $pipes);
    echo "proc_open returned: " . (is_resource($p) ? "RESOURCE" : "FALSE") . "\n";
}
