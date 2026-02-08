<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$apiConfig = config_get('dmm_api', []);
$localPath = __DIR__ . '/../../config.local.php';

$errorMessages = [
    'missing_required' => 'API ID / アフィリエイトIDが未入力です。対象ファイル: ' . $localPath,
    'csrf_failed' => '不正なリクエストです。対象ファイル: ' . $localPath,
    'not_writable_dir' => '設定ディレクトリに書き込めません。対象ファイル: ' . $localPath . '（ディレクトリ権限を確認してください）',
    'not_writable_file' => '設定ファイルに書き込めません。対象ファイル: ' . $localPath . '（ファイル権限を確認してください）',
    'write_failed' => 'config.local.php に書き込めません。対象ファイル: ' . $localPath . '（権限を確認してください）',
    'rename_failed' => '設定ファイルの更新に失敗しました。対象ファイル: ' . $localPath . '（ディスク/権限を確認してください）',
];

include __DIR__ . '/../partials/header.php';
?>
<main>
    <h1>API設定</h1>

    <?php if (($_GET['saved'] ?? '') === '1') : ?>
        <div class="admin-card">
            <p>保存しました。</p>
        </div>
    <?php endif; ?>

    <?php if (($_GET['error'] ?? '') !== '') : ?>
        <div class="admin-card">
            <?php
            $errorCode = (string)($_GET['error'] ?? '');
            $errorMessage = $errorMessages[$errorCode] ?? ('エラーが発生しました。対象ファイル: ' . $localPath . '（' . $errorCode . '）');
            ?>
            <p><?php echo e($errorMessage); ?></p>
        </div>
    <?php endif; ?>

    <form class="admin-card" method="post" action="/admin/save_settings.php">
        <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">

        <label>API ID</label>
        <input type="text" name="api_id" value="<?php echo e((string)($apiConfig['api_id'] ?? '')); ?>">

        <label>Affiliate ID</label>
        <input type="text" name="affiliate_id" value="<?php echo e((string)($apiConfig['affiliate_id'] ?? '')); ?>">

        <label>Site</label>
        <input type="text" name="site" value="<?php echo e((string)($apiConfig['site'] ?? 'FANZA')); ?>">

        <label>Service</label>
        <input type="text" name="service" value="<?php echo e((string)($apiConfig['service'] ?? 'digital')); ?>">

        <label>Floor</label>
        <input type="text" name="floor" value="<?php echo e((string)($apiConfig['floor'] ?? 'videoa')); ?>">

        <button type="submit">保存</button>
    </form>
</main>
<?php include __DIR__ . '/../partials/sidebar.php'; ?>
<?php include __DIR__ . '/../partials/footer.php'; ?>
