<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$runResult = null;
$notice = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_fail(post('_csrf'));
    $runResult = installer_run();

    if (($runResult['success'] ?? false) === true) {
        $notice = ['type' => 'success', 'message' => 'セットアップ完了。ログイン画面へ進んでください。'];
    } else {
        $message = is_string($runResult['error'] ?? null) ? $runResult['error'] : 'セットアップに失敗しました。';
        $notice = ['type' => 'error', 'message' => $message];
    }
}

$status = installer_status();
$checks = [
    'MySQLサーバー接続' => $status['server_connection'] ?? false,
    '対象DB接続' => $status['db_connection'] ?? false,
    'admins テーブル' => $status['admins_table'] ?? false,
    'settings テーブル' => $status['settings_table'] ?? false,
    '初期管理者 admin' => $status['admin_user'] ?? false,
];
$isCompleted = (bool)($status['completed'] ?? false);
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e(APP_NAME) ?> セットアップ確認</title>
  <link rel="stylesheet" href="<?= e(asset_url('assets/css/style.css')) ?>">
</head>
<body>
  <main class="setup-page">
    <section class="setup-card">
      <h1><?= e(APP_NAME) ?> セットアップ確認</h1>

      <?php if ($notice !== null): ?>
        <div class="alert <?= $notice['type'] === 'success' ? 'flash success' : 'alert-error' ?>">
          <?= e($notice['message']) ?>
        </div>
      <?php endif; ?>

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

      <?php if ($runResult !== null && isset($runResult['steps']) && is_array($runResult['steps'])): ?>
        <h2>実行結果</h2>
        <table>
          <thead><tr><th>処理</th><th>結果</th><th>詳細</th></tr></thead>
          <tbody>
            <?php foreach ($runResult['steps'] as $step): ?>
              <tr>
                <td><?= e((string)($step['label'] ?? '-')) ?></td>
                <td><?= e(strtoupper((string)($step['status'] ?? '-'))) ?></td>
                <td><?= e((string)($step['message'] ?? '')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <?php if (!$isCompleted): ?>
        <div class="alert alert-warning">
          初回セットアップが未完了です。「DB自動セットアップを実行」を押してください。
        </div>
        <form method="post">
          <?= csrf_input() ?>
          <button class="login-button" type="submit">DB自動セットアップを実行</button>
        </form>
      <?php else: ?>
        <div class="alert flash success">セットアップ完了。ログイン画面へ進めます。</div>
      <?php endif; ?>

      <p><a href="<?= e(public_url('login0718.php')) ?>">ログイン画面へ</a></p>
      <p><small>失敗時の詳細は <code>logs/install.log</code> を確認してください。</small></p>
    </section>
  </main>
</body>
</html>
