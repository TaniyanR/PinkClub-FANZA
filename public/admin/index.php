<?php
require_once __DIR__ . '/../../lib/config.php';
require_once __DIR__ . '/../../lib/admin_auth.php';

admin_basic_auth_required();

include __DIR__ . '/../partials/header.php';
?>
<main>
    <h1>管理画面</h1>
    <div class="admin-card">
        <ul>
            <li><a href="/admin/settings.php">API設定</a></li>
            <li><a href="/admin/import_items.php">作品インポート</a></li>
            <li><a href="/admin/partners.php">提携先管理</a></li>
        </ul>
    </div>
</main>
<?php include __DIR__ . '/../partials/sidebar.php'; ?>
<?php include __DIR__ . '/../partials/footer.php'; ?>
