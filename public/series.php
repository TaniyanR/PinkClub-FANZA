<?php
$pageStyles = ['/assets/css/series.css'];
$pageScripts = ['/assets/js/series.js'];
include __DIR__ . '/partials/header.php';
?>
    <!-- partial: search -->
    <section class="search-bar">
        <div class="search-bar-inner">
            <strong class="search-note">当サイトはアフィリエイト広告を使用しています。</strong>
            <form action="#" method="get">
                <input type="text" name="s" placeholder="シリーズ名・キーワードで検索">
                <button type="submit">検索</button>
            </form>
        </div>
    </section>

    <div class="layout series-layout">
        <!-- partial: series_sidebar -->
        <aside class="sidebar series-sidebar">
            <div class="sidebar-block">
                <h3>人気シリーズ</h3>
                <ul class="series-mini-list">
                    <li>
                        <a href="/series_one.php">
                            <span class="series-mini-thumb">No image</span>
                            <span class="series-mini-name">ときめき初体験</span>
                        </a>
                    </li>
                    <li>
                        <a href="/series_one.php">
                            <span class="series-mini-thumb">No image</span>
                            <span class="series-mini-name">ベッドルームアワー</span>
                        </a>
                    </li>
                    <li>
                        <a href="/series_one.php">
                            <span class="series-mini-thumb">No image</span>
                            <span class="series-mini-name">甘やかし休日デート</span>
                        </a>
                    </li>
                    <li>
                        <a href="/series_one.php">
                            <span class="series-mini-thumb">No image</span>
                            <span class="series-mini-name">シークレットトラベル</span>
                        </a>
                    </li>
                    <li>
                        <a href="/series_one.php">
                            <span class="series-mini-thumb">No image</span>
                            <span class="series-mini-name">朝までコース</span>
                        </a>
                    </li>
                    <li>
                        <a href="/series_one.php">
                            <span class="series-mini-thumb">No image</span>
                            <span class="series-mini-name">温泉リトリート</span>
                        </a>
                    </li>
                </ul>
            </div>
            <div class="sidebar-block">
                <h3>広告枠</h3>
                <div class="ad-box">300x250</div>
            </div>
        </aside>

        <main class="main-content series-main">
            <!-- partial: series_breadcrumb -->
            <nav class="breadcrumb block" aria-label="パンくず">
                <a href="/">Home</a>
                <span>›</span>
                <a href="#">カテゴリ</a>
                <span>›</span>
                <span>シリーズ一覧</span>
            </nav>

            <!-- partial: series_head -->
            <section class="block series-head">
                <h1>シリーズ一覧</h1>
                <p class="series-lead">人気シリーズから新着シリーズまで、注目ラインナップをまとめてご紹介します。</p>
            </section>

            <!-- partial: series_controls -->
            <section class="block series-controls">
                <div class="series-count">表示中: <span data-series-total>0件</span></div>
                <div class="series-control-grid">
                    <label>
                        シリーズ名検索
                        <input type="search" placeholder="シリーズ名で絞り込み" data-series-search>
                    </label>
                    <label>
                        並び替え
                        <select data-series-sort>
                            <option value="popular">人気</option>
                            <option value="new">新着</option>
                        </select>
                    </label>
                    <label>
                        表示件数
                        <select data-series-count>
                            <option value="24" selected>24</option>
                            <option value="48">48</option>
                        </select>
                    </label>
                </div>
            </section>

            <!-- partial: series_grid -->
            <section class="block">
                <div class="series-grid" data-series-grid></div>
            </section>

            <!-- partial: series_pagination -->
            <nav class="pagination block" aria-label="ページネーション">
                <a href="#" class="page-btn">前へ</a>
                <div class="page-numbers">
                    <a href="#" class="page-btn is-current">1</a>
                    <a href="#" class="page-btn">2</a>
                    <a href="#" class="page-btn">3</a>
                    <span class="page-ellipsis">…</span>
                    <a href="#" class="page-btn">10</a>
                </div>
                <a href="#" class="page-btn">次へ</a>
            </nav>
        </main>
    </div>
<?php include __DIR__ . '/partials/footer.php'; ?>
