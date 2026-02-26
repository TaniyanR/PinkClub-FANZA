<?php
declare(strict_types=1);

require_once __DIR__ . '/../public/_bootstrap.php';
auth_require_admin();

$title = '管理ページ一覧';

$pages = [];
$error = null;

$discoveryPath = __DIR__ . '/../lib/admin_page_discovery.php';
if (is_file($discoveryPath)) {
    require_once $discoveryPath;
}

if (function_exists('admin_discover_pages')) {
    try {
        $pages = admin_discover_pages();
        if (!is_array($pages)) {
            $pages = [];
            $error = 'admin_discover_pages() の戻り値が不正でした。';
        }
    } catch (Throwable $e) {
        $pages = [];
        $error = '管理ページ検出に失敗しました: ' . $e->getMessage();
    }
} else {
    $error = 'admin_discover_pages() が見つかりません（admin_page_discovery.php を確認してください）。';
}

require __DIR__ . '/includes/header.php';
?>
<section class="admin-card">
  <h1>管理ページ一覧（サイトマップ）</h1>
  <p class="admin-form-note">検出した管理ページを一覧表示します。未整備は導線調整が必要な候補です。</p>

  <?php if ($error !== null): ?>
    <div class="admin-notice admin-notice--error"><p><?= e($error) ?></p></div>
  <?php endif; ?>

  <?php if (empty($pages)): ?>
    <div class="admin-notice admin-notice--error"><p>管理ページが検出できませんでした。</p></div>
  <?php else: ?>
    <table class="admin-table">
      <thead>
        <tr>
          <th>パス</th>
          <th>ラベル</th>
          <th>状態</th>
          <th>認証</th>
          <th>存在確認</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($pages as $page): ?>
          <?php
            $path  = (string)($page['path'] ?? '');
            $label = (string)($page['label'] ?? $path);
            $broken = (bool)($page['broken'] ?? false);
            $auth  = (string)($page['auth'] ?? '');
            $exists = (bool)($page['exists'] ?? false);

            // /admin 配下のリンクに統一（path が admin/xxx.php なら basename でOK）
            $file = basename($path);
          ?>
          <tr>
            <td><a href="<?= e(admin_url($file)) ?>"><?= e($path) ?></a></td>
            <td><?= e($label) ?></td>
            <td><?= $broken ? '未整備' : '稼働候補' ?></td>
            <td><?= e($auth) ?></td>
            <td><?= $exists ? 'OK' : 'NG' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>