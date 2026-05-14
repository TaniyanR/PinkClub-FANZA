<?php
declare(strict_types=1);
require_once __DIR__ . '/../public/_bootstrap.php';
auth_require_admin();
$title = '固定ページ一覧';
$message = null;

try {
    db()->exec('CREATE TABLE IF NOT EXISTS fixed_pages (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        slug VARCHAR(120) NOT NULL UNIQUE,
        title VARCHAR(255) NOT NULL,
        body LONGTEXT NOT NULL,
        seo_title VARCHAR(255) NULL,
        seo_description TEXT NULL,
        is_published TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $defaults = [
        ['slug' => 'about', 'title' => 'サイトについて', 'body' => "当サイト
[サイト名](以下当サイト)は、ASP「アプリケーションサービスプロバイダ（Application Service Provider）」というアフィリエイトの会社の広告を配信して運営しているウェブサイトです。
完全なるアダルトサイトで、18歳未満には提供できません。

当サイト情報
サイト名：[サイト名]
URL：[サイトURL]
RSS：[サイトRSS]

リンクについて
当サイトはリンクフリーです。
どのページのどの記事にリンクを貼って頂いてもかまいません。
ただし画像は元サイト様のもので、お借りしているだけなので二次使用やダウンロードはご遠慮ください。"],
        ['slug' => 'privacy-policy', 'title' => 'Privacy Policy', 'body' => "【 当サイト情報 】
サイト名：[サイト名]
URL：[サイトURL]
RSS：[サイトRSS]

【 個人情報の利用目的 】
当サイトでは、メールでの【お問い合わせ】にて「名前（ハンドルネーム）」「メールアドレス」等の個人情報をご登録いただく場合がございます。
これらの個人情報は必要な情報を電子メールなどをでご連絡する場合に利用させていただくものであり、個人情報をご提供いただく際の目的以外では利用いたしません。
尚、動画などを購入して頂いた場合は、購入していただいたサイトに個人情報を登録して頂きますが、その場合は登録したサイトの「個人情報保護方針」の順じます。

【 個人情報の第三者への開示 】
当サイトでは、個人情報は適切に管理し、以下に該当する場合を除いて第三者に開示することはありません。
※ただし「本人の承諾があった場合」「法令に基づく場合」「人の生命」「身体又は財産の保護」「公衆衛生・児童の健全育成上特に必要」な場合は除きます。

【 個人情報の開示、訂正、追加、削除、利用停止 】
ご本人様からの個人データの開示、訂正、追加、削除、利用停止のご希望の場合には、ご本人様であることを確認させていただいた上、速やかに対応させていただきます。

【 Googleアナリティクスについて 】
当サイトでは、サイトの利用動向を分析する目的で「Googleアナリティクス」を使用しています。
Googleアナリティクスはトラフィックデータの収集のためにCookieを使用しています。
このトラフィックデータは匿名で収集されており、個人を特定するものではありません。
また、当サイトを経由してGoogle Analyticsにより収集されたデータはGoogle社のプライバシーポリシーに基づいて管理されており、当サイトはGoogle Analyticsのサービス利用による一切の損害について責任を負わないものとします。
Googleアナリティクスのプライバシーポリシーはこちらのページでご確認いただけます。
この機能はCookieを無効にすることで収集を拒否することが出来ますので、お使いのブラウザの設定をご確認ください。

【 広告配信に関して 】
当サイトが掲載している広告は電気通信事業者等の広告主と直接契約を結んで実施しているものと、広告代理店アフィリエイトサービスプロバイダーを通じて実施しているものがあります。
当サイトでは成果報酬型広告の効果測定のため、利⽤者の⽅のアクセス情報を外部事業者に送信しております。
個⼈を特定する情報ではございません。　また当該の情報が⽬的外利⽤される事は⼀切ございません。
当サイトの広告は閲覧者の閲覧履歴、個⼈データ等を取得し、追跡などをするものではありません。

■ 送信される情報の内容
・ 閲覧したサイトのURL
・ 成果報酬型広告の表⽰⽇時
・ 成果報酬型広告のクリック⽇時
・ 成果報酬型広告の計測に必要なクッキー情報
・ 成果報酬型広告表⽰時及び広告クリック時のIPア ドレス
・ 成果報酬型広告表⽰時及び広告クリック時に使⽤されたインターネット端末およびインターネットブラウザ−の種類

■ 利⽤⽬的
・ 成果報酬型広告の効果測定および不正防⽌のため
また、第三者配信事業者は、ユーザーの興味に応じた広告を表示するためにCookie（クッキー）を使用することがあります。
「https://optout.aboutads.info/」にアクセスすることで Cookie を無効にできます。

【 免責事項 】
当サイトからリンクやバナーなどによって他のサイトに移動された場合、移動先サイトで提供される情報、サービス等について一切の責任を負いません。
当サイトのコンテンツ・情報につきまして、可能な限り正確な情報を掲載するよう努めておりますが、誤情報が入り込んだり、情報が古くなっていることもございます。
当サイトに掲載された内容によって生じた損害等の一切の責任を負いかねますのでご了承ください。

【 プライバシーポリシーの変更について 】
当サイトは、個人情報に関して適用される日本の法令を遵守するとともに、本ポリシーの内容を適宜見直しその改善に努めます。
修正された最新のプライバシーポリシーは常に本ページにて開示されます。"],
        ['slug' => 'contact', 'title' => 'お問い合わせ', 'body' => "お問い合わせは下記フォームよりご連絡ください。"],
    ];

    $insert = db()->prepare('INSERT INTO fixed_pages(slug,title,body,is_published,created_at,updated_at) VALUES(:slug,:title,:body,1,NOW(),NOW()) ON DUPLICATE KEY UPDATE slug=slug');
    foreach ($defaults as $defaultPage) {
        $insert->execute($defaultPage);
    }

    $repair = db()->prepare('UPDATE fixed_pages SET body=:body, updated_at=NOW() WHERE slug=:slug AND (body=:empty_body OR body=:contact_body)');
    $contactBody = "お問い合わせは下記フォームよりご連絡ください。";
    foreach ($defaults as $defaultPage) {
        if (!in_array((string)$defaultPage['slug'], ['about', 'privacy-policy'], true)) {
            continue;
        }
        $repair->execute([
            ':body' => (string)$defaultPage['body'],
            ':slug' => (string)$defaultPage['slug'],
            ':empty_body' => '',
            ':contact_body' => $contactBody,
        ]);
    }
} catch (Throwable $e) {
    $message = '固定ページ初期化でエラーが発生しました: ' . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_fail((string)post('_csrf', ''));
    $id = (int)post('id', 0);
    db()->prepare('UPDATE fixed_pages SET title=:title, body=:body, seo_title=:seo_title, seo_description=:seo_description, is_published=:is_published, updated_at=NOW() WHERE id=:id')->execute([
        ':title' => trim((string)post('title', '')),
        ':body' => trim((string)post('body', '')),
        ':seo_title' => trim((string)post('seo_title', '')),
        ':seo_description' => trim((string)post('seo_description', '')),
        ':is_published' => post('is_published', '0') === '1' ? 1 : 0,
        ':id' => $id,
    ]);
    $message = '固定ページを更新しました。';
}

$rows = [];
$edit = null;
try {
    $rows = db()->query('SELECT id,slug,title,body,seo_title,seo_description,is_published,updated_at FROM fixed_pages ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $editId = (int)($_GET['edit'] ?? 0);
    foreach ($rows as $row) {
        if ((int)$row['id'] === $editId) {
            $edit = $row;
            break;
        }
    }
} catch (Throwable $e) {
    if ($message === null) {
        $message = '固定ページの取得に失敗しました: ' . $e->getMessage();
    }
}

require __DIR__ . '/includes/header.php';
?>
<section class="admin-card admin-card--form">
  <h1>固定ページ一覧</h1>
  <?php if ($message !== null): ?><p><?= e($message) ?></p><?php endif; ?>
  <table class="admin-table">
    <tr><th>ID</th><th>スラッグ</th><th>タイトル</th><th>公開</th><th>更新日時</th><th>操作</th></tr>
    <?php foreach ($rows as $row): ?>
      <tr>
        <td><?= e((string)$row['id']) ?></td>
        <td><?= e((string)$row['slug']) ?></td>
        <td><?= e((string)$row['title']) ?></td>
        <td><?= (int)$row['is_published'] === 1 ? '公開' : '非公開' ?></td>
        <td><?= e((string)$row['updated_at']) ?></td>
        <td><a href="<?= e(admin_url('pages.php?edit=' . (string)$row['id'])) ?>">編集</a></td>
      </tr>
    <?php endforeach; ?>
  </table>

  <?php if (is_array($edit)): ?>
  <h2>固定ページ編集: <?= e((string)$edit['slug']) ?></h2>
  <form method="post">
    <?= csrf_input() ?>
    <input type="hidden" name="id" value="<?= e((string)$edit['id']) ?>">
    <label>タイトル<input name="title" value="<?= e((string)$edit['title']) ?>" required></label>
    <label>本文<textarea name="body" rows="12" required><?= e((string)$edit['body']) ?></textarea></label>
    <label>SEOタイトル<input name="seo_title" value="<?= e((string)($edit['seo_title'] ?? '')) ?>"></label>
    <label>SEO説明<textarea name="seo_description" rows="3"><?= e((string)($edit['seo_description'] ?? '')) ?></textarea></label>
    <label><input type="checkbox" name="is_published" value="1" <?= ((int)$edit['is_published'] === 1) ? 'checked' : '' ?>> 公開する</label>
    <div class="admin-actions"><button type="submit">更新</button></div>
  </form>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
