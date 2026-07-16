<?php

declare(strict_types=1);

require_once __DIR__ . '/../public/_bootstrap.php';
require_once __DIR__ . '/../lib/app.php';
require_once __DIR__ . '/../lib/dmm_normalizer.php';

auth_require_admin();
$title = 'サンプル動画再取得';
$message = '';
$messageType = 'success';
$pdo = db();

$pdo->exec('CREATE TABLE IF NOT EXISTS movie_repair_status (
    item_id INT UNSIGNED NOT NULL PRIMARY KEY,
    content_id VARCHAR(100) NOT NULL,
    status VARCHAR(32) NOT NULL,
    message VARCHAR(255) NULL,
    checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_movie_repair_status_checked (status, checked_at),
    CONSTRAINT fk_movie_repair_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

try {
    $pdo->exec('DROP TRIGGER IF EXISTS trg_items_preserve_sample_movie_urls');
    $pdo->exec('CREATE TRIGGER trg_items_preserve_sample_movie_urls BEFORE UPDATE ON items FOR EACH ROW SET
        NEW.sample_movie_url_476 = COALESCE(NULLIF(TRIM(NEW.sample_movie_url_476), ""), OLD.sample_movie_url_476),
        NEW.sample_movie_url_560 = COALESCE(NULLIF(TRIM(NEW.sample_movie_url_560), ""), OLD.sample_movie_url_560),
        NEW.sample_movie_url_644 = COALESCE(NULLIF(TRIM(NEW.sample_movie_url_644), ""), OLD.sample_movie_url_644),
        NEW.sample_movie_url_720 = COALESCE(NULLIF(TRIM(NEW.sample_movie_url_720), ""), OLD.sample_movie_url_720)');
} catch (Throwable $e) {
    error_log('sample movie preservation trigger setup failed: ' . $e->getMessage());
}

$missingWhere = '(COALESCE(sample_movie_url_476, "") = "" AND COALESCE(sample_movie_url_560, "") = "" AND COALESCE(sample_movie_url_644, "") = "" AND COALESCE(sample_movie_url_720, "") = "")';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_fail((string)post('_csrf', ''));
    $action = (string)post('action', '');

    if ($action === 'repair') {
        $limit = max(1, min(20, (int)post('limit', 10)));
        $settings = settings_get();
        $client = dmm_client_for_type('items');
        $stmt = $pdo->prepare(
            'SELECT i.id, i.content_id, i.raw_json, i.service_code, i.floor_code
             FROM items i
             LEFT JOIN movie_repair_status mrs ON mrs.item_id = i.id
             WHERE ' . $missingWhere . '
             ORDER BY CASE WHEN mrs.checked_at IS NULL THEN 0 ELSE 1 END, mrs.checked_at ASC, i.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $targets = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $checked = 0;
        $restored = 0;
        $notProvided = 0;
        $errors = 0;

        foreach ($targets as $target) {
            $checked++;
            $itemId = (int)($target['id'] ?? 0);
            $contentId = trim((string)($target['content_id'] ?? ''));
            if ($itemId <= 0 || $contentId === '') {
                continue;
            }

            try {
                $siteCode = trim((string)($settings['site'] ?? 'FANZA'));
                $targetService = trim((string)($target['service_code'] ?? ''));
                $targetFloor = trim((string)($target['floor_code'] ?? ''));
                $defaultService = trim((string)($settings['service'] ?? ''));
                $defaultFloor = trim((string)($settings['floor'] ?? ''));

                $scopes = [];
                foreach ([[$targetService, $targetFloor], [$defaultService, $defaultFloor]] as $scope) {
                    [$serviceCode, $floorCode] = $scope;
                    if ($serviceCode === '' || $floorCode === '') {
                        continue;
                    }
                    $scopeKey = $serviceCode . '|' . $floorCode;
                    $scopes[$scopeKey] = [$serviceCode, $floorCode];
                }

                $matched = null;
                $matchedScope = '';
                foreach ($scopes as [$serviceCode, $floorCode]) {
                    $response = $client->fetchItems(
                        $siteCode,
                        $serviceCode,
                        $floorCode,
                        ['hits' => 100, 'keyword' => $contentId]
                    );
                    $rows = DmmNormalizer::normalizeItemsResponse($response);
                    foreach ($rows as $row) {
                        if (strcasecmp(trim((string)($row['content_id'] ?? '')), $contentId) === 0) {
                            $matched = $row;
                            $matchedScope = $serviceCode . '/' . $floorCode;
                            break 2;
                        }
                    }
                }

                $urls = $matched === null ? [] : [
                    trim((string)($matched['sample_movie_url_476'] ?? '')),
                    trim((string)($matched['sample_movie_url_560'] ?? '')),
                    trim((string)($matched['sample_movie_url_644'] ?? '')),
                    trim((string)($matched['sample_movie_url_720'] ?? '')),
                ];
                $hasMovie = count(array_filter($urls, static fn(string $url): bool => $url !== '')) > 0;

                if ($matched !== null && $hasMovie) {
                    $raw = json_decode((string)($target['raw_json'] ?? ''), true);
                    if (!is_array($raw)) {
                        $raw = [];
                    }
                    $newRaw = is_array($matched['raw'] ?? null) ? $matched['raw'] : [];
                    foreach (['sampleMovieURL', 'sampleMovieUrl', 'sample_movie_url', 'sampleMovie', 'sampleMovieURLVR'] as $movieKey) {
                        if (array_key_exists($movieKey, $newRaw)) {
                            $raw[$movieKey] = $newRaw[$movieKey];
                        }
                    }
                    $update = $pdo->prepare(
                        'UPDATE items SET
                            sample_movie_url_476 = NULLIF(:u476, ""),
                            sample_movie_url_560 = NULLIF(:u560, ""),
                            sample_movie_url_644 = NULLIF(:u644, ""),
                            sample_movie_url_720 = NULLIF(:u720, ""),
                            sample_movie_pc_flag = :pc,
                            sample_movie_sp_flag = :sp,
                            raw_json = :raw_json,
                            updated_at = NOW()
                         WHERE id = :id'
                    );
                    $update->execute([
                        ':u476' => $urls[0],
                        ':u560' => $urls[1],
                        ':u644' => $urls[2],
                        ':u720' => $urls[3],
                        ':pc' => (int)($matched['sample_movie_pc_flag'] ?? 0),
                        ':sp' => (int)($matched['sample_movie_sp_flag'] ?? 0),
                        ':raw_json' => json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        ':id' => $itemId,
                    ]);
                    $status = 'restored';
                    $statusMessage = '動画URLを復元しました。照会先: ' . $matchedScope;
                    $restored++;
                } else {
                    $status = 'not_provided';
                    $statusMessage = $matched === null
                        ? '商品のservice/floorを含むAPI応答に対象商品がありません。'
                        : '対象商品のAPI応答に動画URLがありません。';
                    $notProvided++;
                }

                $statusStmt = $pdo->prepare('INSERT INTO movie_repair_status(item_id, content_id, status, message, checked_at) VALUES(?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE content_id=VALUES(content_id), status=VALUES(status), message=VALUES(message), checked_at=NOW()');
                $statusStmt->execute([$itemId, $contentId, $status, $statusMessage]);
            } catch (Throwable $e) {
                $errors++;
                $statusStmt = $pdo->prepare('INSERT INTO movie_repair_status(item_id, content_id, status, message, checked_at) VALUES(?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE content_id=VALUES(content_id), status=VALUES(status), message=VALUES(message), checked_at=NOW()');
                $statusStmt->execute([$itemId, $contentId, 'error', mb_substr($e->getMessage(), 0, 255)]);
            }
        }

        $message = '再取得を実行しました。確認: ' . $checked . '件 / 復元: ' . $restored . '件 / API未提供: ' . $notProvided . '件 / エラー: ' . $errors . '件';
        $messageType = $errors > 0 ? 'error' : 'success';
    }
}

$missingCount = (int)$pdo->query('SELECT COUNT(*) FROM items WHERE ' . $missingWhere)->fetchColumn();
$restoredCount = (int)$pdo->query('SELECT COUNT(*) FROM movie_repair_status WHERE status = "restored"')->fetchColumn();
$notProvidedCount = (int)$pdo->query('SELECT COUNT(*) FROM movie_repair_status WHERE status = "not_provided"')->fetchColumn();
$errorCount = (int)$pdo->query('SELECT COUNT(*) FROM movie_repair_status WHERE status = "error"')->fetchColumn();
$recent = $pdo->query('SELECT mrs.content_id, mrs.status, mrs.message, mrs.checked_at, i.title FROM movie_repair_status mrs INNER JOIN items i ON i.id = mrs.item_id ORDER BY mrs.checked_at DESC LIMIT 50')->fetchAll(PDO::FETCH_ASSOC) ?: [];

require __DIR__ . '/includes/header.php';
?>
<section class="card">
  <h1>サンプル動画再取得</h1>
  <p>動画URLが保存されていない商品を、その商品のservice・floorでFANZA APIへ個別照会し、取得できた動画URLだけを補完します。</p>

  <?php if ($message !== ''): ?>
    <div class="admin-notice <?= $messageType === 'success' ? 'admin-notice--success' : 'admin-notice--error' ?>"><p><?= e($message) ?></p></div>
  <?php endif; ?>

  <div style="display:flex;gap:12px;flex-wrap:wrap;margin:16px 0;">
    <strong>動画無し: <?= e((string)$missingCount) ?>件</strong>
    <span>復元済み: <?= e((string)$restoredCount) ?>件</span>
    <span>API未提供: <?= e((string)$notProvidedCount) ?>件</span>
    <span>エラー: <?= e((string)$errorCount) ?>件</span>
  </div>

  <form method="post" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;">
    <?= csrf_input() ?>
    <input type="hidden" name="action" value="repair">
    <label>1回の確認件数<br>
      <select name="limit">
        <option value="5">5件</option>
        <option value="10" selected>10件</option>
        <option value="20">20件</option>
      </select>
    </label>
    <button type="submit">動画情報を再取得</button>
  </form>

  <p style="margin-top:12px;">FANZA本体に動画があっても、商品APIが動画URLを返さない商品は「API未提供」と表示されます。</p>

  <h2>最近の確認結果</h2>
  <table class="admin-table">
    <tr><th>商品</th><th>結果</th><th>内容</th><th>確認日時</th></tr>
    <?php foreach ($recent as $row): ?>
      <tr>
        <td><a href="<?= e(public_url('item.php?cid=' . rawurlencode((string)$row['content_id']))) ?>" target="_blank" rel="noopener noreferrer"><?= e((string)$row['title']) ?></a></td>
        <td><?= e((string)$row['status']) ?></td>
        <td><?= e((string)$row['message']) ?></td>
        <td><?= e((string)$row['checked_at']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
