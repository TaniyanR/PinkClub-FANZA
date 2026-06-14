<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/admin_auth.php';
require_once __DIR__ . '/partials/_helpers.php';

function contact_destination_email(?int $currentUserId): array
{
    $settingsEmail = setting_admin_email('');
    if ($settingsEmail !== '' && filter_var($settingsEmail, FILTER_VALIDATE_EMAIL)) {
        return [$settingsEmail, ''];
    }

    if (!admin_users_table_available()) {
        return ['', 'admin_users table not found'];
    }

    if ($currentUserId !== null) {
        $stmt = db()->prepare('SELECT email FROM admin_users WHERE id=:id AND email IS NOT NULL AND email <> "" LIMIT 1');
        $stmt->execute([':id' => $currentUserId]);
        $email = (string)($stmt->fetchColumn() ?: '');
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [$email, ''];
        }
    }

    $stmt = db()->query('SELECT email FROM admin_users WHERE email IS NOT NULL AND email <> "" ORDER BY id ASC LIMIT 1');
    $fallback = (string)($stmt->fetchColumn() ?: '');
    if ($fallback !== '' && filter_var($fallback, FILTER_VALIDATE_EMAIL)) {
        return [$fallback, ''];
    }

    return ['', 'admin email not found'];
}

$slug = trim((string)($_GET['slug'] ?? ''));
if ($slug === '') {
    include __DIR__ . '/404.php';
    exit;
}

$p = null;
if (db_table_exists('fixed_pages')) {
    $st = db()->prepare('SELECT * FROM fixed_pages WHERE slug=:slug AND is_published=1 LIMIT 1');
    $st->execute([':slug' => $slug]);
    $p = $st->fetch(PDO::FETCH_ASSOC);
}

if (!is_array($p)) {
    $defaultPages = [
        'about' => ['title' => 'サイトについて', 'body' => "このサイトについての説明ページです。\n内容は管理画面から編集できます。", 'seo_title' => '', 'seo_description' => ''],
        'privacy-policy' => ['title' => 'Privacy Policy', 'body' => "プライバシーポリシーの初期ページです。\n内容は管理画面から編集できます。", 'seo_title' => '', 'seo_description' => ''],
        'contact' => ['title' => 'お問い合わせ', 'body' => 'お問い合わせは下記フォームよりご連絡ください。', 'seo_title' => '', 'seo_description' => ''],
    ];
    $p = $defaultPages[$slug] ?? null;
}

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
    if ($contactForm['name'] === '') {
        $formErrors[] = '氏名は必須です。';
    }
    if ($contactForm['email'] === '') {
        $formErrors[] = 'メールアドレスは必須です。';
    } elseif (!filter_var($contactForm['email'], FILTER_VALIDATE_EMAIL)) {
        $formErrors[] = 'メールアドレスの形式が正しくありません。';
    }
    if ($contactForm['subject'] === '') {
        $formErrors[] = '題名は必須です。';
    }
    if ($contactForm['message'] === '') {
        $formErrors[] = '内容は必須です。';
    }

    if ($formErrors === []) {
        $currentUser = admin_current_user();
        $currentUserId = is_array($currentUser) ? (int)($currentUser['id'] ?? 0) : 0;
        [$toEmail, $toEmailError] = contact_destination_email($currentUserId > 0 ? $currentUserId : null);

        $logId = null;
        if (db_table_exists('mail_logs')) {
            $insert = db()->prepare('INSERT INTO mail_logs(direction,from_name,from_email,to_email,subject,body,status,last_error,created_at,updated_at) VALUES ("in",:from_name,:from_email,:to_email,:subject,:body,"received",NULL,NOW(),NOW())');
            $insert->execute([
                ':from_name' => $contactForm['name'] !== '' ? $contactForm['name'] : null,
                ':from_email' => $contactForm['email'] !== '' ? $contactForm['email'] : null,
                ':to_email' => $toEmail !== '' ? $toEmail : null,
                ':subject' => mb_substr($contactForm['subject'], 0, 255),
                ':body' => $contactForm['message'],
            ]);
            $logId = (int)db()->lastInsertId();
        }

        $status = 'failed';
        $lastError = $toEmailError;
        $safeSubject = str_replace(["\r", "\n"], ' ', $contactForm['subject']);
        $mailSubject = '【お問い合わせ】' . $safeSubject;
        $mailBody = "相手の名前: " . $contactForm['name'] . "\n"
            . "相手のメールアドレス: " . $contactForm['email'] . "\n"
            . "題名: " . $contactForm['subject'] . "\n"
            . "内容:\n" . $contactForm['message'];

        if ($toEmail !== '') {
            $headers = ['Reply-To: ' . $contactForm['email']];
            $ok = @mail($toEmail, $mailSubject, $mailBody, implode("\r\n", $headers));
            $status = $ok ? 'sent' : 'failed';
            $lastError = $ok ? null : 'mail() failed';
        }

        if ($logId !== null) {
            db()->prepare('UPDATE mail_logs SET status=:status,last_error=:last_error,updated_at=NOW() WHERE id=:id')
                ->execute([
                    ':status' => $status,
                    ':last_error' => $lastError,
                    ':id' => $logId,
                ]);
        }

        if ($status === 'sent') {
            $contactSuccess = true;
            $contactForm = ['subject' => '', 'message' => '', 'name' => '', 'email' => ''];
        } else {
            $formErrors[] = '送信に失敗しました。時間をおいて再度お試しください。';
        }
    }
}

$pageTitle = (string)((($p['seo_title'] ?? '') !== '') ? $p['seo_title'] : $p['title']);
$pageDescription = (string)($p['seo_description'] ?? '');

include __DIR__ . '/partials/header.php';
?>
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
                    <form class="contact-form" method="post" action="<?php echo e((string)($_SERVER['REQUEST_URI'] ?? '/p/contact')); ?>">
                        <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">

                        <label for="contact-name">氏名</label>
                        <input id="contact-name" name="name" value="<?php echo e($contactForm['name']); ?>" required>

                        <label for="contact-email">メールアドレス</label>
                        <input id="contact-email" name="email" type="email" value="<?php echo e($contactForm['email']); ?>" required>

                        <label for="contact-subject">題名</label>
                        <input id="contact-subject" name="subject" value="<?php echo e($contactForm['subject']); ?>" required>

                        <label for="contact-message">内容</label>
                        <textarea id="contact-message" name="message" rows="10" required><?php echo e($contactForm['message']); ?></textarea>

                        <button type="submit">送信</button>
                    </form>
                <?php endif; ?>
            </section>
        <?php endif; ?>

<?php include __DIR__ . '/partials/footer.php'; ?>
