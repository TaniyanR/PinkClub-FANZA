<?php
declare(strict_types=1);

require_once __DIR__ . '/../public/_bootstrap.php';
auth_require_admin();

$title = 'cron設定';
$cronTargetFile = realpath(__DIR__ . '/../scripts/auto_import.php');
$cronCommandExample = $cronTargetFile !== false ? 'php ' . $cronTargetFile : '';
$cronScheduleExample = $cronCommandExample !== '' ? '*/10 * * * * ' . $cronCommandExample : '';

require __DIR__ . '/includes/header.php';
?>
<section class="card">
  <h1>cron設定</h1>
  <p class="admin-form-note">自動更新をcronで実行するための参考情報です。</p>

  <h2>cron実行コマンド</h2>
  <table class="admin-table">
    <tr><th>実行対象ファイル</th><td><code><?= e($cronTargetFile !== false ? $cronTargetFile : '要確認') ?></code></td></tr>
    <tr><th>推奨実行間隔</th><td>10分</td></tr>
    <tr><th>PHP CLI</th><td>サーバー管理画面で確認してください。サーバー環境によりPHP CLIパスが異なります。</td></tr>
    <tr><th>参考コマンド</th><td><?php if ($cronCommandExample !== ''): ?><input id="cron-command" type="text" value="<?= e($cronCommandExample) ?>" readonly style="width:100%;"><button type="button" onclick="navigator.clipboard && navigator.clipboard.writeText(document.getElementById('cron-command').value);">コピー</button><br><small>参考例です。<code>php</code> が利用できるか、またはPHP CLIの絶対パスが必要かはサーバー管理画面で確認してください。</small><?php else: ?>要確認<?php endif; ?></td></tr>
    <tr><th>推奨設定例</th><td><?php if ($cronScheduleExample !== ''): ?><input id="cron-example" type="text" value="<?= e($cronScheduleExample) ?>" readonly style="width:100%;"><button type="button" onclick="navigator.clipboard && navigator.clipboard.writeText(document.getElementById('cron-example').value);">コピー</button><br><small>参考例です。実際のPHP CLIパスはサーバー環境に合わせてください。</small><?php else: ?>要確認<?php endif; ?></td></tr>
  </table>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
