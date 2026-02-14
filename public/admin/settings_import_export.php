<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && admin_post_csrf_valid()) {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'export') {
        $tables = db()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $dump = "-- PinkClub-FANZA export " . date('Y-m-d H:i:s') . "\n";
        foreach ($tables as $table) {
            $table = (string)$table;
            $create = db()->query('SHOW CREATE TABLE `' . str_replace('`', '', $table) . '`')->fetch(PDO::FETCH_ASSOC);
            $dump .= "\nDROP TABLE IF EXISTS `{$table}`;\n" . (($create['Create Table'] ?? '') . ";\n\n");
        }
        header('Content-Type: application/sql; charset=UTF-8');
        header('Content-Disposition: attachment; filename="export-' . date('Ymd-His') . '.sql"');
        echo $dump;
        exit;
    }
}

$pageTitle = 'インポート、エクスポート';
ob_start();
?>
<h1>インポート、エクスポート</h1>
<div class="admin-card">
    <h2>インポート</h2>
    <p>既存の手動インポート処理を実行します。</p>
    <a class="admin-button" href="<?php echo e(admin_url('import_items.php')); ?>">インポート画面へ</a>
</div>
<div class="admin-card">
    <h2>エクスポート</h2>
    <p>DBテーブル定義をSQLとしてダウンロードします。</p>
    <form method="post">
        <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
        <input type="hidden" name="action" value="export">
        <button type="submit">エクスポートをダウンロード</button>
    </form>
</div>
<?php
$main = (string)ob_get_clean();
require_once __DIR__ . '/_page.php';
admin_render($pageTitle, static function () use ($main): void {
    echo $main;
});
