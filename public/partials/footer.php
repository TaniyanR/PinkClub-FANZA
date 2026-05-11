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
$rankingPeriods = [
    '24h' => 'INTERVAL 1 DAY',
    'week' => 'INTERVAL 7 DAY',
    'month' => 'INTERVAL 30 DAY',
    'year' => 'INTERVAL 365 DAY',
];
$rankingTarget = null;
$scriptName = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
if (in_array($scriptName, ['items.php', 'posts.php', 'item.php'], true)) {
    $rankingTarget = ['type' => 'item', 'title' => '商品アクセスランキング'];
} elseif (in_array($scriptName, ['actresses.php', 'actress.php'], true)) {
    $rankingTarget = ['type' => 'actress', 'title' => '女優アクセスランキング'];
} elseif (in_array($scriptName, ['genres.php', 'genre.php'], true)) {
    $rankingTarget = ['type' => 'genre', 'title' => 'ジャンルアクセスランキング'];
} elseif (in_array($scriptName, ['makers.php', 'maker.php'], true)) {
    $rankingTarget = ['type' => 'maker', 'title' => 'メーカーアクセスランキング'];
} elseif (in_array($scriptName, ['series_list.php', 'series.php', 'series_one.php', 'series_detail.php', 'series_item.php'], true)) {
    $rankingTarget = ['type' => 'series', 'title' => 'シリーズアクセスランキング'];
} elseif (in_array($scriptName, ['authors.php', 'author.php'], true)) {
    $rankingTarget = ['type' => 'author', 'title' => '作者アクセスランキング'];
}
$rankingRowsByPeriod = [];
if ($rankingTarget !== null) {
    try {
        $pdo = db();
        $pageViewColumns = [];
        $pageViewColumnRows = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'page_views'")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($pageViewColumnRows as $columnRow) {
            $name = (string)($columnRow['COLUMN_NAME'] ?? '');
            if ($name !== '') {
                $pageViewColumns[$name] = true;
            }
        }
        $hasItemId = isset($pageViewColumns['item_id']);
        $hasItemCid = isset($pageViewColumns['item_cid']);
        foreach ($rankingPeriods as $periodKey => $intervalExpr) {
            if ($rankingTarget['type'] === 'item') {
                if ($hasItemId) {
                    $stmt = $pdo->prepare('SELECT i.title, i.content_id, COUNT(*) AS access_count FROM page_views pv INNER JOIN items i ON i.id = pv.item_id WHERE pv.viewed_at >= (NOW() - ' . $intervalExpr . ') GROUP BY i.id, i.title, i.content_id ORDER BY access_count DESC, i.id DESC LIMIT 10');
                    $stmt->execute();
                    $rankingRowsByPeriod[$periodKey] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                } elseif ($hasItemCid) {
                    $stmt = $pdo->prepare('SELECT i.title, i.content_id, COUNT(*) AS access_count FROM page_views pv INNER JOIN items i ON i.content_id = pv.item_cid WHERE pv.item_cid IS NOT NULL AND pv.item_cid <> "" AND pv.viewed_at >= (NOW() - ' . $intervalExpr . ') GROUP BY i.content_id, i.title ORDER BY access_count DESC, i.id DESC LIMIT 10');
                    $stmt->execute();
                    $rankingRowsByPeriod[$periodKey] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                } else {
                    $rankingRowsByPeriod[$periodKey] = [];
                }
            } elseif ($rankingTarget['type'] === 'actress') {
                if ($hasItemId) {
                    $stmt = $pdo->prepare('SELECT a.id, a.name, COUNT(*) AS access_count FROM page_views pv INNER JOIN item_actresses ia ON ia.item_id = pv.item_id INNER JOIN actresses a ON a.id = ia.actress_id WHERE pv.viewed_at >= (NOW() - ' . $intervalExpr . ') GROUP BY a.id, a.name ORDER BY access_count DESC, a.id DESC LIMIT 10');
                    $stmt->execute();
                    $rankingRowsByPeriod[$periodKey] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                } else {
                    $rankingRowsByPeriod[$periodKey] = [];
                }
            } elseif ($rankingTarget['type'] === 'genre') {
                if ($hasItemId) {
                    $stmt = $pdo->prepare('SELECT g.id, g.name, COUNT(*) AS access_count FROM page_views pv INNER JOIN item_genres ig ON ig.item_id = pv.item_id INNER JOIN genres g ON g.id = ig.genre_id WHERE pv.viewed_at >= (NOW() - ' . $intervalExpr . ') GROUP BY g.id, g.name ORDER BY access_count DESC, g.id DESC LIMIT 10');
                    $stmt->execute();
                    $rankingRowsByPeriod[$periodKey] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                } else {
                    $rankingRowsByPeriod[$periodKey] = [];
                }
            } elseif ($rankingTarget['type'] === 'maker') {
                if ($hasItemId) {
                    $stmt = $pdo->prepare('SELECT m.id, m.name, COUNT(*) AS access_count FROM page_views pv INNER JOIN item_makers im ON im.item_id = pv.item_id INNER JOIN makers m ON m.id = im.maker_id WHERE pv.viewed_at >= (NOW() - ' . $intervalExpr . ') GROUP BY m.id, m.name ORDER BY access_count DESC, m.id DESC LIMIT 10');
                    $stmt->execute();
                    $rankingRowsByPeriod[$periodKey] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                } else {
                    $rankingRowsByPeriod[$periodKey] = [];
                }
            } elseif ($rankingTarget['type'] === 'series') {
                if ($hasItemId) {
                    $stmt = $pdo->prepare('SELECT s.id, s.name, COUNT(*) AS access_count FROM page_views pv INNER JOIN item_series ias ON ias.item_id = pv.item_id INNER JOIN series_master s ON s.id = ias.series_id WHERE pv.viewed_at >= (NOW() - ' . $intervalExpr . ') GROUP BY s.id, s.name ORDER BY access_count DESC, s.id DESC LIMIT 10');
                    $stmt->execute();
                    $rankingRowsByPeriod[$periodKey] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                } else {
                    $rankingRowsByPeriod[$periodKey] = [];
                }
            } elseif ($rankingTarget['type'] === 'author') {
                if ($hasItemId) {
                    $stmt = $pdo->prepare('SELECT a.id, a.name, COUNT(*) AS access_count FROM page_views pv INNER JOIN item_authors ia ON ia.item_id = pv.item_id INNER JOIN authors a ON a.id = ia.author_id WHERE pv.viewed_at >= (NOW() - ' . $intervalExpr . ') GROUP BY a.id, a.name ORDER BY access_count DESC, a.id DESC LIMIT 10');
                    $stmt->execute();
                    $rankingRowsByPeriod[$periodKey] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                } else {
                    $rankingRowsByPeriod[$periodKey] = [];
                }
            }
        }
    } catch (Throwable $e) {
        $rankingRowsByPeriod = [];
    }
}

?>
  <?php $pageType = function_exists('ad_current_page_type') ? ad_current_page_type() : 'home'; ?>
  </div>
  <div class="site-main__rss">
    <?php render_shared_content_ad_row('content_bottom', $pageType); ?>
  </div>
  <?php if ($rankingTarget !== null && $rankingRowsByPeriod !== []): ?>
  <section class="block">
    <h2 class="section-title"><?= e((string)$rankingTarget['title']) ?></h2>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin:0 0 12px;">
      <button type="button" class="js-ranking-tab button button--primary" data-target="24h">24時間</button>
      <button type="button" class="js-ranking-tab button" data-target="week">週間</button>
      <button type="button" class="js-ranking-tab button" data-target="month">月間</button>
      <button type="button" class="js-ranking-tab button" data-target="year">年間</button>
    </div>
    <?php foreach ($rankingPeriods as $periodKey => $intervalExpr): ?>
      <div class="js-ranking-panel" data-period="<?= e($periodKey) ?>" style="<?= $periodKey !== '24h' ? 'display:none;' : '' ?>">
        <?php $rankingRows = $rankingRowsByPeriod[$periodKey] ?? []; ?>
        <?php if ($rankingRows !== []): ?>
          <ol style="margin:0;padding-left:1.5em;">
            <?php foreach ($rankingRows as $index => $rankingRow): ?>
              <li style="margin:0 0 6px;">
                <?php if ($rankingTarget['type'] === 'item'): ?>
                  <a href="<?= e(public_url('item.php?cid=' . urlencode((string)($rankingRow['content_id'] ?? '')))) ?>"><?= e((string)($rankingRow['title'] ?? '')) ?></a>
                <?php elseif ($rankingTarget['type'] === 'actress'): ?>
                  <a href="<?= e(public_url('actress.php?id=' . (int)($rankingRow['id'] ?? 0))) ?>"><?= e((string)($rankingRow['name'] ?? '')) ?></a>
                <?php elseif ($rankingTarget['type'] === 'genre'): ?>
                  <a href="<?= e(public_url('genre.php?id=' . (int)($rankingRow['id'] ?? 0))) ?>"><?= e((string)($rankingRow['name'] ?? '')) ?></a>
                <?php elseif ($rankingTarget['type'] === 'maker'): ?>
                  <a href="<?= e(public_url('maker.php?id=' . (int)($rankingRow['id'] ?? 0))) ?>"><?= e((string)($rankingRow['name'] ?? '')) ?></a>
                <?php elseif ($rankingTarget['type'] === 'series'): ?>
                  <a href="<?= e(public_url('series_one.php?id=' . (int)($rankingRow['id'] ?? 0))) ?>"><?= e((string)($rankingRow['name'] ?? '')) ?></a>
                <?php elseif ($rankingTarget['type'] === 'author'): ?>
                  <a href="<?= e(public_url('author.php?id=' . (int)($rankingRow['id'] ?? 0))) ?>"><?= e((string)($rankingRow['name'] ?? '')) ?></a>
                <?php endif; ?>
                <span>（<?= e((string)($rankingRow['access_count'] ?? 0)) ?>）</span>
              </li>
            <?php endforeach; ?>
          </ol>
        <?php else: ?>
          <p class="sidebar-empty">ランキングデータがありません。</p>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </section>
  <script>
  (function () {
    var buttons = document.querySelectorAll('.js-ranking-tab');
    var panels = document.querySelectorAll('.js-ranking-panel');
    buttons.forEach(function (button) {
      button.addEventListener('click', function () {
        var target = button.getAttribute('data-target');
        panels.forEach(function (panel) {
          panel.style.display = panel.getAttribute('data-period') === target ? '' : 'none';
        });
        buttons.forEach(function (btn) {
          if (btn.getAttribute('data-target') === target) {
            btn.classList.add('button--primary');
          } else {
            btn.classList.remove('button--primary');
          }
        });
      });
    });
  })();
  </script>
  <?php endif; ?>
  </main>
</div>
<footer class="site-footer">
  <div class="site-footer__credit">
    <a href="https://affiliate.dmm.com/api/"><img src="https://p.dmm.co.jp/p/affiliate/web_service/r18_135_17.gif" width="135" height="17" alt="WEB SERVICE BY FANZA" /></a>
  </div>
  <div class="site-footer__copy">© <?= e($copyrightYears) ?> <?= e($siteName) ?></div>
</footer>
</body>
</html>
