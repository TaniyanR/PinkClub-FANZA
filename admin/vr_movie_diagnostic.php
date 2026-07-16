<?php

declare(strict_types=1);

require_once __DIR__ . '/../public/_bootstrap.php';
require_once __DIR__ . '/../lib/app.php';
require_once __DIR__ . '/../lib/dmm_normalizer.php';

auth_require_admin();
$title = 'VRサンプル動画診断';
$pdo = db();

function vr_diag_collect(mixed $value, string $path = 'root', array &$rows = []): array
{
    if (count($rows) >= 300) {
        return $rows;
    }

    if (is_array($value)) {
        foreach ($value as $key => $child) {
            $childPath = $path . '.' . (string)$key;
            $keyText = strtolower((string)$key);
            if (preg_match('/movie|video|sample|player|stream|vr|url/', $keyText) === 1) {
                $display = is_scalar($child) || $child === null
                    ? (string)$child
                    : json_encode($child, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $rows[] = [
                    'path' => $childPath,
                    'type' => get_debug_type($child),
                    'value' => mb_strimwidth((string)$display, 0, 1200, '…', 'UTF-8'),
                ];
            }
            vr_diag_collect($child, $childPath, $rows);
            if (count($rows) >= 300) {
                break;
            }
        }
    }

    return $rows;
}

$itemId = max(0, (int)($_GET['id'] ?? $_POST['id'] ?? 0));
$item = null;
$dbRows = [];
$rawRows = [];
$apiRows = [];
$apiMessage = '';

if ($itemId > 0) {
    $stmt = $pdo->prepare('SELECT id, content_id, title, service_code, service_name, floor_code, floor_name, sample_movie_url_476, sample_movie_url_560, sample_movie_url_644, sample_movie_url_720, sample_movie_pc_flag, sample_movie_sp_flag, raw_json FROM items WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $itemId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

if (is_array($item)) {
    foreach (['sample_movie_url_476', 'sample_movie_url_560', 'sample_movie_url_644', 'sample_movie_url_720', 'sample_movie_pc_flag', 'sample_movie_sp_flag'] as $column) {
        $dbRows[] = ['path' => 'items.' . $column, 'type' => get_debug_type($item[$column] ?? null), 'value' => (string)($item[$column] ?? '')];
    }

    $raw = json_decode((string)($item['raw_json'] ?? ''), true);
    if (is_array($raw)) {
        vr_diag_collect($raw, 'raw_json', $rawRows);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_validate_or_fail((string)post('_csrf', ''));
        try {
            $settings = settings_get();
            $client = dmm_client_for_type('items');
            $service = trim((string)($item['service_code'] ?? ''));
            $floor = trim((string)($item['floor_code'] ?? ''));
            if ($service === '') {
                $service = (string)($settings['service'] ?? 'digital');
            }
            if ($floor === '') {
                $floor = (string)($settings['floor'] ?? 'videoa');
            }
            $response = $client->fetchItems(
                (string)($settings['site'] ?? 'FANZA'),
                $service,
                $floor,
                ['hits' => 100, 'keyword' => (string)($item['content_id'] ?? '')]
            );
            vr_diag_collect($response, 'api_response', $apiRows);
            $apiMessage = 'API応答を取得しました。service=' . $service . ' / floor=' . $floor;
        } catch (Throwable $e) {
            $apiMessage = 'API取得エラー: ' . $e->getMessage();
        }
    }
}

require __DIR__ . '/includes/header.php';
?>
<section class="card">
  <h1>VRサンプル動画診断</h1>
  <p>管理者専用です。DB・保存済みraw_json・FANZA商品API応答の動画関連キーを比較します。データの更新は行いません。</p>

  <form method="get" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;">
    <label>商品ID<br><input type="number" name="id" min="1" value="<?= e((string)$itemId) ?>" required></label>
    <button type="submit">DBを確認</button>
  </form>

  <?php if ($itemId > 0 && $item === null): ?>
    <div class="admin-notice admin-notice--error"><p>商品が見つかりません。</p></div>
  <?php endif; ?>

  <?php if (is_array($item)): ?>
    <h2><?= e((string)$item['title']) ?></h2>
    <p>content_id: <?= e((string)$item['content_id']) ?><br>
       service: <?= e((string)$item['service_code']) ?>（<?= e((string)$item['service_name']) ?>）<br>
       floor: <?= e((string)$item['floor_code']) ?>（<?= e((string)$item['floor_name']) ?>）</p>

    <form method="post" style="margin:12px 0;">
      <?= csrf_input() ?>
      <input type="hidden" name="id" value="<?= e((string)$itemId) ?>">
      <button type="submit">同じ商品をAPIへ照会</button>
    </form>

    <?php if ($apiMessage !== ''): ?><p><strong><?= e($apiMessage) ?></strong></p><?php endif; ?>

    <?php foreach ([
      'DBの動画カラム' => $dbRows,
      '保存済みraw_jsonの動画関連キー' => $rawRows,
      'API応答の動画関連キー' => $apiRows,
    ] as $heading => $rows): ?>
      <h2><?= e($heading) ?></h2>
      <?php if ($rows === []): ?>
        <p>該当データはありません。</p>
      <?php else: ?>
        <div style="overflow:auto;max-height:520px;border:1px solid #ddd;">
          <table class="admin-table" style="margin:0;">
            <tr><th>パス</th><th>型</th><th>値</th></tr>
            <?php foreach ($rows as $row): ?>
              <tr>
                <td><?= e((string)$row['path']) ?></td>
                <td><?= e((string)$row['type']) ?></td>
                <td style="word-break:break-all;white-space:pre-wrap;"><?= e((string)$row['value']) ?></td>
              </tr>
            <?php endforeach; ?>
          </table>
        </div>
      <?php endif; ?>
    <?php endforeach; ?>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
