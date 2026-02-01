<?php
require_once __DIR__ . '/../lib/repository.php';

$series = fetch_series(50);

include __DIR__ . '/partials/header.php';
?>
<main>
    <h1>シリーズ一覧</h1>
    <table class="table">
        <tr><th>ID</th><th>名前</th></tr>
        <?php foreach ($series as $seriesRow) : ?>
            <tr>
                <td><?php echo htmlspecialchars((string)($seriesRow['series_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><a href="/series_item.php?id=<?php echo urlencode((string)($seriesRow['series_id'] ?? '')); ?>"><?php echo htmlspecialchars($seriesRow['name'], ENT_QUOTES, 'UTF-8'); ?></a></td>
            </tr>
        <?php endforeach; ?>
    </table>
</main>
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<?php include __DIR__ . '/partials/footer.php'; ?>
