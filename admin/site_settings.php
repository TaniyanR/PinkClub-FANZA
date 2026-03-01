<?php
declare(strict_types=1);

require_once __DIR__ . '/../public/_bootstrap.php';
auth_require_admin();

$title = 'サイト設定';
$message = null;
$error = null;

$uploadDir = __DIR__ . '/../public/uploads/site';
$uploadWebBase = public_url('uploads/site/');

$ensureUploadDir = static function () use ($uploadDir): void {
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }
};

$saveUploadedImage = static function (string $field, array $rules) use ($ensureUploadDir, $uploadDir, $uploadWebBase): ?string {
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) {
        return null;
    }
    $file = $_FILES[$field];
    if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ((int)($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('アップロードに失敗しました。');
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('不正なアップロードです。');
    }

    $size = @getimagesize($tmp);
    if (!is_array($size) || !isset($size[0], $size[1], $size['mime'])) {
        throw new RuntimeException('画像ファイルを選択してください。');
    }

    $width = (int)$size[0];
    $height = (int)$size[1];
    $mime = (string)$size['mime'];

    if ($width < $rules['min_w'] || $width > $rules['max_w'] || $height < $rules['min_h'] || $height > $rules['max_h']) {
        throw new RuntimeException((string)$rules['size_error']);
    }

    if (($rules['square'] ?? false) === true && $width !== $height) {
        throw new RuntimeException('ファビコン画像は正方形でアップロードしてください。');
    }

    $allowed = $rules['allowed_mime'];
    if (!in_array($mime, $allowed, true)) {
        throw new RuntimeException((string)$rules['mime_error']);
    }

    $ext = $mime === 'image/x-icon' ? 'ico' : ($mime === 'image/vnd.microsoft.icon' ? 'ico' : 'png');
    $ensureUploadDir();
    $filename = $field . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = $uploadDir . '/' . $filename;
    if (!move_uploaded_file($tmp, $dest)) {
        throw new RuntimeException('画像の保存に失敗しました。');
    }

    return $uploadWebBase . $filename;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        csrf_validate_or_fail((string)post('_csrf', ''));
        $siteName = trim((string)post('site_name', ''));
        $siteUrl = trim((string)post('site_url', ''));
        $tagline = trim((string)post('site_tagline', ''));
        $keywords = trim((string)post('site_keywords', ''));

        $pairs = [
            'site.title' => $siteName,
            'site.name' => $siteName,
            'site.url' => $siteUrl,
            'site.tagline' => $tagline,
            'site.keywords' => $keywords,
        ];

        $logoUrl = $saveUploadedImage('title_logo', [
            'min_w' => 250,
            'max_w' => 400,
            'min_h' => 50,
            'max_h' => 100,
            'square' => false,
            'allowed_mime' => ['image/png'],
            'size_error' => 'ロゴ画像は横250〜400px / 高さ50〜100pxの範囲でアップロードしてください。',
            'mime_error' => 'ロゴ画像はPNG形式のみ対応です。',
        ]);
        if ($logoUrl !== null) {
            $pairs['site.logo_url'] = $logoUrl;
        }

        $faviconUrl = $saveUploadedImage('site_favicon', [
            'min_w' => 48,
            'max_w' => 512,
            'min_h' => 48,
            'max_h' => 512,
            'square' => true,
            'allowed_mime' => ['image/png', 'image/x-icon', 'image/vnd.microsoft.icon'],
            'size_error' => 'ファビコンは48〜512pxの正方形でアップロードしてください。',
            'mime_error' => 'ファビコンはICOまたはPNG形式のみ対応です。',
        ]);
        if ($faviconUrl !== null) {
            $pairs['site.favicon_url'] = $faviconUrl;
        }

        site_setting_set_many($pairs);
        $message = 'サイト設定を保存しました。';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$currentLogo = trim(site_setting_get('site.logo_url', ''));
$currentFavicon = trim(site_setting_get('site.favicon_url', ''));

require __DIR__ . '/includes/header.php';
?>
<section class="admin-card admin-card--form">
  <h1>サイト設定</h1>
  <?php if ($message !== null): ?><p><?= e($message) ?></p><?php endif; ?>
  <?php if ($error !== null): ?><p><?= e($error) ?></p><?php endif; ?>
  <form method="post" enctype="multipart/form-data" class="admin-form--compact">
    <?= csrf_input() ?>
    <label>サイト名
      <input type="text" name="site_name" value="<?= e(site_setting_get('site.title', site_setting_get('site.name', APP_NAME))) ?>">
    </label>
    <label>URL
      <input type="url" name="site_url" value="<?= e(site_setting_get('site.url', app_url())) ?>">
    </label>
    <label>キャッチフレーズ（Google検索の説明に反映）
      <input type="text" name="site_tagline" value="<?= e(site_setting_get('site.tagline', '')) ?>">
    </label>
    <label>キーワード（meta keywordsに反映）
      <input type="text" name="site_keywords" value="<?= e(site_setting_get('site.keywords', '')) ?>" placeholder="例: FANZA,動画,アフィリエイト">
    </label>

    <label>タイトルロゴ（PNG / 横250〜400px、高さ50〜100px）
      <input type="file" name="title_logo" accept="image/png">
    </label>
    <?php if ($currentLogo !== ''): ?>
      <p><img src="<?= e($currentLogo) ?>" alt="現在のロゴ" style="max-width:300px;height:auto"></p>
    <?php endif; ?>

    <label>ファビコン（ICO/PNG / 48〜512px 正方形）
      <input type="file" name="site_favicon" accept="image/png,image/x-icon,image/vnd.microsoft.icon,.ico">
    </label>
    <?php if ($currentFavicon !== ''): ?>
      <p><img src="<?= e($currentFavicon) ?>" alt="現在のファビコン" style="width:48px;height:48px"></p>
    <?php endif; ?>

    <div class="admin-actions">
      <button type="submit">保存</button>
    </div>
  </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
