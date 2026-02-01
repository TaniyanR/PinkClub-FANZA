<?php
require_once __DIR__ . '/../../lib/config.php';
$siteTitle = (string)config_get('site.title', '');
?>
</div>
<footer>
    <div class="footer-inner">&copy; <?php echo htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8'); ?></div>
</footer>
</body>
</html>
