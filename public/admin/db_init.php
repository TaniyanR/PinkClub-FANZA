<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../scripts/init_db.php';

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

$status = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $result = init_db();
        $status = sprintf('DB初期化が完了しました。（%s使用: %dステートメント）', $result['source'], $result['count']);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

include __DIR__ . '/../partials/header.php';
?>
<main>
    <h1>DB初期化</h1>

    <?php if ($status !== '') : ?>
        <div class="admin-card">
            <p><?php echo e($status); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($error !== '') : ?>
        <div class="admin-card">
            <p>エラーが発生しました: <?php echo e($error); ?></p>
        </div>
    <?php endif; ?>

    <form class="admin-card" method="post" action="/admin/db_init.php">
        <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
        <button type="submit">DB初期化</button>
    </form>
</main>
<?php include __DIR__ . '/../partials/sidebar.php'; ?>
<?php include __DIR__ . '/../partials/footer.php'; ?>
