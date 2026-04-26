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

$copyrightStartYear = (int)date('Y');
try {
    $pdo = db();
    $startDate = null;
    foreach (['date_published', 'release_date', 'created_at'] as $column) {
        $stmt = $pdo->query("SELECT MIN(" . $column . ") FROM items WHERE " . $column . " IS NOT NULL AND " . $column . " <> ''");
        $value = $stmt ? trim((string)$stmt->fetchColumn()) : '';
        if ($value !== '') {
            $startDate = $value;
            break;
        }
    }
    if ($startDate !== null) {
        $timestamp = strtotime($startDate);
        if ($timestamp !== false) {
            $copyrightStartYear = (int)date('Y', $timestamp);
        }
    }
} catch (Throwable $e) {
}
$currentYear = (int)date('Y');
$copyrightYears = $copyrightStartYear >= $currentYear
    ? (string)$currentYear
    : $copyrightStartYear . '-' . $currentYear;

?>
  </main>
</div>
<?php $pageType = function_exists('ad_current_page_type') ? ad_current_page_type() : 'home'; ?>
<div class="layout site-layout only-pc" style="padding-top:0;margin-top:-8px;">
  <div class="sidebar" aria-hidden="true"></div>
  <div class="content">
    <?php render_shared_content_ad_row('content_bottom', $pageType); ?>
  </div>
</div>
<footer class="site-footer">
  <div class="site-footer__credit">
    <a href="https://affiliate.dmm.com/api/"><img src="https://p.dmm.co.jp/p/affiliate/web_service/r18_135_17.gif" width="135" height="17" alt="WEB SERVICE BY FANZA" /></a>
  </div>
  <div class="site-footer__copy">© <?= e($copyrightYears) ?> <?= e($siteName) ?></div>
</footer>
</body>
</html>
