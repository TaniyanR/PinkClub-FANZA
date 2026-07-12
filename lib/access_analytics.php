<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function analytics_beacon_marker_hash(): string
{
    return hash('sha256', 'pinkclub-browser-beacon');
}

function analytics_visitor_hash(string $ua): string
{
    $cookieName = 'pcf_visitor_id';
    $visitorId = (string)($_COOKIE[$cookieName] ?? '');
    if (!preg_match('/\A[a-f0-9]{32}\z/', $visitorId)) {
        try {
            $visitorId = bin2hex(random_bytes(16));
        } catch (Throwable) {
            $visitorId = hash('sha256', ((string)($_SERVER['REMOTE_ADDR'] ?? '')) . '|' . $ua . '|' . microtime(true));
            $visitorId = substr($visitorId, 0, 32);
        }
        if (!headers_sent()) {
            setcookie($cookieName, $visitorId, [
                'expires' => time() + 60 * 60 * 24 * 400,
                'path' => '/',
                'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        $_COOKIE[$cookieName] = $visitorId;
    }

    return hash('sha256', $visitorId . '|pinkclub');
}

function analytics_track_beacon(): void
{
    if (!analytics_ensure_tables()) {
        return;
    }

    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    $hash = analytics_visitor_hash($ua);
    $rawPath = (string)($_POST['path'] ?? '/');
    $path = (string)parse_url($rawPath, PHP_URL_PATH);
    if ($path === '' || $path[0] !== '/') {
        $path = '/';
    }

    $queryParams = [];
    parse_str((string)(parse_url($rawPath, PHP_URL_QUERY) ?? ''), $queryParams);
    unset($queryParams['rank_period']);
    $requestQuery = http_build_query($queryParams);
    $pageKey = $path . ($requestQuery !== '' ? '?' . $requestQuery : '');
    $pathForStats = mb_substr($pageKey, 0, 255);
    $today = date('Y-m-d');
    $referrer = (string)($_POST['referrer'] ?? '');
    $refererHost = parse_url($referrer, PHP_URL_HOST) ?: '';
    $refCode = trim((string)($_POST['ref'] ?? ''));

    $pdo = db();
    $visitStmt = $pdo->prepare('INSERT IGNORE INTO visit_sessions(stat_date,visitor_hash,first_seen_at) VALUES(:d,:h,NOW())');
    $visitStmt->execute([':d' => $today, ':h' => $hash]);
    $isUniqueVisitor = $visitStmt->rowCount() === 1;

    $pdo->prepare("INSERT INTO site_events(event_type,path,referrer,ua_hash,ip_hash,session_id_hash,created_at) VALUES('pv',:path,:referrer,:ua,:ip,:marker,NOW())")->execute([
        ':path' => $pathForStats,
        ':referrer' => $referrer !== '' ? mb_substr($referrer, 0, 500) : null,
        ':ua' => $ua !== '' ? hash('sha256', $ua) : null,
        ':ip' => $hash,
        ':marker' => analytics_beacon_marker_hash(),
    ]);
    $pdo->prepare('INSERT INTO daily_stats(stat_date,pv,uu,in_count,out_count,updated_at) VALUES(:d,1,:uu,0,0,NOW()) ON DUPLICATE KEY UPDATE pv = pv + 1, uu = uu + VALUES(uu), updated_at = NOW()')->execute([':d' => $today, ':uu' => $isUniqueVisitor ? 1 : 0]);

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
    $pdo->prepare('INSERT INTO daily_stats(stat_date,pv,uu,in_count,out_count,updated_at) VALUES(:d,0,0,0,1,NOW()) ON DUPLICATE KEY UPDATE out_count = out_count + 1, updated_at = NOW()')->execute([':d' => $today]);
}

function analytics_log_taxonomy_page_view(string $path): void
{
    return;
}

function analytics_log_actress_page_view(int $actressId): void
{
    $actressId = max(0, $actressId);
    if ($actressId <= 0) {
        return;
    }

    analytics_log_taxonomy_page_view('/actress.php?id=' . $actressId);
}

function analytics_log_genre_page_view(int $genreId): void
{
    $genreId = max(0, $genreId);
    if ($genreId <= 0) {
        return;
    }

    analytics_log_taxonomy_page_view('/genre.php?id=' . $genreId);
}

function analytics_log_maker_page_view(int $makerId): void
{
    $makerId = max(0, $makerId);
    if ($makerId <= 0) {
        return;
    }

    analytics_log_taxonomy_page_view('/maker.php?id=' . $makerId);
}

function analytics_log_series_page_view(int $seriesId): void
{
    $seriesId = max(0, $seriesId);
    if ($seriesId <= 0) {
        return;
    }

    analytics_log_taxonomy_page_view('/series_detail.php?id=' . $seriesId);
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

    try {
        $pdo = db();
        $pdo->query('SELECT 1 FROM daily_stats LIMIT 1');
        $pdo->query('SELECT 1 FROM visit_sessions LIMIT 1');
        $pdo->query('SELECT 1 FROM in_logs LIMIT 1');
        $pdo->query('SELECT 1 FROM out_logs LIMIT 1');
        $pdo->query('SELECT 1 FROM site_events LIMIT 1');
        $ok = true;
        return true;
    } catch (Throwable $e) {
        $disabled = true;
        error_log('analytics disabled: ' . $e->getMessage());
        return false;
    }
}
