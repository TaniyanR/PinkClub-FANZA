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

$oldAboutBody = "このサイトについての説明ページです。\n内容は管理画面から編集できます。";
$aboutBody = "【 ■について 】\n「■」(以下当サイト)にお越しくださってありがとうございます。\n※当サイトはアフィリエイトを使用しております。\n\n【 リンクについて 】\n当サイトはリンクフリーです。\nどのページのどの記事にリンクを貼って頂いてもかまいません。\nただし画像は元サイト様のものでお借りしているだけなので二次使用やダウンロードはご遠慮ください。\n\n【 当サイト情報 】\nサイト名：■\nURL：■\nRSS：■\n\n【 お問い合わせ 】\n何か問題等があればこちら「お問い合わせ」よりご連絡ください。\n※ただし、広告は募集しておりません。\n\n【 個人情報の利用目的 】\n当サイトでは、メールでの【お問い合わせ】にて「名前（ハンドルネーム）」「メールアドレス」等の個人情報をご登録いただく場合がございます。\nこれらの個人情報は必要な情報を電子メールなどをでご連絡する場合に利用させていただくものであり、個人情報をご提供いただく際の目的以外では利用いたしません。\n尚、動画などを購入して頂いた場合は、購入していただいたサイトに個人情報を登録して頂きますが、その場合は登録したサイトの「個人情報保護方針」の順じます。\n\n【 個人情報の第三者への開示 】\n当サイトでは、個人情報は適切に管理し、以下に該当する場合を除いて第三者に開示することはありません。\n※ただし「本人の承諾があった場合」「法令に基づく場合」「人の生命」「身体又は財産の保護」「公衆衛生・児童の健全育成上特に必要」な場合は除きます。\n\n【 個人情報の開示、訂正、追加、削除、利用停止 】\nご本人様からの個人データの開示、訂正、追加、削除、利用停止のご希望の場合には、ご本人様であることを確認させていただいた上、速やかに対応させていただきます。\n\n【 Googleアナリティクスについて 】\n当サイトでは、サイトの利用動向を分析する目的で「Googleアナリティクス」を使用しています。\nGoogleアナリティクスはトラフィックデータの収集のためにCookieを使用しています。\nこのトラフィックデータは匿名で収集されており、個人を特定するものではありません。\nまた、当サイトを経由してGoogle Analyticsにより収集されたデータはGoogle社のプライバシーポリシーに基づいて管理されており、当サイトはGoogle Analyticsのサービス利用による一切の損害について責任を負わないものとします。\nGoogleアナリティクスのプライバシーポリシーはこちらのページでご確認いただけます。\nこの機能はCookieを無効にすることで収集を拒否することが出来ますので、お使いのブラウザの設定をご確認ください。\n\n【 広告配信に関して 】\n当サイトが掲載している広告は電気通信事業者等の広告主と直接契約を結んで実施しているものと、広告代理店アフィリエイトサービスプロバイダーを通じて実施しているものがあります。\n当サイトでは成果報酬型広告の効果測定のため、利⽤者の⽅のアクセス情報を外部事業者に送信しております。\n個⼈を特定する情報ではございません。　また当該の情報が⽬的外利⽤される事は⼀切ございません。\n当サイトの広告は閲覧者の閲覧履歴、個⼈データ等を取得し、追跡などをするものではありません。\n\n■ 送信される情報の内容\n・ 閲覧したサイトのURL\n・ 成果報酬型広告の表⽰⽇時\n・ 成果報酬型広告のクリック⽇時\n・ 成果報酬型広告の計測に必要なクッキー情報\n・ 成果報酬型広告表⽰時及び広告クリック時のIPア ドレス\n・ 成果報酬型広告表⽰時及び広告クリック時に使⽤されたインターネット端末およびインターネットブラウザ−の種類\n\n■ 利⽤⽬的\n・ 成果報酬型広告の効果測定および不正防⽌のため\nまた、第三者配信事業者は、ユーザーの興味に応じた広告を表示するためにCookie（クッキー）を使用することがあります。\n「https://optout.aboutads.info/」にアクセスすることで Cookie を無効にできます。\n\n【 免責事項 】\n当サイトからリンクやバナーなどによって他のサイトに移動された場合、移動先サイトで提供される情報、サービス等について一切の責任を負いません。\n当サイトのコンテンツ・情報につきまして、可能な限り正確な情報を掲載するよう努めておりますが、誤情報が入り込んだり、情報が古くなっていることもございます。\n当サイトに掲載された内容によって生じた損害等の一切の責任を負いかねますのでご了承ください。\n\n【 プライバシーポリシーの変更について 】\n当サイトは、個人情報に関して適用される日本の法令を遵守するとともに、本ポリシーの内容を適宜見直しその改善に努めます。\n修正された最新のプライバシーポリシーは常に本ページにて開示されます。";

$p = null;
if (db_table_exists('fixed_pages')) {
    $st = db()->prepare('SELECT * FROM fixed_pages WHERE slug=:slug AND is_published=1 LIMIT 1');
    $st->execute([':slug' => $slug]);
    $p = $st->fetch(PDO::FETCH_ASSOC);
    if (is_array($p) && $slug === 'about' && (string)($p['body'] ?? '') === $oldAboutBody) {
        $p['body'] = $aboutBody;
        db()->prepare('UPDATE fixed_pages SET body=:body, updated_at=NOW() WHERE slug="about" AND body=:old_body')->execute([
            ':body' => $aboutBody,
            ':old_body' => $oldAboutBody,
        ]);
    }
}

if (!is_array($p)) {
    $defaultPages = [
        'about' => ['title' => 'サイトについて', 'body' => $aboutBody, 'seo_title' => '', 'seo_description' => ''],
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
