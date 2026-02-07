<?php
declare(strict_types=1);

$pageStyles = ['/assets/css/detail.css'];
$pageScripts = ['/assets/js/detail.js'];

include __DIR__ . '/partials/header.php';
include __DIR__ . '/partials/nav_search.php';
?>
<div class="layout detail-layout">
    <!-- partial: sidebar -->
    <aside class="sidebar detail-sidebar">
        <div class="sidebar-block">
            <h3>この作品のメタ</h3>
            <ul class="meta-list">
                <li><a href="#">メーカー: Dummy Studio</a></li>
                <li><a href="#">シリーズ: Pink Luxe</a></li>
                <li><a href="#">ジャンル: ラブロマンス</a></li>
                <li><a href="#">女優: Airi Sakura</a></li>
            </ul>
        </div>
        <div class="sidebar-block">
            <h3>広告枠</h3>
            <div class="ad-box" style="width:100%;height:250px;">300x250</div>
        </div>
    </aside>

    <main class="main-content detail-page">
        <!-- partial: breadcrumb -->
        <nav class="breadcrumb" aria-label="breadcrumb">
            <a href="/">ホーム</a>
            <span>/</span>
            <a href="/posts.php">作品一覧</a>
            <span>/</span>
            <span>サンプル作品詳細</span>
        </nav>

        <!-- partial: detail_video -->
        <section class="detail-video">
            <div class="video-frame">
                <div class="video-placeholder">
                    <span>Sample Video 16:9</span>
                </div>
            </div>
        </section>

        <!-- partial: detail_title -->
        <section class="detail-title">
            <h1>ささやく夜に、彼女と過ごす特別な時間。サンプルタイトルのダミーテキスト。</h1>
        </section>

        <!-- partial: detail_main -->
        <section class="detail-main">
            <div class="detail-left">
                <div class="package-media" data-package-media></div>
                <a class="cta-buy" href="https://example.com" target="_blank" rel="noopener noreferrer">FANZAで購入</a>
                <div class="mini-links">
                    <a class="btn-mini" href="#samples">サンプル画像へ</a>
                    <a class="btn-mini" href="#related">関連商品へ</a>
                </div>
            </div>
            <div class="detail-right">
                <div class="pill-group">
                    <a href="#" class="pill">メーカー: Dummy Studio</a>
                    <a href="#" class="pill">シリーズ: Pink Luxe</a>
                    <a href="#" class="pill">ジャンル: ラブロマンス</a>
                    <a href="#" class="pill">女優: Airi Sakura</a>
                </div>
                <p class="detail-description">
                    ダミーテキスト：都会の夜に紛れて出会った二人のストーリー。視線が重なるたび、静かに熱が増す――。
                    ここには作品の概要や見どころが入る想定です。
                </p>
                <div class="info-grid">
                    <div class="info-card">
                        <span class="info-label">配信日</span>
                        <span class="info-value">2024/10/01</span>
                    </div>
                    <div class="info-card">
                        <span class="info-label">収録時間</span>
                        <span class="info-value">120分</span>
                    </div>
                    <div class="info-card">
                        <span class="info-label">画質</span>
                        <span class="info-value">HD</span>
                    </div>
                    <div class="info-card">
                        <span class="info-label">品番</span>
                        <span class="info-value">PCF-0001</span>
                    </div>
                    <div class="info-card">
                        <span class="info-label">配信形式</span>
                        <span class="info-value">ストリーミング</span>
                    </div>
                    <div class="info-card">
                        <span class="info-label">監督</span>
                        <span class="info-value">Pink Director</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- partial: detail_samples -->
        <section class="detail-samples" id="samples">
            <div class="section-head">
                <h2 class="section-title">サンプル画像</h2>
                <span class="section-sub">全6枚の想定</span>
            </div>
            <div class="sample-grid" data-sample-grid></div>
            <a class="cta-buy" href="https://example.com" target="_blank" rel="noopener noreferrer">FANZAで購入</a>
        </section>

        <!-- partial: detail_related -->
        <section class="detail-related" id="related">
            <div class="section-head">
                <h2 class="section-title">関連商品</h2>
                <span class="section-sub">関連する6作品</span>
            </div>
            <div class="related-grid" data-related-grid></div>
        </section>
    </main>
</div>

<!-- partial: footer -->
<?php include __DIR__ . '/partials/footer.php'; ?>
