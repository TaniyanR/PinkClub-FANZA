<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/config.php';

header('Content-Type: application/xml; charset=UTF-8');
$base = rtrim((string)config_get('site.base_url', ''), '/');
if ($base === '') {
    $scheme = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    $base = $scheme . '://' . $host . '/public';
}
$urls = [$base . '/index.php', $base . '/posts.php'];
$pdo = db();
foreach ($pdo->query('SELECT content_id FROM items ORDER BY updated_at DESC LIMIT 5000')->fetchAll(PDO::FETCH_COLUMN) as $cid) { $urls[] = $base . '/item.php?cid=' . rawurlencode((string)$cid); }
foreach ($pdo->query('SELECT slug FROM fixed_pages WHERE is_published=1')->fetchAll(PDO::FETCH_COLUMN) as $slug) { $urls[] = $base . '/page.php?slug=' . rawurlencode((string)$slug); }
echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">";
foreach ($urls as $u) { echo '<url><loc>' . htmlspecialchars($u, ENT_QUOTES, 'UTF-8') . '</loc></url>'; }
echo '</urlset>';
