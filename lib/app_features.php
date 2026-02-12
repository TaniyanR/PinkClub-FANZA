<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/local_config_writer.php';

function app_session_start(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function app_settings(): array
{
    $local = local_config_load();
    $settings = $local['settings'] ?? [];
    return is_array($settings) ? $settings : [];
}

function app_setting_get(string $key, mixed $default = null): mixed
{
    $settings = app_settings();
    return $settings[$key] ?? $default;
}

function app_setting_set_many(array $values): void
{
    $local = local_config_load();
    $settings = $local['settings'] ?? [];
    if (!is_array($settings)) {
        $settings = [];
    }
    foreach ($values as $k => $v) {
        $settings[(string)$k] = $v;
    }
    $local['settings'] = $settings;
    local_config_write($local);
}

function app_ip_hash_salt(): string
{
    $salt = (string)config_get('security.ip_hash_salt', 'pinkclub-default-salt');
    return $salt !== '' ? $salt : 'pinkclub-default-salt';
}

function track_page_view(?string $itemCid = null): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $path = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (str_starts_with($path, '/admin/')) {
        return;
    }

    app_session_start();
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    $ref = (string)($_SERVER['HTTP_REFERER'] ?? '');

    $ipHash = hash('sha256', $ip . app_ip_hash_salt());
    $uaHash = hash('sha256', $ua);
    $sidHash = hash('sha256', session_id());

    $pdo = db();
    $stmt = $pdo->prepare('INSERT INTO page_views (viewed_at,path,referrer,ip_hash,ua_hash,session_id_hash,item_cid,user_id) VALUES (NOW(),:path,:ref,:ip,:ua,:sid,:cid,:uid)');
    $stmt->execute([
        ':path' => mb_substr($path !== '' ? $path : '/', 0, 255),
        ':ref' => $ref !== '' ? mb_substr($ref, 0, 500) : null,
        ':ip' => $ipHash,
        ':ua' => $uaHash,
        ':sid' => $sidHash,
        ':cid' => $itemCid,
        ':uid' => user_current_id(),
    ]);

    if ((int)date('i') % 10 === 0) {
        refresh_daily_stats(date('Y-m-d'));
    }
}

function refresh_daily_stats(string $ymd): void
{
    $pdo = db();
    $stmtPv = $pdo->prepare('SELECT COUNT(*) FROM page_views WHERE DATE(viewed_at)=:ymd');
    $stmtPv->execute([':ymd' => $ymd]);
    $pvTotal = (int)$stmtPv->fetchColumn();

    $stmtUu = $pdo->prepare('SELECT COUNT(*) FROM (SELECT ip_hash,ua_hash FROM page_views WHERE DATE(viewed_at)=:ymd GROUP BY ip_hash,ua_hash) t');
    $stmtUu->execute([':ymd' => $ymd]);
    $uuTotal = (int)$stmtUu->fetchColumn();

    $stmtTop = $pdo->prepare('SELECT COALESCE(item_cid,path) AS key_name, COUNT(*) AS c FROM page_views WHERE DATE(viewed_at)=:ymd GROUP BY key_name ORDER BY c DESC LIMIT 10');
    $stmtTop->execute([':ymd' => $ymd]);
    $top = $stmtTop->fetchAll(PDO::FETCH_ASSOC);

    $up = $pdo->prepare('INSERT INTO daily_stats (ymd,pv_total,uu_total,pv_top_json,updated_at) VALUES (:ymd,:pv,:uu,:json,NOW()) ON DUPLICATE KEY UPDATE pv_total=VALUES(pv_total),uu_total=VALUES(uu_total),pv_top_json=VALUES(pv_top_json),updated_at=NOW()');
    $up->execute([
        ':ymd' => $ymd,
        ':pv' => $pvTotal,
        ':uu' => $uuTotal,
        ':json' => json_encode($top, JSON_UNESCAPED_UNICODE),
    ]);
}

function sanitize_page_html(string $html): string
{
    $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html) ?? '';
    $html = preg_replace('#<iframe\b[^>]*>.*?</iframe>#is', '', $html) ?? '';
    return trim($html);
}

function user_current_email(): ?string
{
    app_session_start();
    $email = $_SESSION['front_user_email'] ?? null;
    return is_string($email) && $email !== '' ? $email : null;
}

function user_current_id(): ?int
{
    app_session_start();
    $id = $_SESSION['front_user_id'] ?? null;
    return is_int($id) ? $id : null;
}

function user_login(string $email, string $password): bool
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id,email,password_hash FROM users WHERE email=:email AND is_active=1 LIMIT 1');
    $stmt->execute([':email' => $email]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($u) || !password_verify($password, (string)$u['password_hash'])) {
        return false;
    }
    app_session_start();
    session_regenerate_id(true);
    $_SESSION['front_user_id'] = (int)$u['id'];
    $_SESSION['front_user_email'] = (string)$u['email'];
    $pdo->prepare('UPDATE users SET last_login_at=NOW() WHERE id=:id')->execute([':id' => (int)$u['id']]);
    return true;
}

function user_logout(): void
{
    app_session_start();
    unset($_SESSION['front_user_id'], $_SESSION['front_user_email']);
}

function rss_fetch_source(int $sourceId, int $timeoutSec = 4): array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM rss_sources WHERE id=:id AND is_enabled=1');
    $stmt->execute([':id' => $sourceId]);
    $source = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($source)) {
        return ['ok' => false, 'message' => 'source not found'];
    }

    $ctx = stream_context_create(['http' => ['timeout' => $timeoutSec, 'user_agent' => 'PinkClubRSS/1.0']]);
    $xmlRaw = @file_get_contents((string)$source['feed_url'], false, $ctx);
    if (!is_string($xmlRaw) || $xmlRaw === '') {
        return ['ok' => false, 'message' => 'fetch failed'];
    }
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($xmlRaw);
    if ($xml === false) {
        return ['ok' => false, 'message' => 'xml parse failed'];
    }
    $items = $xml->channel->item ?? [];
    $insert = $pdo->prepare('INSERT IGNORE INTO rss_items (source_id,title,url,published_at,summary,guid,created_at) VALUES (:sid,:title,:url,:pub,:summary,:guid,NOW())');
    foreach ($items as $item) {
        $guid = (string)($item->guid ?? $item->link ?? '');
        if ($guid === '') {
            continue;
        }
        $insert->execute([
            ':sid' => $sourceId,
            ':title' => mb_substr((string)($item->title ?? ''), 0, 255),
            ':url' => mb_substr((string)($item->link ?? ''), 0, 500),
            ':pub' => date('Y-m-d H:i:s', strtotime((string)($item->pubDate ?? 'now'))),
            ':summary' => mb_substr(strip_tags((string)($item->description ?? '')), 0, 2000),
            ':guid' => mb_substr($guid, 0, 500),
        ]);
    }
    $pdo->prepare('UPDATE rss_sources SET last_fetched_at=NOW() WHERE id=:id')->execute([':id' => $sourceId]);
    return ['ok' => true, 'message' => 'updated'];
}
