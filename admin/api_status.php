<?php

declare(strict_types=1);

require_once __DIR__ . '/../public/_bootstrap.php';
auth_require_admin();

$title = 'API取得・DB保存ステータス';
$pdo = db();

$credentialRows = [];
foreach (api_credential_types() as $type => $label) {
    $cred = api_credential_get($type);
    $credentialRows[] = [
        'type' => $type,
        'label' => $label,
        'api_id_set' => trim((string)($cred['api_id'] ?? '')) !== '',
        'affiliate_id_set' => trim((string)($cred['affiliate_id'] ?? '')) !== '',
    ];
}

$dbRows = [
    ['label' => '商品(items)', 'table' => 'items'],
    ['label' => 'ジャンル(genres)', 'table' => 'genres'],
    ['label' => '女優(actresses)', 'table' => 'actresses'],
    ['label' => 'シリーズ(series_master)', 'table' => 'series_master'],
    ['label' => '商品xジャンル(item_genres)', 'table' => 'item_genres'],
    ['label' => '商品x女優(item_actresses)', 'table' => 'item_actresses'],
    ['label' => '商品xシリーズ(item_series)', 'table' => 'item_series'],
];

foreach ($dbRows as &$row) {
    try {
        $row['count'] = (int)$pdo->query('SELECT COUNT(*) FROM ' . $row['table'])->fetchColumn();
        $row['ok'] = true;
    } catch (Throwable) {
        $row['count'] = 0;
        $row['ok'] = false;
    }
}
unset($row);

$apiLogRows = $pdo->query("SELECT api_name, response_status, cache_hit, created_at FROM api_logs ORDER BY id DESC LIMIT 60")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$lastApiLogByName = [];
foreach ($apiLogRows as $row) {
    $name = (string)($row['api_name'] ?? '');
    if ($name === '' || isset($lastApiLogByName[$name])) {
        continue;
    }
    $lastApiLogByName[$name] = $row;
}

$jobStates = $pdo->query("SELECT job_key, next_offset, last_success, last_message, last_run_at, lock_until FROM sync_job_state ORDER BY FIELD(job_key, 'items','genres','actresses','series')")->fetchAll(PDO::FETCH_ASSOC) ?: [];

require __DIR__ . '/includes/header.php';
?>
<section class="admin-card">
  <h1>API取得・DB保存ステータス</h1>
  <p class="admin-form-note">API資格情報の設定状況、API実行ログ、DB保存件数を1画面で確認できます。</p>
</section>

<section class="admin-card">
  <h2>API資格情報の設定状態</h2>
  <table class="admin-table">
    <tr><th>API</th><th>API ID</th><th>アフィリエイトID</th></tr>
    <?php foreach ($credentialRows as $row): ?>
      <tr>
        <td><?= e((string)$row['label']) ?></td>
        <td><?= $row['api_id_set'] ? '設定済み' : '未設定' ?></td>
        <td><?= $row['affiliate_id_set'] ? '設定済み' : '未設定' ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</section>

<section class="admin-card">
  <h2>API実行ログ（最新）</h2>
  <table class="admin-table">
    <tr><th>API名</th><th>HTTPステータス</th><th>キャッシュ</th><th>最終実行</th></tr>
    <?php foreach ($lastApiLogByName as $apiName => $row): ?>
      <tr>
        <td><?= e((string)$apiName) ?></td>
        <td><?= e((string)($row['response_status'] ?? '-')) ?></td>
        <td><?= ((int)($row['cache_hit'] ?? 0) === 1) ? 'HIT' : 'MISS' ?></td>
        <td><?= e((string)($row['created_at'] ?? '-')) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</section>

<section class="admin-card">
  <h2>DB保存件数</h2>
  <table class="admin-table">
    <tr><th>テーブル</th><th>状態</th><th>件数</th></tr>
    <?php foreach ($dbRows as $row): ?>
      <tr>
        <td><?= e((string)$row['label']) ?></td>
        <td><?= $row['ok'] ? 'OK' : '参照失敗' ?></td>
        <td><?= e((string)$row['count']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</section>

<section class="admin-card">
  <h2>タイマー同期状態</h2>
  <table class="admin-table">
    <tr><th>ジョブ</th><th>最終結果</th><th>最終実行</th><th>次回offset</th><th>ロック期限</th><th>メッセージ</th></tr>
    <?php foreach ($jobStates as $row): ?>
      <tr>
        <td><?= e((string)$row['job_key']) ?></td>
        <td><?= ((int)($row['last_success'] ?? 0) === 1) ? 'OK' : 'NG/未実行' ?></td>
        <td><?= e((string)($row['last_run_at'] ?? '-')) ?></td>
        <td><?= e((string)($row['next_offset'] ?? '1')) ?></td>
        <td><?= e((string)($row['lock_until'] ?? '-')) ?></td>
        <td><?= e((string)($row['last_message'] ?? '')) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
