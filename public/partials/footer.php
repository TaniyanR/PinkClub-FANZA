<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/config.php';

$siteTitle = (string) config_get('site.title', 'PinkClub-FANZA');
$year = (int) date('Y');
?>
</div>
<footer>
    <div class="footer-inner">
        <a class="footer-credit" href="https://affiliate.dmm.com/api/">
            <img src="https://p.dmm.co.jp/p/affiliate/web_service/r18_135_17.gif" width="135" height="17" alt="WEB SERVICE BY FANZA" />
        </a>
        <div class="footer-copy">&copy; <?php echo $year; ?> <?php echo htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
</footer>
<?php if (isset($pageScripts) && is_array($pageScripts)) : ?>
    <?php foreach ($pageScripts as $scriptPath) : ?>
        <script src="<?php echo htmlspecialchars((string) $scriptPath, ENT_QUOTES, 'UTF-8'); ?>" defer></script>
    <?php endforeach; ?>
<?php endif; ?>
</body>
</html>
