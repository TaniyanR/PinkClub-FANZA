<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/rate_limit.php';

function deletion_request_destination_email(): string
{
    $settingsEmail = setting_admin_email('');
    if ($settingsEmail !== '' && filter_var($settingsEmail, FILTER_VALIDATE_EMAIL)) {
        return $settingsEmail;
    }

    if (db_table_exists('admin_users')) {
        $stmt = db()->query('SELECT email FROM admin_users WHERE email IS NOT NULL AND email <> "" ORDER BY id ASC LIMIT 1');
        $email = (string)($stmt ? $stmt->fetchColumn() : '');
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }
    }

    return '';
}

function deletion_request_send_mail(
    string $to,
    string $replyTo,
    string $receipt,
    string $textBody,
    string $tmpPath,
    string $mime,
    string $extension
): bool {
    $fileData = @file_get_contents($tmpPath);
    if (!is_string($fileData) || $fileData === '') {
        return false;
    }

    $boundary = '=_PCF_' . bin2hex(random_bytes(16));
    $subjectText = '【削除依頼】受付番号 ' . $receipt;
    $subject = function_exists('mb_encode_mimeheader')
        ? mb_encode_mimeheader($subjectText, 'UTF-8', 'B', "\r\n")
        : $subjectText;
    $safeReplyTo = str_replace(["\r", "\n"], '', $replyTo);
    $attachmentName = 'identity-document-' . preg_replace('/[^A-Za-z0-9_-]/', '', $receipt) . '.' . $extension;

    $headers = [
        'MIME-Version: 1.0',
        'Reply-To: ' . $safeReplyTo,
        'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
    ];

    $body = '--' . $boundary . "\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n\r\n"
        . $textBody . "\r\n\r\n"
        . '--' . $boundary . "\r\n"
        . 'Content-Type: ' . $mime . '; name="' . $attachmentName . '"' . "\r\n"
        . "Content-Transfer-Encoding: base64\r\n"
        . 'Content-Disposition: attachment; filename="' . $attachmentName . '"' . "\r\n\r\n"
        . chunk_split(base64_encode($fileData), 76, "\r\n")
        . '--' . $boundary . "--\r\n";

    return @mail($to, $subject, $body, implode("\r\n", $headers));
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit;
}

$backUrl = public_url('page.php?slug=que&type=deletion');

try {
    if (!csrf_verify((string)($_POST['_token'] ?? ''))) {
        throw new RuntimeException('リクエストが無効です。');
    }
    if (!rate_limit_allow('deletion_request', 2, 900)) {
        throw new RuntimeException('短時間に複数回送信されています。時間をおいて再度お試しください。');
    }
    if (trim((string)($_POST['website'] ?? '')) !== '') {
        header('Location: ' . $backUrl . '&submitted=1');
        exit;
    }

    $name = trim((string)($_POST['deletion_name'] ?? ''));
    $email = trim((string)($_POST['deletion_email'] ?? ''));
    $phone = trim((string)($_POST['deletion_phone'] ?? ''));
    $pageUrls = trim((string)($_POST['deletion_urls'] ?? ''));
    $reason = trim((string)($_POST['deletion_reason'] ?? ''));
    $consent = (string)($_POST['deletion_consent'] ?? '') === '1';

    if ($name === '' || $email === '' || $pageUrls === '' || $reason === '') {
        throw new RuntimeException('必須項目を入力してください。');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('メールアドレスの形式が正しくありません。');
    }
    if (!$consent) {
        throw new RuntimeException('プライバシーポリシーへの同意が必要です。');
    }
    if (mb_strlen($name) > 100 || mb_strlen($email) > 254 || mb_strlen($phone) > 30 || mb_strlen($pageUrls) > 5000 || mb_strlen($reason) > 5000) {
        throw new RuntimeException('入力内容が長すぎます。');
    }

    $urls = preg_split('/\R/u', $pageUrls) ?: [];
    $validUrlFound = false;
    foreach ($urls as $url) {
        $url = trim((string)$url);
        if ($url === '') {
            continue;
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('該当ページURLの形式が正しくありません。');
        }
        $validUrlFound = true;
    }
    if (!$validUrlFound) {
        throw new RuntimeException('該当ページURLを入力してください。');
    }

    $upload = $_FILES['identity_document'] ?? null;
    if (!is_array($upload) || (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('本人確認書類を選択してください。');
    }
    $size = (int)($upload['size'] ?? 0);
    if ($size < 1 || $size > 5 * 1024 * 1024) {
        throw new RuntimeException('本人確認書類は5MB以内にしてください。');
    }
    $tmp = (string)($upload['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('アップロードされたファイルを確認できません。');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($tmp);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'];
    if (!isset($extensions[$mime])) {
        throw new RuntimeException('本人確認書類はJPEG・PNG・PDFのみ対応しています。');
    }
    if (str_starts_with($mime, 'image/') && @getimagesize($tmp) === false) {
        throw new RuntimeException('画像ファイルを読み取れません。');
    }

    $toEmail = deletion_request_destination_email();
    if ($toEmail === '') {
        throw new RuntimeException('送信先メールアドレスが設定されていません。');
    }

    $receipt = 'DEL-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
    $mailBody = "受付番号: {$receipt}\n"
        . "対象サイト: PinkClub FANZA\n"
        . "お名前（本名）: {$name}\n"
        . "メールアドレス: {$email}\n"
        . "電話番号: " . ($phone !== '' ? $phone : '未入力') . "\n\n"
        . "該当ページURL:\n{$pageUrls}\n\n"
        . "申請理由:\n{$reason}\n\n"
        . "本人確認書類はこのメールに添付されています。サーバーには保存していません。";

    if (!deletion_request_send_mail($toEmail, $email, $receipt, $mailBody, $tmp, $mime, $extensions[$mime])) {
        throw new RuntimeException('削除依頼メールの送信に失敗しました。時間をおいて再度お試しください。');
    }

    $pdo = db();
    $pdo->exec('CREATE TABLE IF NOT EXISTS deletion_requests (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        receipt_number VARCHAR(40) NOT NULL UNIQUE,
        site_code VARCHAR(50) NOT NULL DEFAULT "pinkclub-fanza",
        requester_name VARCHAR(100) NOT NULL,
        requester_email VARCHAR(254) NOT NULL,
        requester_phone VARCHAR(30) NULL,
        page_urls TEXT NOT NULL,
        reason TEXT NOT NULL,
        document_mime VARCHAR(100) NOT NULL,
        document_original_name VARCHAR(255) NULL,
        document_delivery VARCHAR(30) NOT NULL DEFAULT "email_only",
        status VARCHAR(30) NOT NULL DEFAULT "received",
        ip_hash CHAR(64) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_deletion_requests_status_created (status, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $stmt = $pdo->prepare('INSERT INTO deletion_requests(receipt_number,requester_name,requester_email,requester_phone,page_urls,reason,document_mime,document_original_name,document_delivery,ip_hash,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,"email_only",?,NOW(),NOW())');
    $stmt->execute([
        $receipt,
        $name,
        $email,
        $phone !== '' ? $phone : null,
        $pageUrls,
        $reason,
        $mime,
        mb_substr(basename((string)($upload['name'] ?? 'document')), 0, 255),
        hash('sha256', rate_limit_client_ip()),
    ]);

    header('Location: ' . $backUrl . '&receipt=' . rawurlencode($receipt));
    exit;
} catch (Throwable $e) {
    $message = mb_substr($e->getMessage(), 0, 200);
    header('Location: ' . $backUrl . '&deletion_error=' . rawurlencode($message));
    exit;
}
