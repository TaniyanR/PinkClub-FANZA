<?php
$pageStyles = ['/assets/css/actresses.css'];
$pageScripts = ['/assets/js/actresses.js'];
include __DIR__ . '/partials/header.php';
?>
    <!-- partial: search -->
    <section class="search-bar">
        <div class="search-bar-inner">
            <strong class="search-note">当サイトはアフィリエイト広告を使用しています。</strong>
            <form action="#" method="get">
                <input type="text" name="s" placeholder="女優名で検索">
                <button type="submit">検索</button>
            </form>
        </div>
    </section>

    <div class="layout">
        <!-- partial: sidebar -->
        <aside class="sidebar actresses-sidebar">
            <div class="sidebar-block">
                <h3>人気女優（ダミー）</h3>
                <ul class="sidebar-actress-list">
                    <li>
                        <a href="#" class="sidebar-actress">
                            <span class="sidebar-actress-thumb">
                                <img src="https://via.placeholder.com/80x110" alt="サンプル女優 1">
                            </span>
                            <span class="sidebar-actress-name">芹沢あや</span>
                        </a>
                    </li>
                    <li>
                        <a href="#" class="sidebar-actress">
                            <span class="sidebar-actress-thumb">
                                <img src="https://via.placeholder.com/80x110" alt="サンプル女優 2">
                            </span>
                            <span class="sidebar-actress-name">羽月みさ</span>
                        </a>
                    </li>
                    <li>
                        <a href="#" class="sidebar-actress">
                            <span class="sidebar-actress-thumb">
                                <img src="https://via.placeholder.com/80x110" alt="サンプル女優 3">
                            </span>
                            <span class="sidebar-actress-name">白石ゆり</span>
                        </a>
                    </li>
                    <li>
                        <a href="#" class="sidebar-actress">
                            <span class="sidebar-actress-thumb">
                                <img src="https://via.placeholder.com/80x110" alt="サンプル女優 4">
                            </span>
                            <span class="sidebar-actress-name">天海りお</span>
                        </a>
                    </li>
                    <li>
                        <a href="#" class="sidebar-actress">
                            <span class="sidebar-actress-thumb">
                                <img src="https://via.placeholder.com/80x110" alt="サンプル女優 5">
                            </span>
                            <span class="sidebar-actress-name">星野さくら</span>
                        </a>
                    </li>
                </ul>
            </div>
            <div class="sidebar-block">
                <h3>広告枠</h3>
                <div class="ad-box" aria-label="広告枠">300x250</div>
            </div>
        </aside>

        <main class="main-content">
            <!-- partial: breadcrumb -->
            <nav class="breadcrumb block" aria-label="パンくず">
                <a href="/">Home</a>
                <span>›</span>
                <a href="#">女優</a>
                <span>›</span>
                <span>女優一覧</span>
            </nav>

            <!-- partial: actresses_title -->
            <section class="block actresses-title">
                <h1>女優一覧</h1>
                <p class="actresses-lead">人気女優・新人女優を一覧で探せるディレクトリページです。</p>
            </section>

            <!-- partial: actresses_controls -->
            <section class="block actresses-controls">
                <div class="actresses-controls-row">
                    <label class="control-input">
                        女優名検索
                        <input type="text" placeholder="例：さくら" data-actress-search>
                    </label>
                    <label class="control-select">
                        並び替え
                        <select data-actress-sort>
                            <option value="popular">人気</option>
                            <option value="new">新着</option>
                        </select>
                    </label>
                    <label class="control-select">
                        表示件数
                        <select data-actress-limit>
                            <option value="24" selected>24</option>
                            <option value="48">48</option>
                        </select>
                    </label>
                </div>
                <div class="actresses-count">
                    <span>表示中：</span>
                    <strong data-actress-count>0</strong>
                    <span>件</span>
                </div>
            </section>

            <!-- partial: actresses_grid -->
            <section class="block actresses-grid-wrapper">
                <div class="actresses-grid" data-actress-grid></div>
            </section>

            <!-- partial: pagination -->
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
