<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../../lib/site_settings.php';

const DESIGN_UPLOAD_MAX_BYTES = 2097152;

/**
 * @return array{0:string,1:string}
 */
function design_redirect_with_message(string $type, string $message): array
{
    $key = $type === 'err' ? 'err' : 'msg';
    return [$key, $message];
}

function design_saved_url(string $relativePath): string
{
    $base = rtrim(base_url(), '/');
    if ($base !== '') {
        return $base . $relativePath;
    }

    return $relativePath;
}

/**
 * @return array{ok:bool,message:string,url?:string}
 */
function design_handle_upload(string $inputName, string $prefix): array
{
    $file = $_FILES[$inputName] ?? null;
    if (!is_array($file)) {
        return ['ok' => false, 'message' => 'ファイルが選択されていません。'];
    }

    $errorCode = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errorCode !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'message' => 'アップロードに失敗しました。'];
    }

    $tmpName = (string)($file['tmp_name'] ?? '');
    $originalName = (string)($file['name'] ?? '');
    $size = (int)($file['size'] ?? 0);

    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        return ['ok' => false, 'message' => '不正なアップロードです。'];
    }

    if ($size <= 0 || $size > DESIGN_UPLOAD_MAX_BYTES) {
        return ['ok' => false, 'message' => 'ファイルサイズは2MB以下にしてください。'];
    }

    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($extension, $allowedExt, true)) {
        return ['ok' => false, 'message' => '対応していない拡張子です。'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? (string)finfo_file($finfo, $tmpName) : '';
    if ($finfo) {
        finfo_close($finfo);
    }

    $allowedMime = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    if (!isset($allowedMime[$mime])) {
        return ['ok' => false, 'message' => '画像ファイルのみアップロードできます。'];
    }

    $targetExt = $allowedMime[$mime];
    if ($extension === 'jpeg') {
        $targetExt = 'jpg';
    }

    $uploadDir = dirname(__DIR__) . '/uploads/design';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        return ['ok' => false, 'message' => 'アップロード先ディレクトリを作成できませんでした。'];
    }

    $fileName = sprintf('%s_%s_%s.%s', $prefix, date('YmdHis'), bin2hex(random_bytes(4)), $targetExt);
    $destinationPath = $uploadDir . '/' . $fileName;
    if (!move_uploaded_file($tmpName, $destinationPath)) {
        return ['ok' => false, 'message' => 'ファイル保存に失敗しました。'];
    }

    $relative = '/uploads/design/' . $fileName;
    return ['ok' => true, 'message' => '画像を保存しました。', 'url' => design_saved_url($relative)];
}

$pageTitle = 'デザイン設定';
$error = '';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (!admin_post_csrf_valid()) {
            throw new RuntimeException('CSRFトークンが無効です。');
        }

        $action = (string)($_POST['action'] ?? '');
        [$redirectKey, $redirectMessage] = design_redirect_with_message('err', '不正な操作です。');

        switch ($action) {
            case 'upload_logo':
                $upload = design_handle_upload('logo_file', 'logo');
                if ($upload['ok'] === false) {
                    [$redirectKey, $redirectMessage] = design_redirect_with_message('err', $upload['message']);
                } else {
                    setting_set('design.logo_url', (string)($upload['url'] ?? ''));
                    [$redirectKey, $redirectMessage] = design_redirect_with_message('msg', 'ロゴ画像を保存しました。');
                }
                break;

            case 'delete_logo':
                setting_delete('design.logo_url');
                [$redirectKey, $redirectMessage] = design_redirect_with_message('msg', 'ロゴ画像を削除しました。');
                break;

            case 'upload_ogp':
                $upload = design_handle_upload('ogp_file', 'ogp');
                if ($upload['ok'] === false) {
                    [$redirectKey, $redirectMessage] = design_redirect_with_message('err', $upload['message']);
                } else {
                    setting_set('design.ogp_image_url', (string)($upload['url'] ?? ''));
                    [$redirectKey, $redirectMessage] = design_redirect_with_message('msg', 'OGP画像を保存しました。');
                }
                break;

            case 'delete_ogp':
                setting_delete('design.ogp_image_url');
                [$redirectKey, $redirectMessage] = design_redirect_with_message('msg', 'OGP画像を削除しました。');
                break;
        }

        $location = admin_url('design.php?' . http_build_query([$redirectKey => $redirectMessage]));
        header('Location: ' . $location);
        exit;
    }
} catch (Throwable $exception) {
    $error = $exception->getMessage() !== '' ? $exception->getMessage() : 'エラーが発生しました。';
}

$logoUrl = (string)(setting_get('design.logo_url', '') ?? '');
$ogpUrl = (string)(setting_get('design.ogp_image_url', '') ?? '');
$msg = trim((string)($_GET['msg'] ?? ''));
$err = trim((string)($_GET['err'] ?? ''));
if ($error !== '' && $err === '') {
    $err = $error;
}

ob_start();
?>
<h1>デザイン設定</h1>
<p class="admin-form-note">ロゴ画像とOGP画像を設定できます</p>

<?php if ($msg !== '') : ?>
    <div class="admin-card admin-notice admin-notice--success"><p><?php echo e($msg); ?></p></div>
<?php endif; ?>
<?php if ($err !== '') : ?>
    <div class="admin-card admin-notice admin-notice--error"><p><?php echo e($err); ?></p></div>
<?php endif; ?>

<div class="admin-card design-card">
    <h2>ロゴ画像</h2>
    <?php if ($logoUrl !== '') : ?>
        <p class="design-preview"><img src="<?php echo e($logoUrl); ?>" alt="ロゴ画像のプレビュー"></p>
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
    <?php if ($ogpUrl !== '') : ?>
        <p class="design-preview"><img src="<?php echo e($ogpUrl); ?>" alt="OGP画像のプレビュー"></p>
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
