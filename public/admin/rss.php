<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
admin_trace_push('page:start:rss.php');
$pageTitle = 'RSS管理';
ob_start();
?>
<h1>RSS管理</h1>
<p>RSS設定ページは現在準備中です。</p>
<?php
$main = (string)ob_get_clean();
require_once __DIR__ . '/_page.php';
admin_render($pageTitle, static function () use ($main): void {
    echo $main;
});
