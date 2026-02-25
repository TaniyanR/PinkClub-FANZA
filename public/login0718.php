<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

if (auth_user()) {
    app_redirect('admin/index.php');
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_fail(post('_csrf'));
    $username = trim((string) post('username', ''));
    $password = (string) post('password', '');
    if (auth_attempt($username, $password)) {
        flash_set('success', 'ログインしました。');
        app_redirect('admin/index.php');
    }
    $error = 'ログインに失敗しました。';
}
?>
<!doctype html>
<html lang="ja"><head><meta charset="UTF-8"><title>管理ログイン</title><link rel="stylesheet" href="<?= e(app_url('assets/css/style.css')) ?>"></head><body>
<h1>管理ログイン</h1>
<?php if ($error): ?><div class="flash error"><?= e($error) ?></div><?php endif; ?>
<form method="post">
  <?= csrf_input() ?>
  <div><label>ID <input name="username" required></label></div>
  <div><label>Password <input type="password" name="password" required></label></div>
  <button type="submit">ログイン</button>
</form>
<p>URL: <code>http://localhost/pinkclub-fanza/public/login0718.php</code></p>
</body></html>
