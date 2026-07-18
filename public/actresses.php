<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/repository.php';
require_once __DIR__ . '/partials/public_ui.php';
require_once __DIR__ . '/../lib/actress_directory_cache.php';

$manifest = ['groups' => []];
try {
    $manifest = pcf_actress_directory_cache_manifest();
} catch (Throwable $e) {
    error_log('actress directory cache failed: ' . $e->getMessage());
}

$kanaOrder = ['あ', 'か', 'さ', 'た', 'な', 'は', 'ま', 'や', 'ら', 'わ'];
$kanaGroups = [];
$alphaGroups = [];
foreach (($manifest['groups'] ?? []) as $group) {
    if (!is_array($group)) {
        continue;
    }
    $key = (string)($group['key'] ?? '');
    $label = (string)($group['label'] ?? '');
    if (($group['type'] ?? '') === 'kana' && in_array($label, $kanaOrder, true)) {
        $kanaGroups[$label] = $key;
    } elseif (($group['type'] ?? '') === 'alpha' && preg_match('/\A[A-Z]\z/', $label)) {
        $alphaGroups[$label] = $key;
    }
}
ksort($alphaGroups);

$title = '女優一覧';
require __DIR__ . '/partials/header.php';
?>
<?php pcf_render_hero('女優一覧', '気になる女優のプロフィールと出演作品へ。'); ?>

<?php if ($kanaGroups !== [] || $alphaGroups !== []): ?>
  <nav class="pcf-index-nav">
    <?php foreach ($kanaOrder as $kana): ?>
      <?php if (!isset($kanaGroups[$kana])): continue; endif; ?>
      <a class="pcf-index-nav__item" href="#actress-kana-<?= e(rawurlencode($kana)) ?>"><?= e($kana) ?></a>
    <?php endforeach; ?>
    <?php if ($alphaGroups !== []): ?><a class="pcf-index-nav__item" href="#actress-alpha">A-Z</a><?php endif; ?>
  </nav>

  <div class="pcf-actress-directory">
    <?php foreach ($kanaOrder as $kana): ?>
      <?php if (!isset($kanaGroups[$kana])): continue; endif; ?>
      <section class="pcf-index-block" id="actress-kana-<?= e(rawurlencode($kana)) ?>" style="content-visibility:auto;contain-intrinsic-size:700px;">
        <h2 class="pcf-section-title"><?= e($kana) ?>行</h2>
        <div class="pcf-list-card__meta" data-actress-lazy-group="<?= e($kanaGroups[$kana]) ?>" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px;"></div>
      </section>
    <?php endforeach; ?>

    <?php if ($alphaGroups !== []): ?>
      <section class="pcf-index-block" id="actress-alpha" style="content-visibility:auto;contain-intrinsic-size:700px;">
        <h2 class="pcf-section-title">A-Z</h2>
        <?php foreach ($alphaGroups as $letter => $groupKey): ?>
          <div class="pcf-list-card__meta" style="margin-bottom:12px;">
            <strong><?= e($letter) ?></strong>
            <div data-actress-lazy-group="<?= e($groupKey) ?>" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px;margin-top:6px;"></div>
          </div>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>
  </div>
<?php else: ?>
  <?php pcf_render_empty('女優データが見つかりませんでした。'); ?>
<?php endif; ?>

<script>
(() => {
  const detailBase = <?= json_encode(public_url('actress.php') . '?id=', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  const groupEndpoint = <?= json_encode(public_url('actresses_group.php') . '?group=', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  const placeholder = <?= json_encode(pcf_placeholder_data_uri('No Photo'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

  const appendRows = (container, rows) => {
    const fragment = document.createDocumentFragment();
    rows.forEach((row) => {
      const id = Number(row[0] || 0);
      const name = String(row[1] || '');
      if (id <= 0 || name === '') return;

      const link = document.createElement('a');
      link.href = detailBase + encodeURIComponent(String(id));
      link.style.cssText = 'display:flex;align-items:center;gap:8px;padding:6px;border:1px solid #e8e8e8;border-radius:6px;text-decoration:none;color:inherit;';

      const image = document.createElement('img');
      image.src = String(row[2] || placeholder);
      image.alt = name;
      image.loading = 'lazy';
      image.decoding = 'async';
      image.fetchPriority = 'low';
      image.style.cssText = 'width:44px;height:44px;object-fit:cover;border-radius:50%;flex:0 0 44px;';

      const label = document.createElement('span');
      label.textContent = name;
      link.append(image, label);
      fragment.appendChild(link);
    });
    container.appendChild(fragment);
  };

  const renderGroup = (container) => {
    if (!container || container.dataset.actressLoaded === '1' || container.dataset.actressLoading === '1') {
      return;
    }

    container.dataset.actressLoading = '1';
    const key = container.dataset.actressLazyGroup || '';
    fetch(groupEndpoint + encodeURIComponent(key), {
      credentials: 'same-origin',
      headers: {'Accept': 'application/json'}
    })
      .then((response) => response.ok ? response.json() : null)
      .then((data) => {
        if (!data || !data.success || !Array.isArray(data.rows)) {
          throw new Error('invalid actress group');
        }
        appendRows(container, data.rows);
        container.dataset.actressLoaded = '1';
      })
      .catch(() => {
        delete container.dataset.actressLoading;
        window.setTimeout(() => renderGroup(container), 3000);
      });
  };

  const containers = document.querySelectorAll('[data-actress-lazy-group]');
  if (!('IntersectionObserver' in window)) {
    containers.forEach(renderGroup);
    return;
  }

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      renderGroup(entry.target);
      observer.unobserve(entry.target);
    });
  }, {rootMargin: '800px 0px'});

  containers.forEach((container) => observer.observe(container));
})();
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
