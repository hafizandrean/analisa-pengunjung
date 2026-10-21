<?php
/**
 * Pengaturan Sistem & Kustomisasi
 * config/settings.php
 * Mengelola preferensi zona waktu, bahasa, jam operasional restoran, dan identitas bisnis.
 */

require_once __DIR__ . '/database.php';

// Pengaturan default jika belum ada di database
$DEFAULT_SETTINGS = [
    'timezone'            => 'Asia/Jakarta', // WIB
    'timezone_label'      => 'WIB (Asia/Jakarta)',
    'time_format'         => '24',           // '24' atau '12'
    'language'            => 'id',           // 'id' (Bahasa Indonesia) atau 'en' (English)
    'restaurant_name'     => 'Ayam Bang Jungko',
    'restaurant_tagline'  => 'Spesialis Ayam Goreng & Bakar',
    'restaurant_logo'     => '',             // Path file logo kustom (misal: assets/uploads/logo.png)
    'logo_scale'          => '100',          // Persentase skala zoom logo (70 - 140%)
    'logo_shape'          => 'rounded-xl',   // 'rounded-xl' (kotak rounded) atau 'rounded-full' (lingkaran bulat)
    'logo_bg'             => 'white',        // 'white' (putih bersih), 'dark' (gelap), atau 'transparent'
    'branch_name'         => 'Cabang Utama',
    'capacity_dine_in'    => '50',           // Kapasitas kursi makan di tempat
    'daily_target'        => '300',          // Target pengunjung harian
    'opening_time'            => '10:00',        // Jam buka operasional
    'closing_time'            => '22:00',        // Jam tutup operasional
    'auto_operating_hours'    => '1',            // 1 = Otomatis buka & tutup sesuai jam, 0 = Manual oleh kasir/manajer
    'store_status'            => 'closed',       // 'open' (Buka) atau 'closed' (Tutup)
    'operating_hours_filter'  => '1',            // Kompatibilitas alias untuk auto_operating_hours
    'detection_sound'         => '0',            // Bunyi lonceng saat ada tamu masuk (0=mati, 1=nyala)
];

function initSettingsTable(PDO $pdo): void {
    global $DEFAULT_SETTINGS;
    
    // Buat tabel jika belum ada
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS app_settings (
            setting_key TEXT PRIMARY KEY,
            setting_value TEXT NOT NULL,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Isi nilai default yang belum ada
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO app_settings (setting_key, setting_value) VALUES (?, ?)");
    foreach ($DEFAULT_SETTINGS as $key => $val) {
        $stmt->execute([$key, (string)$val]);
    }
}

function getAllSettings(bool $refresh = false): array {
    static $cached = null;
    if ($cached !== null && !$refresh) {
        return $cached;
    }

    global $DEFAULT_SETTINGS;
    $settings = $DEFAULT_SETTINGS;

    try {
        $pdo = getDBConnection();
        initSettingsTable($pdo);
        $rows = $pdo->query("SELECT setting_key, setting_value FROM app_settings")->fetchAll();
        foreach ($rows as $r) {
            $settings[$r['setting_key']] = $r['setting_value'];
        }
    } catch (Exception $e) {
        // Fallback ke default jika database belum siap
    }

    $cached = $settings;
    return $settings;
}

function getSetting(string $key, $default = null) {
    $settings = getAllSettings();
    return $settings[$key] ?? $default;
}

function saveSetting(string $key, string $value): bool {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            INSERT INTO app_settings (setting_key, setting_value, updated_at) 
            VALUES (:k, :v, CURRENT_TIMESTAMP)
            ON CONFLICT(setting_key) DO UPDATE SET setting_value = :v2, updated_at = CURRENT_TIMESTAMP
        ");
        $res = $stmt->execute([':k' => $key, ':v' => $value, ':v2' => $value]);
        if ($res) {
            getAllSettings(true);
        }
        return $res;
    } catch (Exception $e) {
        return false;
    }
}

// Inisialisasi Zona Waktu Global PHP
$currentTz = getSetting('timezone', 'Asia/Jakarta');
if ($currentTz) {
    @date_default_timezone_set($currentTz);
} else {
    date_default_timezone_set('Asia/Jakarta');
}
