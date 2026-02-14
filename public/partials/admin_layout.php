<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

if (function_exists('admin_trace_push')) {
    admin_trace_push('layout:begin');
}

$siteTitle = (string)config_get('site.title', 'PinkClub-FANZA');
$rawPageTitle = isset($pageTitle) && $pageTitle !== '' ? (string)$pageTitle : '管理画面';
$fullTitle = $rawPageTitle . ' | ' . $siteTitle;
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($fullTitle); ?></title>
    <link rel="stylesheet" href="<?php echo e(base_url() . '/assets/css/admin.css'); ?>">
</head>
<body>
<?php
try {
    if (function_exists('admin_trace_push')) {
        admin_trace_push('include:header_bar:before');
    }
    include __DIR__ . '/admin_header_bar.php';
    if (function_exists('admin_trace_push')) {
        admin_trace_push('include:header_bar:after');
    }
} catch (Throwable $exception) {
    if (function_exists('admin_trace_push')) {
        admin_trace_push('include:header_bar:failed');
    }
    admin_render_plain_error_page('管理画面エラー', 'ヘッダの読み込みに失敗しました。', $exception);
    return;
}
?>
<div class="admin-shell">
    <?php
    $sidebarPath = __DIR__ . '/admin_sidebar.php';
    if (function_exists('admin_trace_push')) {
        admin_trace_push('include:sidebar:before');
    }

    if (is_file($sidebarPath)) {
        try {
            include $sidebarPath;
            if (function_exists('admin_trace_push')) {
                admin_trace_push('include:sidebar:after');
            }
        } catch (Throwable $exception) {
            if (function_exists('admin_trace_push')) {
                admin_trace_push('include:sidebar:failed');
            }
            echo '<aside class="admin-sidebar" aria-label="管理メニュー"><nav><p>sidebar unavailable</p></nav></aside>';
        }
    } else {
        if (function_exists('admin_trace_push')) {
            admin_trace_push('include:sidebar:missing');
        }
        echo '<aside class="admin-sidebar" aria-label="管理メニュー"><nav><p>sidebar missing</p></nav></aside>';
    }
    ?>
    <main class="admin-main">
        <?php
        if (function_exists('admin_trace_push')) {
            admin_trace_push('layout:content:render');
        }
        if (isset($content) && is_callable($content)) {
            $content();
        } elseif (isset($content) && is_string($content)) {
            echo $content;
        } else {
            echo '<div class="admin-card"><p>ページ本文が未設定です。</p></div>';
        }
        ?>
    </main>
</div>
</body>
</html>
<?php if (function_exists('admin_trace_push')) { admin_trace_push('layout:end'); } ?>
