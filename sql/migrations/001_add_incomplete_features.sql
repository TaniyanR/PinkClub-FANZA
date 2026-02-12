-- Migration: Add tables and columns for incomplete features
-- Run this migration on existing databases to add new tables and columns

-- Add view_count column to items table if not exists
ALTER TABLE items ADD COLUMN IF NOT EXISTS view_count INT DEFAULT 0;
ALTER TABLE items ADD INDEX IF NOT EXISTS idx_items_view_count (view_count);

-- Create api_logs table
CREATE TABLE IF NOT EXISTS api_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    created_at DATETIME NOT NULL,
    endpoint VARCHAR(255) NOT NULL,
    params_json TEXT DEFAULT NULL,
    status VARCHAR(50) NOT NULL,
    http_code INT DEFAULT NULL,
    item_count INT DEFAULT 0,
    error_message TEXT DEFAULT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    INDEX idx_api_logs_created (created_at),
    INDEX idx_api_logs_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create dmm_api table
CREATE TABLE IF NOT EXISTS dmm_api (
    id INT AUTO_INCREMENT PRIMARY KEY,
    api_id VARCHAR(255) NOT NULL,
    affiliate_id VARCHAR(255) NOT NULL,
    site VARCHAR(100) DEFAULT 'FANZA',
    service VARCHAR(100) DEFAULT 'digital',
    floor VARCHAR(100) DEFAULT 'videoa',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create api_schedules table
CREATE TABLE IF NOT EXISTS api_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    schedule_type VARCHAR(50) NOT NULL,
    last_run_at DATETIME DEFAULT NULL,
    next_run_at DATETIME DEFAULT NULL,
    interval_minutes INT DEFAULT 60,
    lock_until DATETIME DEFAULT NULL,
    is_enabled TINYINT(1) DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_schedule_type (schedule_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create tags table
CREATE TABLE IF NOT EXISTS tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    created_at DATETIME NOT NULL,
    INDEX idx_tags_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create item_tags table
CREATE TABLE IF NOT EXISTS item_tags (
    item_content_id VARCHAR(64) NOT NULL,
    tag_id INT NOT NULL,
    PRIMARY KEY (item_content_id, tag_id),
    INDEX idx_item_tags_tag (tag_id),
    CONSTRAINT fk_item_tags_content
        FOREIGN KEY (item_content_id) REFERENCES items(content_id) ON DELETE CASCADE,
    CONSTRAINT fk_item_tags_tag
        FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create access_events table
CREATE TABLE IF NOT EXISTS access_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(50) NOT NULL,
    event_at DATETIME NOT NULL,
    path VARCHAR(500) DEFAULT NULL,
    referrer VARCHAR(500) DEFAULT NULL,
    link_id INT DEFAULT NULL,
    ip_hash CHAR(64) DEFAULT NULL,
    INDEX idx_access_events_event_at (event_at),
    INDEX idx_access_events_type (event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Initialize api_schedules with default auto_import schedule
INSERT IGNORE INTO api_schedules (schedule_type, interval_minutes, is_enabled, created_at, updated_at)
VALUES ('auto_import', 60, 1, NOW(), NOW());
