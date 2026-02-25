<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/dmm_sync_service.php';
require_admin();

$result = null; $error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $article = array_filter(array_map('trim', explode(',', (string)($_POST['article'] ?? ''))));
        $articleId = array_filter(array_map('trim', explode(',', (string)($_POST['article_id'] ?? ''))));
        $params = [
            'site' => get_setting('default_site','FANZA'),'service'=>get_setting('default_service','digital'),'floor'=>get_setting('default_floor','videoa'),
            'keyword' => trim((string)($_POST['keyword'] ?? '')),
            'sort' => trim((string)($_POST['sort'] ?? 'date')),
            'gte_date' => trim((string)($_POST['gte_date'] ?? '')),
            'lte_date' => trim((string)($_POST['lte_date'] ?? '')),
            'hits' => (int)($_POST['hits'] ?? 20),
            'offset' => (int)($_POST['offset'] ?? 1),
        ];
        if ($article) $params['article'] = $article;
        if ($articleId) $params['article_id'] = $articleId;
        $result = (new DmmSyncService())->syncItems($params);
    } catch (Throwable $e) { $error = $e->getMessage(); }
}
include __DIR__ . '/includes/header.php';
?>
<div class="card"><h1>商品同期</h1>
<?php if ($result): ?><div class="alert success"><?= e(json_encode($result, JSON_UNESCAPED_UNICODE)) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<form method="post"><?= csrf_input() ?>
<label>keyword<input name="keyword"></label>
<label>sort<input name="sort" value="date"></label>
<label>gte_date<input name="gte_date" placeholder="2024-01-01T00:00:00"></label>
<label>lte_date<input name="lte_date" placeholder="2024-12-31T23:59:59"></label>
<label>article(複数はカンマ区切り)<input name="article" placeholder="genre,actress"></label>
<label>article_id(複数はカンマ区切り)<input name="article_id" placeholder="6003,1054998"></label>
<label>hits<input type="number" name="hits" value="20"></label>
<label>offset<input type="number" name="offset" value="1"></label>
<button type="submit">同期実行</button>
</form></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
