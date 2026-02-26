<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$notice = null;
$runResult = null;
$autoSetup = installer_auto_run_if_needed();

if (($autoSetup['blocked'] ?? false) === true) {
    $notice = ['type' => 'error', 'message' => (string)($autoSetup['message'] ?? '自動セットアップは許可されていません。')];
} elseif (($autoSetup['attempted'] ?? false) === true) {
    $runResult = is_array($autoSetup['result'] ?? null) ? $autoSetup['result'] : null;
    if (($autoSetup['success'] ?? false) === true) {
        $notice = ['type' => 'success', 'message' => '自動セットアップが完了しました。'];
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
    'settings(id=1)' => $status['settings_row'] ?? false,
];
$isCompleted = (bool)($status['completed'] ?? false);

if ($isCompleted) {
    app_redirect('public/login0718.php');
}
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
          セットアップ未完了です。login0718.php アクセス時に自動実行されます。詳細は logs/install.log を確認してください。
        </div>
      <?php else: ?>
        <div class="alert flash success">セットアップ完了。ログイン画面へ進めます。</div>
      <?php endif; ?>

      <p><a href="<?= e(public_url('login0718.php')) ?>">ログイン画面へ</a></p>
      <p><small>失敗時の詳細は <code>logs/install.log</code> を確認してください。</small></p>
    </section>
  </main>
</body>
</html>
