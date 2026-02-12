<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

$currentScript = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
$menuItems = [
    ['file' => 'index.php', 'label' => 'ダッシュボード'],
    ['file' => 'settings.php', 'label' => 'API設定'],
    ['file' => 'import_items.php', 'label' => 'インポート'],
    ['file' => 'analytics.php', 'label' => 'PV/UU・アクセス解析'],
    ['file' => 'links.php', 'label' => '相互リンク管理'],
    ['file' => 'rss.php', 'label' => 'RSS取得'],
    ['file' => 'pages.php', 'label' => '固定ページ'],
    ['file' => 'seo.php', 'label' => 'SEO'],
    ['file' => 'design.php', 'label' => 'デザイン設定'],
    ['file' => 'ads.php', 'label' => '広告/コード挿入'],
    ['file' => 'mail.php', 'label' => 'メール'],
    ['file' => 'backup.php', 'label' => 'Backup'],
    ['file' => 'users.php', 'label' => 'アカウント設定'],
    ['file' => 'change_password.php', 'label' => 'パスワード変更'],
];
?>
<aside class="admin-sidebar" aria-label="管理メニュー"><nav><ul><?php foreach ($menuItems as $item) : $isActive = $currentScript === $item['file']; ?><li><a class="<?php echo $isActive ? 'is-active' : ''; ?>" href="<?php echo e(admin_url($item['file'])); ?>"><?php echo e($item['label']); ?></a></li><?php endforeach; ?></ul></nav></aside>
