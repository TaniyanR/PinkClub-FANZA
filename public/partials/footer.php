<?php

declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

$siteName = trim(front_safe_text_setting('site_name', ''));
if ($siteName === '') {
    $siteName = trim(front_safe_text_setting('site.title', ''));
}
if ($siteName === '') {
    $siteName = 'PinkClub FANZA';
}
?>
  </main>
</div>
<footer class="site-footer">
  <div class="site-footer__credit">
    <a href="https://affiliate.dmm.com/api/"><img src="https://p.dmm.co.jp/p/affiliate/web_service/r18_135_17.gif" width="135" height="17" alt="WEB SERVICE BY FANZA" /></a>
  </div>
  <div class="site-footer__copy">© 2024-<?= e(date('Y')) ?> <?= e($siteName) ?></div>
</footer>
</body>
</html>
