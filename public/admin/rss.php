<?php
$main = (string)ob_get_clean();

require_once __DIR__ . '/_page.php';
admin_trace_push('before_layout_include');
admin_render($pageTitle, static function () use ($main): void {
    echo $main;
});
admin_trace_push('after_layout_include');
