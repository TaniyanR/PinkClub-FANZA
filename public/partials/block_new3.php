<?php if (!empty($items)) : ?>
<section>
    <h2 class="section-title">新着</h2>
    <div class="card-grid">
        <?php foreach ($items as $item) : ?>
            <div class="card">
                <strong><?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                <div><?php echo htmlspecialchars($item['date_published'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                <div><a href="/item.php?cid=<?php echo urlencode($item['content_id']); ?>">詳細</a></div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
