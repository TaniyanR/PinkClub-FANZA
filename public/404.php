<?php
declare(strict_types=1);

require_once __DIR__ . '/partials/_helpers.php';

$pageTitle = isset($pageTitle) ? safe_str($pageTitle, 120) : '404 Not Found | PinkClub-FANZA';
$pageDescription = isset($pageDescription) ? safe_str($pageDescription, 200) : 'ページが見つかりませんでした。';
$canonicalUrl = isset($canonicalUrl) ? (string)$canonicalUrl : canonical_url('/404.php');
$notFoundTitle = isset($notFoundTitle) ? (string)$notFoundTitle : '404 Not Found';
$notFoundMessage = isset($notFoundMessage) ? (string)$notFoundMessage : '指定されたページまたはデータは見つかりませんでした。';

if (http_response_code() !== 404) {
    http_response_code(404);
}

include __DIR__ . '/partials/header.php';
include __DIR__ . '/partials/nav_search.php';
?>
<div class="layout">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <main class="main-content">
        <section class="block">
            <h1 class="section-title"><?php echo e($notFoundTitle); ?></h1>
            <p><?php echo e($notFoundMessage); ?></p>
            <a class="button button--primary" href="/">トップへ戻る</a>
        </section>
    </main>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
