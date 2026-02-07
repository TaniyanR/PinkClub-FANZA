<?php
declare(strict_types=1);

$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$normalizedPath = trim($path, '/');
$normalizedKey = $normalizedPath === '' ? 'index' : $normalizedPath;
$normalizedKey = preg_replace('/\.php$/', '', $normalizedKey) ?? $normalizedKey;

$navItems = [
    'index.php' => 'TOP',
    'posts.php' => '記事一覧',
    'actresses.php' => '女優一覧',
    'series.php' => 'シリーズ一覧',
    'makers.php' => 'メーカー一覧',
    'labels.php' => 'レーベル一覧',
    'genres.php' => 'ジャンル一覧',
];

$aliases = [
    'index' => 'index.php',
    'posts' => 'posts.php',
    'actresses' => 'actresses.php',
    'series' => 'series.php',
    'makers' => 'makers.php',
    'labels' => 'labels.php',
    'genres' => 'genres.php',
];

$current = $aliases[$normalizedKey] ?? basename($path);
if ($current === '') {
    $current = 'index.php';
}
?>
<section class="nav-search">
    <div class="nav-search__inner">
        <nav class="nav-search__menu" aria-label="メインメニュー">
            <ul>
                <?php foreach ($navItems as $file => $label) : ?>
                    <?php $isActive = $current === $file; ?>
                    <li>
                        <a class="nav-link<?php echo $isActive ? ' is-active' : ''; ?>" href="/<?php echo htmlspecialchars($file, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <form class="nav-search__form" method="get" action="/posts.php">
            <input type="text" name="q" placeholder="作品名・女優名で検索">
            <button type="submit">検索</button>
        </form>
    </div>
</section>
