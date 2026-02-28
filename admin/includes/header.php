<?php
declare(strict_types=1);

if (!function_exists('e') || !function_exists('asset_url')) {
    require_once __DIR__ . '/../../public/_bootstrap.php';
}

$currentScript = basename((string)($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
$menuGroups = [
    ['label' => 'ダッシュボード', 'file' => 'index.php'],
    ['label' => '一般設定', 'children' => [
        ['label' => 'サイト設定', 'file' => 'site_settings.php'],
        ['label' => 'アカウント設定', 'file' => 'account_settings.php'],
        ['label' => 'デザイン設定', 'file' => 'design_settings.php'],
    ]],
    ['label' => 'リンク設定', 'children' => [
        ['label' => '相互リンク管理', 'file' => 'links_partner.php'],
        ['label' => 'RSS管理', 'file' => 'links_rss.php'],
    ]],
    ['label' => 'アクセス解析', 'file' => 'analytics.php'],
    ['label' => 'アフィリエイト設定', 'children' => [
        ['label' => 'API設定', 'file' => 'affiliate_api.php'],
        ['label' => '広告コード', 'file' => 'affiliate_ads.php'],
    ]],
    ['label' => '固定ページ', 'children' => [
        ['label' => '固定ページ一覧', 'file' => 'pages.php'],
        ['label' => '新規', 'file' => 'pages_new.php'],
    ]],
];

$flash = function_exists('flash_get') ? flash_get() : null;
$titleText = (string)($title ?? APP_NAME);
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title><?= e($titleText) ?></title>
  <link rel="stylesheet" href="<?= e(asset_url('css/style.css')) ?>">
</head>
<body class="admin-page">
<header class="admin-topbar">
  <div class="admin-topbar__brand"><a href="<?= e(admin_url('index.php')) ?>">PinkClub FANZA 管理</a></div>
  <div class="admin-topbar__right"><span id="api-timer-status" style="font-size:12px;color:#c3c4c7;">タイマー待機中</span><a href="<?= e(public_url('')) ?>" target="_blank" rel="noopener noreferrer">フロント表示</a></div>
</header>
<div class="admin-shell">
  <aside class="admin-sidebar" aria-label="管理メニュー">
    <nav>
      <ul class="admin-sidebar__list">
        <?php foreach ($menuGroups as $group): ?>
          <?php if (isset($group['children']) && is_array($group['children'])): ?>
            <li>
              <span class="admin-menu__link"><?= e('> ' . (string)$group['label']) ?></span>
              <ul class="admin-sidebar__list admin-menu__child">
                <?php foreach ($group['children'] as $item): $isActive = ($currentScript === basename($item['file'])); ?>
                  <li><a class="admin-menu__link <?= $isActive ? 'is-active' : '' ?>" href="<?= e(admin_url($item['file'])) ?>">┗ <?= e($item['label']) ?></a></li>
                <?php endforeach; ?>
              </ul>
            </li>
          <?php else: $isActive = ($currentScript === basename((string)$group['file'])); ?>
            <li><a class="admin-menu__link <?= $isActive ? 'is-active' : '' ?>" href="<?= e(admin_url((string)$group['file'])) ?>"><?= e('> ' . (string)$group['label']) ?></a></li>
          <?php endif; ?>
        <?php endforeach; ?>
      </ul>
    </nav>
  </aside>
  <main class="admin-main">
    <?php if (is_array($flash) && isset($flash['message'])): ?>
      <div class="admin-notice <?= ($flash['type'] ?? '') === 'success' ? 'admin-notice--success' : 'admin-notice--error' ?>"><p><?= e((string)$flash['message']) ?></p></div>
    <?php endif; ?>
