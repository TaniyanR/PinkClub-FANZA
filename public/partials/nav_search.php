<?php
declare(strict_types=1);

$path = (string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$current = basename($path) ?: 'index.php';
if ($path === '/' || $path === '') {
    $current = 'index.php';
}

$navItems = [
    'index.php' => 'TOP',
    'posts.php' => '作品一覧',
    'actresses.php' => '女優一覧',
    'series.php' => 'シリーズ一覧',
    'makers.php' => 'メーカー一覧',
    'genres.php' => 'ジャンル一覧',
];
?>
<section class="nav-search">
    <div class="nav-search__inner">
        <nav class="nav-search__menu" aria-label="メインメニュー">
            <ul>
                <?php foreach ($navItems as $file => $label) : ?>
                    <li><a class="nav-link<?php echo $current === $file ? ' is-active' : ''; ?>" href="/<?php echo e($file); ?>"><?php echo e($label); ?></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <form class="nav-search__form" method="get" action="/posts.php">
            <input type="text" name="q" value="<?php echo e((string)($_GET['q'] ?? '')); ?>" placeholder="作品名で検索">
            <button type="submit">検索</button>
        </form>
    </div>
</section>
