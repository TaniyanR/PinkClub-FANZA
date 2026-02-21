<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../../lib/site_settings.php';

const DESIGN_UPLOAD_MAX_BYTES = 5 * 1024 * 1024; // 5MB

/**
 * @return array<string,string>
 */
function design_message_catalog(): array
{
    return [
        'logo_saved' => 'ロゴ画像を保存しました。',
        'ogp_saved' => 'OGP画像を保存しました。',
        'logo_deleted' => 'ロゴ画像を削除しました。',
        'ogp_deleted' => 'OGP画像を削除しました。',
        'csrf_invalid' => 'CSRFトークンが無効です。',
        'invalid_action' => '不正な操作です。',
        'file_required' => 'ファイルを選択してください。',
        'upload_failed' => 'アップロードに失敗しました。',
        'upload_ini_size' => 'アップロード上限を超えています（PHP設定: upload_max_filesize / post_max_size）。',
        'upload_form_size' => 'アップロード上限を超えています（フォーム指定サイズ超過）。',
        'upload_partial' => 'ファイルが一部しかアップロードされませんでした。再度お試しください。',
        'upload_no_tmp_dir' => '一時ディレクトリが見つからないためアップロードできません。',
        'upload_cant_write' => '一時ファイルの書き込みに失敗しました。権限を確認してください。',
        'upload_extension' => '拡張モジュールによりアップロードが停止されました。',
        'invalid_upload' => '不正なアップロードです。',
        'invalid_size' => 'ファイルサイズは5MB以下にしてください。',
        'invalid_extension' => '対応していない拡張子です。',
        'mime_check_failed' => 'ファイル形式の判定に失敗しました。',
        'invalid_type' => '画像ファイルのみアップロードできます。',
        'mkdir_failed' => 'アップロード先ディレクトリを作成できませんでした。',
        'save_failed' => 'ファイル保存に失敗しました。',
        'setting_save_failed' => '設定の保存に失敗しました。',
        'setting_delete_failed' => '設定の削除に失敗しました。',
    ];
}

function design_message(string $code, string $default = 'エラーが発生しました。'): string
{
    $catalog = design_message_catalog();
    return $catalog[$code] ?? $default;
}

function design_delete_uploaded_file(string $url): void
{
    $url = trim($url);
    $publicPrefix = '/uploads/design/';
    $sitePrefix = '/public/uploads/design/';
    if ($url === '' || (!str_starts_with($url, $publicPrefix) && !str_starts_with($url, $sitePrefix))) {
        return;
    }

    if (str_starts_with($url, $sitePrefix)) {
        $url = substr($url, 7);
    }

    $fullPath = dirname(__DIR__) . $url;
    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}

function design_prepare_upload_dir(string $uploadDir): bool
{
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        return false;
    }

    $htaccessPath = $uploadDir . '/.htaccess';
    if (!is_file($htaccessPath)) {
        @file_put_contents($htaccessPath, "Options -Indexes\n<FilesMatch \"\\.(php|phtml|phar)$\">\n  Require all denied\n</FilesMatch>\n");
    }

    return true;
}

/**
 * @return array{ok:bool,code:string,url?:string}
 */
function design_handle_upload(string $inputName, string $targetBaseName): array
{
    $file = $_FILES[$inputName] ?? null;
    if (!is_array($file)) {
        return ['ok' => false, 'code' => 'file_required'];
    }

    $errorCode = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errorCode === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'code' => 'file_required'];
    }
    if ($errorCode !== UPLOAD_ERR_OK) {
        $uploadErrorMap = [
            UPLOAD_ERR_INI_SIZE => 'upload_ini_size',
            UPLOAD_ERR_FORM_SIZE => 'upload_form_size',
            UPLOAD_ERR_PARTIAL => 'upload_partial',
            UPLOAD_ERR_NO_TMP_DIR => 'upload_no_tmp_dir',
            UPLOAD_ERR_CANT_WRITE => 'upload_cant_write',
            UPLOAD_ERR_EXTENSION => 'upload_extension',
        ];
        if (isset($uploadErrorMap[$errorCode])) {
            return ['ok' => false, 'code' => $uploadErrorMap[$errorCode]];
        }
        return ['ok' => false, 'code' => 'upload_failed'];
    }

    $tmpName = (string)($file['tmp_name'] ?? '');
    $originalName = (string)($file['name'] ?? '');
    $size = (int)($file['size'] ?? 0);

    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        return ['ok' => false, 'code' => 'invalid_upload'];
    }
    if ($size <= 0 || $size > DESIGN_UPLOAD_MAX_BYTES) {
        return ['ok' => false, 'code' => 'invalid_size'];
    }

    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($extension, $allowedExt, true)) {
        return ['ok' => false, 'code' => 'invalid_extension'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo === false) {
        return ['ok' => false, 'code' => 'mime_check_failed'];
    }
    $mime = (string)finfo_file($finfo, $tmpName);
    finfo_close($finfo);

    $allowedMime = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    if (!isset($allowedMime[$mime])) {
        return ['ok' => false, 'code' => 'invalid_type'];
    }

    $targetExt = $allowedMime[$mime];
    $uploadDir = dirname(__DIR__) . '/uploads/design';
    if (!design_prepare_upload_dir($uploadDir)) {
        return ['ok' => false, 'code' => 'mkdir_failed'];
    }

    foreach (['jpg', 'png', 'gif', 'webp'] as $ext) {
        $oldPath = $uploadDir . '/' . $targetBaseName . '.' . $ext;
        if (is_file($oldPath)) {
            @unlink($oldPath);
        }
    }

    $fileName = $targetBaseName . '.' . $targetExt;
    $destinationPath = $uploadDir . '/' . $fileName;
    if (!move_uploaded_file($tmpName, $destinationPath)) {
        return ['ok' => false, 'code' => 'save_failed'];
    }

    return ['ok' => true, 'code' => 'saved', 'url' => '/uploads/design/' . $fileName];
}

$pageTitle = 'デザイン設定';
$error = '';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $redirect = ['err' => 'invalid_action'];

        if (!admin_post_csrf_valid()) {
            $redirect = ['err' => 'csrf_invalid'];
        } else {
            $action = (string)($_POST['action'] ?? '');
            switch ($action) {
                case 'upload_logo': {
                    $upload = design_handle_upload('logo_file', 'logo');
                    if ($upload['ok'] === false) {
                        $redirect = ['err' => $upload['code']];
                        break;
                    }

                    try {
                        setting_set('design.logo_url', (string)($upload['url'] ?? ''));
                        $redirect = ['ok' => 'logo_saved'];
                    } catch (Throwable) {
                        $redirect = ['err' => 'setting_save_failed'];
                    }
                    break;
                }

                case 'delete_logo': {
                    $old = (string)(setting_get('design.logo_url', '') ?? '');
                    design_delete_uploaded_file($old);
                    try {
                        setting_delete('design.logo_url');
                        $redirect = ['ok' => 'logo_deleted'];
                    } catch (Throwable) {
                        $redirect = ['err' => 'setting_delete_failed'];
                    }
                    break;
                }

                case 'upload_ogp': {
                    $upload = design_handle_upload('ogp_file', 'ogp');
                    if ($upload['ok'] === false) {
                        $redirect = ['err' => $upload['code']];
                        break;
                    }

                    try {
                        setting_set('design.ogp_image_url', (string)($upload['url'] ?? ''));
                        $redirect = ['ok' => 'ogp_saved'];
                    } catch (Throwable) {
                        $redirect = ['err' => 'setting_save_failed'];
                    }
                    break;
                }

                case 'delete_ogp': {
                    $old = (string)(setting_get('design.ogp_image_url', '') ?? '');
                    design_delete_uploaded_file($old);
                    try {
                        setting_delete('design.ogp_image_url');
                        $redirect = ['ok' => 'ogp_deleted'];
                    } catch (Throwable) {
                        $redirect = ['err' => 'setting_delete_failed'];
                    }
                    break;
                }
            }
        }

        $location = admin_url('design.php?' . http_build_query($redirect));
        header('Location: ' . $location);
        exit;
    }
} catch (Throwable $exception) {
    $error = $exception->getMessage() !== '' ? $exception->getMessage() : 'エラーが発生しました。';
}

$logoUrl = (string)(setting_get('design.logo_url', '') ?? '');
$ogpUrl = (string)(setting_get('design.ogp_image_url', '') ?? '');
$logoPreviewUrl = $logoUrl !== '' ? front_asset_url($logoUrl) : '';
$ogpPreviewUrl = $ogpUrl !== '' ? front_asset_url($ogpUrl) : '';

$okCode = trim((string)($_GET['ok'] ?? ''));
$errCode = trim((string)($_GET['err'] ?? ''));
$msg = $okCode !== '' ? design_message($okCode, '') : '';
$err = $errCode !== '' ? design_message($errCode, 'エラーが発生しました。') : '';
if ($error !== '' && $err === '') {
    $err = $error;
}

ob_start();
?>
<h1>デザイン設定</h1>
<p class="admin-form-note">ロゴ画像とOGP画像を設定できます（JPG/PNG/GIF/WEBP、5MBまで）</p>

<?php if ($msg !== '') : ?>
    <div class="admin-card admin-notice admin-notice--success"><p><?php echo e($msg); ?></p></div>
<?php endif; ?>
<?php if ($err !== '') : ?>
    <div class="admin-card admin-notice admin-notice--error"><p><?php echo e($err); ?></p></div>
<?php endif; ?>

<div class="admin-card design-card">
    <h2>ロゴ画像</h2>
    <?php if ($logoPreviewUrl !== '') : ?>
        <p class="design-preview"><img src="<?php echo e($logoPreviewUrl); ?>" alt="ロゴ画像のプレビュー"></p>
    <?php else : ?>
        <p class="design-preview-empty">現在ロゴ画像は未設定です。</p>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="design-form">
        <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
        <input type="hidden" name="action" value="upload_logo">
        <label for="logo_file">ロゴ画像をアップロード</label>
        <input id="logo_file" type="file" name="logo_file" accept=".jpg,.jpeg,.png,.gif,.webp,image/*" required>
        <button type="submit">保存</button>
    </form>

    <?php if ($logoUrl !== '') : ?>
        <form method="post" class="design-form-inline">
            <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
            <input type="hidden" name="action" value="delete_logo">
            <button type="submit" class="button-danger">削除</button>
        </form>
    <?php endif; ?>
</div>

<div class="admin-card design-card">
    <h2>OGP画像</h2>
    <?php if ($ogpPreviewUrl !== '') : ?>
        <p class="design-preview"><img src="<?php echo e($ogpPreviewUrl); ?>" alt="OGP画像のプレビュー"></p>
    <?php else : ?>
        <p class="design-preview-empty">現在OGP画像は未設定です。</p>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="design-form">
        <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
        <input type="hidden" name="action" value="upload_ogp">
        <label for="ogp_file">OGP画像をアップロード</label>
        <input id="ogp_file" type="file" name="ogp_file" accept=".jpg,.jpeg,.png,.gif,.webp,image/*" required>
        <button type="submit">保存</button>
    </form>

    <?php if ($ogpUrl !== '') : ?>
        <form method="post" class="design-form-inline">
            <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
            <input type="hidden" name="action" value="delete_ogp">
            <button type="submit" class="button-danger">削除</button>
        </form>
    <?php endif; ?>
</div>
<?php
$content = (string)ob_get_clean();
include __DIR__ . '/../partials/admin_layout.php';
