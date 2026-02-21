<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../lib/site_settings.php';

$year = (int)date('Y');
$siteTitle = trim(site_title_setting(''));
if ($siteTitle === '') {
    $siteTitle = 'サイトタイトル未設定';
}
?>
</div>
<footer class="site-footer">
    <div class="site-footer__inner">
        <div class="site-footer__copy">&copy; <?php echo $year; ?> <?php echo e($siteTitle); ?></div>
    </div>
</footer>
</body>
</html>
