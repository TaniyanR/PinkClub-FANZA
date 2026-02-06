<?php
$pageStyles = ['/assets/css/list.css'];
$pageScripts = ['/assets/js/list.js'];
include __DIR__ . '/partials/header.php';
?>
    <!-- partial: search -->
    <section class="search-bar">
        <div class="search-bar-inner">
            <strong class="search-note">当サイトはアフィリエイト広告を使用しています。</strong>
            <form action="#" method="get">
                <input type="text" name="s" placeholder="作品名・女優名で検索">
                <button type="submit">検索</button>
            </form>
        </div>
    </section>

    <div class="layout">
        <!-- partial: sidebar -->
        <aside class="sidebar">
            <div class="sidebar-block">
                <h3>現在の条件（ダミー）</h3>
                <div class="condition-chips">
                    <span class="chip">ジャンル: 素人</span>
                    <span class="chip">メーカー: SAMPLE</span>
                </div>
            </div>
            <div class="sidebar-block">
                <h3>広告枠</h3>
                <div class="ad-box">300x250</div>
            </div>
        </aside>

        <main class="main-content">
            <!-- partial: list_breadcrumb -->
            <nav class="breadcrumb block" aria-label="パンくず">
                <a href="/">Home</a>
                <span>›</span>
                <a href="#">ジャンル</a>
                <span>›</span>
                <span>素人</span>
            </nav>

            <!-- partial: list_head -->
            <section class="block list-head">
                <div>
                    <p class="list-subtitle">明るい世界観でまとめた商品一覧ページのサンプルです。</p>
                    <h1>商品一覧（サンプル）</h1>
                </div>
            </section>

            <!-- partial: list_controls -->
            <section class="block list-controls">
                <div class="list-count">件数: <span data-list-count>0件</span></div>
                <div class="control-group">
                    <label>
                        並び替え
                        <select>
                            <option>人気</option>
                            <option>新着</option>
                            <option>価格安い</option>
                            <option>価格高い</option>
                        </select>
                    </label>
                    <label>
                        表示件数
                        <select>
                            <option>12</option>
                            <option selected>24</option>
                            <option>48</option>
                        </select>
                    </label>
                </div>
                <div class="pill-group" role="list">
                    <button class="pill is-active" type="button">すべて</button>
                    <button class="pill" type="button">VR</button>
                    <button class="pill" type="button">4K</button>
                    <button class="pill" type="button">巨乳</button>
                    <button class="pill" type="button">人妻</button>
                </div>
            </section>

            <!-- partial: list_grid -->
            <section class="block">
                <div class="list-grid" data-list-grid></div>
            </section>

            <!-- partial: list_pagination -->
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
