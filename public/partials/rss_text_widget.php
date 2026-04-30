<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../../lib/app_features.php';
require_once __DIR__ . '/../../lib/db.php';

try {
    db()->exec('CREATE TABLE IF NOT EXISTS rss_sources (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,name VARCHAR(255) NOT NULL,feed_url VARCHAR(1000) NOT NULL,is_enabled TINYINT(1) NOT NULL DEFAULT 1,last_fetched_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uk_rss_source_feed (feed_url)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    db()->exec('CREATE TABLE IF NOT EXISTS rss_items (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,source_id BIGINT UNSIGNED NOT NULL,title VARCHAR(255) NOT NULL,url VARCHAR(500) NOT NULL,published_at DATETIME NULL,summary TEXT NULL,guid VARCHAR(500) NOT NULL,image_url TEXT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uk_rss_guid (source_id,guid),INDEX idx_rss_pub (published_at),CONSTRAINT fk_rss_items_source FOREIGN KEY (source_id) REFERENCES rss_sources(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $partnerFeeds = db()->query('SELECT ps.name, pr.feed_url FROM partner_rss pr INNER JOIN partner_sites ps ON ps.id = pr.partner_site_id WHERE COALESCE(pr.show_rss, pr.is_enabled, 1)=1')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $find = db()->prepare('SELECT id FROM rss_sources WHERE feed_url = :feed LIMIT 1');
    $insert = db()->prepare('INSERT INTO rss_sources(name,feed_url,is_enabled,created_at,updated_at) VALUES(:name,:feed,1,NOW(),NOW())');
    $update = db()->prepare('UPDATE rss_sources SET name=:name,is_enabled=1,updated_at=NOW() WHERE id=:id');

    foreach ($partnerFeeds as $feed) {
        $feedUrl = trim((string)($feed['feed_url'] ?? ''));
        if ($feedUrl === '') {
            continue;
        }

        $name = trim((string)($feed['name'] ?? 'RSS'));
        $find->execute([':feed' => $feedUrl]);
        $id = (int)($find->fetchColumn() ?: 0);

        if ($id > 0) {
            $update->execute([':name' => $name, ':id' => $id]);
        } else {
            $insert->execute([':name' => $name, ':feed' => $feedUrl]);
        }
    }

    $sources = db()->query('SELECT id,last_fetched_at FROM rss_sources WHERE is_enabled=1 ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($sources as $source) {
        $lastFetched = strtotime((string)($source['last_fetched_at'] ?? '')) ?: 0;
        if ($lastFetched < time() - 900) {
            rss_fetch_source((int)$source['id'], 2);
        }
    }
} catch (Throwable $e) {
}

rss_widget_bootstrap();

$items = [];
try {
    $items = rss_pick_display_items(50, false, 14);
} catch (Throwable $e) {
    $items = [];
}

if (is_array($items) && count($items) > 1) {
    shuffle($items);
}

$rssUsedKeys = [];
if (isset($GLOBALS['pcf_rss_widget_used_keys']) && is_array($GLOBALS['pcf_rss_widget_used_keys'])) {
    $rssUsedKeys = $GLOBALS['pcf_rss_widget_used_keys'];
}
$maxItems = 0;
if (isset($GLOBALS['pcf_rss_widget_max_items'])) {
    $maxItems = (int)$GLOBALS['pcf_rss_widget_max_items'];
}

$filteredItems = [];
foreach ($items as $rssItem) {
    if (!is_array($rssItem)) {
        continue;
    }
    $key = rss_normalize_display_key($rssItem);
    if ($key === '') {
        $key = mb_strtolower(trim((string)($rssItem['title'] ?? '')));
    }
    if ($key !== '' && isset($rssUsedKeys[$key])) {
        continue;
    }
    if ($key !== '') {
        $rssUsedKeys[$key] = true;
    }
    $filteredItems[] = $rssItem;
    if ($maxItems > 0 && count($filteredItems) >= $maxItems) {
        break;
    }
}

$items = $filteredItems;
$GLOBALS['pcf_rss_widget_used_keys'] = $rssUsedKeys;
?>
<div class="rss-widget rss-widget--text block">
    <div class="rss-box">
        <?php if ($items !== []) : ?>
            <ul class="rss-list">
                <?php foreach ($items as $rssItem) : ?>
                    <li class="rss-list__item">
                        <a href="<?php echo e((string)($rssItem['link'] ?? '')); ?>" target="_blank" rel="noopener noreferrer"><?php echo e((string)($rssItem['title'] ?? '')); ?></a>
                        <small><?php echo e((string)($rssItem['source_name'] ?? '')); ?> / <?php echo e((string)($rssItem['published_at'] ?? '')); ?></small>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else : ?>
            <p class="sidebar-empty">テキストRSSの記事がありません。</p>
        <?php endif; ?>
    </div>
</div>
