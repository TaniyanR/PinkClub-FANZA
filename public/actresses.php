<?php
$pageStyles = ['/assets/css/actresses.css'];
$pageScripts = ['/assets/js/actresses.js'];
include __DIR__ . '/partials/header.php';
?>

<!-- partial: header -->
<!-- partial: search -->
<section class="search-bar actresses-search">
  <div class="search-bar-inner">
    <strong class="search-note">当サイトはアフィリエイト広告を使用しています。</strong>
    <form action="#" method="get">
      <input type="text" name="s" placeholder="女優名で検索">
      <button type="submit">検索</button>
    </form>
  </div>
</section>

<div class="layout actresses-layout">
  <!-- partial: sidebar -->
  <aside class="sidebar actresses-sidebar">
    <div class="sidebar-block">
      <h3>人気女優（ダミー）</h3>
      <ul class="popular-actress-list">
        <li>
          <a href="#">
            <span class="popular-thumb" aria-hidden="true"></span>
            <span class="popular-name">葵 さくら</span>
          </a>
        </li>
        <li>
          <a href="#">
            <span class="popular-thumb" aria-hidden="true"></span>
            <span class="popular-name">宮下 玲奈</span>
          </a>
        </li>
        <li>
          <a href="#">
            <span class="popular-thumb" aria-hidden="true"></span>
            <span class="popular-name">天音 まひな</span>
          </a>
        </li>
        <li>
          <a href="#">
            <span class="popular-thumb" aria-hidden="true"></span>
            <span class="popular-name">渚 みつき</span>
          </a>
        </li>
        <li>
          <a href="#">
            <span class="popular-thumb" aria-hidden="true"></span>
            <span class="popular-name">石原 希</span>
          </a>
        </li>
      </ul>
    </div>

    <div class="sidebar-block">
      <h3>広告枠</h3>
      <div class="ad-box" aria-label="広告枠">300x250</div>
    </div>
  </aside>

  <main class="main-content actresses-main">
    <!-- partial: breadcrumb -->
    <nav class="breadcrumb block" aria-label="パンくず">
      <a href="/">Home</a>
      <span>›</span>
      <a href="#">女優</a>
      <span>›</span>
      <span>一覧</span>
    </nav>

    <!-- partial: actresses_head -->
    <section class="block actresses-head">
      <p class="actresses-subtitle">人気女優から新着まで、気になる女優をチェックできます。</p>
      <h1>女優一覧</h1>
    </section>

    <!-- partial: actresses_controls -->
    <section class="block actresses-controls">
      <div class="actresses-count">表示件数: <span data-actresses-count>0</span>件</div>

      <div class="actresses-controls-row">
        <label class="actresses-search-field">
          女優名検索
          <input type="search" data-actresses-search placeholder="女優名を入力">
        </label>

        <label>
          並び替え
          <select data-actresses-sort>
            <option value="popular">人気</option>
            <option value="new">新着</option>
          </select>
        </label>

        <label>
          表示件数
          <select data-actresses-per-page>
            <option value="24" selected>24</option>
            <option value="48">48</option>
          </select>
        </label>
      </div>
    </section>

    <!-- partial: actresses_grid -->
    <section class="block">
      <div class="actresses-grid" data-actresses-grid></div>
      <p class="actresses-empty" data-actresses-empty hidden>該当する女優が見つかりません。</p>
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

<!-- partial: footer -->
<?php include __DIR__ . '/partials/footer.php'; ?>
