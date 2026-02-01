<?php
require_once __DIR__ . '/../lib/repository.php';

$new3 = fetch_items('date_published DESC', 3);
$new10 = fetch_items('date_published DESC', 10);
$pickup10 = fetch_items('RAND()', 10);

$genresSets = [];
for ($i = 0; $i < 3; $i++) {
    $genresSets[] = [
        'title' => 'ジャンルレール ' . chr(65 + $i),
        'items' => fetch_genres(10, 'RAND()'),
    ];
}

$seriesSets = [];
for ($i = 0; $i < 3; $i++) {
    $seriesSets[] = [
        'title' => 'シリーズレール ' . chr(65 + $i),
        'items' => fetch_series(10, 'RAND()'),
    ];
}

$makerSets = [];
for ($i = 0; $i < 3; $i++) {
    $makerSets[] = [
        'title' => 'メーカーレール ' . chr(65 + $i),
        'items' => fetch_makers(10, 'RAND()'),
    ];
}

include __DIR__ . '/partials/header.php';
?>
<main>
    <?php include __DIR__ . '/partials/block_new3.php'; ?>

    <?php
    $railTitle = '新着レール';
    $railItems = $new10;
    include __DIR__ . '/partials/block_rail.php';
    ?>

    <?php
    $railTitle = 'ピックアップレール';
    $railItems = $pickup10;
    include __DIR__ . '/partials/block_rail.php';
    ?>

    <?php
    $taxonomySets = $genresSets;
    include __DIR__ . '/partials/block_taxonomy_rails.php';
    ?>

    <?php
    $taxonomySets = $seriesSets;
    include __DIR__ . '/partials/block_taxonomy_rails.php';
    ?>

    <?php
    $taxonomySets = $makerSets;
    include __DIR__ . '/partials/block_taxonomy_rails.php';
    ?>
</main>
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<?php include __DIR__ . '/partials/footer.php'; ?>
