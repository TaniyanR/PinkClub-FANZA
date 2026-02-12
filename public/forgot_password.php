<?php

declare(strict_types=1);

require_once __DIR__ . '/partials/_helpers.php';

$pageTitle = 'パスワードを忘れた方へ';
$resetConfigured = trim((string)config_get('admin_reset_token', '')) !== '';

include __DIR__ . '/partials/login_header.php';
?>
<div class="login-page">
    <div class="login-headline" aria-label="パスワードリセット見出し">
        <span class="login-headline__item">PinkClub-FANZA</span>
        <span class="login-headline__item">パスワード再設定のご案内</span>
    </div>

    <section class="admin-card login-card">
        <p>この画面では、メール送信の代わりに <code>config.local.php</code> のトークン方式で管理者パスワードを再設定します。</p>
        <ol>
            <li><code>config.local.php</code> に <code>admin_reset_token</code> を設定してください。</li>
            <li>下記URLにアクセスし、トークン付きでパスワードを再設定してください。</li>
        </ol>
        <p><code><?php echo e(base_url() . '/reset_password.php?token=...'); ?></code></p>
        <p>※ トークンは十分に長いランダム文字列を設定し、第三者に共有しないでください。</p>

        <?php if (!$resetConfigured) : ?>
            <p class="login-note">現在、<code>admin_reset_token</code> が未設定です。設定後にご利用ください。</p>
        <?php endif; ?>

        <div class="login-help">
            <a href="<?php echo e(login_url()); ?>">ログイン画面に戻る</a>
        </div>
    </section>
</div>
<?php include __DIR__ . '/partials/login_footer.php'; ?>
