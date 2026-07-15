<?php

declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

$safeTextSetting = static function (string $key, string $default = ''): string {
    if (function_exists('front_safe_text_setting')) {
        return front_safe_text_setting($key, $default);
    }

    try {
        if (function_exists('setting')) {
            $value = setting($key, $default);
            return is_string($value) ? $value : $default;
        }
        if (function_exists('app_setting_get')) {
            $value = app_setting_get($key, $default);
            return is_string($value) ? $value : $default;
        }
    } catch (Throwable $e) {
        if (function_exists('app_log_error')) {
            app_log_error('footer safe text setting fallback failed: ' . $key, $e);
        }
    }

    return $default;
};

$siteName = trim($safeTextSetting('site_name', ''));
if ($siteName === '') {
    $siteName = trim($safeTextSetting('site.title', ''));
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
  <?php $pageType = function_exists('ad_current_page_type') ? ad_current_page_type() : 'home'; ?>
  </div>
  <?php if (site_setting_get('link.rss_display.pc_text_bottom', '1') === '1'): ?>
  <div class="site-main__rss only-pc">
    <?php render_shared_content_ad_row('content_bottom', $pageType); ?>
  </div>
  <?php endif; ?>
  </main>
</div>
<button type="button" class="page-top-button" aria-label="トップに戻る">↑ トップへ</button>
<?php if (function_exists('render_ad') && (!function_exists('should_show_ad') || should_show_ad('sp_footer_above', $pageType, 'sp'))): ?>
<div class="only-sp site-ad site-ad--sp-footer-above"><?php render_ad('sp_footer_above', $pageType, 'sp'); ?></div>
<?php endif; ?>
<?php if (site_setting_get('link.rss_display.sp_footer_above', '1') === '1'): ?>
<div class="site-main__rss only-sp">
  <?php render_shared_mobile_rss_widget(); ?>
</div>
<?php endif; ?>
<footer class="site-footer">
  <div class="site-footer__credit">
    <a href="https://affiliate.dmm.com/api/"><img src="https://p.dmm.co.jp/p/affiliate/web_service/r18_135_17.gif" width="135" height="17" alt="WEB SERVICE BY FANZA" /></a>
  </div>
  <div class="site-footer__copy">© <?= e($copyrightYears) ?> <a href="<?= e(public_url('')) ?>"><?= e($siteName) ?></a></div>
</footer>
<script>
(function () {
  var header = document.querySelector('.site-header');
  var button = document.querySelector('.page-top-button');
  if (!header || !button) return;
  var updateButton = function () { button.classList.toggle('is-visible', header.getBoundingClientRect().bottom <= 0); };
  button.addEventListener('click', function () {
    if ('scrollBehavior' in document.documentElement.style) window.scrollTo({ top: 0, behavior: 'smooth' });
    else window.scrollTo(0, 0);
  });
  if ('IntersectionObserver' in window) {
    var observer = new IntersectionObserver(function (entries) { button.classList.toggle('is-visible', !entries[0].isIntersecting); });
    observer.observe(header);
  } else {
    window.addEventListener('scroll', updateButton);
    window.addEventListener('resize', updateButton);
  }
  updateButton();
}());
</script>
<script>
(function () {
  var cards = document.querySelectorAll('.rail-row--home-actresses .rail-card');
  cards.forEach(function (card) {
    var image = card.querySelector('img.thumb');
    var titleLink = card.querySelector('a.rail-card__title');
    if (!image || !titleLink || image.parentElement.tagName.toLowerCase() === 'a') return;
    var imageLink = document.createElement('a');
    imageLink.href = titleLink.href;
    imageLink.setAttribute('aria-label', titleLink.textContent.trim());
    image.parentNode.insertBefore(imageLink, image);
    imageLink.appendChild(image);
  });
}());
</script>
<script>
(function () {
  var params = new URLSearchParams(window.location.search);
  if (window.location.pathname.indexOf('page.php') === -1 || params.get('slug') !== 'que') return;
  var contactForm = document.querySelector('form.contact-form');
  if (!contactForm) return;

  var nameLabel = document.querySelector('label[for="contact-name"]');
  var emailLabel = document.querySelector('label[for="contact-email"]');
  if (nameLabel && nameLabel.textContent.indexOf('必須') === -1) nameLabel.textContent = '氏名（必須）';
  if (emailLabel && emailLabel.textContent.indexOf('必須') === -1) emailLabel.textContent = 'メールアドレス（必須）';

  var section = contactForm.closest('section.block');
  if (!section) return;
  var token = contactForm.querySelector('input[name="_token"]');
  var tabs = document.createElement('div');
  tabs.style.cssText = 'display:flex;gap:8px;flex-wrap:wrap;margin:0 0 18px';
  tabs.innerHTML = '<button type="button" data-form="contact">お問い合わせ</button><button type="button" data-form="deletion" class="button-secondary">削除依頼</button>';
  contactForm.parentNode.insertBefore(tabs, contactForm);

  var deletion = document.createElement('form');
  deletion.method = 'post';
  deletion.action = '<?= e(public_url('deletion_request_submit.php')) ?>';
  deletion.enctype = 'multipart/form-data';
  deletion.className = 'contact-form';
  deletion.style.display = 'none';
  deletion.innerHTML =
    '<input type="hidden" name="_token" value="' + (token ? token.value.replace(/&/g, '&amp;').replace(/"/g, '&quot;') : '') + '">' +
    '<input type="text" name="website" value="" autocomplete="off" tabindex="-1" style="display:none">' +
    '<p>元出演者・権利者からの削除依頼専用です。受付後に受付番号を発行します。</p>' +
    '<label>お名前（本名・必須）</label><input name="deletion_name" maxlength="100" placeholder="例：山田 花子" required>' +
    '<label>連絡用メールアドレス（必須）</label><input name="deletion_email" type="email" maxlength="254" placeholder="例：example@example.com" required>' +
    '<label>電話番号（任意）</label><input name="deletion_phone" maxlength="30" placeholder="例：090-1234-5678">' +
    '<label>該当ページURL（必須）</label><textarea name="deletion_urls" rows="5" maxlength="5000" placeholder="複数ある場合は1行ずつ全て記載してください" required></textarea>' +
    '<label>本人確認書類（必須）</label><input name="identity_document" type="file" accept="image/jpeg,image/png,application/pdf" required><small>JPEG・PNG・PDF、5MB以内。公開領域外に保存します。</small>' +
    '<label>申請理由（必須）</label><textarea name="deletion_reason" rows="8" maxlength="5000" placeholder="取り消しを希望する理由と経緯をご記入ください" required></textarea>' +
    '<label><input type="checkbox" name="deletion_consent" value="1" required> プライバシーポリシーを読み、本人確認書類を提出することに同意します（提出書類は本人確認の目的以外には使用しません）。</label>' +
    '<button type="submit">削除依頼を送信する</button>';
  contactForm.parentNode.insertBefore(deletion, contactForm.nextSibling);

  var buttons = tabs.querySelectorAll('button');
  function show(type) {
    contactForm.style.display = type === 'contact' ? '' : 'none';
    deletion.style.display = type === 'deletion' ? '' : 'none';
    buttons[0].className = type === 'contact' ? '' : 'button-secondary';
    buttons[1].className = type === 'deletion' ? '' : 'button-secondary';
  }
  buttons[0].addEventListener('click', function () { show('contact'); });
  buttons[1].addEventListener('click', function () { show('deletion'); });

  var receipt = params.get('receipt');
  var error = params.get('deletion_error');
  if (receipt || error || params.get('type') === 'deletion') show('deletion');
  if (receipt) {
    var notice = document.createElement('p');
    notice.textContent = '削除依頼を受け付けました。受付番号：' + receipt;
    notice.style.cssText = 'padding:12px;border:1px solid #2d8a47;background:#eefbf1;font-weight:bold';
    deletion.parentNode.insertBefore(notice, deletion);
  } else if (error) {
    var errorNotice = document.createElement('p');
    errorNotice.textContent = error;
    errorNotice.style.cssText = 'padding:12px;border:1px solid #b52b2b;background:#fff0f0';
    deletion.parentNode.insertBefore(errorNotice, deletion);
  }
}());
</script>
<script>
(function () {
  if (!navigator.sendBeacon) return;
  var data = new FormData();
  data.append('path', window.location.pathname + window.location.search);
  data.append('referrer', document.referrer || '');
  try {
    var params = new URLSearchParams(window.location.search);
    data.append('ref', params.get('ref') || '');
  } catch (e) {
    data.append('ref', '');
  }
  navigator.sendBeacon('<?= e(public_url('analytics.php')) ?>', data);
}());
</script>
</body>
</html>
