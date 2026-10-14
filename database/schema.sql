-- ==============================================================================
-- DATABASE SCHEMA: Ayam Bang Jungko - Visitor Counting & Crowd Analytics
-- PostgreSQL 14+ compatible
-- Timezone: All timestamps stored in UTC / TIMESTAMPTZ, displayed in Asia/Jakarta (WIB)
-- ==============================================================================

-- Enable UUID extension
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- ------------------------------------------------------------------------------
-- 1. USERS TABLE
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    email VARCHAR(255) UNIQUE NOT NULL,
    hashed_password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'manager', -- 'admin', 'manager', 'operator'
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);

-- ------------------------------------------------------------------------------
-- 2. CAMERAS TABLE
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cameras (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    name VARCHAR(100) NOT NULL,
    location VARCHAR(100) NOT NULL,
    source_type VARCHAR(50) NOT NULL DEFAULT 'VIDEO_FILE', -- 'RTSP', 'VIDEO_FILE', 'WEBCAM'
    source_uri VARCHAR(500) NOT NULL,                      -- Masked on frontend, raw here
    resolution_width INTEGER NOT NULL DEFAULT 1280,
    resolution_height INTEGER NOT NULL DEFAULT 720,
    fps INTEGER NOT NULL DEFAULT 25,
    line_coords JSONB NOT NULL DEFAULT '{"x1": 100, "y1": 360, "x2": 1180, "y2": 360, "direction_in": "down", "offset": 30}'::jsonb,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_cameras_is_active ON cameras(is_active);

-- ------------------------------------------------------------------------------
-- 3. COUNTING SESSIONS TABLE
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS counting_sessions (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    camera_id UUID NOT NULL REFERENCES cameras(id) ON DELETE CASCADE,
    mode VARCHAR(50) NOT NULL DEFAULT 'DEMO', -- 'DEMO', 'VIDEO_FILE', 'LIVE_CCTV'
    status VARCHAR(50) NOT NULL DEFAULT 'STOPPED', -- 'RUNNING', 'STOPPED', 'COMPLETED', 'FAILED'
    started_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMPTZ,
    total_in INTEGER NOT NULL DEFAULT 0,
    total_out INTEGER NOT NULL DEFAULT 0,
    notes TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_counting_sessions_camera ON counting_sessions(camera_id);
CREATE INDEX IF NOT EXISTS idx_counting_sessions_status ON counting_sessions(status);
CREATE INDEX IF NOT EXISTS idx_counting_sessions_started_at ON counting_sessions(started_at);

-- ------------------------------------------------------------------------------
-- 4. COUNTING EVENTS TABLE
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS counting_events (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    camera_id UUID NOT NULL REFERENCES cameras(id) ON DELETE CASCADE,
    session_id UUID REFERENCES counting_sessions(id) ON DELETE SET NULL,
    event_timestamp TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    direction VARCHAR(10) NOT NULL CHECK (direction IN ('IN', 'OUT')),
    count_value INTEGER NOT NULL DEFAULT 1 CHECK (count_value > 0),
    track_id INTEGER,
    confidence REAL,
    source_mode VARCHAR(20) NOT NULL DEFAULT 'DEMO', -- 'DEMO', 'VIDEO', 'LIVE'
    metadata_json JSONB,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Compound index for fast timestamp queries by camera & direction
CREATE INDEX IF NOT EXISTS idx_counting_events_ts ON counting_events(event_timestamp DESC);
CREATE INDEX IF NOT EXISTS idx_counting_events_cam_ts ON counting_events(camera_id, event_timestamp DESC);
CREATE INDEX IF NOT EXISTS idx_counting_events_dir ON counting_events(direction);
CREATE INDEX IF NOT EXISTS idx_counting_events_mode ON counting_events(source_mode);

-- ------------------------------------------------------------------------------
-- 5. SYSTEM LOGS TABLE
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS system_logs (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    module VARCHAR(50) NOT NULL, -- 'AUTH', 'API', 'VISION_WORKER', 'DATABASE'
    level VARCHAR(20) NOT NULL DEFAULT 'INFO', -- 'INFO', 'WARNING', 'ERROR'
    message TEXT NOT NULL,
    details JSONB,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_system_logs_level ON system_logs(level);
CREATE INDEX IF NOT EXISTS idx_system_logs_created_at ON system_logs(created_at DESC);
