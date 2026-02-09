<aside class="sidebar">
    <?php if (function_exists('admin_current_user') && admin_current_user() !== null) : ?>
        <div class="sidebar-block">
            <h3>管理</h3>
            <form method="post" action="/admin/logout.php">
                <?php if (function_exists('csrf_token')) : ?>
                    <input type="hidden" name="_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8'); ?>">
                <?php endif; ?>
                <button type="submit">ログアウト</button>
            </form>
        </div>
    <?php endif; ?>
    <div class="sidebar-block">
        <h3>クイックメニュー</h3>
        <ul>
            <li><a href="/posts.php">記事一覧</a></li>
            <li><a href="/actresses.php">女優一覧</a></li>
            <li><a href="/series.php">シリーズ一覧</a></li>
            <li><a href="/makers.php">メーカー一覧</a></li>
            <li><a href="/labels.php">レーベル一覧</a></li>
            <li><a href="/genres.php">ジャンル一覧</a></li>
        </ul>
    </div>
    <div class="sidebar-block">
        <h3>注目コンテンツ</h3>
        <p>更新予定のコンテンツ枠。後から差し替えやすいダミー文です。</p>
    </div>
    <div class="sidebar-block">
        <h3>広告枠</h3>
        <div class="ad-box ad-box--sidebar" aria-label="広告枠">300x250</div>
    </div>
</aside>
