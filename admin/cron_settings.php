<?php
declare(strict_types=1);

require_once __DIR__ . '/../public/_bootstrap.php';
require_once __DIR__ . '/../lib/cron_guard.php';
auth_require_admin();

$title = 'cron設定';
$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_fail((string)post('_csrf', ''));
    if ((string)post('action', '') === 'regenerate_token') {
        cron_regenerate_secret_token();
        $message = 'Web実行用秘密トークンを再生成しました。古いURLは使用できません。';
    }
}

$fetchFile = realpath(__DIR__ . '/../cron/fetch.php');
$postFile = realpath(__DIR__ . '/../cron/post.php');
$fetchCommand = $fetchFile !== false ? 'php ' . $fetchFile : '';
$postCommand = $postFile !== false ? 'php ' . $postFile : '';
$token = cron_secret_token();
$baseUrl = rtrim(app_url(), '/');
$fetchUrl = $baseUrl . '/cron/fetch.php?token=' . rawurlencode($token);
$postUrl = $baseUrl . '/cron/post.php?token=' . rawurlencode($token);

require __DIR__ . '/includes/header.php';
?>
<section class="card">
  <h1>cron設定</h1>
  <p class="admin-form-note">CLI実行はそのまま実行できます。Web URL実行は秘密トークンが必須で、トークン不一致時はHTTP 403で終了します。各cronはファイルロックで多重実行を防止します。</p>
  <?php if ($message !== null): ?><p class="flash success"><?= e($message) ?></p><?php endif; ?>

  <h2>CLI実行コマンド</h2>
  <table class="admin-table">
    <tr><th>RSS取得</th><td><input id="cron-fetch-command" type="text" value="<?= e($fetchCommand) ?>" readonly style="width:100%;"><button type="button" onclick="navigator.clipboard && navigator.clipboard.writeText(document.getElementById('cron-fetch-command').value);">コピー</button></td></tr>
    <tr><th>投稿処理</th><td><input id="cron-post-command" type="text" value="<?= e($postCommand) ?>" readonly style="width:100%;"><button type="button" onclick="navigator.clipboard && navigator.clipboard.writeText(document.getElementById('cron-post-command').value);">コピー</button></td></tr>
    <tr><th>推奨例</th><td><code>*/10 * * * * <?= e($fetchCommand) ?></code><br><code>*/10 * * * * <?= e($postCommand) ?></code></td></tr>
  </table>

  <h2>Web実行用秘密トークン</h2>
  <table class="admin-table">
    <tr><th>トークン</th><td><input id="cron-token" type="text" value="<?= e($token) ?>" readonly style="width:100%;"><button type="button" onclick="navigator.clipboard && navigator.clipboard.writeText(document.getElementById('cron-token').value);">コピー</button></td></tr>
    <tr><th>RSS取得URL</th><td><input id="cron-fetch-url" type="text" value="<?= e($fetchUrl) ?>" readonly style="width:100%;"><button type="button" onclick="navigator.clipboard && navigator.clipboard.writeText(document.getElementById('cron-fetch-url').value);">コピー</button></td></tr>
    <tr><th>投稿URL</th><td><input id="cron-post-url" type="text" value="<?= e($postUrl) ?>" readonly style="width:100%;"><button type="button" onclick="navigator.clipboard && navigator.clipboard.writeText(document.getElementById('cron-post-url').value);">コピー</button></td></tr>
  </table>
  <form method="post">
    <?= csrf_input() ?>
    <input type="hidden" name="action" value="regenerate_token">
    <button type="submit">秘密トークンを再生成する</button>
  </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
