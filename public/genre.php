<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/repository.php';
require_once __DIR__ . '/../lib/public_rankings.php';
require_once __DIR__ . '/partials/public_ui.php';

function pcf_genre_count_items(int $genreId): int
{
    return count_items_by_genre($genreId);
}

function pcf_genre_display_name(array $row): string
{
    $name = trim((string)($row['name'] ?? ''));
    if ($name !== '' && !pcf_is_noise_name($name)) {
        return $name;
    }

    $genreId = (int)($row['id'] ?? 0);
    if ($genreId > 0) {
        try {
            $sql = db_column_exists('item_genres', 'item_id')
                ? 'SELECT ig.genre_name, COUNT(*) AS item_count
                   FROM item_genres ig
                   INNER JOIN genres g ON g.id = :id AND ig.dmm_id = g.dmm_id
                   WHERE TRIM(COALESCE(ig.genre_name, \'\')) <> \'\'
                   GROUP BY ig.genre_name
                   ORDER BY item_count DESC
                   LIMIT 10'
                : 'SELECT genre_name, COUNT(*) AS item_count
                   FROM item_genres
                   WHERE genre_id = :id AND TRIM(COALESCE(genre_name, \'\')) <> \'\'
                   GROUP BY genre_name
                   ORDER BY item_count DESC
                   LIMIT 10';
            $stmt = db()->prepare($sql);
            $stmt->execute([':id' => $genreId]);
            foreach (($stmt->fetchAll() ?: []) as $candidate) {
                $candidateName = trim((string)($candidate['genre_name'] ?? ''));
                if ($candidateName !== '' && !pcf_is_noise_name($candidateName)) {
                    return $candidateName;
                }
            }
        } catch (Throwable) {
        }
    }

    return $name !== '' ? $name : 'ジャンル詳細';
}

$id = (int)get('id', 0);
$page = max(1, (int)get('page', 1));
$per = 20;
$row = null;
$list = [];
$total = 0;
$pg = paginate(0, $page, $per);

try {
    $row = fetch_genre($id);
} catch (Throwable) {
    $row = null;
}

if ($row === null && $id <= 0) {
    require __DIR__ . '/404.php';
    exit;
}

$genreName = $row !== null ? pcf_genre_display_name($row) : '';
if ($genreName === '' || pcf_is_noise_name($genreName)) {
    // try to find from item_genres
    try {
        $gStmt = db()->prepare("SELECT genre_name FROM item_genres WHERE genre_id = :id OR item_id = :id2 GROUP BY genre_name ORDER BY COUNT(*) DESC LIMIT 1");
        $gStmt->execute([':id' => $id, ':id2' => $id]);
        $cand = trim((string)($gStmt->fetchColumn() ?: ''));
        if ($cand !== '' && !pcf_is_noise_name($cand)) {
            $genreName = $cand;
        }
    } catch (Throwable) {
    }
}

if ($row === null && $genreName !== '') {
    $row = ['id' => $id, 'name' => $genreName, 'dmm_id' => ''];
}

if ($row === null) {
    require __DIR__ . '/404.php';
    exit;
}

try {
    $total = count_items_by_genre($id, $genreName);
    if ($total <= 0) {
        $total = pcf_genre_count_items($id);
    }
    $pg = paginate($total, $page, $per);
    $list = dedupe_items_by_key(fetch_items_by_genre($id, (int)$pg['perPage'], (int)$pg['offset'], $genreName));
    if ($list !== [] && $total <= 0) {
        $total = count($list);
        $pg = paginate($total, $page, $per);
    }
} catch (Throwable) {
    $list = [];
    $total = 0;
    $pg = paginate(0, $page, $per);
}

try {
    analytics_log_genre_page_view((int)$row['id']);
} catch (Throwable $e) {
    error_log('genre page view logging failed: ' . $e->getMessage());
}

$accessRankingPeriod = trim((string)get('rank_period', 'daily'));
$accessRankingTabs = [
    'daily' => ['label' => '本日'],
    'weekly' => ['label' => '週間'],
    'monthly' => ['label' => '月間'],
    'yearly' => ['label' => '年間'],
];
if (!isset($accessRankingTabs[$accessRankingPeriod])) {
    $accessRankingPeriod = 'daily';
}
$accessRankingRows = pcf_public_weighted_ranking('genres', $accessRankingPeriod);

$title = $genreName;
$pageDescription = mb_strimwidth($genreName . 'のAV・成人向け動画作品一覧。FANZAアフィリエイト最新作を紹介。', 0, 150, '…', 'UTF-8');
$canonicalParams = ['id' => $id];
if ((int)($pg['page'] ?? 1) > 1) {
    $canonicalParams['page'] = (int)$pg['page'];
}
$canonicalUrl = public_url('genre.php') . '?' . http_build_query($canonicalParams);
if ((int)($pg['page'] ?? 1) > 1) {
    $relPrev = public_url('genre.php') . '?' . http_build_query(['id' => $id, 'page' => (int)$pg['page'] - 1]);
}
if ((int)($pg['page'] ?? 1) < (int)($pg['pages'] ?? 1)) {
    $relNext = public_url('genre.php') . '?' . http_build_query(['id' => $id, 'page' => (int)$pg['page'] + 1]);
}
require __DIR__ . '/partials/header.php';
?>
<?php pcf_render_breadcrumbs([
    ['label' => 'トップ', 'url' => public_url('index.php')],
    ['label' => 'ジャンル一覧', 'url' => public_url('genres.php')],
    ['label' => $genreName],
]); ?>

<?php pcf_render_hero($genreName); ?>

<h2 class="pcf-section-title"><?= e($genreName) ?>一覧</h2>
<?php if ($list !== []): ?>
  <section class="pcf-related-grid pcf-genre-related-grid">
    <?php foreach ($list as $item): pcf_render_item_card(is_array($item) ? $item : []); endforeach; ?>
  </section>
  <?php pcf_render_pagination($pg, public_url('genre.php'), ['id' => (int)$row['id']]); ?>
<?php else: ?>
  <?php pcf_render_empty('このジャンルに紐づく商品はまだありません。'); ?>
<?php endif; ?>

<?php pcf_render_entity_access_ranking(
    '人気のジャンルランキング',
    $accessRankingTabs,
    $accessRankingPeriod,
    static fn(string $period): string => public_url('genre.php') . '?' . http_build_query(['id' => (int)$row['id'], 'rank_period' => $period]) . '#access-ranking',
    $accessRankingRows,
    static fn(array $rankingRow): string => public_url('genre.php') . '?id=' . rawurlencode((string)($rankingRow['id'] ?? '')),
    '人気のジャンルランキングのデータがありません。'
); ?>

<?php pcf_render_sample_movie_modal(); ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
