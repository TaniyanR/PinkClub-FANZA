<?php
require_once __DIR__ . '/_bootstrap.php';

include __DIR__ . '/../partials/header.php';
?>
<main>
    <h1>管理画面</h1>
    <div class="admin-card">
        <ul>
            <li><a href="<?php echo htmlspecialchars(admin_url('settings.php'), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8'); ?>">API設定</a></li>
            <li><a href="<?php echo htmlspecialchars(admin_url('import_items.php'), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8'); ?>">作品インポート</a></li>
        </ul>
    </div>
</main>
<?php include __DIR__ . '/../partials/sidebar.php'; ?>
<?php include __DIR__ . '/../partials/footer.php'; ?>
