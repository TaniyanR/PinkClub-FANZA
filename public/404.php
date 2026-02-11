<?php
declare(strict_types=1);

require_once __DIR__ . '/partials/_helpers.php';
$pageTitle = $pageTitle ?? '404 Not Found | PinkClub-FANZA';
$pageDescription = $pageDescription ?? 'ページが見つかりませんでした。';
$canonicalUrl = $canonicalUrl ?? canonical_url(current_path());

include __DIR__ . '/partials/header.php';
include __DIR__ . '/partials/nav_search.php';
?>
<div class="layout">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <main class="main-content">
        <section class="block">
            <h1 class="section-title">404 Not Found</h1>
            <p>指定されたページまたはデータは見つかりませんでした。</p>
            <a class="button button--primary" href="/">トップへ戻る</a>
        </section>
    </main>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
