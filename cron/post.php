<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/cron_guard.php';
require_once __DIR__ . '/../lib/site_article_feeds.php';

cron_require_authorized_web();
$exit = cron_with_file_lock('post', static function (): int {
    foreach (site_article_feed_configs() as $feedKey => $config) {
        site_article_feed_maybe_publish((string)$feedKey, $config);
    }
    echo '[' . date('Y-m-d H:i:s') . "] post cron executed\n";
    return 0;
});
if (PHP_SAPI === 'cli') { exit($exit); }
