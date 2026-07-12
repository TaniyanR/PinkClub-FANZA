<?php
declare(strict_types=1);

require_once __DIR__ . '/../public/_bootstrap.php';
auth_require_admin();

$title = 'cron設定';
$cronTargetFile = '/home/ganmodokir/fanzalove.email/public_html/scripts/auto_import.php';
$cronPhpCli = '/usr/bin/php8.3';
$cronLogFile = '/home/ganmodokir/fanzalove.email/cron_auto_import.log';
$cronCommandExample = $cronPhpCli . ' ' . $cronTargetFile . ' >> ' . $cronLogFile . ' 2>&1';
$cronScheduleExample = '*/10 * * * * ' . $cronCommandExample;

require __DIR__ . '/includes/header.php';
?>
<section class="card">
  <h1>cron設定</h1>
  <p class="admin-form-note">自動更新をcronで実行するための参考情報です。</p>

  <h2>cron実行コマンド</h2>
  <table class="admin-table">
    <tr><th>実行対象ファイル</th><td><code><?= e($cronTargetFile) ?></code></td></tr>
    <tr><th>推奨実行間隔</th><td>10分</td></tr>
    <tr><th>PHP CLI</th><td><code><?= e($cronPhpCli) ?></code><br><small>このサーバーでは <code>php</code> だけで実行するとPHP 8.0が使われます。このアプリではPHP 8.3の絶対パス <code><?= e($cronPhpCli) ?></code> を使用してください。cronには下記の画面表示コマンドをそのまま登録すれば実行結果とエラーをログで確認できます。</small></td></tr>
    <tr><th>参考コマンド</th><td><input id="cron-command" type="text" value="<?= e($cronCommandExample) ?>" readonly style="width:100%; overflow-x:auto;"><button type="button" onclick="navigator.clipboard && navigator.clipboard.writeText(document.getElementById('cron-command').value);">コピー</button><br><small>このコマンドをそのままcronに登録してください。<code><?= e($cronLogFile) ?></code> に実行結果とエラーが追記されます。</small></td></tr>
    <tr><th>推奨設定例</th><td><input id="cron-example" type="text" value="<?= e($cronScheduleExample) ?>" readonly style="width:100%; overflow-x:auto;"><button type="button" onclick="navigator.clipboard && navigator.clipboard.writeText(document.getElementById('cron-example').value);">コピー</button><br><small>10分ごとにPHP 8.3で自動更新を実行し、ログを追記する推奨設定例です。</small></td></tr>
  </table>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
