<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/repository.php';
require_once __DIR__ . '/../../lib/admin_auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/url.php';

$sidebarGenres = fetch_genres(8, 0);
$sidebarMakers = fetch_makers(8, 0);
$sidebarSeries = fetch_series(8, 0);
?>
<aside class="sidebar">
    <div class="sidebar-block">
        <h3>管理</h3>
        <?php if (function_exists('admin_current_user') && admin_current_user() !== null) : ?>
            <ul>
                <li><a href="<?php echo e(admin_url('settings.php')); ?>">管理設定</a></li>
                <li><a href="<?php echo e(admin_url('change_password.php')); ?>">パスワード変更</a></li>
            </ul>
            <form method="post" action="<?php echo e(admin_url('logout.php')); ?>">
                <?php if (function_exists('csrf_token')) : ?><input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>"><?php endif; ?>
                <button type="submit">ログアウト</button>
            </form>
        <?php else : ?>
            <ul>
                <li><a href="<?php echo e(login_url()); ?>">管理ログイン</a></li>
            </ul>
        <?php endif; ?>
    </div>

    <div class="sidebar-block">
        <h3>クイックメニュー</h3>
        <ul>
            <li><a href="/posts.php">作品一覧</a></li>
            <li><a href="/actresses.php">女優一覧</a></li>
            <li><a href="/series.php">シリーズ一覧</a></li>
            <li><a href="/makers.php">メーカー一覧</a></li>
            <li><a href="/genres.php">ジャンル一覧</a></li>
        </ul>
    </div>

    <div class="sidebar-block">
        <h3>ジャンル</h3>
        <ul>
            <?php foreach ($sidebarGenres as $genre) : ?>
                <li><a href="/genre.php?id=<?php echo urlencode((string)$genre['id']); ?>"><?php echo e((string)$genre['name']); ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="sidebar-block">
        <h3>メーカー</h3>
        <ul>
            <?php foreach ($sidebarMakers as $maker) : ?>
                <li><a href="/maker.php?id=<?php echo urlencode((string)$maker['id']); ?>"><?php echo e((string)$maker['name']); ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="sidebar-block">
        <h3>シリーズ</h3>
        <ul>
            <?php foreach ($sidebarSeries as $series) : ?>
                <li><a href="/series_one.php?id=<?php echo urlencode((string)$series['id']); ?>"><?php echo e((string)$series['name']); ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="sidebar-block">
        <h3>広告枠</h3>
        <div class="ad-box ad-box--sidebar" aria-label="広告枠">300x250</div>
    </div>
</aside>
