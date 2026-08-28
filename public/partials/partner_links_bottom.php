<?php

declare(strict_types=1);

require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/app_features.php';

$partnerLinksBottom = [];
$partnerSortMode = site_setting_get('link.sort_mode', 'registered');
$partnerOrderBy = $partnerSortMode === 'kana' ? 'ps.name ASC, ps.id ASC' : 'ps.id DESC';

try {
    $stmt = db()->query(
        'SELECT ps.id, ps.name, ps.url '
        . 'FROM partner_sites ps '
        . 'WHERE COALESCE(ps.show_link, ps.is_enabled, 1) = 1 '
        . 'ORDER BY ' . $partnerOrderBy
    );
    $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    $seenPartnerUrls = [];

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $name = trim((string)($row['name'] ?? ''));
        $url = trim((string)($row['url'] ?? ''));
        if ($name === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            continue;
        }

        $parts = parse_url($url);
        if (!is_array($parts)) {
            continue;
        }

        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $host = trim((string)($parts['host'] ?? ''));
        $port = isset($parts['port']) ? (int)$parts['port'] : ($scheme === 'https' ? 443 : 80);
        if (!in_array($scheme, ['http', 'https'], true)
            || $host === ''
            || !in_array($port, [80, 443], true)
            || isset($parts['user'])
            || isset($parts['pass'])) {
            continue;
        }

        $dedupeKey = function_exists('rss_normalize_url') ? rss_normalize_url($url) : mb_strtolower(rtrim($url, '/'));
        if ($dedupeKey === '') {
            $dedupeKey = mb_strtolower(rtrim($url, '/'));
        }
        if (isset($seenPartnerUrls[$dedupeKey])) {
            continue;
        }

        $seenPartnerUrls[$dedupeKey] = true;
        $partnerLinksBottom[] = [
            'name' => $name,
            'url' => $url,
        ];
    }
} catch (Throwable) {
    $partnerLinksBottom = [];
}

if ($partnerLinksBottom === []) {
    return;
}
?>
<section class="block partner-links-bottom" aria-labelledby="partner-links-bottom-title">
  <h2 id="partner-links-bottom-title" class="section-title">相互リンク</h2>
  <ul class="rss-list partner-links-bottom__list">
    <?php foreach ($partnerLinksBottom as $partnerLink): ?>
      <li class="rss-list__item partner-links-bottom__item">
        <a href="<?= e((string)$partnerLink['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e((string)$partnerLink['name']) ?></a>
      </li>
    <?php endforeach; ?>
  </ul>
</section>
