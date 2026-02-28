<?php
declare(strict_types=1);
require_once __DIR__ . '/_helpers.php';
$pageType = function_exists('ad_current_page_type') ? ad_current_page_type() : 'home';
?>
</main>
<footer style="padding:16px;">
  <div class="only-sp">
    <?php include __DIR__ . '/rss_text_widget.php'; ?>
    <?php render_ad('sp_footer_above', $pageType, 'sp'); ?>
  </div>
</footer>
</body>
</html>
