<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/config.php';
require_once __DIR__ . '/../../lib/app_features.php';

$siteTitle = (string) app_setting_get('site_name', (string)config_get('site.title', 'PinkClub-FANZA'));
$year = (int) date('Y');
$footerText = (string)app_setting_get('footer_text', '');
$bodyEndCode = (string)app_setting_get('body_end_injection_code', '');
?>
</div>
<?php render_ad('sp_footer_above', ad_current_page_type(), 'sp'); ?>
<footer class="site-footer">
    <div class="site-footer__inner">
        <div class="site-footer__credit">
            <a href="https://affiliate.dmm.com/api/">
                <img src="https://p.dmm.co.jp/p/affiliate/web_service/r18_135_17.gif" width="135" height="17" alt="WEB SERVICE BY FANZA" />
            </a>
        </div>
        <div class="site-footer__copy">&copy; <?php echo $year; ?> <?php echo htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php if ($footerText !== '') : ?><div><?php echo htmlspecialchars($footerText, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
    </div>
</footer>
<?php if (isset($pageScripts) && is_array($pageScripts)) : foreach ($pageScripts as $scriptPath) : ?>
    <script src="<?php echo htmlspecialchars((string) $scriptPath, ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<?php endforeach; endif; ?>
<?php if ($bodyEndCode !== '') : ?><?php echo $bodyEndCode; ?><?php endif; ?>
</body>
</html>
