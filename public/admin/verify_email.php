<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

$token = trim((string)($_GET['token'] ?? ''));
$message = '確認リンクが無効または期限切れです。';

if ($token !== '') {
    $hash = hash('sha256', $token);
    $stmt = db()->prepare('SELECT * FROM admin_email_verifications WHERE token_hash=:hash AND consumed_at IS NULL AND expires_at > NOW() ORDER BY id DESC LIMIT 1');
    $stmt->execute([':hash' => $hash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (is_array($row)) {
        db()->beginTransaction();
        try {
            db()->prepare('UPDATE admin_email_verifications SET consumed_at=NOW() WHERE id=:id')->execute([':id' => (int)$row['id']]);
            db()->prepare('UPDATE admin_users SET email=pending_email, email_verified_at=NOW(), login_mode="email_only", pending_email=NULL, updated_at=NOW() WHERE id=:id')
                ->execute([':id' => (int)$row['user_id']]);
            db()->commit();
            $message = 'メール確認が完了しました。次回からメールアドレスでログインできます。';
        } catch (Throwable $e) {
            db()->rollBack();
            log_message('[verify_email] ' . $e->getMessage());
            $message = '処理中にエラーが発生しました。';
        }
    }
}

$pageTitle = 'メール確認';
ob_start();
?>
<h1>メール確認</h1>
<div class="admin-card"><p><?php echo e($message); ?></p></div>
<div class="admin-card"><a href="<?php echo e(login_url()); ?>">ログインへ戻る</a></div>
<?php
$content = (string)ob_get_clean();
include __DIR__ . '/../partials/admin_layout.php';
