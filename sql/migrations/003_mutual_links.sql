SET @db_name := DATABASE();

SET @exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'mutual_links'
      AND COLUMN_NAME = 'status'
);
SET @sql := IF(@exists = 0,
    'ALTER TABLE mutual_links ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT ''pending''',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'mutual_links'
      AND COLUMN_NAME = 'is_enabled'
);
SET @sql := IF(@exists = 0,
    'ALTER TABLE mutual_links ADD COLUMN is_enabled TINYINT(1) NOT NULL DEFAULT 1',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'mutual_links'
      AND COLUMN_NAME = 'display_order'
);
SET @sql := IF(@exists = 0,
    'ALTER TABLE mutual_links ADD COLUMN display_order INT NOT NULL DEFAULT 100',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'mutual_links'
      AND COLUMN_NAME = 'approved_at'
);
SET @sql := IF(@exists = 0,
    'ALTER TABLE mutual_links ADD COLUMN approved_at DATETIME NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'mutual_links'
      AND COLUMN_NAME = 'created_at'
);
SET @sql := IF(@exists = 0,
    'ALTER TABLE mutual_links ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'mutual_links'
      AND COLUMN_NAME = 'updated_at'
);
SET @sql := IF(@exists = 0,
    'ALTER TABLE mutual_links ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'mutual_links'
      AND INDEX_NAME = 'idx_mutual_links_status'
);
SET @sql := IF(@exists = 0,
    'CREATE INDEX idx_mutual_links_status ON mutual_links(status)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'mutual_links'
      AND INDEX_NAME = 'idx_mutual_links_status_enabled_order'
);
SET @sql := IF(@exists = 0,
    'CREATE INDEX idx_mutual_links_status_enabled_order ON mutual_links(status, is_enabled, display_order, id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
