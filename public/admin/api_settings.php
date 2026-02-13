<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../../lib/site_settings.php';
require_once __DIR__ . '/../../lib/scheduler.php';

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!admin_post_csrf_valid()) {
        $error = 'CSRFトークンが無効です。';
    } else {
        $interval = scheduler_normalize_interval((int)($_POST['interval_minutes'] ?? 60));
        $itemLimit = scheduler_normalize_item_limit((int)($_POST['api_item_limit'] ?? 100));

        $apiKey = trim((string)($_POST['api_key'] ?? ''));
        $affiliateId = trim((string)($_POST['api_affiliate_id'] ?? ''));
        $endpoint = trim((string)($_POST['api_endpoint'] ?? 'ItemList'));
        if ($endpoint === '') {
            $endpoint = 'ItemList';
        }

        site_setting_set_many([
            'api_key' => $apiKey,
            'api_affiliate_id' => $affiliateId,
            'api_endpoint' => $endpoint,
            'api_item_limit' => (string)$itemLimit,
        ]);

        $state = scheduler_get_state(db());
        db()->prepare('UPDATE api_schedules SET interval_minutes=:interval, updated_at=NOW() WHERE id=:id')
            ->execute([':interval' => $interval, ':id' => (int)$state['id']]);

        admin_flash_set('ok', 'API設定を保存しました。');
        header('Location: ' . admin_url('api_settings.php'));
        exit;
    }
}

$settings = scheduler_settings();
$schedule = scheduler_get_state(db());
$ok = admin_flash_get('ok');

$apiConfigured = $settings['api_key'] !== '' && $settings['api_affiliate_id'] !== '';
$lockUntil = (string)($schedule['lock_until'] ?? '');
$isLocked = $lockUntil !== '' && strtotime($lockUntil) > time();
$pageTitle = 'API設定';
ob_start();
?>
<h1>API設定</h1>
<?php if ($ok !== '') : ?><div class="admin-card"><p><?php echo e($ok); ?></p></div><?php endif; ?>
<?php if ($error !== '') : ?><div class="admin-card"><p><?php echo e($error); ?></p></div><?php endif; ?>

<div class="admin-card">
    <h2>API資格情報</h2>
    <form method="post">
        <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
        <label>APIキー</label>
        <input type="text" name="api_key" value="<?php echo e($settings['api_key']); ?>" placeholder="未設定">

        <label>アフィリエイトID</label>
        <input type="text" name="api_affiliate_id" value="<?php echo e($settings['api_affiliate_id']); ?>" placeholder="未設定">

        <label>APIエンドポイント</label>
        <input type="text" name="api_endpoint" value="<?php echo e($settings['api_endpoint']); ?>" placeholder="ItemList">

        <label>取得件数</label>
        <select name="api_item_limit">
            <?php foreach (scheduler_allowed_item_limits() as $limit) : ?>
                <option value="<?php echo e((string)$limit); ?>" <?php echo (int)$settings['api_item_limit'] === $limit ? 'selected' : ''; ?>><?php echo e((string)$limit); ?></option>
            <?php endforeach; ?>
        </select>

        <label>実行間隔（分）</label>
        <select name="interval_minutes">
            <?php foreach (scheduler_allowed_intervals() as $m) : ?>
                <option value="<?php echo e((string)$m); ?>" <?php echo (int)($schedule['interval_minutes'] ?? 60) === $m ? 'selected' : ''; ?>><?php echo e((string)$m); ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit">保存</button>
    </form>
</div>

<div class="admin-card">
    <h2>現在状態</h2>
    <p>API設定: <?php echo $apiConfigured ? '設定済み' : '未設定'; ?></p>
    <?php if (!$apiConfigured) : ?><p>APIキーまたはアフィリエイトIDが未設定です。</p><?php endif; ?>
    <p>スケジュール: <?php echo e((string)($schedule['interval_minutes'] ?? 60)); ?> 分</p>
    <p>件数: <?php echo e((string)$settings['api_item_limit']); ?></p>
    <p>last_run: <?php echo e((string)($schedule['last_run'] ?? '未実行')); ?></p>
    <p>last_success_at: <?php echo e((string)($schedule['last_success_at'] ?? '未成功')); ?></p>
    <p>fail_count: <?php echo e((string)($schedule['fail_count'] ?? 0)); ?></p>
    <?php if ((int)($schedule['fail_count'] ?? 0) >= 5) : ?>
        <p style="color:#b00020;">fail_count が 5 以上です。last_error: <?php echo e((string)($schedule['last_error'] ?? '')); ?></p>
    <?php endif; ?>
    <?php if ($isLocked) : ?>
        <p>lock_until: <?php echo e($lockUntil); ?>（ロック中）</p>
    <?php else : ?>
        <p>lock_until: <?php echo e($lockUntil !== '' ? $lockUntil : 'ロックなし'); ?></p>
    <?php endif; ?>
</div>

<?php
$content = (string)ob_get_clean();
include __DIR__ . '/../partials/admin_layout.php';
