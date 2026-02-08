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
            <p>エラーが発生しました: <?php echo e((string)($_GET['error'] ?? '')); ?></p>
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
