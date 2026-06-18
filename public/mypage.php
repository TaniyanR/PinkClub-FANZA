<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$title = 'My Page';
$pageDescription = 'お気に入りに追加した商品、女優、ジャンル、メーカー、シリーズを表示します。';
$canonicalUrl = public_url('mypage.php');
require __DIR__ . '/partials/header.php';
?>
<section class="pcf-mypage">
  <h1 class="pcf-hero__title">My Page</h1>
  <p>お気に入りに追加した商品、女優、ジャンル、メーカー、シリーズを表示します。</p>
  <div id="pcf-mypage-favorites" class="pcf-mypage__favorites" data-empty="お気に入りはまだありません。"></div>
</section>
<script>
(function () {
  var labels = {
    item: '商品',
    actress: '女優',
    genre: 'ジャンル',
    maker: 'メーカー',
    series: 'シリーズ'
  };
  var order = ['item', 'actress', 'genre', 'maker', 'series'];
  function escapeHtml(value) {
    return String(value).replace(/[&<>'"]/g, function (ch) {
      return {'&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'}[ch];
    });
  }
  function loadFavorites() {
    try {
      var raw = window.localStorage.getItem('pcfFavorites');
      var parsed = raw ? JSON.parse(raw) : [];
      return Array.isArray(parsed) ? parsed : [];
    } catch (e) {
      return [];
    }
  }
  function renderFavorites() {
    var root = document.getElementById('pcf-mypage-favorites');
    if (!root) {
      return;
    }
    var favorites = loadFavorites().filter(function (item) {
      return item && labels[item.type] && item.id && item.title && item.url;
    });
    if (!favorites.length) {
      root.innerHTML = '<p>' + escapeHtml(root.getAttribute('data-empty') || '') + '</p>';
      return;
    }
    root.innerHTML = order.map(function (type) {
      var rows = favorites.filter(function (item) { return item.type === type; });
      if (!rows.length) {
        return '';
      }
      return '<section class="pcf-mypage__group"><h2 class="pcf-section-title">' + escapeHtml(labels[type]) + '</h2><ul class="pcf-mypage__list">' + rows.map(function (item) {
        return '<li><a href="' + escapeHtml(item.url) + '">★ ' + escapeHtml(item.title) + '</a></li>';
      }).join('') + '</ul></section>';
    }).join('');
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', renderFavorites);
  } else {
    renderFavorites();
  }
}());
</script>
<?php require __DIR__ . '/partials/footer.php';
