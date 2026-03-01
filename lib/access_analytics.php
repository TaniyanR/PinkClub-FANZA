<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function analytics_track_request(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $path = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    $isAdminRequest = preg_match('#/(?:admin)(?:/|$)#', $path) === 1;
    $isLoginRequest = str_ends_with($path, '/public/login0718.php') || str_ends_with($path, '/public/login.php');

    if ($isAdminRequest || $isLoginRequest) {
        return;
    }

    if (!analytics_ensure_tables()) {
        return;
    }

    $today = date('Y-m-d');
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $hash = hash('sha256', $ip . '|' . $ua . '|pinkclub');
    $refererHost = parse_url((string)($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_HOST) ?: '';
    $refCode = trim((string)($_GET['ref'] ?? ''));

    $pdo = db();
    $pdo->prepare('INSERT INTO daily_stats(stat_date,pv,uu,in_count,out_count,updated_at) VALUES(:d,0,0,0,0,NOW()) ON DUPLICATE KEY UPDATE updated_at=NOW()')->execute([':d' => $today]);
    $pdo->prepare('UPDATE daily_stats SET pv = pv + 1, updated_at = NOW() WHERE stat_date = :d')->execute([':d' => $today]);

    $seenStmt = $pdo->prepare('SELECT id FROM visit_sessions WHERE stat_date=:d AND visitor_hash=:h LIMIT 1');
    $seenStmt->execute([':d' => $today, ':h' => $hash]);
    if (!$seenStmt->fetchColumn()) {
        $pdo->prepare('INSERT INTO visit_sessions(stat_date,visitor_hash,first_seen_at) VALUES(:d,:h,NOW())')->execute([':d' => $today, ':h' => $hash]);
        $pdo->prepare('UPDATE daily_stats SET uu = uu + 1, updated_at = NOW() WHERE stat_date=:d')->execute([':d' => $today]);
    }

    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($refCode !== '' || ($refererHost !== '' && !str_contains(strtolower($refererHost), $host))) {
        $pdo->prepare('INSERT INTO in_logs(created_at,ref_code,referer_host,path) VALUES(NOW(),:ref,:host,:path)')->execute([
            ':ref' => $refCode,
            ':host' => mb_substr((string)$refererHost, 0, 255),
            ':path' => mb_substr($path, 0, 255),
        ]);
        $pdo->prepare('UPDATE daily_stats SET in_count = in_count + 1, updated_at = NOW() WHERE stat_date=:d')->execute([':d' => $today]);
    }
}

function analytics_log_out(string $targetUrl, string $refCode, string $path): void
{
    if (!analytics_ensure_tables()) {
        return;
    }
    $today = date('Y-m-d');
    $pdo = db();
    $pdo->prepare('INSERT INTO out_logs(created_at,ref_code,target_url,path) VALUES(NOW(),:ref,:url,:path)')->execute([
        ':ref' => mb_substr($refCode, 0, 64),
        ':url' => mb_substr($targetUrl, 0, 1000),
        ':path' => mb_substr($path, 0, 255),
    ]);
    $pdo->prepare('INSERT INTO daily_stats(stat_date,pv,uu,in_count,out_count,updated_at) VALUES(:d,0,0,0,0,NOW()) ON DUPLICATE KEY UPDATE updated_at=NOW()')->execute([':d' => $today]);
    $pdo->prepare('UPDATE daily_stats SET out_count = out_count + 1, updated_at = NOW() WHERE stat_date=:d')->execute([':d' => $today]);
}

function analytics_ensure_tables(): bool
{
    static $ok = false;
    static $disabled = false;
    if ($ok) {
        return true;
    }
    if ($disabled) {
        return false;
    }

    $ok = true;
    try {
        $pdo = db();
        $pdo->exec('CREATE TABLE IF NOT EXISTS daily_stats (stat_date DATE PRIMARY KEY,pv INT NOT NULL DEFAULT 0,uu INT NOT NULL DEFAULT 0,in_count INT NOT NULL DEFAULT 0,out_count INT NOT NULL DEFAULT 0,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $pdo->exec('CREATE TABLE IF NOT EXISTS visit_sessions (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,stat_date DATE NOT NULL,visitor_hash CHAR(64) NOT NULL,first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uk_visit_daily (stat_date, visitor_hash)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $pdo->exec('CREATE TABLE IF NOT EXISTS in_logs (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,ref_code VARCHAR(64) NULL,referer_host VARCHAR(255) NULL,path VARCHAR(255) NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $pdo->exec('CREATE TABLE IF NOT EXISTS out_logs (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,ref_code VARCHAR(64) NULL,target_url TEXT NOT NULL,path VARCHAR(255) NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $pdo->exec('CREATE TABLE IF NOT EXISTS partner_sites (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,name VARCHAR(255) NOT NULL,ref_code VARCHAR(64) NOT NULL UNIQUE,url TEXT NOT NULL,is_enabled TINYINT(1) NOT NULL DEFAULT 1,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $pdo->exec('CREATE TABLE IF NOT EXISTS partner_rss (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,partner_site_id BIGINT UNSIGNED NOT NULL,feed_url TEXT NOT NULL,is_enabled TINYINT(1) NOT NULL DEFAULT 1,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX idx_partner_rss_partner (partner_site_id),CONSTRAINT fk_partner_rss_site FOREIGN KEY (partner_site_id) REFERENCES partner_sites(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $pdo->exec('CREATE TABLE IF NOT EXISTS mutual_links (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,site_name VARCHAR(255) NOT NULL,site_url TEXT NOT NULL,link_url TEXT NULL,rss_url TEXT NULL,contact_email VARCHAR(255) NULL,apply_type VARCHAR(32) NOT NULL DEFAULT "link_only",rule_text TEXT NULL,rule_json LONGTEXT NULL,status VARCHAR(20) NOT NULL DEFAULT "pending",is_enabled TINYINT(1) NOT NULL DEFAULT 1,enabled TINYINT(1) NOT NULL DEFAULT 1,display_order INT NOT NULL DEFAULT 100,display_position VARCHAR(32) NOT NULL DEFAULT "sidebar",rss_enabled TINYINT(1) NOT NULL DEFAULT 0,banner_image_url TEXT NULL,image_url TEXT NULL,approved_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX idx_mutual_links_status (status),INDEX idx_mutual_links_status_enabled_order (status,is_enabled,display_order,id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        return true;
    } catch (Throwable $e) {
        $ok = false;
        $disabled = true;
        error_log('analytics disabled: ' . $e->getMessage());
        return false;
    }
}
