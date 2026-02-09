<?php
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/dmm_api.php';
require_once __DIR__ . '/../lib/repository.php';

const SECONDS_PER_DAY = 86400;
const ACTRESS_CACHE_DAYS = 30;

function should_refresh_actress(array $actress): bool
{
    if (empty($actress['updated_at'])) {
        return true;
    }

    $updatedAt = strtotime($actress['updated_at']);
    if ($updatedAt === false) {
        return true;
    }

    $days = (time() - $updatedAt) / SECONDS_PER_DAY;
    if ($days > ACTRESS_CACHE_DAYS) {
        return true;
    }

    return empty($actress['bust']) && empty($actress['waist']) && empty($actress['hip']);
}

function build_actress_payload(array $base, array $incoming): array
{
    $normalizeInt = static function ($value): ?int {
        return is_numeric($value) ? (int)$value : null;
    };

    $listUrl = $incoming['list_url'] ?? $incoming['listurl'] ?? [];

    return [
        'id' => (int)($incoming['id'] ?? $base['id']),
        'name' => $incoming['name'] ?? $base['name'] ?? '',
        'ruby' => $incoming['ruby'] ?? $base['ruby'] ?? null,
        'bust' => $normalizeInt($incoming['bust'] ?? $base['bust'] ?? null),
        'cup' => $incoming['cup'] ?? $base['cup'] ?? null,
        'waist' => $normalizeInt($incoming['waist'] ?? $base['waist'] ?? null),
        'hip' => $normalizeInt($incoming['hip'] ?? $base['hip'] ?? null),
        'height' => $normalizeInt($incoming['height'] ?? $base['height'] ?? null),
        'birthday' => $incoming['birthday'] ?? $base['birthday'] ?? null,
        'blood_type' => $incoming['blood_type'] ?? $base['blood_type'] ?? null,
        'hobby' => $incoming['hobby'] ?? $base['hobby'] ?? null,
        'prefectures' => $incoming['prefectures'] ?? $base['prefectures'] ?? null,
        'image_small' => $incoming['imageURL']['small'] ?? $incoming['image_small'] ?? $base['image_small'] ?? null,
        'image_large' => $incoming['imageURL']['large'] ?? $incoming['image_large'] ?? $base['image_large'] ?? null,
        'listurl_digital' => is_array($listUrl) ? ($listUrl['digital'] ?? $base['listurl_digital'] ?? null) : ($base['listurl_digital'] ?? null),
        'listurl_monthly' => is_array($listUrl) ? ($listUrl['monthly'] ?? $base['listurl_monthly'] ?? null) : ($base['listurl_monthly'] ?? null),
        'listurl_mono' => is_array($listUrl) ? ($listUrl['mono'] ?? $base['listurl_mono'] ?? null) : ($base['listurl_mono'] ?? null),
    ];
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($id === false || $id === null || $id <= 0) {
    http_response_code(404);
    echo 'Invalid actress ID';
    exit;
}
$actress = fetch_actress($id);

if ($actress && should_refresh_actress($actress)) {
    $apiConfig = config_get('dmm_api', config_get('api', []));
    if (!empty($apiConfig['api_id']) && !empty($apiConfig['affiliate_id'])) {
        $params = [
            'api_id' => $apiConfig['api_id'],
            'affiliate_id' => $apiConfig['affiliate_id'],
            'site' => $apiConfig['site'] ?? 'FANZA',
            'service' => $apiConfig['service'] ?? 'digital',
            'floor' => $apiConfig['floor'] ?? 'videoa',
            'actress_id' => $actress['id'],
        ];
        $response = dmm_api_request('ActressSearch', $params);
        if ($response['ok']) {
            $result = $response['data']['result'] ?? [];
            $apiActresses = $result['actress'] ?? [];
            if (is_array($apiActresses) && $apiActresses) {
                $first = array_key_exists(0, $apiActresses) ? $apiActresses[0] : $apiActresses;
                $payload = build_actress_payload($actress, $first);
                if (!empty($payload['name'])) {
                    upsert_actress($payload);
                    $actress = fetch_actress($id) ?? $actress;
                }
            }
        } elseif (!empty($response['error'])) {
            log_message('ActressSearch failed: ' . $response['error']);
        }
    }
}

if (!$actress) {
    $dummy = dummy_actresses(1);
    $actress = $dummy[0];
}

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;
$items = fetch_items_by_actress((int)$actress['id'], $limit, $offset);
$relatedSeries = fetch_related_series_by_actress((int)$actress['id']);
$relatedMakers = fetch_related_makers_by_actress((int)$actress['id']);

include __DIR__ . '/partials/header.php';
include __DIR__ . '/partials/nav_search.php';
?>
<main>
    <h1><?php echo htmlspecialchars($actress['name'], ENT_QUOTES, 'UTF-8'); ?></h1>

    <?php if (!empty($actress['image_large']) || !empty($actress['image_small'])) : ?>
        <img src="<?php echo htmlspecialchars($actress['image_large'] ?: $actress['image_small'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($actress['name'], ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>

    <div class="detail-meta">
        <?php if (!empty($actress['ruby'])) : ?>
            <p>読み: <?php echo htmlspecialchars($actress['ruby'], ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <?php if (!empty($actress['birthday'])) : ?>
            <p>生年月日: <?php echo htmlspecialchars($actress['birthday'], ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <?php if (!empty($actress['height'])) : ?>
            <p>身長: <?php echo htmlspecialchars((string)$actress['height'], ENT_QUOTES, 'UTF-8'); ?>cm</p>
        <?php endif; ?>
        <?php if (!empty($actress['bust']) || !empty($actress['waist']) || !empty($actress['hip'])) : ?>
            <p>サイズ: <?php echo htmlspecialchars((string)($actress['bust'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                <?php if (!empty($actress['cup'])) : ?>
                    <?php echo htmlspecialchars($actress['cup'], ENT_QUOTES, 'UTF-8'); ?>
                <?php endif; ?>
                /
                <?php echo htmlspecialchars((string)($actress['waist'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                /
                <?php echo htmlspecialchars((string)($actress['hip'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
            </p>
        <?php endif; ?>
        <?php if (!empty($actress['prefectures'])) : ?>
            <p>出身地: <?php echo htmlspecialchars($actress['prefectures'], ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
    </div>

    <h2 class="section-title">出演作品</h2>
    <?php if (!$items) : ?>
        <div class="notice">まだデータがありません。</div>
    <?php else : ?>
        <?php
        $railTitle = '出演作品';
        $railItems = $items;
        include __DIR__ . '/partials/block_rail.php';
        ?>
        <div class="pagination">
            <a href="?id=<?php echo urlencode((string)$actress['id']); ?>&page=<?php echo $page - 1; ?>">前へ</a>
            <a href="?id=<?php echo urlencode((string)$actress['id']); ?>&page=<?php echo $page + 1; ?>">次へ</a>
        </div>
    <?php endif; ?>

    <?php if ($relatedSeries) : ?>
        <?php
        $railTitle = '関連シリーズ';
        $railItems = array_map(static function (array $series): array {
            $series['series_id'] = $series['id'] ?? null;
            return $series;
        }, $relatedSeries);
        include __DIR__ . '/partials/block_rail.php';
        ?>
    <?php endif; ?>

    <?php if ($relatedMakers) : ?>
        <?php
        $railTitle = '関連メーカー';
        $railItems = array_map(static function (array $maker): array {
            $maker['maker_id'] = $maker['id'] ?? null;
            return $maker;
        }, $relatedMakers);
        include __DIR__ . '/partials/block_rail.php';
        ?>
    <?php endif; ?>
</main>
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<?php include __DIR__ . '/partials/footer.php'; ?>
