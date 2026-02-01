<?php
$config = require __DIR__ . '/../../config.php';
include __DIR__ . '/../partials/header.php';
?>
<main>
    <h1>API設定</h1>
    <form class="admin-card" method="post" action="/admin/save_settings.php">
        <label>API ID</label>
        <input type="text" name="api_id" value="<?php echo htmlspecialchars($config['dmm_api']['api_id'], ENT_QUOTES, 'UTF-8'); ?>">
        <label>Affiliate ID</label>
        <input type="text" name="affiliate_id" value="<?php echo htmlspecialchars($config['dmm_api']['affiliate_id'], ENT_QUOTES, 'UTF-8'); ?>">
        <label>Site</label>
        <input type="text" name="site" value="<?php echo htmlspecialchars($config['dmm_api']['site'], ENT_QUOTES, 'UTF-8'); ?>">
        <label>Service</label>
        <input type="text" name="service" value="<?php echo htmlspecialchars($config['dmm_api']['service'], ENT_QUOTES, 'UTF-8'); ?>">
        <label>Floor</label>
        <input type="text" name="floor" value="<?php echo htmlspecialchars($config['dmm_api']['floor'], ENT_QUOTES, 'UTF-8'); ?>">
        <button type="submit">保存</button>
    </form>
</main>
<?php include __DIR__ . '/../partials/sidebar.php'; ?>
<?php include __DIR__ . '/../partials/footer.php'; ?>
