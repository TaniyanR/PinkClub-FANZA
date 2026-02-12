<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

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
<?php include __DIR__ . '/admin_header_bar.php'; ?>
<div class="admin-shell">
    <?php include __DIR__ . '/admin_sidebar.php'; ?>
    <main class="admin-main">
        <?php
        if (isset($content) && is_callable($content)) {
            $content();
        } elseif (isset($content) && is_string($content)) {
            echo $content;
        }
        ?>
    </main>
</div>
</body>
</html>
