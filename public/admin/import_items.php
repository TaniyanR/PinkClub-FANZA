<?php
require_once __DIR__ . '/../../lib/config.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/dmm_api.php';
require_once __DIR__ . '/../../lib/repository.php';

$apiConfig = config_get('dmm_api', []);

$resultLog = [];
$errorLog  = [];

function api_base_params(array $apiConfig): array
{
    return [
        'api_id'        => $apiConfig['api_id'] ?? '',
        'affiliate_id'  => $apiConfig['affiliate_id'] ?? '',
        'site'          => $apiConfig['site'] ?? '',
        'service'       => $apiConfig['service'] ?? '',
        'floor'         => $apiConfig['floor'] ?? '',
    ];
}

function validate_api_config(array $apiConfig): array
{
    $required = ['api_id', 'affiliate_id', 'site', 'service', 'floor'];
    $missing = [];
    foreach ($required as $k) {
        if (empty($apiConfig[$k])) {
            $missing[] = $k;
        }
    }
    return $missing;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $missing = validate_api_config($apiConfig);
    if ($missing) {
        $errorLog[] = 'DMM API設定が不足しています: ' . implode(', ', $missing);
    } else {
        $hits        = min(100, max(1, (int)($_POST['hits'] ?? 100)));
        $startOffset = max(1, (int)($_POST['offset'] ?? 1));
        $maxPages    = max(1, (int)($_POST['pages'] ?? 1));
        $keyword     = trim($_POST['keyword'] ?? '');

        $paramsBase = api_base_params($apiConfig);

        $inserted = 0;
        $updated  = 0;

        for ($page = 0; $page < $maxPages; $page++) {
            $offset = $startOffset + ($page * $hits);
            $params = array_merge($paramsBase, [
                'hits'   => $hits,
                'offset' => $offset,
            ]);

            if ($keyword !== '') {
                $params['keyword'] = $keyword;
            }

            $response = dmm_api_request('ItemList', $params);
            if (!$response['ok']) {
                $errorLog[] = sprintf('APIエラー: HTTP %d %s', $response['http_code'], $response['error']);
                break;
            }

            $data = $response['data']['result'] ?? [];
            if (($data['status'] ?? '') !== '200') {
                $errorLog[] = sprintf('APIステータスエラー: %s', $data['status'] ?? 'unknown');
                break;
            }

            $list = $data['items'] ?? [];
            foreach ($list as $row) {
                $price = $row['prices']['price'] ?? ($row['price'] ?? null);

                $result = upsert_item([
                    'content_id'      => $row['content_id'] ?? '',
                    'product_id'      => $row['product_id'] ?? '',
                    'title'           => $row['title'] ?? '',
                    'url'             => $row['URL'] ?? '',
                    'affiliate_url'   => $row['affiliateURL'] ?? '',
                    'image_list'      => $row['imageURL']['list'] ?? '',
                    'image_small'     => $row['imageURL']['small'] ?? '',
                    'image_large'     => $row['imageURL']['large'] ?? '',
                    'date_published'  => $row['date'] ?? null,
                    'service_code'    => $row['service_code'] ?? '',
                    'floor_code'      => $row['floor_code'] ?? '',
                    'category_name'   => $row['category_name'] ?? '',
                    'price_min'       => is_numeric($price) ? (int)$price : null,
                ]);

                $result['status'] === 'inserted' ? $inserted++ : $updated++;
            }

            $resultLog[] = sprintf('offset %d を処理しました。', $offset);
        }

        $resultLog[] = sprintf('追加 %d件 / 更新 %d件', $inserted, $updated);
    }
}

include __DIR__ . '/../partials/header.php';
?>
<main>
    <h1>作品インポート</h1>
    <div class="admin-card">
        <form method="post">
            <label>Hits (最大100)</label>
            <input type="number" name="hits" value="100">
            <label>Offset (開始)</label>
            <input type="number" name="offset" value="1">
            <label>ページ数</label>
            <input type="number" name="pages" value="1">
            <label>キーワード (任意)</label>
            <input type="text" name="keyword" value="">
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
