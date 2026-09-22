<?php
declare(strict_types=1);
require_once __DIR__ . '/../public/_bootstrap.php';
auth_require_admin();
require_once __DIR__ . '/../lib/search_lifecycle.php';
$title = 'SEO・IndexNow';
$message = '';
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_validate_or_fail((string)post('_csrf', ''));
    try {
        $action = (string)post('action', '');
        if ($action === 'install') {
            $result = installer_run();
            if (empty($result['success'])) throw new RuntimeException('DB更新に失敗しました。セットアップ画面のログを確認してください。');
            $message = '既存データを保持してDBを更新しました。';
        } elseif ($action === 'enable') {
            if (!db_table_exists('indexnow_queue')) throw new RuntimeException('先にDB更新を実行してください。');
            if ((string)post('confirmed_origin', '') !== pcf_indexnow_origin()) throw new RuntimeException('送信対象サイトを確認してください。');
            if (pcf_indexnow_key() === '') setting_set('indexnow.key', bin2hex(random_bytes(16)));
            setting_set('indexnow.origin', pcf_indexnow_origin());
            setting_set('indexnow.enabled', '1');
            $message = 'このサイトのIndexNowを有効にしました。更新されたURLを順次送信します。';
        } elseif ($action === 'disable') {
            setting_set('indexnow.enabled', '0');
            $message = '自動送信を停止しました。送信待ちURLは保持しています。';
        } elseif ($action === 'backfill') {
            $message = pcf_indexnow_backfill() . '件を送信待ちに追加しました。0件なら既存作品の登録は完了です。';
        } elseif ($action === 'send') {
            $result = pcf_indexnow_dispatch();
            $message = '送信結果: ' . $result['status'] . ' / ' . $result['count'] . '件 / HTTP ' . ($result['http'] ?? '-');
        } elseif ($action === 'gone') {
            pcf_item_mark_gone((int)post('item_id', 0), (string)post('reason', ''));
            $message = '掲載終了（410）に設定しました。元の商品データは保持しています。';
        } elseif ($action === 'restore') {
            pcf_item_restore((int)post('item_id', 0));
            $message = '掲載終了設定を解除しました。';
        }
    } catch (Throwable $e) {
        error_log('Search settings action failed: ' . $e->getMessage());
        $error = $e instanceof InvalidArgumentException || $e instanceof RuntimeException && !$e instanceof PDOException
            ? $e->getMessage() : '処理に失敗しました。DB更新状況とサーバーログを確認してください。';
    }
}
$ready = db_table_exists('indexnow_queue') && db_table_exists('item_tombstones');
$pending = 0;
$gone = [];
if ($ready) {
    $stmt = db()->prepare('SELECT COUNT(*) FROM indexnow_queue WHERE origin=?');
    $stmt->execute([pcf_indexnow_origin()]);
    $pending = (int)$stmt->fetchColumn();
    $gone = db()->query('SELECT item_id,content_id,reason,removed_at FROM item_tombstones ORDER BY removed_at DESC LIMIT 50')->fetchAll(PDO::FETCH_ASSOC);
}
$last = json_decode((string)setting_get('indexnow.last_result', ''), true);
require __DIR__ . '/includes/header.php';
?>
<section class="card">
<h1>SEO・IndexNow</h1>
<?php if ($message !== ''): ?><p role="status"><?= e($message) ?></p><?php endif; ?>
<?php if ($error !== ''): ?><p role="alert"><?= e($error) ?></p><?php endif; ?>
<?php if (!$ready): ?>
<p>この機能の初回利用時はDB更新が必要です。商品や管理者のデータを保持して追加テーブルを作成します。</p>
<form method="post"><?= csrf_input() ?><button name="action" value="install">DBを更新する</button></form>
<?php else: ?>
<h2>IndexNow</h2>
<p>送信対象：<?= e(pcf_indexnow_origin()) ?> / <?= pcf_indexnow_enabled() ? '有効' : '停止中' ?> / 送信待ち：<?= $pending ?>件</p>
<p>本番サイトで有効にしてください。テストサイトでは、確認のために送信する場合だけ有効にします。DBを別ドメインへコピーした場合は自動送信を停止します。</p>
<form method="post"><?= csrf_input() ?>
<label><input type="checkbox" name="confirmed_origin" value="<?= e(pcf_indexnow_origin()) ?>" required>このサイトのURLを検索エンジンへ通知する</label>
<button name="action" value="enable">このサイトで有効にする</button></form>
<form method="post"><?= csrf_input() ?><button name="action" value="disable">送信を停止</button> <button name="action" value="send">送信待ちを処理（最大1,000件）</button></form>
<p>通常は既存のcronによる自動更新時に送信します。送信失敗時は間隔を空けて再送します。200は受信済み、202はキー検証待ちで、検索への掲載を保証するものではありません。</p>
<?php if (pcf_indexnow_enabled()): ?><p><a href="<?= e(pcf_indexnow_origin() . '/indexnow-key.php') ?>" target="_blank" rel="noopener">所有権確認ファイルを開く</a>（文字列だけが表示されれば正常です）</p><?php endif; ?>
<?php if (is_array($last)): ?><p>最終送信：<?= e((string)($last['at'] ?? '')) ?> / HTTP <?= (int)($last['http'] ?? 0) ?> / <?= (int)($last['count'] ?? 0) ?>件</p><?php endif; ?>
<form method="post"><?= csrf_input() ?><button name="action" value="backfill">既存作品の次の1,000件を送信待ちに追加</button></form>
<p>導入前の作品も通知したい場合に使います。繰り返すと続きから処理し、全件を一度に送信しません。</p>
<h2>確認済みの配信終了・掲載終了</h2>
<p>提供元の配信終了、または当サイトから恒久的に掲載を終了すると確認できた作品だけ登録してください。APIの取得失敗や未登録という理由では登録しません。</p>
<form method="post"><?= csrf_input() ?>
<label>商品ID <input name="item_id" type="number" min="1" required></label>
<label>確認した理由 <input name="reason" maxlength="500" required></label>
<button name="action" value="gone">掲載終了にする（410）</button></form>
<ul><?php foreach ($gone as $row): ?>
<li><?= (int)$row['item_id'] ?> / <?= e($row['content_id']) ?> / <?= e($row['reason']) ?>
<form method="post"><?= csrf_input() ?><input type="hidden" name="item_id" value="<?= (int)$row['item_id'] ?>"><button name="action" value="restore">掲載終了を解除</button></form></li>
<?php endforeach; ?></ul>
<?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
