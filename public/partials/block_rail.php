<?php if (!empty($railItems)) : ?>
<section>
    <h2 class="section-title"><?php echo e($railTitle); ?></h2>
    <div class="rail">
        <?php foreach ($railItems as $item) : ?>
            <div class="rail-item">
                <?php if (!empty($item['image_list'])) : ?>
                    <img src="<?php echo e($item['image_list']); ?>" alt="<?php echo e($item['title'] ?? 'アイテム'); ?>">
                <?php endif; ?>
                <div class="rail-title"><?php echo e($item['name'] ?? $item['title'] ?? 'アイテム'); ?></div>
                <?php if (!empty($item['content_id'])) : ?>
                    <a href="<?php echo e(public_url('item.php?cid=' . urlencode($item['content_id']))); ?>">詳細</a>
                <?php elseif (!empty($item['id'])) : ?>
                    <a href="<?php echo e(public_url('actress.php?id=' . urlencode((string)$item['id']))); ?>">詳細</a>
                <?php elseif (!empty($item['genre_id'])) : ?>
                    <a href="<?php echo e(public_url('genre.php?id=' . urlencode((string)$item['genre_id']))); ?>">詳細</a>
                <?php elseif (!empty($item['series_id'])) : ?>
                    <a href="<?php echo e(public_url('series_one.php?id=' . urlencode((string)$item['series_id']))); ?>">詳細</a>
                <?php elseif (!empty($item['maker_id'])) : ?>
                    <a href="<?php echo e(public_url('maker.php?id=' . urlencode((string)$item['maker_id']))); ?>">詳細</a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
