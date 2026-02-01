<?php
require_once __DIR__ . '/../lib/repository.php';

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;
$actresses = fetch_actresses($limit, $offset);

include __DIR__ . '/partials/header.php';
?>
<main>
    <h1>女優一覧</h1>
    <table class="table">
        <tr><th>ID</th><th>名前</th></tr>
        <?php foreach ($actresses as $actress) : ?>
            <tr>
                <td><?php echo htmlspecialchars((string)$actress['id'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><a href="/actress.php?id=<?php echo urlencode((string)$actress['id']); ?>"><?php echo htmlspecialchars($actress['name'], ENT_QUOTES, 'UTF-8'); ?></a></td>
            </tr>
        <?php endforeach; ?>
    </table>
    <div class="pagination">
        <a href="?page=<?php echo $page - 1; ?>">前へ</a>
        <a href="?page=<?php echo $page + 1; ?>">次へ</a>
    </div>
</main>
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<?php include __DIR__ . '/partials/footer.php'; ?>
