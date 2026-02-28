<?php
declare(strict_types=1);
require_once __DIR__ . '/../public/_bootstrap.php';
auth_require_admin();
$title = 'お問い合わせ設定';
require __DIR__ . '/includes/header.php';
?>
<section class="admin-card admin-card--form">
  <h1>お問い合わせ設定</h1>
  <p>現在は仮ページです。今後、お問い合わせ通知先や自動返信設定を追加予定です。</p>
  <ul>
    <li>通知先メールアドレス（予定）</li>
    <li>件名テンプレート（予定）</li>
    <li>送信完了メッセージ（予定）</li>
  </ul>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
