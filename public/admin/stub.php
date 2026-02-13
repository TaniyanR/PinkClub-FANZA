<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$pageTitle = '管理画面';
ob_start();
?>
<h1>管理画面</h1>
<div class="admin-card">
    <p>このURLは統合済みメニューからは使用しません。左メニューから機能ページを選択してください。</p>
</div>
<?php
$content = (string)ob_get_clean();
include __DIR__ . '/../partials/admin_layout.php';
