<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

http_response_code(404);

$pageTitle = 'メール';
ob_start();
?>
<h1>メール</h1>
<div class="admin-card">
    <p>この機能は現在利用できません。</p>
</div>
<?php
$main = (string)ob_get_clean();
require_once __DIR__ . '/_page.php';
admin_render($pageTitle, static function () use ($main): void {
    echo $main;
});
