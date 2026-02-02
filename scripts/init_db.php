<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/db.php';

function split_sql_statements(string $sql): array
{
    $statements = [];
    $buffer = '';
    $inString = false;
    $stringChar = '';

    $length = strlen($sql);
    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        if ($inString) {
            if ($char === $stringChar) {
                $escaped = $i > 0 && $sql[$i - 1] === '\\';
                if (!$escaped) {
                    $inString = false;
                }
            }
            $buffer .= $char;
            continue;
        }

        if ($char === '\'' || $char === '"') {
            $inString = true;
            $stringChar = $char;
            $buffer .= $char;
            continue;
        }

        if ($char === ';') {
            $statement = trim($buffer);
            if ($statement !== '') {
                $statements[] = $statement;
            }
            $buffer = '';
            continue;
        }

        $buffer .= $char;
    }

    $statement = trim($buffer);
    if ($statement !== '') {
        $statements[] = $statement;
    }

    return $statements;
}

function schema_statements_from_file(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $sql = file_get_contents($path);
    if ($sql === false) {
        return [];
    }

    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
    $lines = preg_split('/\R/', (string)$sql);
    $filtered = [];
    foreach ($lines as $line) {
        $trim = ltrim($line);
        if ($trim === '' || str_starts_with($trim, '--') || str_starts_with($trim, '#')) {
            continue;
        }
        $filtered[] = $line;
    }

    $cleanSql = implode("\n", $filtered);
    return split_sql_statements($cleanSql);
}

function fallback_schema_statements(): array
{
    return [
        <<<SQL
CREATE TABLE IF NOT EXISTS items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_id VARCHAR(64) NOT NULL UNIQUE,
    product_id VARCHAR(64) DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    url TEXT,
    affiliate_url TEXT,
    image_list TEXT,
    image_small TEXT,
    image_large TEXT,
    date_published DATETIME DEFAULT NULL,
    service_code VARCHAR(64) DEFAULT NULL,
    floor_code VARCHAR(64) DEFAULT NULL,
    category_name VARCHAR(128) DEFAULT NULL,
    price_min INT DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_items_date_published (date_published),
    INDEX idx_items_price_min (price_min)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        ,
        <<<SQL
CREATE TABLE IF NOT EXISTS actresses (
    id BIGINT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    ruby VARCHAR(255) DEFAULT NULL,
    bust INT DEFAULT NULL,
    cup VARCHAR(32) DEFAULT NULL,
    waist INT DEFAULT NULL,
    hip INT DEFAULT NULL,
    height INT DEFAULT NULL,
    birthday DATE DEFAULT NULL,
    blood_type VARCHAR(32) DEFAULT NULL,
    hobby TEXT DEFAULT NULL,
    prefectures VARCHAR(255) DEFAULT NULL,
    image_small TEXT,
    image_large TEXT,
    listurl_digital TEXT,
    listurl_monthly TEXT,
    listurl_mono TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_actresses_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        ,
        <<<SQL
CREATE TABLE IF NOT EXISTS item_actresses (
    content_id VARCHAR(64) NOT NULL,
    actress_id BIGINT NOT NULL,
    PRIMARY KEY (content_id, actress_id),
    INDEX idx_item_actresses_actress (actress_id),
    CONSTRAINT fk_item_actresses_content
        FOREIGN KEY (content_id) REFERENCES items (content_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_item_actresses_actress
        FOREIGN KEY (actress_id) REFERENCES actresses (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        ,
        <<<SQL
CREATE TABLE IF NOT EXISTS item_labels (
    content_id VARCHAR(64) NOT NULL,
    label_id INT DEFAULT NULL,
    label_name VARCHAR(255) NOT NULL,
    label_ruby VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (content_id, label_name),
    INDEX idx_item_labels_label_id (label_id),
    INDEX idx_item_labels_label_name (label_name),
    CONSTRAINT fk_item_labels_content
        FOREIGN KEY (content_id) REFERENCES items (content_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        ,
        <<<SQL
CREATE TABLE IF NOT EXISTS partners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    site_url TEXT,
    rss_url TEXT,
    token VARCHAR(128) NOT NULL,
    supports_images_override TINYINT NULL,
    supports_images_detected TINYINT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    last_checked_at DATETIME DEFAULT NULL,
    UNIQUE KEY uq_partners_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        ,
        <<<SQL
CREATE TABLE IF NOT EXISTS in_access_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    partner_id INT NOT NULL,
    ip_hash CHAR(64) NOT NULL,
    ua_hash CHAR(64) NOT NULL,
    ref TEXT,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_in_access_partner_created (partner_id, created_at),
    CONSTRAINT fk_in_access_partner
        FOREIGN KEY (partner_id) REFERENCES partners (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        ,
    ];
}

function init_db(): array
{
    $schemaPath = __DIR__ . '/../sql/schema.sql';
    $statements = schema_statements_from_file($schemaPath);
    $source = 'schema.sql';

    if ($statements === []) {
        $statements = fallback_schema_statements();
        $source = 'fallback';
    }

    $pdo = db();

    try {
        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }
    } catch (Throwable $e) {
        log_message('DB init failed: ' . $e->getMessage());
        throw $e;
    }

    return [
        'source' => $source,
        'count' => count($statements),
    ];
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        $result = init_db();
        fwrite(STDOUT, sprintf("DB初期化が完了しました。（%s使用: %dステートメント）\n", $result['source'], $result['count']));
    } catch (Throwable $e) {
        fwrite(STDERR, "DB初期化に失敗しました: " . $e->getMessage() . "\n");
        throw $e;
    }
}
