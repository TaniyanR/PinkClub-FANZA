<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/helpers.php';
app_config();
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/admin_auth.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/partials/_helpers.php';

admin_session_start();
$token = trim((string)($_GET['token'] ?? ''));
$stmt = db()->prepare('SELECT r.*, u.username FROM admin_password_resets r INNER JOIN admin_users u ON u.id = r.admin_user_id WHERE r.token_hash=:h AND r.used_at IS NULL AND r.expires_at >= NOW() ORDER BY r.id DESC LIMIT 1');
$stmt->execute([':h' => hash('sha256', $token)]);
$reset = $stmt->fetch(PDO::FETCH_ASSOC);

if (!is_array($reset)) {
    http_response_code(403);
    ?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e(APP_NAME) ?> 403 Forbidden</title>
  <link rel="stylesheet" href="<?= e(asset_url('css/style.css')) ?>">
</head>
<body class="login-page">
  <main class="login-wrap">
    <section class="login-card">
      <h1 class="login-title"><?= e(APP_NAME) ?></h1>
      <p class="login-subtitle">403 Forbidden</p>
      <div class="alert alert-error" role="alert">リセットトークンが無効または期限切れです。</div>
      <p class="login-note"><a href="<?= e(public_url('forgot_password.php')) ?>">再発行へ戻る</a></p>
    </section>
  </main>
</body>
</html>
<?php
    exit;
}
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_verify((string)($_POST['_token'] ?? ''))) {
        $error = '不正なリクエストです。';
    } else {
        $password = (string)($_POST['password'] ?? '');
        $passwordConfirm = (string)($_POST['password_confirm'] ?? '');
        if (strlen($password) < 8) {
            $error = '新しいパスワードは8文字以上で入力してください。';
        } elseif ($password !== $passwordConfirm) {
            $error = '確認用パスワードが一致しません。';
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            db()->prepare('UPDATE admin_users SET password_hash=:h, updated_at=NOW() WHERE id=:id')
                ->execute([':h' => $passwordHash, ':id' => (int)$reset['admin_user_id']]);
            db()->prepare('UPDATE admins SET password_hash=:h, updated_at=NOW() WHERE username=:username')
                ->execute([':h' => $passwordHash, ':username' => (string)$reset['username']]);
            db()->prepare('UPDATE admin_password_resets SET used_at=NOW() WHERE id=:id')->execute([':id' => (int)$reset['id']]);
            $_SESSION['forgot_password_success'] = 'パスワードを再設定しました。新しいパスワードでログインしてください。';
            app_redirect(login_url());
        }
    }
}

?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e(APP_NAME) ?> パスワード再設定</title>
  <link rel="stylesheet" href="<?= e(asset_url('css/style.css')) ?>">
</head>
<body class="login-page">
  <main class="login-wrap">
    <section class="login-card">
      <h1 class="login-title"><?= e(APP_NAME) ?></h1>
      <p class="login-subtitle">パスワード再設定</p>

      <?php if ($error !== ''): ?>
        <div class="alert alert-error" role="alert"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="post" class="login-form" action="<?= e(public_url('reset_password.php?token=' . rawurlencode($token))) ?>">
        <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
        <label class="login-label">
          新しいパスワード
          <input class="login-input" type="password" name="password" autocomplete="new-password" minlength="8" required>
        </label>
        <label class="login-label">
          新しいパスワード（確認）
          <input class="login-input" type="password" name="password_confirm" autocomplete="new-password" minlength="8" required>
        </label>
        <button class="login-button" type="submit">再設定する</button>
      </form>
    </section>
  </main>
</body>
</html>
