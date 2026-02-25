<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title><?= e($title ?? APP_NAME) ?></title>
  <link rel="stylesheet" href="<?= e(asset_url('css/style.css')) ?>">
</head>
<body>
<header><h1>Admin - PinkClub FANZA</h1><nav>
  <a href="<?= e(admin_url('index.php')) ?>">Dashboard</a>
  <a href="<?= e(admin_url('settings.php')) ?>">Settings</a>
  <a href="<?= e(admin_url('sync_floors.php')) ?>">Floors</a>
  <a href="<?= e(admin_url('sync_master.php')) ?>">Masters</a>
  <a href="<?= e(admin_url('sync_items.php')) ?>">Items</a>
  <a href="<?= e(admin_url('logout.php')) ?>">Logout</a>
</nav></header>
<main>
<?php if ($flash = flash_get()): ?>
<div class="flash <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>
