<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

header('Content-Type: application/xml; charset=UTF-8');

function sitemap_e(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
}

function sitemap_url(string $loc, string $changefreq, string $priority, string $lastmod = ''): void
{
    echo "  <url>\n";
    echo '    <loc>' . sitemap_e($loc) . "</loc>\n";
    if ($lastmod !== '') {
        echo '    <lastmod>' . sitemap_e(substr($lastmod, 0, 10)) . "</lastmod>\n";
    }
    echo '    <changefreq>' . sitemap_e($changefreq) . "</changefreq>\n";
    echo '    <priority>' . sitemap_e($priority) . "</priority>\n";
    echo "  </url>\n";
}

function sitemap_table_count(string $table): int
{
    try {
        if (!db_table_exists($table)) {
            return 0;
        }
        return (int)db()->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    } catch (Throwable) {
        return 0;
    }
}

function sitemap_emit_table(string $table, string $path, string $changefreq, string $priority, int $start, int &$remaining): int
{
    $count = sitemap_table_count($table);
    if ($remaining <= 0) {
        return $count;
    }
    if ($start >= $count) {
        return $count;
    }

    $limit = min($remaining, $count - $start);
    try {
        $stmt = db()->prepare('SELECT id, updated_at FROM ' . $table . ' ORDER BY id ASC LIMIT :limit OFFSET :offset');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $start, PDO::PARAM_INT);
        $stmt->execute();
        foreach ($stmt->fetchAll() ?: [] as $row) {
            sitemap_url(public_url($path) . '?id=' . rawurlencode((string)(int)($row['id'] ?? 0)), $changefreq, $priority, (string)($row['updated_at'] ?? ''));
            $remaining--;
        }
    } catch (Throwable) {
    }

    return $count;
}

$perSitemap = 10000;
$staticUrls = [
    [public_url('index.php'), 'daily', '1.0'],
    [public_url('items.php'), 'daily', '0.9'],
    [public_url('search.php'), 'daily', '0.9'],
];
$tables = [
    ['items', 'item.php', 'weekly', '0.8'],
    ['genres', 'genre.php', 'daily', '0.9'],
    ['series_master', 'series_detail.php', 'daily', '0.9'],
    ['actresses', 'actress.php', 'daily', '0.9'],
    ['makers', 'maker.php', 'daily', '0.9'],
];
$totalUrls = count($staticUrls);
foreach ($tables as $table) {
    $totalUrls += sitemap_table_count((string)$table[0]);
}

if ((isset($_GET['index']) && (string)$_GET['index'] === '1') || ($totalUrls > $perSitemap && !isset($_GET['part']))) {
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    echo "<sitemapindex xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
    $pages = max(1, (int)ceil($totalUrls / $perSitemap));
    for ($i = 1; $i <= $pages; $i++) {
        echo "  <sitemap>\n";
        echo '    <loc>' . sitemap_e(public_url('sitemap.php') . '?part=' . $i) . "</loc>\n";
        echo "  </sitemap>\n";
    }
    echo "</sitemapindex>\n";
    return;
}

$part = max(1, (int)($_GET['part'] ?? 1));
$start = ($part - 1) * $perSitemap;
$remaining = $perSitemap;


echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

foreach ($staticUrls as $index => $url) {
    if ($index < $start) {
        continue;
    }
    if ($remaining <= 0) {
        break;
    }
    sitemap_url((string)$url[0], (string)$url[1], (string)$url[2]);
    $remaining--;
}
$start = max(0, $start - count($staticUrls));

foreach ($tables as $table) {
    $count = sitemap_emit_table((string)$table[0], (string)$table[1], (string)$table[2], (string)$table[3], $start, $remaining);
    $start = max(0, $start - $count);
}

echo "</urlset>\n";
