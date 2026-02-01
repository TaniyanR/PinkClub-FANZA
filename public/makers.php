<?php
require_once __DIR__ . '/../lib/repository.php';

$makers = fetch_makers(50);

include __DIR__ . '/partials/header.php';
?>
<main>
    <h1>メーカー一覧</h1>
    <table class="table">
        <tr><th>ID</th><th>名前</th></tr>
        <?php foreach ($makers as $maker) : ?>
            <tr>
                <td><?php echo htmlspecialchars((string)($maker['maker_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><a href="/maker.php?id=<?php echo urlencode((string)($maker['maker_id'] ?? '')); ?>"><?php echo htmlspecialchars($maker['name'], ENT_QUOTES, 'UTF-8'); ?></a></td>
            </tr>
        <?php endforeach; ?>
    </table>
</main>
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<?php include __DIR__ . '/partials/footer.php'; ?>
