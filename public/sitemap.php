<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/url.php';

header('Content-Type: application/xml; charset=UTF-8');

$base = rtrim(base_url(), '/');

/**
 * @param mixed $value
 */
function sitemap_lastmod_format($value): ?string
{
    if (!is_string($value) || trim($value) === '') {
        return null;
    }

    try {
        $date = new DateTimeImmutable($value, new DateTimeZone('Asia/Tokyo'));
        return $date->format(DateTimeInterface::ATOM);
    } catch (Throwable $e) {
        return null;
    }
}

$urls = [
    ['loc' => $base . '/', 'lastmod' => null],
    ['loc' => $base . '/posts.php', 'lastmod' => null],
];

// 固定ページ（fixed_pages）は仕様により sitemap に含めない。

$stmt = db()->query('SELECT content_id, updated_at, created_at FROM items ORDER BY id DESC');
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
    $cid = (string)($row['content_id'] ?? '');
    if ($cid === '') {
        continue;
    }

    $lastmodRaw = $row['updated_at'] ?? ($row['created_at'] ?? null);
    $urls[] = [
        'loc' => $base . '/item.php?cid=' . rawurlencode($cid),
        'lastmod' => sitemap_lastmod_format($lastmodRaw),
    ];
}

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
foreach ($urls as $entry) {
    echo '<url>';
    echo '<loc>' . htmlspecialchars((string)$entry['loc'], ENT_QUOTES, 'UTF-8') . '</loc>';
    if (is_string($entry['lastmod']) && $entry['lastmod'] !== '') {
        echo '<lastmod>' . htmlspecialchars($entry['lastmod'], ENT_QUOTES, 'UTF-8') . '</lastmod>';
    }
    echo '</url>';
}
echo '</urlset>';
