<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
admin_trace_push('page:start:pages.php');

$error='';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!admin_post_csrf_valid()) {
        $error='CSRFトークンが無効です。';
    } else {
        $action=(string)($_POST['action'] ?? '');
        if ($action==='save') {
            $id=(int)($_POST['id'] ?? 0);
            $params=[':slug'=>trim((string)$_POST['slug']), ':title'=>trim((string)$_POST['title']), ':body'=>(string)($_POST['body'] ?? ''), ':pub'=>isset($_POST['is_published'])?1:0];
            db()->prepare('UPDATE fixed_pages SET slug=:slug,title=:title,body=:body,is_published=:pub,updated_at=NOW() WHERE id=:id')->execute($params+[':id'=>$id]);
            admin_flash_set('ok','固定ページを更新しました。');
        } elseif ($action==='delete') {
            db()->prepare('DELETE FROM fixed_pages WHERE id=:id')->execute([':id'=>(int)$_POST['id']]);
            admin_flash_set('ok','削除しました。');
        }
        header('Location: '.admin_url('pages.php')); exit;
    }
}
$editId=(int)($_GET['edit'] ?? 0);
$edit=['id'=>0,'slug'=>'','title'=>'','body'=>'','is_published'=>1];
if($editId>0){$st=db()->prepare('SELECT * FROM fixed_pages WHERE id=:id');$st->execute([':id'=>$editId]);$tmp=$st->fetch(PDO::FETCH_ASSOC);if(is_array($tmp)){$edit=$tmp;}}
$rows=db()->query('SELECT * FROM fixed_pages ORDER BY updated_at DESC')->fetchAll(PDO::FETCH_ASSOC);
$ok=admin_flash_get('ok');
$pageTitle='固定ページ編集'; ob_start(); ?>
<h1>固定ページ編集</h1>
<?php if($ok!==''): ?><div class="admin-card"><p><?php echo e($ok); ?></p></div><?php endif; ?>
<?php if($error!==''): ?><div class="admin-card"><p><?php echo e($error); ?></p></div><?php endif; ?>

<?php if ((int)$edit['id'] > 0): ?>
<div class="admin-card">
    <h2>ID <?php echo e((string)$edit['id']); ?> の編集</h2>
    <form method="post">
        <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?php echo e((string)$edit['id']); ?>">
        <label>slug</label><input name="slug" required value="<?php echo e((string)$edit['slug']); ?>">
        <label>タイトル</label><input name="title" required value="<?php echo e((string)$edit['title']); ?>">
        <label>本文</label><textarea name="body" rows="10"><?php echo e((string)$edit['body']); ?></textarea>
        <label><input type="checkbox" name="is_published" value="1" <?php echo ((int)$edit['is_published']===1)?'checked':''; ?>> 公開</label>
        <button>保存</button>
    </form>
</div>
<?php endif; ?>

<div class="admin-card"><table class="admin-table"><thead><tr><th>ID</th><th>slug</th><th>タイトル</th><th>公開</th><th>公開URL</th><th></th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?php echo e((string)$r['id']); ?></td><td><?php echo e((string)$r['slug']); ?></td><td><?php echo e((string)$r['title']); ?></td><td><?php echo ((int)$r['is_published']===1)?'ON':'OFF'; ?></td><td><?php $url = base_url().'/page.php?slug='.(string)$r['slug']; ?><a href="<?php echo e($url); ?>" target="_blank" rel="noopener"><?php echo e($url); ?></a></td><td><a href="<?php echo e(admin_url('pages.php?edit='.(string)$r['id'])); ?>">編集</a><form method="post" style="display:inline"><input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo e((string)$r['id']); ?>"><button>削除</button></form></td></tr><?php endforeach; ?><?php if($rows===[]): ?><tr><td colspan="6">固定ページなし</td></tr><?php endif; ?></tbody></table></div>
<?php $main = (string)ob_get_clean();
require_once __DIR__ . '/_page.php';
admin_render($pageTitle, static function () use ($main): void {
    echo $main;
});
