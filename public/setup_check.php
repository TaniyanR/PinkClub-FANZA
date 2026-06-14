<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/local_config_writer.php';

$dbConfigError = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)post('action', '') === 'save_db_config') {
    csrf_validate_or_fail(post('_csrf'));
    $host = trim((string)post('db_host', ''));
    $port = (int)post('db_port', 3306);
    $dbname = trim((string)post('db_name', ''));
    $user = trim((string)post('db_user', ''));
    $pass = (string)post('db_pass', '');
    if ($host === '' || $port <= 0 || $dbname === '' || $user === '') {
        $dbConfigError = 'DBホスト名、DBポート、データベース、ユーザー名を入力してください。';
    } else {
        try {
            $local = local_config_load();
            if ($pass === '' && isset($local['db']['pass'])) {
                $pass = (string)$local['db']['pass'];
            }
            $testDsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $dbname);
            new PDO($testDsn, $user, $pass, db_options());
            $local['db'] = [
                'host' => $host,
                'port' => $port,
                'dbname' => $dbname,
                'user' => $user,
                'pass' => $pass,
                'charset' => 'utf8mb4',
            ];
            local_config_write($local);
            app_redirect('/public/setup_check.php');
        } catch (Throwable $exception) {
            $dbConfigError = 'DB接続テストに失敗しました。シンサーバーのMySQLホスト名、データベース名、ユーザー名、パスワードを確認してください。あわせて、サーバーパネルのMySQL設定で対象データベースにこのMySQLユーザーを追加済みか確認してください。';
        }
    }
}

$currentDbConfig = app_config()['db'] ?? [];
$status = installer_status();
if (($status['completed'] ?? false) === true) {
    app_redirect(LOGIN_PATH);
}

$checks = [
    'MySQLサーバー接続' => $status['server_connection'] ?? false,
    '対象DB接続' => $status['db_connection'] ?? false,
    'admins テーブル' => $status['admins_table'] ?? false,
    'settings テーブル' => $status['settings_table'] ?? false,
    '初期管理者 admin' => $status['admin_user'] ?? false,
    'settings(installer.ready=1)' => $status['settings_row'] ?? false,
];

$errorSummary = installer_last_error_summary();
$logTail = installer_log_tail(30);
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
      <div class="alert alert-warning">セットアップ失敗時の診断ページです。再実行は <code>login0718.php</code> へアクセスしてください。</div>


      <h2>DB接続設定</h2>
      <div class="alert alert-warning">シンサーバーのサーバーパネルに表示されるMySQL情報を入力してください。接続テストに成功した場合のみ保存します。</div>
      <?php if ($dbConfigError !== null): ?>
        <div class="alert alert-error"><?= e($dbConfigError) ?></div>
      <?php endif; ?>
      <form method="post">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="save_db_config">
        <table><tbody>
          <tr><th>DBホスト名</th><td><input name="db_host" value="<?= e((string)($currentDbConfig['host'] ?? '')) ?>" required></td></tr>
          <tr><th>DBポート</th><td><input name="db_port" type="number" value="<?= e((string)($currentDbConfig['port'] ?? 3306)) ?>" required></td></tr>
          <tr><th>データベース</th><td><input name="db_name" value="<?= e((string)($currentDbConfig['dbname'] ?? '')) ?>" required></td></tr>
          <tr><th>ユーザー名</th><td><input name="db_user" value="<?= e((string)($currentDbConfig['user'] ?? '')) ?>" required><br><small>サーバーパネルのMySQL設定で、このユーザーを対象データベースに追加してください。</small></td></tr>
          <tr><th>パスワード</th><td><input name="db_pass" type="password" value="" autocomplete="new-password"><br><small>保存済みの場合、空欄のまま保存すると既存値を維持します。</small></td></tr>
        </tbody></table>
        <p><button type="submit">DB設定を保存する</button></p>
      </form>

      <table>
        <thead><tr><th>項目</th><th>状態</th></tr></thead>
        <tbody>
          <?php foreach ($checks as $label => $ok): ?>
            <tr><td><?= e((string)$label) ?></td><td><?= $ok ? 'OK' : 'NG' ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <h2>直近エラー要約</h2>
      <?php if (is_array($errorSummary)): ?>
        <table><tbody>
          <tr><th>時刻</th><td><?= e((string)($errorSummary['time'] ?? '-')) ?></td></tr>
          <tr><th>ステップ</th><td><?= e((string)($errorSummary['step'] ?? '-')) ?></td></tr>
          <tr><th>例外クラス</th><td><?= e((string)($errorSummary['class'] ?? '-')) ?></td></tr>
          <tr><th>メッセージ</th><td><?= e((string)($errorSummary['message'] ?? '-')) ?></td></tr>
          <tr><th>発生箇所</th><td><?= e((string)($errorSummary['file'] ?? '-')) ?>:<?= e((string)($errorSummary['line'] ?? '-')) ?></td></tr>
          <tr><th>失敗SQL</th><td><pre><?= e((string)($errorSummary['failed_sql'] ?? '取得なし')) ?></pre></td></tr>
        </tbody></table>
      <?php else: ?>
        <p>直近エラー要約はありません。</p>
      <?php endif; ?>

      <h2>install.log 末尾30行</h2>
      <?php if (($logTail['error'] ?? null) !== null): ?>
        <div class="alert alert-warning"><?= e((string)$logTail['error']) ?></div>
      <?php else: ?>
        <pre><?php foreach (($logTail['lines'] ?? []) as $line): ?><?= e((string)$line) . "\n" ?><?php endforeach; ?></pre>
      <?php endif; ?>

      <p><a href="<?= e(public_url('login0718.php')) ?>">ログイン画面へ戻る</a></p>
    </section>
  </main>
</body>
</html>
