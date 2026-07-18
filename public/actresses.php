<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/repository.php';
require_once __DIR__ . '/partials/public_ui.php';

$rows = [];
$displayRows = [];

function is_invalid_actress_name(string $name): bool
{
    if (pcf_is_noise_name($name)) {
        return true;
    }
    $v = mb_strtolower(trim($name), 'UTF-8');
    if ($v === '') {
        return true;
    }
    foreach (['相互リンク', '相互rss', 'お問い合わせ', 'privacy policy', 'プライバシー', 'サイトについて', '公式サイト', 'オフィシャルサイト'] as $ng) {
        if (str_contains($v, mb_strtolower($ng, 'UTF-8'))) {
            return true;
        }
    }
    return false;
}

function dedupe_actress_rows(array $rows): array
{
    $seen = [];
    $result = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $id = trim((string)($row['id'] ?? ''));
        $dmmId = trim((string)($row['dmm_id'] ?? ''));
        $name = mb_strtolower(trim((string)($row['name'] ?? '')), 'UTF-8');
        $key = $dmmId !== '' ? 'dmm_id:' . $dmmId : ($id !== '' ? 'id:' . $id : ($name !== '' ? 'name:' . $name : ''));
        if ($key !== '' && isset($seen[$key])) {
            continue;
        }
        if ($key !== '') {
            $seen[$key] = true;
        }
        $result[] = $row;
    }
    return $result;
}

function actress_list_image(array $row): string
{
    foreach (['image_small', 'image_large', 'image_url'] as $key) {
        $value = trim((string)($row[$key] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }

    return pcf_placeholder_data_uri('No Photo');
}

if (db_table_exists('actresses')) {
    try {
        $rows = dedupe_actress_rows(fetch_actresses(10000, 0, 'name'));
    } catch (Throwable) {
        $rows = [];
    }
}

foreach ($rows as $r) {
    if (!is_array($r)) {
        continue;
    }
    $name = trim((string)($r['name'] ?? ''));
    $dmmId = trim((string)($r['dmm_id'] ?? ''));
    if ($name === '') {
        continue;
    }
    if (is_invalid_actress_name($name) || str_starts_with($dmmId, 'name:') || !ctype_digit($dmmId)) {
        continue;
    }
    $displayRows[] = $r;
}

$title = '女優一覧';
require __DIR__ . '/partials/header.php';

$kanaOrder = ['あ', 'か', 'さ', 'た', 'な', 'は', 'ま', 'や', 'ら', 'わ'];
$kanaGroups = [];
foreach ($kanaOrder as $kana) {
    $kanaGroups[$kana] = [];
}
$alphaGroups = [];

$resolveIndex = static function (array $row): array {
    $name = trim((string)($row['name'] ?? ''));
    $ruby = trim((string)($row['ruby'] ?? ''));
    $base = $ruby !== '' ? $ruby : $name;
    $ch = mb_substr($base, 0, 1);
    if ($ch === '') {
        return ['type' => 'none', 'key' => ''];
    }

    $h = mb_convert_kana($ch, 'c', 'UTF-8');
    if (preg_match('/^[ぁ-お]/u', $h)) { return ['type' => 'kana', 'key' => 'あ']; }
    if (preg_match('/^[か-ご]/u', $h)) { return ['type' => 'kana', 'key' => 'か']; }
    if (preg_match('/^[さ-ぞ]/u', $h)) { return ['type' => 'kana', 'key' => 'さ']; }
    if (preg_match('/^[た-ど]/u', $h)) { return ['type' => 'kana', 'key' => 'た']; }
    if (preg_match('/^[な-の]/u', $h)) { return ['type' => 'kana', 'key' => 'な']; }
    if (preg_match('/^[は-ぽ]/u', $h)) { return ['type' => 'kana', 'key' => 'は']; }
    if (preg_match('/^[ま-も]/u', $h)) { return ['type' => 'kana', 'key' => 'ま']; }
    if (preg_match('/^[や-よ]/u', $h)) { return ['type' => 'kana', 'key' => 'や']; }
    if (preg_match('/^[ら-ろ]/u', $h)) { return ['type' => 'kana', 'key' => 'ら']; }
    if (preg_match('/^[わ-ん]/u', $h)) { return ['type' => 'kana', 'key' => 'わ']; }

    if (preg_match('/^[A-Za-z]/', $ch)) {
        return ['type' => 'alpha', 'key' => strtoupper($ch)];
    }

    return ['type' => 'none', 'key' => ''];
};

foreach ($displayRows as $r) {
    $idx = $resolveIndex($r);
    if ($idx['type'] === 'kana' && isset($kanaGroups[$idx['key']])) {
        $kanaGroups[$idx['key']][] = $r;
        continue;
    }
    if ($idx['type'] === 'alpha') {
        $alphaGroups[$idx['key']][] = $r;
    }
}

$sortByName = static function (array &$rows): void {
    usort($rows, static function (array $a, array $b): int {
        $an = trim((string)($a['name'] ?? ''));
        $bn = trim((string)($b['name'] ?? ''));
        return strcmp(mb_strtolower($an, 'UTF-8'), mb_strtolower($bn, 'UTF-8'));
    });
};

foreach ($kanaGroups as &$rowsByKana) {
    $sortByName($rowsByKana);
}
unset($rowsByKana);

ksort($alphaGroups);
foreach ($alphaGroups as &$rowsByAlpha) {
    $sortByName($rowsByAlpha);
}
unset($rowsByAlpha);

$actressLazyGroups = [];
$actressLazyRow = static function (array $row): array {
    $image = '';
    foreach (['image_small', 'image_large', 'image_url'] as $imageKey) {
        $candidate = trim((string)($row[$imageKey] ?? ''));
        if ($candidate !== '') {
            $image = $candidate;
            break;
        }
    }
    return [(int)($row['id'] ?? 0), (string)($row['name'] ?? ''), $image];
};
?>
<?php pcf_render_hero('女優一覧', '気になる女優のプロフィールと出演作品へ。'); ?>

<?php if ($displayRows !== []): ?>
  <nav class="pcf-index-nav">
    <?php foreach ($kanaOrder as $kana): ?>
      <a class="pcf-index-nav__item" href="#actress-kana-<?= e(rawurlencode($kana)) ?>"><?= e($kana) ?></a>
    <?php endforeach; ?>
    <?php if ($alphaGroups !== []): ?><a class="pcf-index-nav__item" href="#actress-alpha">A-Z</a><?php endif; ?>
  </nav>

  <div class="pcf-actress-directory">
    <?php foreach ($kanaGroups as $kana => $groupRows): ?>
      <?php if ($groupRows === []): continue; endif; ?>
      <section class="pcf-index-block" id="actress-kana-<?= e(rawurlencode($kana)) ?>" style="content-visibility:auto;contain-intrinsic-size:700px;">
        <h2 class="pcf-section-title"><?= e($kana) ?>行</h2>
        <?php $lazyGroupKey = 'kana:' . $kana; $actressLazyGroups[$lazyGroupKey] = array_map($actressLazyRow, $groupRows); ?>
        <div class="pcf-list-card__meta" data-actress-lazy-group="<?= e($lazyGroupKey) ?>" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px;"></div>
      </section>
    <?php endforeach; ?>

    <?php if ($alphaGroups !== []): ?>
      <section class="pcf-index-block" id="actress-alpha" style="content-visibility:auto;contain-intrinsic-size:700px;">
        <h2 class="pcf-section-title">A-Z</h2>
        <?php foreach ($alphaGroups as $letter => $groupRows): ?>
          <div class="pcf-list-card__meta" style="margin-bottom:12px;">
            <strong><?= e($letter) ?></strong>
            <?php $lazyGroupKey = 'alpha:' . $letter; $actressLazyGroups[$lazyGroupKey] = array_map($actressLazyRow, $groupRows); ?>
            <div data-actress-lazy-group="<?= e($lazyGroupKey) ?>" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px;margin-top:6px;"></div>
          </div>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>
  </div>
<?php else: ?>
  <?php pcf_render_empty('女優データが見つかりませんでした。'); ?>
<?php endif; ?>

<?php if ($actressLazyGroups !== []): ?>
<script type="application/json" id="pcf-actress-directory-data"><?= json_encode($actressLazyGroups, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script>
(() => {
  const dataNode = document.getElementById('pcf-actress-directory-data');
  if (!dataNode) return;

  let groups = {};
  try {
    groups = JSON.parse(dataNode.textContent || '{}');
  } catch (_) {
    return;
  }

  const detailBase = <?= json_encode(public_url('actress.php') . '?id=', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  const placeholder = <?= json_encode(pcf_placeholder_data_uri('No Photo'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

  const renderGroup = (container) => {
    if (!container || container.dataset.actressLoaded === '1') return;
    container.dataset.actressLoaded = '1';
    const rows = groups[container.dataset.actressLazyGroup || ''] || [];
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
    delete groups[container.dataset.actressLazyGroup || ''];
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
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
