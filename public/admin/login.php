<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/config.php';
require_once __DIR__ . '/../../lib/admin_auth.php';
require_once __DIR__ . '/../../lib/csrf.php';

if (admin_current_user() !== null) {
    header('Location: /admin/settings.php');
    exit;
}

$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $token = $_POST['_token'] ?? null;
    if (!csrf_verify(is_string($token) ? $token : null)) {
        $error = '不正なリクエストです。';
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if (admin_login($username, $password)) {
            header('Location: /admin/settings.php');
            exit;
        }
        $error = 'ユーザー名またはパスワードが違います。';
    }
}

include __DIR__ . '/../partials/header.php';
?>
<main>
    <h1>管理画面ログイン</h1>

    <?php if ($error !== '') : ?>
        <div class="admin-card">
            <p><?php echo htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8'); ?></p>
        </div>
    <?php endif; ?>

    <form class="admin-card" method="post" action="/admin/login.php">
        <input type="hidden" name="_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8'); ?>">

        <label>ユーザー名</label>
        <input type="text" name="username" autocomplete="username" required>

        <label>パスワード</label>
        <input type="password" name="password" autocomplete="current-password" required>

        <button type="submit">ログイン</button>
    </form>
</main>
<?php include __DIR__ . '/../partials/footer.php'; ?>
