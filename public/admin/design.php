<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../../lib/site_settings.php';

function admin_design_handle_upload(string $fieldName, string $settingKey): ?string
{
    $file = $_FILES[$fieldName] ?? null;
    if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $error = (int)($file['error'] ?? UPLOAD_ERR_OK);
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('アップロードに失敗しました。');
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('アップロードファイルが不正です。');
    }

    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > 5 * 1024 * 1024) {
        throw new RuntimeException('画像サイズは5MB以下にしてください。');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($tmp);
    $allowed = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('画像形式は PNG/JPG/WEBP のみ対応です。');
    }

    $uploadDir = dirname(__DIR__) . '/uploads';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('アップロード先フォルダを作成できませんでした。');
    }

    $filename = $fieldName . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(5)) . '.' . $allowed[$mime];
    $destination = $uploadDir . '/' . $filename;
    if (!move_uploaded_file($tmp, $destination)) {
        throw new RuntimeException('画像ファイルの保存に失敗しました。');
    }

    $old = site_setting_get($settingKey, '');
    if ($old !== '' && str_starts_with($old, '/uploads/')) {
        $oldPath = dirname(__DIR__) . $old;
        if (is_file($oldPath)) {
            @unlink($oldPath);
        }
    }

    return '/uploads/' . $filename;
}

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!admin_post_csrf_valid()) {
        $error = 'CSRFトークンが無効です。';
    } else {
        try {
            $updates = [];
            $logoPath = admin_design_handle_upload('logo_file', 'design.logo_url');
            if ($logoPath !== null) {
                $updates['design.logo_url'] = $logoPath;
            }
            $ogpPath = admin_design_handle_upload('ogp_file', 'design.ogp_image_url');
            if ($ogpPath !== null) {
                $updates['design.ogp_image_url'] = $ogpPath;
            }

            if ($updates !== []) {
                site_setting_set_many($updates);
                admin_flash_set('ok', 'デザイン設定を保存しました。');
            } else {
                admin_flash_set('ok', '変更はありませんでした。');
            }

            header('Location: ' . admin_url('design.php'));
            exit;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$ok = admin_flash_get('ok');
$logoUrl = site_setting_get('design.logo_url', '');
$ogpUrl = site_setting_get('design.ogp_image_url', '');
$pageTitle = 'デザイン設定';
ob_start();
?>
<h1>デザイン設定</h1>
<?php if ($ok !== '') : ?><div class="admin-card"><p><?php echo e($ok); ?></p></div><?php endif; ?>
<?php if ($error !== '') : ?><div class="admin-card"><p style="color:#d63638"><?php echo e($error); ?></p></div><?php endif; ?>
<div class="admin-card">
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">

        <label>ロゴ画像（PNG/JPG/WEBP、5MBまで）</label>
        <?php if ($logoUrl !== '') : ?><p><img src="<?php echo e($logoUrl); ?>" alt="logo preview" style="max-width:240px;height:auto;"></p><?php endif; ?>
        <input type="file" name="logo_file" accept="image/png,image/jpeg,image/webp">

        <label>OGP画像（PNG/JPG/WEBP、5MBまで）</label>
        <?php if ($ogpUrl !== '') : ?><p><img src="<?php echo e($ogpUrl); ?>" alt="ogp preview" style="max-width:240px;height:auto;"></p><?php endif; ?>
        <input type="file" name="ogp_file" accept="image/png,image/jpeg,image/webp">

        <button type="submit">保存</button>
    </form>
</div>
<?php
$content = (string)ob_get_clean();
include __DIR__ . '/../partials/admin_layout.php';
