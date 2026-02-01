<?php
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/dmm_api.php';
require_once __DIR__ . '/../../lib/repository.php';

$config = require __DIR__ . '/../../config.php';
$apiConfig = $config['api'];

$resultLog = [];
$errorLog = [];

function api_base_params(array $apiConfig): array
{
    return [
        'api_id' => $apiConfig['api_id'],
        'affiliate_id' => $apiConfig['affiliate_id'],
        'site' => $apiConfig['site'],
        'service' => $apiConfig['service'],
        'floor' => $apiConfig['floor'],
    ];
}

function get_value(array $data, string $key, $default = null)
{
    return $data[$key] ?? $default;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? '';
    $hits = min(100, max(1, (int)($_POST['hits'] ?? 100)));
    $startOffset = max(1, (int)($_POST['offset'] ?? 1));
    $maxPages = max(1, (int)($_POST['pages'] ?? 1));
    $keyword = trim($_POST['keyword'] ?? '');
    $initial = trim($_POST['initial'] ?? '');

    $paramsBase = api_base_params($apiConfig);

    $inserted = 0;
    $updated = 0;

    for ($page = 0; $page < $maxPages; $page++) {
        $offset = $startOffset + ($page * $hits);
        $params = array_merge($paramsBase, [
            'hits' => $hits,
            'offset' => $offset,
        ]);

        if ($keyword !== '') {
            $params['keyword'] = $keyword;
        }
        if ($initial !== '') {
            $params['initial'] = $initial;
        }

        $endpoint = '';
        if ($type === 'genres') {
            $endpoint = 'GenreSearch';
        } elseif ($type === 'makers') {
            $endpoint = 'MakerSearch';
        } elseif ($type === 'series') {
            $endpoint = 'SeriesSearch';
        } elseif ($type === 'actresses') {
            $endpoint = 'ActressSearch';
        } elseif ($type === 'items') {
            $endpoint = 'ItemList';
        }

        if ($endpoint === '') {
            $errorLog[] = '不明なタイプです。';
            break;
        }

        $response = dmm_api_request($endpoint, $params);
        if (!$response['ok']) {
            $errorLog[] = sprintf('APIエラー: HTTP %d %s', $response['http_code'], $response['error']);
            break;
        }

        $data = $response['data']['result'] ?? [];
        if (($data['status'] ?? '') !== '200') {
            $errorLog[] = sprintf('APIステータスエラー: %s', $data['status'] ?? 'unknown');
            break;
        }

        if ($type === 'genres') {
            $list = $data['genre'] ?? [];
            foreach ($list as $row) {
                $status = upsert_taxonomy('genres', 'genre_id', [
                    'genre_id' => (int)($row['genre_id'] ?? 0),
                    'name' => $row['name'] ?? '',
                    'ruby' => $row['ruby'] ?? '',
                    'list_url' => $row['list_url'] ?? '',
                    'site_code' => $row['site_code'] ?? '',
                    'service_code' => $row['service_code'] ?? '',
                    'floor_id' => $row['floor_id'] ?? '',
                    'floor_code' => $row['floor_code'] ?? '',
                ]);
                $status === 'inserted' ? $inserted++ : $updated++;
            }
        }

        if ($type === 'makers') {
            $list = $data['maker'] ?? [];
            foreach ($list as $row) {
                $status = upsert_taxonomy('makers', 'maker_id', [
                    'maker_id' => (int)($row['maker_id'] ?? 0),
                    'name' => $row['name'] ?? '',
                    'ruby' => $row['ruby'] ?? '',
                    'list_url' => $row['list_url'] ?? '',
                    'site_code' => $row['site_code'] ?? '',
                    'service_code' => $row['service_code'] ?? '',
                    'floor_id' => $row['floor_id'] ?? '',
                    'floor_code' => $row['floor_code'] ?? '',
                ]);
                $status === 'inserted' ? $inserted++ : $updated++;
            }
        }

        if ($type === 'series') {
            $list = $data['series'] ?? [];
            foreach ($list as $row) {
                $status = upsert_taxonomy('series', 'series_id', [
                    'series_id' => (int)($row['series_id'] ?? 0),
                    'name' => $row['name'] ?? '',
                    'ruby' => $row['ruby'] ?? '',
                    'list_url' => $row['list_url'] ?? '',
                    'site_code' => $row['site_code'] ?? '',
                    'service_code' => $row['service_code'] ?? '',
                    'floor_id' => $row['floor_id'] ?? '',
                    'floor_code' => $row['floor_code'] ?? '',
                ]);
                $status === 'inserted' ? $inserted++ : $updated++;
            }
        }

        if ($type === 'actresses') {
            $list = $data['actress'] ?? [];
            foreach ($list as $row) {
                $status = upsert_actress([
                    'id' => (int)($row['id'] ?? 0),
                    'name' => $row['name'] ?? '',
                    'ruby' => $row['ruby'] ?? '',
                    'bust' => $row['bust'] ?? '',
                    'cup' => $row['cup'] ?? '',
                    'waist' => $row['waist'] ?? '',
                    'hip' => $row['hip'] ?? '',
                    'height' => $row['height'] ?? '',
                    'birthday' => $row['birthday'] ?? '',
                    'image_small' => $row['imageURL']['small'] ?? '',
                    'image_large' => $row['imageURL']['large'] ?? '',
                    'listurl_digital' => $row['list_url']['digital'] ?? '',
                    'listurl_monthly' => $row['list_url']['monthly'] ?? '',
                    'listurl_mono' => $row['list_url']['mono'] ?? '',
                ]);
                $status === 'inserted' ? $inserted++ : $updated++;
            }
        }

        if ($type === 'items') {
            $list = $data['items'] ?? [];
            foreach ($list as $row) {
                $itemInfo = $row['iteminfo'] ?? [];
                $price = $row['prices']['price'] ?? $row['price'] ?? null;
                $result = upsert_item([
                    'content_id' => $row['content_id'] ?? '',
                    'product_id' => $row['product_id'] ?? '',
                    'title' => $row['title'] ?? '',
                    'url' => $row['URL'] ?? '',
                    'affiliate_url' => $row['affiliateURL'] ?? '',
                    'image_list' => $row['imageURL']['list'] ?? '',
                    'image_small' => $row['imageURL']['small'] ?? '',
                    'image_large' => $row['imageURL']['large'] ?? '',
                    'date_published' => $row['date'] ?? null,
                    'service_code' => $row['service_code'] ?? '',
                    'floor_code' => $row['floor_code'] ?? '',
                    'category_name' => $row['category_name'] ?? '',
                    'price_min' => is_numeric($price) ? (int)$price : null,
                ]);

                $result['status'] === 'inserted' ? $inserted++ : $updated++;

                $itemId = $result['id'];
                $actressIds = [];
                foreach (($itemInfo['actress'] ?? []) as $actress) {
                    $actressIds[] = (int)($actress['id'] ?? 0);
                }
                $genreIds = [];
                foreach (($itemInfo['genre'] ?? []) as $genre) {
                    $genreIds[] = (int)($genre['id'] ?? 0);
                }
                $makerIds = [];
                foreach (($itemInfo['maker'] ?? []) as $maker) {
                    $makerIds[] = (int)($maker['id'] ?? 0);
                }
                $seriesIds = [];
                foreach (($itemInfo['series'] ?? []) as $series) {
                    $seriesIds[] = (int)($series['id'] ?? 0);
                }

                link_item_relations($itemId, $actressIds, 'item_actresses', 'actress_id');
                link_item_relations($itemId, $genreIds, 'item_genres', 'genre_id');
                link_item_relations($itemId, $makerIds, 'item_makers', 'maker_id');
                link_item_relations($itemId, $seriesIds, 'item_series', 'series_id');
            }
        }

        $resultLog[] = sprintf('offset %d を処理しました。', $offset);
    }

    $resultLog[] = sprintf('追加 %d件 / 更新 %d件', $inserted, $updated);
}

include __DIR__ . '/../partials/header.php';
?>
<main>
    <h1>インポート実行</h1>
    <div class="admin-card">
        <form method="post">
            <label>タイプ</label>
            <select name="type">
                <option value="genres">ジャンル</option>
                <option value="makers">メーカー</option>
                <option value="series">シリーズ</option>
                <option value="actresses">女優</option>
                <option value="items">作品</option>
            </select>
            <label>Hits (最大100)</label>
            <input type="number" name="hits" value="100">
            <label>Offset (開始)</label>
            <input type="number" name="offset" value="1">
            <label>ページ数</label>
            <input type="number" name="pages" value="1">
            <label>キーワード (任意)</label>
            <input type="text" name="keyword" value="">
            <label>女優initial (任意)</label>
            <input type="text" name="initial" value="">
            <button type="submit">インポート</button>
        </form>
    </div>

    <?php if ($resultLog) : ?>
        <div class="admin-card">
            <h2>結果</h2>
            <ul>
                <?php foreach ($resultLog as $line) : ?>
                    <li><?php echo htmlspecialchars($line, ENT_QUOTES, 'UTF-8'); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($errorLog) : ?>
        <div class="admin-card">
            <h2>エラー</h2>
            <ul>
                <?php foreach ($errorLog as $line) : ?>
                    <li><?php echo htmlspecialchars($line, ENT_QUOTES, 'UTF-8'); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</main>
<?php include __DIR__ . '/../partials/sidebar.php'; ?>
<?php include __DIR__ . '/../partials/footer.php'; ?>
