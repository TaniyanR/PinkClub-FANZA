<?php if (!empty($railItems)) : ?>
<section>
    <h2 class="section-title"><?php echo htmlspecialchars($railTitle, ENT_QUOTES, 'UTF-8'); ?></h2>
    <div class="rail">
        <?php foreach ($railItems as $item) : ?>
            <div class="rail-item">
                <div><?php echo htmlspecialchars($item['name'] ?? $item['title'] ?? 'アイテム', ENT_QUOTES, 'UTF-8'); ?></div>
                <?php if (!empty($item['content_id'])) : ?>
                    <a href="/item.php?cid=<?php echo urlencode($item['content_id']); ?>">詳細</a>
                <?php elseif (!empty($item['id'])) : ?>
                    <a href="/actress.php?id=<?php echo urlencode($item['id']); ?>">詳細</a>
                <?php elseif (!empty($item['genre_id'])) : ?>
                    <a href="/genre.php?id=<?php echo urlencode($item['genre_id']); ?>">詳細</a>
                <?php elseif (!empty($item['series_id'])) : ?>
                    <a href="/series_item.php?id=<?php echo urlencode($item['series_id']); ?>">詳細</a>
                <?php elseif (!empty($item['maker_id'])) : ?>
                    <a href="/maker.php?id=<?php echo urlencode($item['maker_id']); ?>">詳細</a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
