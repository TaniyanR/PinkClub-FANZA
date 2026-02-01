<?php if (!empty($taxonomySets)) : ?>
    <?php foreach ($taxonomySets as $set) : ?>
        <?php
        $railTitle = $set['title'];
        $railItems = $set['items'];
        include __DIR__ . '/block_rail.php';
        ?>
    <?php endforeach; ?>
<?php endif; ?>
