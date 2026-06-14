<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/helpers.php';
app_config();
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/rate_limit.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/admin_auth.php';
require_once __DIR__ . '/partials/_helpers.php';

admin_session_start();
$message = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    rate_limit_check('password_reset');
    if (!csrf_verify((string)($_POST['_token'] ?? ''))) {
        $message = 'リクエストが無効です。';
    } else {
        $email = trim((string)($_POST['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'メールアドレスの形式が正しくありません。';
        } else {
            $stmt = db()->prepare('SELECT id, username, email FROM admin_users WHERE email=:email AND is_active=1 LIMIT 1');
            $stmt->execute([':email' => $email]);
            $u = $stmt->fetch(PDO::FETCH_ASSOC);

            if (is_array($u)) {
                $token = bin2hex(random_bytes(32));
                db()->prepare('INSERT INTO admin_password_resets(admin_user_id,token_hash,expires_at) VALUES (:admin_user_id,:token_hash,DATE_ADD(NOW(), INTERVAL 1 HOUR))')
                    ->execute([':admin_user_id' => (int)$u['id'], ':token_hash' => hash('sha256', $token)]);

                $resetUrl = public_url('reset_password.php?token=' . rawurlencode($token));
                $body = "管理者パスワード再設定の申請を受け付けました。\n"
                    . "ユーザー名: " . (string)$u['username'] . "\n"
                    . "メールアドレス: " . (string)$u['email'] . "\n"
                    . "再設定URL: " . $resetUrl . "\n\n"
                    . "このURLは1時間で期限切れになります。";
                $ok = @mail($email, '[PinkClub-FANZA] Password Reset', $body);
            } else {
                $ok = true;
            }

            db()->prepare('INSERT INTO mail_logs(direction,from_name,from_email,to_email,subject,body,status,last_error,created_at,updated_at) VALUES ("out",NULL,:from,:to,:subj,:body,:status,:err,NOW(),NOW())')
                ->execute([':from' => 'noreply@pinkclub.local', ':to' => $email, ':subj' => 'Password Reset', ':body' => 'パスワード再発行メールを送信しました。', ':status' => $ok ? 'sent' : 'failed', ':err' => $ok ? null : 'mail() unavailable']);
            if (!$ok) {
                $message = '現在メールを送信できません。しばらくしてから再度お試しください。';
            }
        }
        if ($message === '') {
            $message = '入力情報を受け付けました。該当ユーザーが存在する場合は再設定案内を送信しました。';
        }
    }
}

?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e(APP_NAME) ?> パスワード再発行</title>
  <link rel="stylesheet" href="<?= e(asset_url('css/style.css')) ?>">
</head>
<body class="login-page">
  <main class="login-wrap">
    <section class="login-card">
      <h1 class="login-title"><?= e(APP_NAME) ?></h1>
      <p class="login-subtitle">パスワード再発行</p>

      <?php if ($message !== ''): ?>
        <div class="alert alert-warning" role="alert"><?= e($message) ?></div>
      <?php endif; ?>

      <form method="post" class="login-form">
        <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
        <label class="login-label">
          登録メールアドレス
          <input class="login-input" name="email" type="email" autocomplete="email" required>
        </label>
        <button class="login-button" type="submit">再発行メールを送る</button>
      </form>

      <p class="login-note"><a href="<?= e(login_url()) ?>">ログインへ戻る</a></p>
    </section>
  </main>
</body>
</html>
