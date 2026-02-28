<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/admin_auth.php';
require_once __DIR__ . '/partials/_helpers.php';

function contact_destination_email(?int $currentUserId): array
{
    if ($currentUserId !== null) {
        $stmt = db()->prepare('SELECT email FROM admin_users WHERE id=:id AND email_verified_at IS NOT NULL AND email IS NOT NULL AND email <> "" LIMIT 1');
        $stmt->execute([':id' => $currentUserId]);
        $email = (string)($stmt->fetchColumn() ?: '');
        if ($email !== '') {
            return [$email, ''];
        }
    }

    $stmt = db()->query('SELECT email FROM admin_users WHERE email_verified_at IS NOT NULL AND email IS NOT NULL AND email <> "" ORDER BY id ASC LIMIT 1');
    $fallback = (string)($stmt->fetchColumn() ?: '');
    if ($fallback !== '') {
        return [$fallback, ''];
    }

    return ['', 'verified admin email not found'];
}

$slug = trim((string)($_GET['slug'] ?? ''));
if ($slug === '') {
    include __DIR__ . '/404.php';
    exit;
}

$st = db()->prepare('SELECT * FROM fixed_pages WHERE slug=:slug AND is_published=1 LIMIT 1');
$st->execute([':slug' => $slug]);
$p = $st->fetch(PDO::FETCH_ASSOC);
if (!is_array($p)) {
    include __DIR__ . '/404.php';
    exit;
}

$formErrors = [];
$contactSuccess = false;
$contactForm = [
    'subject' => '',
    'message' => '',
    'name' => '',
    'email' => '',
];

if ($slug === 'contact' && (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST')) {
    $contactForm['subject'] = trim((string)($_POST['subject'] ?? ''));
    $contactForm['message'] = trim((string)($_POST['message'] ?? ''));
    $contactForm['name'] = trim((string)($_POST['name'] ?? ''));
    $contactForm['email'] = trim((string)($_POST['email'] ?? ''));

    if (!csrf_verify((string)($_POST['_token'] ?? ''))) {
        $formErrors[] = 'リクエストが無効です。';
    }
    if ($contactForm['subject'] === '') {
        $formErrors[] = '件名は必須です。';
    }
    if ($contactForm['message'] === '') {
        $formErrors[] = '本文は必須です。';
    }
    if ($contactForm['email'] !== '' && !filter_var($contactForm['email'], FILTER_VALIDATE_EMAIL)) {
        $formErrors[] = 'メールアドレスの形式が正しくありません。';
    }

    if ($formErrors === []) {
        [$toEmail, $toEmailError] = contact_destination_email(admin_current_user_id());

        $insert = db()->prepare('INSERT INTO mail_logs(direction,from_name,from_email,to_email,subject,body,status,last_error,created_at,updated_at) VALUES ("in",:from_name,:from_email,:to_email,:subject,:body,"received",NULL,NOW(),NOW())');
        $insert->execute([
            ':from_name' => $contactForm['name'] !== '' ? $contactForm['name'] : null,
            ':from_email' => $contactForm['email'] !== '' ? $contactForm['email'] : null,
            ':to_email' => $toEmail !== '' ? $toEmail : null,
            ':subject' => mb_substr($contactForm['subject'], 0, 255),
            ':body' => $contactForm['message'],
        ]);

        $logId = (int)db()->lastInsertId();
        $status = 'failed';
        $lastError = $toEmailError;

        if ($toEmail !== '') {
            $headers = [];
            if ($contactForm['email'] !== '') {
                $headers[] = 'From: ' . $contactForm['email'];
                $headers[] = 'Reply-To: ' . $contactForm['email'];
            }
            $ok = @mail($toEmail, $contactForm['subject'], $contactForm['message'], implode("\r\n", $headers));
            $status = $ok ? 'sent' : 'failed';
            $lastError = $ok ? null : 'mail() failed';
        }

        db()->prepare('UPDATE mail_logs SET status=:status,last_error=:last_error,updated_at=NOW() WHERE id=:id')
            ->execute([
                ':status' => $status,
                ':last_error' => $lastError,
                ':id' => $logId,
            ]);

        $contactSuccess = true;
        $contactForm = ['subject' => '', 'message' => '', 'name' => '', 'email' => ''];
    }
}

$pageTitle = (string)((($p['seo_title'] ?? '') !== '') ? $p['seo_title'] : $p['title']);
$pageDescription = (string)($p['seo_description'] ?? '');

include __DIR__ . '/partials/header.php';
include __DIR__ . '/partials/nav_search.php';
?>
        <div class="only-pc"><?php include __DIR__ . '/partials/rss_text_widget.php'; ?></div>
        <?php render_ad('content_top', 'page', 'pc'); ?>
        <section class="block">
            <h1 class="section-title"><?php echo e((string)$p['title']); ?></h1>
            <?php echo nl2br(e((string)$p['body'])); ?>
        </section>

        <?php if ($slug === 'contact') : ?>
            <section class="block">
                <h2 class="section-title">お問い合わせフォーム</h2>
                <?php if ($contactSuccess) : ?>
                    <p>送信しました</p>
                <?php else : ?>
                    <?php foreach ($formErrors as $error) : ?>
                        <p><?php echo e((string)$error); ?></p>
                    <?php endforeach; ?>
                    <form method="post" action="<?php echo e((string)($_SERVER['REQUEST_URI'] ?? '/p/contact')); ?>">
                        <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">

                        <label for="contact-subject">件名（必須）</label>
                        <input id="contact-subject" name="subject" value="<?php echo e($contactForm['subject']); ?>" required>

                        <label for="contact-message">本文（必須）</label>
                        <textarea id="contact-message" name="message" rows="8" required><?php echo e($contactForm['message']); ?></textarea>

                        <label for="contact-name">お名前（任意）</label>
                        <input id="contact-name" name="name" value="<?php echo e($contactForm['name']); ?>">

                        <label for="contact-email">メールアドレス（任意）</label>
                        <input id="contact-email" name="email" type="email" value="<?php echo e($contactForm['email']); ?>">

                        <button type="submit">送信</button>
                    </form>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php render_ad('content_bottom', 'page', 'pc'); ?>
        <div class="only-pc"><?php include __DIR__ . '/partials/rss_text_widget.php'; ?></div>
<?php include __DIR__ . '/partials/footer.php'; ?>
