<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../../lib/admin_page_discovery.php';

$pageTitle = '管理ページ一覧';
$pages = admin_discover_pages();

ob_start();
?>
<h1>管理ページ一覧（サイトマップ）</h1>
<p>管理画面として検出したページを一覧表示します。未整備バッジは画面遷移向けに未調整の可能性があるページです。</p>

<div class="admin-card">
    <table class="admin-table">
        <thead>
        <tr>
            <th>パス</th>
            <th>ラベル</th>
            <th>状態</th>
            <th>認証</th>
            <th>存在確認</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($pages as $page) : ?>
            <tr>
                <td><a href="<?php echo e(url($page['path'])); ?>"><?php echo e($page['path']); ?></a></td>
                <td><?php echo e($page['label']); ?></td>
                <td><?php echo $page['broken'] ? '未整備' : '稼働候補'; ?></td>
                <td><?php echo e($page['auth']); ?></td>
                <td><?php echo $page['exists'] ? 'OK' : 'NG'; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php
$content = (string)ob_get_clean();
include __DIR__ . '/../partials/admin_layout.php';
