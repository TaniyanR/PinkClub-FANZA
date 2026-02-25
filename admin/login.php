<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        if (admin_login((string)($_POST['username'] ?? ''), (string)($_POST['password'] ?? ''))) {
            header('Location: /admin/index.php'); exit;
        }
        $error = 'ログインに失敗しました。';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!doctype html><html lang="ja"><head><meta charset="UTF-8"><title>Admin Login</title><link rel="stylesheet" href="/assets/css/style.css"></head><body>
<main><div class="container"><div class="card" style="max-width:460px;margin:50px auto">
<h1>管理ログイン</h1>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<form method="post"><?= csrf_input() ?>
<label>Username<input type="text" name="username" required></label>
<label>Password<input type="password" name="password" required></label>
<button type="submit">ログイン</button>
</form></div></div></main></body></html>
