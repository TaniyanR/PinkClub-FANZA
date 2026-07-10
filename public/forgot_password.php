<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/site_settings.php';
require_once __DIR__ . '/../lib/rate_limit.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/admin_auth.php';
require_once __DIR__ . '/../lib/url.php';
require_once __DIR__ . '/partials/_helpers.php';

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

function password_reset_log(string $reason, array $context = []): void
{
    $safeContext = [];
    foreach ($context as $key => $value) {
        if ($value === null || is_scalar($value)) {
            $safeContext[$key] = $value;
        }
    }
    error_log('[password_reset] ' . $reason . ' ' . json_encode($safeContext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function password_reset_find_admin_by_email(string $email): ?array
{
    $siteAdminEmail = setting_admin_email('');
    if ($siteAdminEmail === '' || strcasecmp($email, $siteAdminEmail) !== 0) {
        password_reset_log('target_not_found', ['reason' => 'site_admin_email_mismatch']);
        return null;
    }

    $stmt = db()->query('SELECT COUNT(*) FROM admins');
    $adminCount = $stmt ? (int)$stmt->fetchColumn() : 0;
    if ($adminCount !== 1) {
        password_reset_log('target_not_found', ['reason' => 'admin_count_not_one', 'admin_count' => $adminCount]);
        return null;
    }

    $stmt = db()->query('SELECT id, username FROM admins ORDER BY id ASC LIMIT 1');
    $admin = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
    if (!is_array($admin)) {
        password_reset_log('target_not_found', ['reason' => 'single_admin_fetch_failed']);
        return null;
    }

    return [
        'id' => (int)$admin['id'],
        'username' => (string)$admin['username'],
        'email' => $siteAdminEmail,
    ];
}

function password_reset_write_mail_log(string $email, string $status, ?string $lastError): void
{
    if (!db_table_exists('mail_logs')) {
        password_reset_log('mail_log_skipped', ['reason' => 'mail_logs_missing', 'status' => $status, 'last_error' => $lastError]);
        return;
    }

    db()->prepare('INSERT INTO mail_logs(direction,from_name,from_email,to_email,subject,body,status,last_error,created_at,updated_at) VALUES ("out",NULL,:from,:to,:subj,:body,:status,:err,NOW(),NOW())')
        ->execute([
            ':from' => 'noreply@pinkclub.local',
            ':to' => $email,
            ':subj' => 'Password Reset',
            ':body' => 'パスワード再発行メール処理を受け付けました。',
            ':status' => $status,
            ':err' => $lastError,
        ]);
}

admin_session_start();
$message = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    rate_limit_check('password_reset');
    if (!csrf_verify((string)($_POST['_token'] ?? ''))) {
        $message = 'リクエストが無効です。';
        password_reset_log('csrf_error');
    } else {
        $email = trim((string)($_POST['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'メールアドレスの形式が正しくありません。';
            password_reset_log('invalid_email_format');
        } else {
            $target = password_reset_find_admin_by_email($email);
            $status = 'failed';
            $lastError = 'no matching reset target';

            if (is_array($target) && db_table_exists('admin_password_resets')) {
                $token = bin2hex(random_bytes(32));
                db()->prepare('INSERT INTO admin_password_resets(admin_user_id,token_hash,expires_at) VALUES (:admin_user_id,:token_hash,DATE_ADD(NOW(), INTERVAL 1 HOUR))')
                    ->execute([':admin_user_id' => (int)$target['id'], ':token_hash' => hash('sha256', $token)]);

                $resetUrl = url('/public/reset_password.php?token=' . rawurlencode($token));
                $body = "管理者パスワード再設定の申請を受け付けました。\n"
                    . "ユーザー名: " . (string)$target['username'] . "\n"
                    . "メールアドレス: " . (string)$target['email'] . "\n"
                    . "再設定URL: " . $resetUrl . "\n\n"
                    . "このURLは1時間で期限切れになります。";
                $ok = @mail($email, '[PinkClub-FANZA] Password Reset', $body);
                $status = $ok ? 'sent' : 'failed';
                $lastError = $ok ? null : 'mail() failed or unavailable';
                password_reset_log($ok ? 'mail_sent' : 'mail_failed', ['admin_id' => (int)$target['id']]);
            } elseif (is_array($target)) {
                $lastError = 'admin_password_resets table missing';
                password_reset_log('token_not_created', ['reason' => $lastError, 'admin_id' => (int)$target['id']]);
            }

            password_reset_write_mail_log($email, $status, $lastError);
        }
        if ($message === '') {
            $message = '入力情報を受け付けました。該当ユーザーが存在する場合は再設定案内を送信しました。';
        }
    }
}

if (headers_sent() === false) {
    header('X-Robots-Tag: noindex, nofollow');
}

?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>パスワード再発行 | PinkClub-FANZA</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="login-page">
  <main class="login-wrap">
    <section class="login-card">
      <h1 class="login-title">PinkClub-FANZA</h1>
      <p class="login-subtitle">パスワード再発行</p>

      <?php if ($message !== '') : ?><p><?php echo e($message); ?></p><?php endif; ?>
      <form method="post" class="login-form" action="<?php echo e(url('/public/forgot_password.php')); ?>">
        <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
        <label class="login-label">
          登録メールアドレス
          <input class="login-input" name="email" type="email" required>
        </label>
        <button class="login-button" type="submit">再発行メールを送る</button>
      </form>

      <p class="login-note"><a href="login0718.php">ログインへ戻る</a></p>
    </section>
  </main>
</body>
</html>
