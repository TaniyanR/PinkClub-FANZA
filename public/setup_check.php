<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/local_config_writer.php';

$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_fail((string)post('_csrf', ''));

    $dbConfig = [
        'host' => trim((string)post('db_host', '')),
        'port' => (int)post('db_port', 3306),
        'dbname' => trim((string)post('db_name', '')),
        'user' => trim((string)post('db_user', '')),
        'pass' => (string)post('db_pass', ''),
        'charset' => 'utf8mb4',
    ];
    $configErrors = db_validate_config($dbConfig, true);

    if ($configErrors !== []) {
        $error = 'DB設定を確認してください: ' . implode('、', $configErrors);
    } else {
        try {
            $local = local_config_load();
            $local['db'] = $dbConfig;
            local_config_write($local);
            $GLOBALS['app_config'] = array_replace_recursive(app_config(), ['db' => $dbConfig]);
            db_reset_connections();
            $result = installer_run();
            if (($result['success'] ?? false) === true) {
                app_redirect(LOGIN_PATH);
            }
            $error = (string)($result['error'] ?? 'セットアップに失敗しました。');
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
    }
}

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
$dbConfig = app_config()['db'] ?? [];
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
      <div class="alert alert-warning">サーバー設置時はDB設定を入力してセットアップを実行してください。</div>
      <?php if ($message !== null): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
      <?php if ($error !== null): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

      <h2>DB設定</h2>
      <form method="post">
        <?= csrf_input() ?>
        <table><tbody>
          <tr><th>DBホスト</th><td><input name="db_host" value="<?= e((string)($dbConfig['host'] ?? '')) ?>" required></td></tr>
          <tr><th>DBポート</th><td><input name="db_port" type="number" value="<?= e((string)($dbConfig['port'] ?? 3306)) ?>" required></td></tr>
          <tr><th>DB名</th><td><input name="db_name" value="<?= e((string)($dbConfig['dbname'] ?? '')) ?>" required></td></tr>
          <tr><th>DBユーザー</th><td><input name="db_user" value="<?= e((string)($dbConfig['user'] ?? '')) ?>" required></td></tr>
          <tr><th>DBパスワード</th><td><input name="db_pass" type="password" value=""></td></tr>
        </tbody></table>
        <button type="submit">DB設定を保存してセットアップ実行</button>
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
