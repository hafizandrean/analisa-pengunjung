-- ==============================================================================
-- DATABASE SCHEMA: Ayam Bang Jungko - Visitor Counting & Crowd Analytics
-- Versi MySQL / MariaDB (Kompatibel dengan XAMPP & Apache)
-- ==============================================================================

CREATE DATABASE IF NOT EXISTS `analisa_pengunjung` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `analisa_pengunjung`;

-- 1. TABEL USERS
CREATE TABLE IF NOT EXISTS `users` (
    `id` VARCHAR(50) NOT NULL PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `role` VARCHAR(50) NOT NULL DEFAULT 'manager',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. TABEL CAMERAS
CREATE TABLE IF NOT EXISTS `cameras` (
    `id` VARCHAR(50) NOT NULL PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `location` VARCHAR(100) NOT NULL,
    `source_type` VARCHAR(50) NOT NULL DEFAULT 'VIDEO_FILE',
    `source_uri` VARCHAR(500) NOT NULL,
    `resolution_width` INT NOT NULL DEFAULT 1280,
    `resolution_height` INT NOT NULL DEFAULT 720,
    `fps` INT NOT NULL DEFAULT 25,
    `line_coords` TEXT NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. TABEL COUNTING_EVENTS
CREATE TABLE IF NOT EXISTS `counting_events` (
    `id` VARCHAR(50) NOT NULL PRIMARY KEY,
    `camera_id` VARCHAR(50) NOT NULL,
    `session_id` VARCHAR(50) NULL,
    `event_timestamp` DATETIME NOT NULL,
    `direction` VARCHAR(10) NOT NULL, -- 'IN' atau 'OUT'
    `count_value` INT NOT NULL DEFAULT 1,
    `track_id` INT NULL,
    `confidence` FLOAT DEFAULT 0.85,
    `source_mode` VARCHAR(50) NOT NULL DEFAULT 'LIVE',
    `metadata_json` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_camera_id` (`camera_id`),
    KEY `idx_timestamp` (`event_timestamp`),
    KEY `idx_direction` (`direction`),
    CONSTRAINT `fk_counting_events_camera` FOREIGN KEY (`camera_id`) REFERENCES `cameras` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. TABEL SYSTEM_LOGS
CREATE TABLE IF NOT EXISTS `system_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `level` VARCHAR(20) NOT NULL DEFAULT 'INFO',
    `message` TEXT NOT NULL,
    `source` VARCHAR(50) NOT NULL DEFAULT 'SYSTEM',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- DATA SEED AWAL
INSERT INTO `users` (`id`, `email`, `password_hash`, `full_name`, `role`)
VALUES ('usr-001', 'admin@ayambangjungko.com', '$2y$10$w8gW1c.r3GvJ7tYgXgVwyeCgQ9KovdEsqa44M6a1H8B5QxQ6K0Cya', 'Manager Ayam Bang Jungko', 'manager')
ON DUPLICATE KEY UPDATE `email`=`email`;

INSERT INTO `cameras` (`id`, `name`, `location`, `source_type`, `source_uri`, `line_coords`, `is_active`)
VALUES 
('cam-1', 'CCTV Pintu Masuk Utama', 'Area Pintu Masuk Restoran', 'VIDEO_FILE', 'samples/video_sample.mp4', '{"x1":120,"y1":380,"x2":1160,"y2":380,"direction_in":"down","offset":35}', 1),
('cam-2', 'CCTV Area Dining Lantai 2', 'Tangga Lantai 2', 'VIDEO_FILE', 'samples/video_sample.mp4', '{"x1":80,"y1":280,"x2":600,"y2":280,"direction_in":"down","offset":25}', 1)
ON DUPLICATE KEY UPDATE `name`=`name`;
