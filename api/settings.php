<?php
/**
 * API Simpan Pengaturan Sistem & Kustomisasi
 * api/settings.php
 */

header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/settings.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Metode request tidak diizinkan.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$allowedKeys = [
    'timezone', 'time_format', 'language',
    'restaurant_name', 'restaurant_tagline', 'branch_name',
    'capacity_dine_in', 'daily_target',
    'opening_time', 'closing_time', 'operating_hours_filter', 'auto_operating_hours',
    'store_status', 'detection_sound', 'restaurant_logo',
    'logo_scale', 'logo_shape', 'logo_bg'
];

$tzLabels = [
    'Asia/Jakarta'  => 'WIB (Asia/Jakarta)',
    'Asia/Makassar' => 'WITA (Asia/Makassar)',
    'Asia/Jayapura' => 'WIT (Asia/Jayapura)',
    'UTC'           => 'UTC (Standard)',
];

$saved = 0;

// Penanganan Unggah Berkas Logo Restoran
if (!empty($_FILES['restaurant_logo_file']['name'])) {
    $file = $_FILES['restaurant_logo_file'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $allowedExts = ['png', 'jpg', 'jpeg', 'svg', 'webp'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowedExts) && $file['size'] <= 3 * 1024 * 1024) {
            $uploadDir = __DIR__ . '/../assets/uploads/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }
            $filename = 'logo_' . time() . '.' . $ext;
            $destPath = $uploadDir . $filename;
            if (move_uploaded_file($file['tmp_name'], $destPath)) {
                $oldLogo = getSetting('restaurant_logo');
                if ($oldLogo && file_exists(__DIR__ . '/../' . $oldLogo)) {
                    @unlink(__DIR__ . '/../' . $oldLogo);
                }
                saveSetting('restaurant_logo', 'assets/uploads/' . $filename);
                $saved++;
            }
        }
    }
} elseif (isset($input['remove_logo']) && $input['remove_logo'] === '1') {
    $oldLogo = getSetting('restaurant_logo');
    if ($oldLogo && file_exists(__DIR__ . '/../' . $oldLogo)) {
        @unlink(__DIR__ . '/../' . $oldLogo);
    }
    saveSetting('restaurant_logo', '');
    $saved++;
}

foreach ($allowedKeys as $key) {
    if (isset($input[$key])) {
        $val = trim((string)$input[$key]);
        saveSetting($key, $val);
        $saved++;

        // Jika mengubah timezone, otomatis simpan labelnya
        if ($key === 'timezone' && isset($tzLabels[$val])) {
            saveSetting('timezone_label', $tzLabels[$val]);
        }
    }
}

// Log aktivitas sistem
try {
    $pdo = getDBConnection();
    $logStmt = $pdo->prepare("INSERT INTO system_logs (level, category, message) VALUES ('INFO', 'SETTINGS', 'Pengaturan sistem diperbarui oleh manajer')");
    $logStmt->execute();
} catch (Exception $e) {}

echo json_encode([
    'success' => true,
    'message' => 'Pengaturan sistem berhasil disimpan!',
    'saved_count' => $saved,
    'current_time' => date('d M Y, H:i:s'),
    'timezone' => date_default_timezone_get()
]);
