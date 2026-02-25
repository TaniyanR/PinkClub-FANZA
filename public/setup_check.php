<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$dbConnected = db_can_connect();
$adminsTable = $dbConnected && db_table_exists('admins');
$settingsTable = $dbConnected && db_table_exists('settings');
$adminExists = false;

if ($adminsTable) {
    try {
        $stmt = db()->prepare('SELECT COUNT(*) FROM admins WHERE username = :username LIMIT 1');
        $stmt->execute(['username' => 'admin']);
        $adminExists = (int)$stmt->fetchColumn() > 0;
    } catch (Throwable) {
        $adminExists = false;
    }
}

$checks = [
    'DB接続' => $dbConnected,
    'admins テーブル' => $adminsTable,
    'settings テーブル' => $settingsTable,
    '初期管理者 admin' => $adminExists,
];
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e(APP_NAME) ?> セットアップ確認</title>
  <link rel="stylesheet" href="<?= e(asset_url('css/style.css')) ?>">
</head>
<body>
  <main class="setup-page">
    <section class="setup-card">
      <h1><?= e(APP_NAME) ?> セットアップ確認</h1>
      <table>
        <thead>
          <tr><th>項目</th><th>状態</th></tr>
        </thead>
        <tbody>
          <?php foreach ($checks as $label => $ok): ?>
            <tr>
              <td><?= e($label) ?></td>
              <td><?= $ok ? 'OK' : 'NG' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <div class="alert alert-warning">
        DB未初期化の場合は、phpMyAdminで <code>sql/schema.sql</code> → <code>sql/seed.sql</code> の順で実行してください。
      </div>

      <p><a href="<?= e(login_url()) ?>">ログイン画面へ戻る</a></p>
    </section>
  </main>
</body>
</html>
