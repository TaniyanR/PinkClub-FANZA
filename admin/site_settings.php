<?php
declare(strict_types=1);

require_once __DIR__ . '/../public/_bootstrap.php';
auth_require_admin();

$title = 'サイト設定';
$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_fail((string)post('_csrf', ''));
    $siteName = trim((string)post('site_name', ''));
    $siteUrl = trim((string)post('site_url', ''));
    $tagline = trim((string)post('site_tagline', ''));
    $keywords = trim((string)post('site_keywords', ''));

    site_setting_set_many([
        'site.title' => $siteName,
        'site.name' => $siteName,
        'site.url' => $siteUrl,
        'site.tagline' => $tagline,
        'site.keywords' => $keywords,
    ]);
    $message = 'サイト設定を保存しました。';
}

require __DIR__ . '/includes/header.php';
?>
<section class="admin-card admin-card--form">
  <h1>サイト設定</h1>
  <?php if ($message !== null): ?><p><?= e($message) ?></p><?php endif; ?>
  <form method="post">
    <?= csrf_input() ?>
    <label>サイト名
      <input type="text" name="site_name" value="<?= e(site_setting_get('site.title', site_setting_get('site.name', APP_NAME))) ?>">
    </label>
    <label>URL
      <input type="url" name="site_url" value="<?= e(site_setting_get('site.url', app_url())) ?>">
    </label>
    <label>キャッチフレーズ
      <input type="text" name="site_tagline" value="<?= e(site_setting_get('site.tagline', '')) ?>">
    </label>
    <label>キーワード
      <input type="text" name="site_keywords" value="<?= e(site_setting_get('site.keywords', '')) ?>" placeholder="例: FANZA,動画,アフィリエイト">
    </label>
    <div class="admin-actions">
      <button type="submit">保存</button>
    </div>
  </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
