<?php
declare(strict_types=1);

$year = (int)date('Y');
$siteTitle = (string)config_get('site.title', 'PinkClub-FANZA');
?>
</div>
<footer class="site-footer">
    <div class="site-footer__inner">
        <div class="site-footer__copy">&copy; <?php echo $year; ?> <?php echo e($siteTitle); ?></div>
    </div>
</footer>
</body>
</html>
