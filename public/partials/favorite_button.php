<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

if (!function_exists('pcf_render_favorite_button')) {
    function pcf_render_favorite_button(string $type, int $id, string $title, string $url): void
    {
        $type = trim($type);
        $title = trim($title);
        $url = trim($url);
        if ($type === '' || $id <= 0 || $title === '' || $url === '') {
            return;
        }
        $payload = [
            'type' => $type,
            'id' => $id,
            'title' => $title,
            'url' => $url,
        ];
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        if (!is_string($json)) {
            return;
        }
        ?>
<button type="button" class="pcf-favorite-button" data-favorite='<?= e($json) ?>' aria-pressed="false"><span aria-hidden="true">☆</span> お気に入り</button>
        <?php
    }
}

if (!defined('PCF_FAVORITE_BUTTON_SCRIPT')) {
    define('PCF_FAVORITE_BUTTON_SCRIPT', true);
    ?>
<script>
(function () {
  var storageKey = 'pcfFavorites';
  function loadFavorites() {
    try {
      var raw = window.localStorage.getItem(storageKey);
      var parsed = raw ? JSON.parse(raw) : [];
      return Array.isArray(parsed) ? parsed : [];
    } catch (e) {
      return [];
    }
  }
  function saveFavorites(items) {
    try {
      window.localStorage.setItem(storageKey, JSON.stringify(items));
    } catch (e) {
    }
  }
  function favoriteKey(item) {
    return String(item.type || '') + ':' + String(item.id || '');
  }
  function updateButton(button, active) {
    button.setAttribute('aria-pressed', active ? 'true' : 'false');
    button.classList.toggle('is-active', active);
    button.innerHTML = '<span aria-hidden="true">' + (active ? '★' : '☆') + '</span> ' + (active ? 'お気に入り済み' : 'お気に入り');
  }
  function initFavorites() {
  document.querySelectorAll('[data-favorite]').forEach(function (button) {
    var item;
    try {
      item = JSON.parse(button.getAttribute('data-favorite') || '{}');
    } catch (e) {
      return;
    }
    if (!item.type || !item.id || !item.title || !item.url) {
      return;
    }
    var key = favoriteKey(item);
    updateButton(button, loadFavorites().some(function (favorite) { return favoriteKey(favorite) === key; }));
    button.addEventListener('click', function () {
      var favorites = loadFavorites();
      var exists = favorites.some(function (favorite) { return favoriteKey(favorite) === key; });
      if (exists) {
        favorites = favorites.filter(function (favorite) { return favoriteKey(favorite) !== key; });
      } else {
        item.addedAt = new Date().toISOString();
        favorites.unshift(item);
      }
      saveFavorites(favorites);
      updateButton(button, !exists);
    });
  });
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initFavorites);
  } else {
    initFavorites();
  }
}());
</script>
    <?php
}
