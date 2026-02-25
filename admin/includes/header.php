<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title><?= e($title ?? APP_NAME) ?></title>
  <link rel="stylesheet" href="<?= e(app_url('assets/css/style.css')) ?>">
</head>
<body>
<header><h1>Admin - PinkClub FANZA</h1><nav>
  <a href="<?= e(app_url('admin/index.php')) ?>">Dashboard</a>
  <a href="<?= e(app_url('admin/settings.php')) ?>">Settings</a>
  <a href="<?= e(app_url('admin/sync_floors.php')) ?>">Floors</a>
  <a href="<?= e(app_url('admin/sync_master.php')) ?>">Masters</a>
  <a href="<?= e(app_url('admin/sync_items.php')) ?>">Items</a>
  <a href="<?= e(app_url('admin/logout.php')) ?>">Logout</a>
</nav></header>
<main>
<?php if ($flash = flash_get()): ?>
<div class="flash <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>
