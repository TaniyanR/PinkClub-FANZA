<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && admin_post_csrf_valid()) {
    $tables = db()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $dump = "-- PinkClub-FANZA backup " . date('Y-m-d H:i:s') . "\n";
    foreach ($tables as $table) {
        $table = (string)$table;
        $create = db()->query('SHOW CREATE TABLE `' . str_replace('`', '', $table) . '`')->fetch(PDO::FETCH_ASSOC);
        $dump .= "\nDROP TABLE IF EXISTS `{$table}`;\n" . (($create['Create Table'] ?? '') . ";\n\n");
    }
    header('Content-Type: application/sql; charset=UTF-8');
    header('Content-Disposition: attachment; filename="backup-' . date('Ymd-His') . '.sql"');
    echo $dump;
    exit;
}

$pageTitle = 'バックアップ';
ob_start(); ?>
<h1>バックアップ</h1>
<div class="admin-card"><p>テーブル定義をSQLとしてダウンロードします。</p><form method="post"><input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>"><button type="submit">バックアップSQLをダウンロード</button></form></div>
<?php $content=(string)ob_get_clean(); include __DIR__.'/../partials/admin_layout.php';
