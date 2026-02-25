<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/auth.php';
require_admin();
$tables = ['items','actresses','genres','makers','series_master','authors'];
$counts = [];
foreach ($tables as $t) { $counts[$t] = (int)db()->query("SELECT COUNT(*) FROM {$t}")->fetchColumn(); }
$logs = db()->query('SELECT * FROM sync_logs ORDER BY id DESC LIMIT 20')->fetchAll();
include __DIR__ . '/includes/header.php';
?>
<div class="grid">
<?php foreach ($counts as $k=>$v): ?><div class="card"><strong><?= e($k) ?></strong><div><?= e((string)$v) ?></div></div><?php endforeach; ?>
</div>
<div class="card"><h2>最近の同期ログ</h2><table><tr><th>ID</th><th>Type</th><th>Status</th><th>Fetched</th><th>Saved</th><th>Message</th><th>Created</th></tr>
<?php foreach ($logs as $log): ?><tr><td><?= e((string)$log['id']) ?></td><td><?= e($log['sync_type']) ?></td><td><?= e($log['status']) ?></td><td><?= e((string)$log['fetched_count']) ?></td><td><?= e((string)$log['saved_count']) ?></td><td><?= e($log['message']) ?></td><td><?= e($log['created_at']) ?></td></tr><?php endforeach; ?>
</table></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
