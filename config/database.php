<?php
/**
 * Konfigurasi Database PDO - PHP Native
 * Sistem Penghitungan Pengunjung Ayam Bang Jungko
 * 
 * Mendukung MySQL (XAMPP) dan SQLite secara otomatis sebagai fallback.
 * Struktur database bersih tanpa data dummy / palsu.
 */

define('DB_DRIVER', getenv('DB_DRIVER') ?: 'sqlite'); // 'mysql' atau 'sqlite'
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'analisa_pengunjung');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

// Path SQLite lokal
define('SQLITE_FILE', __DIR__ . '/../database/analisa_pengunjung.sqlite');

function getDBConnection(): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $driver = DB_DRIVER;

    // Jika driver diset mysql, coba konek ke MySQL
    if ($driver === 'mysql') {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            return $pdo;
        } catch (PDOException $e) {
            // Jika koneksi MySQL gagal (misal XAMPP MySQL belum start), fallback ke SQLite
            error_log("Koneksi MySQL gagal (" . $e->getMessage() . "), beralih otomatis ke SQLite.");
            $driver = 'sqlite';
        }
    }

    // SQLite Setup
    $dbDir = dirname(SQLITE_FILE);
    if (!is_dir($dbDir)) {
        mkdir($dbDir, 0777, true);
    }

    $isNewDb = !file_exists(SQLITE_FILE);
    $pdo = new PDO("sqlite:" . SQLITE_FILE, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Aktifkan foreign keys di SQLite
    $pdo->exec("PRAGMA foreign_keys = ON;");

    if ($isNewDb || filesize(SQLITE_FILE) === 0) {
        initializeDatabase($pdo);
    }

    return $pdo;
}

/**
 * Inisialisasi struktur tabel dan data master awal
 */
function initializeDatabase(PDO $pdo): void {
    // 1. Tabel users
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id TEXT PRIMARY KEY,
        email TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        full_name TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'manager',
        is_active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL DEFAULT (datetime('now', '+7 hours'))
    )");

    // 2. Tabel cameras
    $pdo->exec("CREATE TABLE IF NOT EXISTS cameras (
        id TEXT PRIMARY KEY,
        name TEXT NOT NULL,
        location TEXT NOT NULL,
        source_type TEXT NOT NULL DEFAULT 'VIDEO_FILE',
        source_uri TEXT NOT NULL,
        resolution_width INTEGER NOT NULL DEFAULT 1280,
        resolution_height INTEGER NOT NULL DEFAULT 720,
        fps INTEGER NOT NULL DEFAULT 25,
        line_coords TEXT NOT NULL,
        is_active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL DEFAULT (datetime('now', '+7 hours'))
    )");

    // 3. Tabel counting_events (Murni untuk data riil dari kamera CCTV / AI, 0 dummy data)
    $pdo->exec("CREATE TABLE IF NOT EXISTS counting_events (
        id TEXT PRIMARY KEY,
        camera_id TEXT NOT NULL,
        session_id TEXT,
        event_timestamp TEXT NOT NULL,
        direction TEXT NOT NULL, -- 'IN' atau 'OUT'
        count_value INTEGER NOT NULL DEFAULT 1,
        track_id INTEGER,
        confidence REAL DEFAULT 0.85,
        source_mode TEXT NOT NULL DEFAULT 'LIVE',
        metadata_json TEXT,
        created_at TEXT NOT NULL DEFAULT (datetime('now', '+7 hours')),
        FOREIGN KEY (camera_id) REFERENCES cameras(id) ON DELETE CASCADE
    )");

    // 4. Tabel system_logs
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        level TEXT NOT NULL DEFAULT 'INFO',
        message TEXT NOT NULL,
        source TEXT NOT NULL DEFAULT 'SYSTEM',
        created_at TEXT NOT NULL DEFAULT (datetime('now', '+7 hours'))
    )");

    // Data Master Awal (Hanya akun manajer dan konfigurasi kamera aktif)
    seedMasterData($pdo);
}

function seedMasterData(PDO $pdo): void {
    // 1. Akun manajer operasional default
    $defaultPassHash = password_hash('admin123', PASSWORD_BCRYPT);
    $userStmt = $pdo->prepare("INSERT OR IGNORE INTO users (id, email, password_hash, full_name, role) VALUES (?, ?, ?, ?, ?)");
    $userStmt->execute(['usr-001', 'admin@ayambangjungko.com', $defaultPassHash, 'Manager Ayam Bang Jungko', 'manager']);

    // 2. Konfigurasi kamera CCTV operasional (siap digunakan untuk tracking)
    $lineCoords1 = json_encode([
        'x1' => 120, 'y1' => 380, 'x2' => 1160, 'y2' => 380,
        'direction_in' => 'down', 'offset' => 35
    ]);
    $camStmt = $pdo->prepare("INSERT OR IGNORE INTO cameras (id, name, location, source_type, source_uri, line_coords, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $camStmt->execute(['cam-1', 'CCTV Pintu Masuk Utama', 'Area Pintu Masuk Restoran', 'VIDEO_FILE', 'samples/video_sample.mp4', $lineCoords1, 1]);
    
    $lineCoords2 = json_encode([
        'x1' => 80, 'y1' => 280, 'x2' => 600, 'y2' => 280,
        'direction_in' => 'down', 'offset' => 25
    ]);
    $camStmt->execute(['cam-2', 'CCTV Area Dining Lantai 2', 'Tangga Lantai 2', 'VIDEO_FILE', 'samples/video_sample.mp4', $lineCoords2, 1]);

    // 3. Log sistem awal
    $pdo->exec("INSERT INTO system_logs (level, message, source) VALUES ('INFO', 'Basis data Ayam Bang Jungko berhasil diinisialisasi bersih tanpa data palsu.', 'SYSTEM')");
}
