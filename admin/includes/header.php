<?php declare(strict_types=1); ?>
<?php
$currentScript = basename((string)($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
$menuItems = [
    ['file' => 'index.php', 'label' => 'Dashboard'],
    ['file' => 'settings.php', 'label' => 'Settings'],
    ['file' => 'sync_floors.php', 'label' => 'Floors'],
    ['file' => 'sync_master.php', 'label' => 'Masters'],
    ['file' => 'sync_items.php', 'label' => 'Items'],
    ['file' => 'sync_logs.php', 'label' => 'Logs'],
    ['file' => 'logout.php', 'label' => 'Logout'],
];
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
          <?php $isActive = $currentScript === $item['file']; ?>
          <li>
            <a class="admin-menu__link <?= $isActive ? 'is-active' : '' ?>" href="<?= e(admin_url($item['file'])) ?>"><?= e($item['label']) ?></a>
          </li>
        <?php endforeach; ?>
      </ul>
    </nav>
  </aside>
  <main class="admin-main">
<?php if ($flash = flash_get()): ?>
<div class="admin-notice <?= ($flash['type'] ?? '') === 'success' ? 'admin-notice--success' : 'admin-notice--error' ?>"><p><?= e($flash['message']) ?></p></div>
<?php endif; ?>
