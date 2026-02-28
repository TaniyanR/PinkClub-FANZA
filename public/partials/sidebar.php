<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/repository.php';
require_once __DIR__ . '/../../lib/admin_auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/url.php';
require_once __DIR__ . '/../../lib/app_features.php';

$sidebarSafeFetch = static function (callable $fetcher, string $context): array {
    try {
        return $fetcher();
    } catch (Throwable $e) {
        if (function_exists('log_message')) {
            log_message('[sidebar] ' . $context . ' fetch failed: ' . $e->getMessage());
        }
        return [];
    }
};

$sidebarGenres = $sidebarSafeFetch(static fn(): array => fetch_genres(8, 0), 'genres');
$sidebarMakers = $sidebarSafeFetch(static fn(): array => fetch_makers(8, 0), 'makers');
$sidebarSeries = $sidebarSafeFetch(static fn(): array => fetch_series(8, 0), 'series');

$scriptName = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
$isHome = isset($is_home) ? (bool)$is_home : ($scriptName === 'index.php');

$mutualLinks = [];
$mutualLinksDebug = null;
if ($isHome) {
    try {
        $hasIsEnabled = false;
        $hasDisplayOrder = false;

        $colStmt = db()->prepare(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME IN ('is_enabled', 'display_order')"
        );
        $colStmt->execute([':table' => 'mutual_links']);
        foreach ($colStmt->fetchAll(PDO::FETCH_COLUMN) as $column) {
            if ($column === 'is_enabled') {
                $hasIsEnabled = true;
            }
            if ($column === 'display_order') {
                $hasDisplayOrder = true;
            }
        }

        $where = ["status='approved'"];
        if ($hasIsEnabled) {
            $where[] = 'is_enabled=1';
        }
        $orderBy = $hasDisplayOrder ? 'display_order ASC, id ASC' : 'id ASC';

        $sql = 'SELECT id, site_name, site_url, link_url FROM mutual_links WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $orderBy . ' LIMIT 50';
        $stmt = db()->query($sql);
        $mutualLinks = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Throwable $e) {
        $mutualLinks = [];
    }
}

if ($isHome) {
    try {
        $mutualLinksDebugStmt = db()->query("SELECT DATABASE() AS db_name, COUNT(*) AS approved_enabled_count FROM mutual_links WHERE status='approved' AND is_enabled=1");
        $mutualLinksDebug = $mutualLinksDebugStmt ? $mutualLinksDebugStmt->fetch(PDO::FETCH_ASSOC) : null;
    } catch (Throwable $e) {
        $mutualLinksDebug = ['db_name' => '(unknown)', 'approved_enabled_count' => 'error: ' . $e->getMessage()];
    }
}

?>
<aside class="sidebar" style="--links-box-max-height:420px;">
    <div class="sidebar-block">
        <h3>クイックメニュー</h3>
        <ul>
            <li><a href="/posts.php">作品一覧</a></li>
            <li><a href="/rss.php">RSS</a></li>
            <li><a href="/contact.php">お問い合わせ</a></li>
            <li><a href="/links.php">リンク集</a></li>
        </ul>
    </div>

    <?php if ((string)($_GET['debug'] ?? '') === '1' && $isHome) : ?>
        <div class="sidebar-block">
            <h3>Debug</h3>
            <ul>
                <li>DB: <?php echo e((string)($mutualLinksDebug['db_name'] ?? '(unknown)')); ?></li>
                <li>mutual_links(approved &amp; enabled): <?php echo e((string)($mutualLinksDebug['approved_enabled_count'] ?? '0')); ?></li>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($mutualLinks !== []) : ?>
        <div class="sidebar-block">
            <h3>相互リンク</h3>
            <ul style="max-height:var(--links-box-max-height);overflow:auto;">
                <?php foreach ($mutualLinks as $link) : ?>
                    <li>
                        <a href="<?php echo e(base_url() . '/out.php?id=' . (string)$link['id']); ?>" target="_blank" rel="noopener noreferrer"><?php echo e((string)$link['site_name']); ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="sidebar-block"><h3>画像RSS</h3><?php include __DIR__ . '/rss_image_widget.php'; ?></div>

    <?php if (should_show_ad('sidebar_bottom', ad_current_page_type(), 'pc')) : ?>
        <div class="sidebar-block"><h3>広告枠</h3><?php render_ad('sidebar_bottom', ad_current_page_type(), 'pc'); ?></div>
    <?php endif; ?>
</aside>
