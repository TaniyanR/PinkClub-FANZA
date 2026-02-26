<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/admin_page_discovery.php';

$currentScript = basename((string)($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
$pages = admin_discover_pages();
$menuItems = [];

foreach ($pages as $page) {
    if ($page['scope'] !== 'legacy') {
        continue;
    }

    $menuItems[] = [
        'file' => $page['path'],
        'label' => $page['label'],
        'badge' => $page['broken'] ? '未整備' : '',
    ];
}

?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title><?= e($title ?? APP_NAME) ?></title>
  <link rel="stylesheet" href="<?= e(asset_url('css/style.css')) ?>">
</head>
<body class="admin-page">
<header class="admin-topbar">
  <div class="admin-topbar__brand"><a href="<?= e(admin_url('index.php')) ?>">PinkClub FANZA 管理</a></div>
  <div class="admin-topbar__right">
    <a href="<?= e(public_url('index.php')) ?>" target="_blank" rel="noopener noreferrer">フロント表示</a>
  </div>
</header>

<div class="admin-shell">
  <aside class="admin-sidebar" aria-label="管理メニュー">
    <nav>
      <p class="admin-sidebar__heading">メニュー</p>
      <ul class="admin-sidebar__list">
        <?php foreach ($menuItems as $item): ?>
          <?php $itemScript = basename((string)parse_url((string)$item['file'], PHP_URL_PATH)); ?>
          <?php $isActive = $currentScript === $itemScript; ?>
          <li>
            <a class="admin-menu__link <?= $isActive ? 'is-active' : '' ?>"
               href="<?= e(url((string)$item['file'])) ?>"><?= e((string)$item['label']) ?>
               <?php if ((string)($item['badge'] ?? '') !== ''): ?><span class="admin-menu__badge"><?= e((string)$item['badge']) ?></span><?php endif; ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </nav>
  </aside>

  <main class="admin-main">
    <?php if ($flash = flash_get()): ?>
      <div class="admin-notice <?= ($flash['type'] ?? '') === 'success' ? 'admin-notice--success' : 'admin-notice--error' ?>">
        <p><?= e($flash['message']) ?></p>
      </div>
    <?php endif; ?>
