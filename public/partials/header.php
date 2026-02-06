<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/config.php';

$siteTitle = (string) config_get('site.title', 'PinkClub-FANZA');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="/assets/style.css">
    <?php if (isset($pageStyles) && is_array($pageStyles)) : ?>
        <?php foreach ($pageStyles as $stylePath) : ?>
            <link rel="stylesheet" href="<?php echo htmlspecialchars((string) $stylePath, ENT_QUOTES, 'UTF-8'); ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>
<header>
    <div class="header-inner">
        <div class="header-title">
            <a href="/" style="color:#fff;text-decoration:none;">
                <?php echo htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8'); ?>
            </a>
        </div>
        <div class="header-ad">
            <div class="ad-box">728x90 Ad</div>
        </div>
    </div>
</header>
<div class="container">
