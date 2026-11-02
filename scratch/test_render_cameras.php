<?php
session_start();
$_SESSION['user'] = ['id' => 1, 'username' => 'admin'];

ob_start();
include 'cameras.php';
$html = ob_get_clean();

if (preg_match_all('/(Warning|Deprecated|Fatal error):[^\n<]+/i', $html, $m)) {
    echo "WARNINGS FOUND:\n";
    foreach ($m[0] as $w) {
        echo "- " . trim(strip_tags($w)) . "\n";
    }
} else {
    echo "ALL CLEAN! 100% Zero PHP warnings or errors!\n";
}
