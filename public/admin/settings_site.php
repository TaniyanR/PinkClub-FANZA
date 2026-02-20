<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../../lib/site_settings.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!admin_post_csrf_valid()) {
        header('Location: ' . admin_url('settings_site.php?err=csrf_invalid'));
        exit;
    }

    $siteName = trim((string)($_POST['site_name'] ?? ''));
    $siteUrl = trim((string)($_POST['site_url'] ?? ''));
    $adminEmail = trim((string)($_POST['admin_email'] ?? ''));

    if ($siteName === '' || $siteUrl === '' || $adminEmail === '') {
        header('Location: ' . admin_url('settings_site.php?err=required'));
        exit;
    }

    if (filter_var($siteUrl, FILTER_VALIDATE_URL) === false) {
        header('Location: ' . admin_url('settings_site.php?err=invalid_url'));
        exit;
    }

    if (filter_var($adminEmail, FILTER_VALIDATE_EMAIL) === false) {
        header('Location: ' . admin_url('settings_site.php?err=invalid_email'));
        exit;
    }

    try {
        site_setting_set_many([
            'site.name' => $siteName,
            'site.url' => $siteUrl,
            'site.admin_email' => $adminEmail,
        ]);

        header('Location: ' . admin_url('settings_site.php?saved=1'));
        exit;
    } catch (Throwable) {
        header('Location: ' . admin_url('settings_site.php?err=save_failed'));
        exit;
    }
}

$siteName = site_setting_get('site.name', (string)config_get('site.title', 'PinkClub-FANZA'));
$siteUrl = site_setting_get('site.url', detect_base_url());
$adminEmail = site_setting_get('site.admin_email', '');
$errorCode = (string)($_GET['err'] ?? '');
$messages = [
    'csrf_invalid' => 'CSRFトークンが無効です。再度お試しください。',
    'required' => 'すべての項目を入力してください。',
    'invalid_url' => 'サイトURLの形式が正しくありません。',
    'invalid_email' => '管理者メールアドレスの形式が正しくありません。',
    'save_failed' => '設定の保存に失敗しました。時間をおいて再度お試しください。',
];

$pageTitle = 'サイト設定';
ob_start();
?>
<h1>サイト設定</h1>

<?php if (($_GET['saved'] ?? '') === '1') : ?>
    <div class="admin-card admin-notice admin-notice--success"><p>サイト設定を保存しました。</p></div>
<?php endif; ?>

<?php if ($errorCode !== '' && isset($messages[$errorCode])) : ?>
    <div class="admin-card admin-notice admin-notice--error"><p><?php echo e($messages[$errorCode]); ?></p></div>
<?php endif; ?>

<form method="post" class="admin-card">
    <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">

    <label for="site_name">サイト名</label>
    <input id="site_name" type="text" name="site_name" value="<?php echo e($siteName); ?>" required>

    <label for="site_url">サイトURL</label>
    <input id="site_url" type="url" name="site_url" value="<?php echo e($siteUrl); ?>" required>

    <label for="admin_email">管理者メールアドレス</label>
    <input id="admin_email" type="email" name="admin_email" value="<?php echo e($adminEmail); ?>" required>

    <button type="submit">保存</button>
</form>

<div class="admin-card">
    <h2>パスワード変更</h2>
    <p><a href="<?php echo e(admin_url('change_password.php')); ?>">パスワード変更はこちら</a></p>
</div>
<?php
$main = (string)ob_get_clean();
require_once __DIR__ . '/_page.php';
admin_render($pageTitle, static function () use ($main): void {
    echo $main;
});
