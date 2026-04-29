<?php if (!empty($items)) : ?>
<section>
    <h2 class="section-title">新着</h2>
    <div class="card-grid">
        <?php foreach ($items as $item) : ?>
            <div class="card">
                <?php if (!empty($item['image_small'])) : ?>
                    <img src="<?php echo htmlspecialchars($item['image_small'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?>">
                <?php endif; ?>
                <strong><?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                <div class="meta"><?php echo htmlspecialchars($item['date_published'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                <div><a href="<?php echo e(public_url('item.php?cid=' . urlencode($item['content_id']))); ?>">詳細</a></div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
