<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/dmm_api.php';
require_once __DIR__ . '/../../lib/repository.php';
require_once __DIR__ . '/../../lib/site_settings.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$apiConfig = config_get('dmm_api', []);

$resultLog = [];
$errorLog  = [];

function import_items_redirect(): void
{
    header('Location: ' . admin_url('import_items.php'));
    exit;
}

function import_items_table_has_column(PDO $pdo, string $table, string $column): bool
{
    try {
        $stmt = $pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '`');
        if ($stmt === false) {
            return false;
        }

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (strtolower((string)($row['Field'] ?? '')) === strtolower($column)) {
                return true;
            }
        }
    } catch (Throwable $e) {
        error_log('import_items table inspection failed: ' . $e->getMessage());
    }

    return false;
}

function api_base_params(array $apiConfig): array
{
    return [
        'api_id' => (string)($apiConfig['api_id'] ?? ''),
        'affiliate_id' => (string)($apiConfig['affiliate_id'] ?? ''),
        'site' => (string)($apiConfig['site'] ?? ''),
        'service' => (string)($apiConfig['service'] ?? ''),
        'floor' => (string)($apiConfig['floor'] ?? ''),
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

/**
 * DMM APIの iteminfo は「単体object」or「list」になり得るので正規化
 */
function normalize_iteminfo_list(mixed $value): array
{
    if (!is_array($value)) {
        return [];
    }

    // 連想配列なら単体として扱う
    $keys = array_keys($value);
    $isList = $keys === array_keys($keys);
    if (!$isList) {
        return [$value];
    }

    return $value;
}

function normalize_actress_payload(array $actress): array
{
    $listUrl = $actress['list_url'] ?? ($actress['listurl'] ?? []);
    return [
        'id' => (int)($actress['id'] ?? 0),
        'name' => (string)($actress['name'] ?? ''),
        'ruby' => $actress['ruby'] ?? null,
        'bust' => null,
        'cup' => null,
        'waist' => null,
        'hip' => null,
        'height' => null,
        'birthday' => null,
        'blood_type' => null,
        'hobby' => null,
        'prefectures' => null,
        'image_small' => $actress['imageURL']['small'] ?? ($actress['image_small'] ?? null),
        'image_large' => $actress['imageURL']['large'] ?? ($actress['image_large'] ?? null),
        'listurl_digital' => is_array($listUrl) ? ($listUrl['digital'] ?? null) : null,
        'listurl_monthly' => is_array($listUrl) ? ($listUrl['monthly'] ?? null) : null,
        'listurl_mono' => is_array($listUrl) ? ($listUrl['mono'] ?? null) : null,
    ];
}

function normalize_taxonomy_payload(array $taxonomy): array
{
    return [
        'id' => (int)($taxonomy['id'] ?? 0),
        'name' => (string)($taxonomy['name'] ?? ''),
        'ruby' => $taxonomy['ruby'] ?? null,
        'list_url' => $taxonomy['list_url'] ?? ($taxonomy['listurl'] ?? null),
        'site_code' => $taxonomy['site_code'] ?? null,
        'service_code' => $taxonomy['service_code'] ?? null,
        'floor_id' => $taxonomy['floor_id'] ?? null,
        'floor_code' => $taxonomy['floor_code'] ?? null,
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_post_csrf_valid()) {
        admin_flash_set('import_items_error', '不正なリクエストです。');
        import_items_redirect();
    } else {
        try {
            $missing = validate_api_config($apiConfig);
            if ($missing) {
                admin_flash_set('import_items_error', 'API設定が未設定です。設定画面から API ID / アフィリエイトID を設定してください。');
                import_items_redirect();
            }

            $hits        = (int)($_POST['hits'] ?? 100);
            $startOffset = (int)($_POST['offset'] ?? 1);
            $maxPages    = (int)($_POST['pages'] ?? 1);
            $keyword     = trim((string)($_POST['keyword'] ?? ''));

            // バリデーション（最低限）
            $hits = min(100, max(1, $hits));
            $startOffset = max(1, $startOffset);
            $maxPages = max(1, min(200, $maxPages)); // 暴走防止（必要なら後で上限変更）

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

                // Log API request to api_logs table
                try {
                    $pdo = db();
                    $isSuccess = $response['ok'] ?? false;
                    $itemCount = 0;

                    if ($isSuccess && isset($response['data']['result']['items'])) {
                        $items = $response['data']['result']['items'];
                        $itemCount = is_array($items) ? count($items) : 0;
                    }

                    $stmt = $pdo->prepare(
                        'INSERT INTO api_logs (
                            created_at, endpoint, params_json, status, http_code,
                            item_count, error_message, success
                         ) VALUES (
                            NOW(), :endpoint, :params_json, :status, :http_code,
                            :item_count, :error_message, :success
                         )'
                    );

                    $stmt->execute([
                        ':endpoint' => 'ItemList',
                        ':params_json' => json_encode($params, JSON_UNESCAPED_UNICODE),
                        ':status' => $isSuccess ? 'success' : 'error',
                        ':http_code' => $response['http_code'] ?? 0,
                        ':item_count' => $itemCount,
                        ':error_message' => $response['error'] ?? null,
                        ':success' => $isSuccess ? 1 : 0,
                    ]);
                } catch (PDOException $e) {
                    error_log('Failed to log API request: ' . $e->getMessage());
                    // Don't fail the import if logging fails
                }

                if (!($response['ok'] ?? false)) {
                    error_log('import_items api failed: ' . json_encode([
                        'http_code' => (int)($response['http_code'] ?? 0),
                        'error' => (string)($response['error'] ?? 'unknown'),
                        'endpoint' => 'ItemList',
                    ], JSON_UNESCAPED_UNICODE));
                    $errorLog[] = sprintf('APIエラー: HTTP %d %s', (int)($response['http_code'] ?? 0), (string)($response['error'] ?? 'unknown'));
                    break;
                }

                $data = $response['data']['result'] ?? [];
                if (($data['status'] ?? '') !== '200') {
                    $errorLog[] = sprintf('APIステータスエラー: %s', (string)($data['status'] ?? 'unknown'));
                    break;
                }

                $list = $data['items'] ?? [];
                if (!is_array($list)) {
                    $list = [];
                }

                foreach ($list as $row) {
                    if (!is_array($row)) {
                        continue;
                    }

                    $contentId = (string)($row['content_id'] ?? '');
                    if ($contentId === '') {
                        continue;
                    }

                    $pdo = db();
                    $pdo->beginTransaction();
                    try {
                        $price = $row['prices']['price'] ?? ($row['price'] ?? null);

                        $result = upsert_item([
                            'content_id' => $contentId,
                            'product_id' => (string)($row['product_id'] ?? ''),
                            'title' => (string)($row['title'] ?? ''),
                            'url' => (string)($row['URL'] ?? ''),
                            'affiliate_url' => (string)($row['affiliateURL'] ?? ''),
                            'image_list' => (string)($row['imageURL']['list'] ?? ''),
                            'image_small' => (string)($row['imageURL']['small'] ?? ''),
                            'image_large' => (string)($row['imageURL']['large'] ?? ''),
                            'date_published' => $row['date'] ?? null,
                            'service_code' => (string)($row['service_code'] ?? ''),
                            'floor_code' => (string)($row['floor_code'] ?? ''),
                            'category_name' => (string)($row['category_name'] ?? ''),
                            'price_min' => is_numeric($price) ? (int)$price : null,
                        ]);

                        // ---- 以下は phase2（存在すれば反映）: 無ければスキップしてもimport自体は動く ----
                        if (
                            function_exists('upsert_actress')
                            && function_exists('upsert_taxonomy')
                            && function_exists('replace_item_relations')
                            && function_exists('replace_item_labels')
                        ) {
                            $itemInfo = $row['iteminfo'] ?? [];
                            if (!is_array($itemInfo)) {
                                $itemInfo = [];
                            }

                            $actressIds = [];
                            foreach (normalize_iteminfo_list($itemInfo['actress'] ?? []) as $actress) {
                                if (!is_array($actress)) {
                                    continue;
                                }
                                $payload = normalize_actress_payload($actress);
                                if (($payload['id'] ?? 0) > 0 && ($payload['name'] ?? '') !== '') {
                                    upsert_actress($payload);
                                    $actressIds[] = (int)$payload['id'];
                                }
                            }

                            $genreIds = [];
                            foreach (normalize_iteminfo_list($itemInfo['genre'] ?? []) as $genre) {
                                if (!is_array($genre)) {
                                    continue;
                                }
                                $payload = normalize_taxonomy_payload($genre);
                                if (($payload['id'] ?? 0) > 0 && ($payload['name'] ?? '') !== '') {
                                    upsert_taxonomy('genres', 'id', $payload);
                                    $genreIds[] = (int)$payload['id'];
                                }
                            }

                            $makerIds = [];
                            foreach (normalize_iteminfo_list($itemInfo['maker'] ?? []) as $maker) {
                                if (!is_array($maker)) {
                                    continue;
                                }
                                $payload = normalize_taxonomy_payload($maker);
                                if (($payload['id'] ?? 0) > 0 && ($payload['name'] ?? '') !== '') {
                                    upsert_taxonomy('makers', 'id', $payload);
                                    $makerIds[] = (int)$payload['id'];
                                }
                            }

                            $seriesIds = [];
                            foreach (normalize_iteminfo_list($itemInfo['series'] ?? []) as $series) {
                                if (!is_array($series)) {
                                    continue;
                                }
                                $payload = normalize_taxonomy_payload($series);
                                if (($payload['id'] ?? 0) > 0 && ($payload['name'] ?? '') !== '') {
                                    upsert_taxonomy('series', 'id', $payload);
                                    $seriesIds[] = (int)$payload['id'];
                                }
                            }

                            $labels = [];
                            foreach (normalize_iteminfo_list($itemInfo['label'] ?? []) as $label) {
                                if (!is_array($label)) {
                                    continue;
                                }
                                $labelName = (string)($label['name'] ?? '');
                                if ($labelName === '') {
                                    continue;
                                }
                                $labels[] = [
                                    'id' => isset($label['id']) ? (int)$label['id'] : null,
                                    'name' => $labelName,
                                    'ruby' => $label['ruby'] ?? null,
                                ];
                            }

                            replace_item_relations($contentId, $actressIds, 'item_actresses', 'actress_id');
                            replace_item_relations($contentId, $genreIds, 'item_genres', 'genre_id');
                            replace_item_relations($contentId, $makerIds, 'item_makers', 'maker_id');
                            replace_item_relations($contentId, $seriesIds, 'item_series', 'series_id');
                            replace_item_labels($contentId, $labels);
                        }

                        $pdo->commit();

                        if (($result['status'] ?? '') === 'inserted') {
                            $inserted++;
                        } else {
                            $updated++;
                        }
                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        log_message('import_items failed: ' . $contentId . ' ' . $e->getMessage());
                        $errorLog[] = sprintf('content_id %s の処理に失敗しました。', $contentId);
                    }
                }

                $resultLog[] = sprintf('offset %d を処理しました。', $offset);

                // 返却が空なら以降も空の可能性が高いので打ち切り（無限ループ防止）
                if (count($list) === 0) {
                    $resultLog[] = '取得件数が0件のため終了しました。';
                    break;
                }
            }

            $resultLog[] = sprintf('追加 %d件 / 更新 %d件', $inserted, $updated);

            try {
                site_setting_set('admin.api_last_run_at', date('Y-m-d H:i:s'));
            } catch (Throwable $e) {
                error_log('import_items save last run failed: ' . $e->getMessage());
            }

            if ($resultLog !== []) {
                admin_flash_set('import_items_ok', implode("\n", $resultLog));
            }
            if ($errorLog !== []) {
                admin_flash_set('import_items_error', implode("\n", $errorLog));
            }

            import_items_redirect();
        } catch (Throwable $e) {
            error_log('import_items unexpected error: ' . $e->getMessage());
            admin_flash_set('import_items_error', '取得に失敗しました。時間をおいて再度実行してください。');
            import_items_redirect();
        }
    }

    if ($errorLog !== []) {
        admin_flash_set('import_items_error', implode("\n", $errorLog));
        import_items_redirect();
    }
}

$flashOkRaw = admin_flash_get('import_items_ok');
$flashErrorRaw = admin_flash_get('import_items_error');
$flashOk = $flashOkRaw !== '' ? explode("\n", $flashOkRaw) : [];
$flashError = $flashErrorRaw !== '' ? explode("\n", $flashErrorRaw) : [];

$recentItems = [];
$listError = '';
$lastImportLabel = '未実施';

try {
    $pdo = db();

    $columns = ['id', 'content_id', 'product_id', 'title', 'image_small', 'image_list', 'updated_at', 'created_at'];
    $hasStatusColumn = import_items_table_has_column($pdo, 'items', 'status');
    if ($hasStatusColumn) {
        $columns[] = 'status';
    }

    $stmt = $pdo->query(
        'SELECT ' . implode(', ', $columns) . ' FROM items ORDER BY id DESC LIMIT 50'
    );
    if ($stmt !== false) {
        $recentItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    error_log('import_items list fetch failed: ' . $e->getMessage());
    $listError = '一覧データの取得に失敗しました。';
}

try {
    $pdo = db();

    if (admin_table_exists('api_logs')) {
        $stmt = $pdo->query("SELECT MAX(created_at) FROM api_logs WHERE status IN ('success', 'ok', 'SUCCESS') OR success = 1");
        if ($stmt !== false) {
            $value = (string)$stmt->fetchColumn();
            if ($value !== '') {
                $lastImportLabel = $value;
            }
        }
    }

    if ($lastImportLabel === '未実施') {
        $stmt = $pdo->query('SELECT MAX(updated_at) FROM items');
        if ($stmt !== false) {
            $value = (string)$stmt->fetchColumn();
            if ($value !== '') {
                $lastImportLabel = $value;
            }
        }
    }
} catch (Throwable $e) {
    error_log('import_items last import fetch failed: ' . $e->getMessage());
}

$lastRunAt = site_setting_get('admin.api_last_run_at', '');

$pageTitle = 'インポート';
ob_start();
?>
    <h1>作品インポート</h1>

    <?php if ($flashOk !== []) : ?>
        <div class="admin-card" style="background:#e7f5e7;padding:12px;margin-bottom:16px;">
            <h2 style="margin-top:0;">直近のインポート結果</h2>
            <ul>
                <?php foreach ($flashOk as $line) : ?>
                    <li><?php echo e((string)$line); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($flashError !== []) : ?>
        <div class="admin-card" style="background:#ffe7e7;padding:12px;margin-bottom:16px;">
            <h2 style="margin-top:0;">インポートエラー</h2>
            <ul>
                <?php foreach ($flashError as $line) : ?>
                    <li><?php echo e((string)$line); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="admin-card">
        <p>最終実行日時: <?php echo e($lastRunAt !== '' ? $lastRunAt : '未実行'); ?></p>
    </div>

    <div class="admin-card">
        <p>最終インポート日時: <?php echo e($lastImportLabel); ?></p>
        <form method="post">
            <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">

            <label>Hits (最大100)</label>
            <input type="number" name="hits" value="100" min="1" max="100">

            <label>Offset (開始)</label>
            <input type="number" name="offset" value="1" min="1">

            <label>ページ数</label>
            <input type="number" name="pages" value="1" min="1" max="200">

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
                    <li><?php echo e((string)$line); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($errorLog) : ?>
        <div class="admin-card">
            <h2>エラー</h2>
            <ul>
                <?php foreach ($errorLog as $line) : ?>
                    <li><?php echo e((string)$line); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="admin-card">
        <h2>取得済みデータ一覧（新しい順・最大50件）</h2>

        <?php if ($listError !== '') : ?>
            <p style="color:#c00;"><?php echo e($listError); ?></p>
        <?php elseif ($recentItems === []) : ?>
            <p>データなし</p>
        <?php else : ?>
            <table style="width:100%;border-collapse:collapse;">
                <thead>
                <tr style="background:#f5f5f5;">
                    <th style="border:1px solid #ddd;padding:8px;text-align:left;">ID</th>
                    <th style="border:1px solid #ddd;padding:8px;text-align:left;">タイトル</th>
                    <th style="border:1px solid #ddd;padding:8px;text-align:left;">商品ID / コンテンツID</th>
                    <th style="border:1px solid #ddd;padding:8px;text-align:left;">更新日時</th>
                    <th style="border:1px solid #ddd;padding:8px;text-align:left;">状態</th>
                    <th style="border:1px solid #ddd;padding:8px;text-align:left;">画像</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($recentItems as $row) : ?>
                    <?php
                    $id = (int)($row['id'] ?? 0);
                    $title = trim((string)($row['title'] ?? ''));
                    $titleLabel = $title !== '' ? mb_strimwidth($title, 0, 80, '...') : '(タイトルなし)';
                    $productId = trim((string)($row['product_id'] ?? ''));
                    $contentId = trim((string)($row['content_id'] ?? ''));
                    $productOrContentId = $productId !== '' ? $productId : ($contentId !== '' ? $contentId : '-');
                    $updatedAt = (string)($row['updated_at'] ?? '');
                    $createdAt = (string)($row['created_at'] ?? '');
                    $dateLabel = $updatedAt !== '' ? $updatedAt : ($createdAt !== '' ? $createdAt : '-');
                    $status = trim((string)($row['status'] ?? ''));
                    $statusLabel = $status !== '' ? $status : '-';
                    $imageUrl = trim((string)($row['image_small'] ?? ''));
                    if ($imageUrl === '') {
                        $imageUrl = trim((string)($row['image_list'] ?? ''));
                    }
                    ?>
                    <tr>
                        <td style="border:1px solid #ddd;padding:8px;"><?php echo e((string)$id); ?></td>
                        <td style="border:1px solid #ddd;padding:8px;"><?php echo e($titleLabel); ?></td>
                        <td style="border:1px solid #ddd;padding:8px;"><?php echo e($productOrContentId); ?></td>
                        <td style="border:1px solid #ddd;padding:8px;"><?php echo e($dateLabel); ?></td>
                        <td style="border:1px solid #ddd;padding:8px;"><?php echo e($statusLabel); ?></td>
                        <td style="border:1px solid #ddd;padding:8px;">
                            <?php if ($imageUrl !== '') : ?>
                                <img src="<?php echo e($imageUrl); ?>" alt="thumb" style="max-width:60px;height:auto;display:block;">
                            <?php else : ?>
                                なし
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
<?php
$content = (string)ob_get_clean();
include __DIR__ . '/../partials/admin_layout.php';