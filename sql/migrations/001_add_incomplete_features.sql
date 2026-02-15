-- Migration: Add tables and columns for incomplete features
-- Run this migration on existing databases to add new tables and columns

SET @items_exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'items'
);

SET @sql := IF(
    @items_exists = 0,
    'SELECT 1',
    IF(
        EXISTS(
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'items' AND COLUMN_NAME = 'view_count'
        ),
        'SELECT 1',
        'ALTER TABLE items ADD COLUMN view_count INT DEFAULT 0'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    @items_exists = 0,
    'SELECT 1',
    IF(
        EXISTS(
            SELECT 1 FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'items' AND INDEX_NAME = 'idx_items_view_count'
        ),
        'SELECT 1',
        'CREATE INDEX idx_items_view_count ON items(view_count)'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

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
    interval_minutes INT NOT NULL DEFAULT 60,
    last_run DATETIME DEFAULT NULL,
    lock_until DATETIME DEFAULT NULL,
    fail_count INT NOT NULL DEFAULT 0,
    last_error TEXT DEFAULT NULL,
    last_success_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL
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

-- Initialize api_schedules with default interval
INSERT INTO api_schedules (interval_minutes, created_at, updated_at)
SELECT 60, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM api_schedules);
