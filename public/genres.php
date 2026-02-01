<?php
require_once __DIR__ . '/../lib/repository.php';

$genres = fetch_genres(50);

include __DIR__ . '/partials/header.php';
?>
<main>
    <h1>ジャンル一覧</h1>
    <table class="table">
        <tr><th>ID</th><th>名前</th></tr>
        <?php foreach ($genres as $genre) : ?>
            <tr>
                <td><?php echo htmlspecialchars((string)($genre['genre_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><a href="/genre.php?id=<?php echo urlencode((string)($genre['genre_id'] ?? '')); ?>"><?php echo htmlspecialchars($genre['name'], ENT_QUOTES, 'UTF-8'); ?></a></td>
            </tr>
        <?php endforeach; ?>
    </table>
</main>
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<?php include __DIR__ . '/partials/footer.php'; ?>
