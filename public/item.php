<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/repository.php';

function e(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

function safe_url(?string $url): string
{
    $url = trim((string)$url);
    if ($url === '') {
        return '#';
    }
    // http/https のみ許可
    if (!preg_match('#^https?://#i', $url)) {
        return '#';
    }
    return $url;
}

$cid = trim((string)($_GET['cid'] ?? ''));
if ($cid !== '' && strlen($cid) > 64) { // 暴走防止（content_idは通常もっと短い）
    $cid = '';
}

$item = null;

try {
    $item = $cid !== '' ? fetch_item_by_content_id($cid) : null;
} catch (Throwable $e) {
    if (function_exists('log_message')) {
        log_message('item.php failed: ' . $e->getMessage());
    }
    $item = null;
}

if (!$item) {
    http_response_code(404);
}

include __DIR__ . '/partials/header.php';
?>
<main>
    <?php if ($item) : ?>
        <?php
        $title = (string)($item['title'] ?? '');
        $img = (string)($item['image_large'] ?? '');
        if ($img === '') {
            $img = (string)($item['image_small'] ?? '');
        }

        $date = (string)($item['date_published'] ?? '');
        $priceMin = $item['price_min'] ?? null;
        $priceText = '';
        if (is_numeric($priceMin)) {
            $priceText = number_format((int)$priceMin) . '円';
        }

        $affiliateUrl = safe_url($item['affiliate_url'] ?? '');
        $officialUrl  = safe_url($item['url'] ?? '');
        ?>
        <h1><?php echo e($title); ?></h1>

        <div class="detail-card">
            <?php if ($img !== '') : ?>
                <img
                    src="<?php echo e($img); ?>"
                    alt="<?php echo e($title); ?>"
                    loading="lazy"
                >
            <?php endif; ?>

            <div class="detail-meta">
                <?php if ($date !== '') : ?>
                    <p>発売日: <?php echo e($date); ?></p>
                <?php endif; ?>

                <?php if ($priceText !== '') : ?>
                    <p>価格: <?php echo e($priceText); ?></p>
                <?php endif; ?>

                <p>
                    <?php if ($affiliateUrl !== '#') : ?>
                        <a href="<?php echo e($affiliateUrl); ?>" target="_blank" rel="noopener noreferrer">関連リンク（アフィリエイト）</a>
                    <?php else : ?>
                        <span>関連リンク（アフィリエイト）: なし</span>
                    <?php endif; ?>
                </p>

                <?php if ($officialUrl !== '#') : ?>
                    <p><a href="<?php echo e($officialUrl); ?>" target="_blank" rel="noopener noreferrer">公式ページ</a></p>
                <?php endif; ?>

                <p><a href="/">← トップへ戻る</a></p>
            </div>
        </div>
    <?php else : ?>
        <div class="notice">該当する作品が見つかりませんでした。</div>
        <p><a href="/">← トップへ戻る</a></p>
    <?php endif; ?>
</main>
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<?php include __DIR__ . '/partials/footer.php'; ?>
