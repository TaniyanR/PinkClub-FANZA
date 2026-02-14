<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
admin_trace_push('page:start:pages.php');

$errors = [];
$ok = (string)($_GET['ok'] ?? '');
$okMessageMap = [
    'created' => '固定ページを作成しました。',
    'updated' => '固定ページを更新しました。',
    'deleted' => '固定ページを削除しました。',
];
$okMessage = $okMessageMap[$ok] ?? '';

$editId = (int)($_GET['edit'] ?? 0);
$isCreate = isset($_GET['new']) || $editId === 0;
$form = [
    'id' => 0,
    'slug' => '',
    'title' => '',
    'body' => '',
    'status' => 'draft',
];

if ($editId > 0) {
    $st = db()->prepare('SELECT id, slug, title, body, is_published FROM fixed_pages WHERE id=:id LIMIT 1');
    $st->execute([':id' => $editId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (is_array($row)) {
        $form = [
            'id' => (int)$row['id'],
            'slug' => (string)$row['slug'],
            'title' => (string)$row['title'],
            'body' => (string)$row['body'],
            'status' => ((int)$row['is_published'] === 1) ? 'published' : 'draft',
        ];
        $isCreate = false;
    } else {
        $errors[] = '指定された固定ページが見つかりません。';
        $editId = 0;
        $isCreate = true;
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!admin_post_csrf_valid()) {
        $errors[] = 'CSRFトークンが無効です。';
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'delete') {
            $deleteId = (int)($_POST['id'] ?? 0);
            if ($deleteId > 0) {
                db()->prepare('DELETE FROM fixed_pages WHERE id=:id')->execute([':id' => $deleteId]);
                header('Location: ' . admin_url('pages.php?ok=deleted'));
                exit;
            }
            $errors[] = '削除対象が不正です。';
        }

        if ($action === 'save') {
            $form = [
                'id' => (int)($_POST['id'] ?? 0),
                'slug' => trim((string)($_POST['slug'] ?? '')),
                'title' => trim((string)($_POST['title'] ?? '')),
                'body' => (string)($_POST['body'] ?? ''),
                'status' => ((string)($_POST['status'] ?? 'draft') === 'published') ? 'published' : 'draft',
            ];

            if ($form['slug'] === '') {
                $errors[] = 'slugは必須です。';
            } elseif (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $form['slug'])) {
                $errors[] = 'slugは半角英数字とハイフンのみ使用できます。';
            }
            if ($form['title'] === '') {
                $errors[] = 'タイトルは必須です。';
            }

            if ($errors === []) {
                try {
                    $params = [
                        ':slug' => $form['slug'],
                        ':title' => $form['title'],
                        ':body' => $form['body'],
                        ':pub' => $form['status'] === 'published' ? 1 : 0,
                    ];

                    if ($form['id'] > 0) {
                        db()->prepare('UPDATE fixed_pages SET slug=:slug,title=:title,body=:body,is_published=:pub,updated_at=NOW() WHERE id=:id')
                            ->execute($params + [':id' => $form['id']]);
                        header('Location: ' . admin_url('pages.php?ok=updated'));
                        exit;
                    }

                    db()->prepare('INSERT INTO fixed_pages(slug,title,body,is_published,created_at,updated_at) VALUES (:slug,:title,:body,:pub,NOW(),NOW())')
                        ->execute($params);
                    header('Location: ' . admin_url('pages.php?ok=created'));
                    exit;
                } catch (PDOException $e) {
                    if ((string)$e->getCode() === '23000') {
                        $errors[] = 'slugが重複しています。別のslugを指定してください。';
                    } else {
                        throw $e;
                    }
                }
            }

            $isCreate = $form['id'] === 0;
            $editId = $form['id'];
        }
    }
}

$rows = db()->query('SELECT id, slug, title, is_published, updated_at FROM fixed_pages ORDER BY updated_at DESC, id DESC')->fetchAll(PDO::FETCH_ASSOC);
$pageTitle = '固定ページ管理';
ob_start();
?>
<h1>固定ページ管理</h1>
<?php if ($okMessage !== '') : ?>
    <div class="admin-card"><p><?php echo e($okMessage); ?></p></div>
<?php endif; ?>
<?php if ($errors !== []) : ?>
    <div class="admin-card">
        <?php foreach ($errors as $error) : ?>
            <p><?php echo e((string)$error); ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="admin-card">
    <h2><?php echo $isCreate ? '固定ページ新規作成' : '固定ページ編集'; ?></h2>
    <form method="post">
        <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?php echo e((string)$form['id']); ?>">

        <label>タイトル</label>
        <input name="title" required value="<?php echo e((string)$form['title']); ?>">

        <label>slug</label>
        <input name="slug" required value="<?php echo e((string)$form['slug']); ?>" placeholder="about-us">

        <label>ステータス</label>
        <select name="status">
            <option value="draft" <?php echo ($form['status'] === 'draft') ? 'selected' : ''; ?>>下書き</option>
            <option value="published" <?php echo ($form['status'] === 'published') ? 'selected' : ''; ?>>公開</option>
        </select>

        <label>本文</label>
        <textarea name="body" rows="12"><?php echo e((string)$form['body']); ?></textarea>

        <button type="submit">保存</button>
        <?php if (!$isCreate) : ?>
            <a href="<?php echo e(admin_url('pages.php?new=1')); ?>">新規作成に切り替え</a>
        <?php endif; ?>
    </form>
</div>

<div class="admin-card">
    <table class="admin-table">
        <thead>
        <tr><th>ID</th><th>タイトル</th><th>slug</th><th>status</th><th>更新日</th><th>操作</th></tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row) : ?>
            <tr>
                <td><?php echo e((string)$row['id']); ?></td>
                <td><?php echo e((string)$row['title']); ?></td>
                <td><?php echo e((string)$row['slug']); ?></td>
                <td><?php echo ((int)$row['is_published'] === 1) ? 'published' : 'draft'; ?></td>
                <td><?php echo e((string)$row['updated_at']); ?></td>
                <td>
                    <a href="<?php echo e(admin_url('pages.php?edit=' . (string)$row['id'])); ?>">編集</a>
                    <a href="<?php echo e(base_url() . '/page.php?slug=' . rawurlencode((string)$row['slug'])); ?>" target="_blank" rel="noopener">表示</a>
                    <form method="post" style="display:inline" onsubmit="return confirm('この固定ページを削除しますか？');">
                        <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo e((string)$row['id']); ?>">
                        <button type="submit">削除</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if ($rows === []) : ?>
            <tr>
                <td colspan="6">固定ページはまだありません。上のフォームから新規作成してください。</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php
$main = (string)ob_get_clean();
require_once __DIR__ . '/_page.php';
admin_render($pageTitle, static function () use ($main): void {
    echo $main;
});
