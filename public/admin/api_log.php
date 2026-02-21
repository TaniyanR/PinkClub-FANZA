<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

$logPath = dirname(__DIR__, 2) . '/storage/logs/api.log';
$maxLines = (int)($_GET['lines'] ?? 100);
if ($maxLines < 50) { $maxLines = 50; }
if ($maxLines > 200) { $maxLines = 200; }

$lines = [];
if (is_file($logPath) && is_readable($logPath)) {
    $all = @file($logPath, FILE_IGNORE_NEW_LINES);
    if (is_array($all)) {
        $lines = array_slice($all, -$maxLines);
        $lines = array_reverse($lines);
    }
}

$pageTitle = 'APIログ';
ob_start();
?>
<h1>APIログ</h1>
<div class="admin-card">
    <form method="get" action="<?php echo e(admin_url('api_log.php')); ?>">
        <label>表示行数
            <select name="lines">
                <?php foreach ([50, 100, 150, 200] as $n) : ?>
                    <option value="<?php echo e((string)$n); ?>" <?php echo $maxLines === $n ? 'selected' : ''; ?>><?php echo e((string)$n); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit">更新</button>
    </form>
    <p class="admin-form-note">ファイル: <?php echo e($logPath); ?></p>
    <?php if ($lines === []) : ?>
        <p>ログはまだありません。</p>
    <?php else : ?>
        <pre style="white-space: pre-wrap; word-break: break-word; max-height: 70vh; overflow: auto;"><?php echo e(implode("\n", $lines)); ?></pre>
    <?php endif; ?>
</div>
<?php
$content = (string)ob_get_clean();
include __DIR__ . '/../partials/admin_layout.php';
