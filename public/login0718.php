<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/admin_auth.php';
require_once __DIR__ . '/../lib/site_settings.php';

start_admin_session();

if (admin_is_logged_in()) {
    app_redirect(admin_path('index.php'));
}

$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $identifier = trim((string)($_POST['identifier'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if (admin_login($identifier, $password)) {
        $current = admin_current_user();
        if (is_array($current)) {
            admin_login_success($current, admin_path('index.php'));
        }
        app_redirect(admin_path('index.php'));
    }

    $error = 'ユーザー名/メールまたはパスワードが違います。';
}

$siteTitle = setting_site_title('サイトタイトル未設定');
?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理ログイン</title>
    <style>
        body { font-family: sans-serif; margin: 2rem; }
        .login { max-width: 420px; margin: 0 auto; }
        label { display: block; margin-top: 1rem; }
        input { width: 100%; padding: .5rem; box-sizing: border-box; }
        button { margin-top: 1rem; padding: .6rem 1rem; }
        .error { color: #b00020; margin-top: 1rem; }
    </style>
</head>
<body>
<div class="login">
    <h1><?php echo htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
    <h2>管理ログイン</h2>

    <?php if ($error !== ''): ?>
        <p class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>

    <form method="post" action="<?php echo htmlspecialchars(login_url(), ENT_QUOTES, 'UTF-8'); ?>">
        <label for="identifier">ユーザー名またはメール</label>
        <input id="identifier" name="identifier" type="text" autocomplete="username" required>

        <label for="password">パスワード</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>

        <button type="submit">ログイン</button>
    </form>
</div>
</body>
</html>
