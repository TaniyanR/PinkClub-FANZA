  <?php if ($affiliateUrl !== ''): ?>
    <p><a class="pcf-btn" style="display:block; text-align:center; border:2px solid #9aa0ab; font-weight:700; font-size:18px; padding:12px 14px;" href="<?= e($affiliateUrl) ?>" target="_blank" rel="noopener noreferrer">購入ボタン</a></p>
  <?php endif; ?>

  <section class="pcf-detail pcf-item-main">
    <div class="pcf-item-main__media">
      <?php if ($packageImage !== ''): ?>
      <a href="<?= e($packageImage) ?>" target="_blank" rel="noopener noreferrer">
        <img class="pcf-detail__package" src="<?= e($packageImage) ?>" alt="<?= e((string)($item['title'] ?? '')) ?>">
      </a>
      <?php endif; ?>
      <?php if ($desc !== ''): ?><p><?= nl2br(e($desc)) ?></p><?php endif; ?>
    </div>

    <div class="pcf-item-main__info">
      <ul class="pcf-item-card__meta" style="color:#000 !important; font-size:14px;">
        <li>対応デバイス: <?= e($deviceText !== '' ? $deviceText : '―') ?></li>
        <li>配信開始日: <?= e($deliveryStartText !== '' ? $deliveryStartText : '―') ?></li>
        <li>商品発売日: <?= e($releaseDateDisplay !== '' ? $releaseDateDisplay : '―') ?></li>
        <li>収録時間: <?= e($volumeDisplay !== '' ? $volumeDisplay : '―') ?></li>
        <li>出演者: <?= e($performerText !== '' ? $performerText : '―') ?></li>
        <li>監督: <?= e($rawDirectorName !== '' ? $rawDirectorName : '―') ?></li>
        <li>シリーズ: <?= e($rawSeriesName !== '' ? $rawSeriesName : '―') ?></li>
        <li>メーカー: <?= e($rawMakerName !== '' ? $rawMakerName : '―') ?></li>
        <li>レーベル: <?= e($labelName !== '' ? $labelName : '―') ?></li>
        <li>ジャンル: <?= e($genreText !== '' ? $genreText : '―') ?></li>
        <li>関連タグ: <?= e($tagText !== '' ? $tagText : '―') ?></li>
        <li>配信品番: <?= e($contentIdDisplay !== '' ? $contentIdDisplay : '―') ?></li>
        <li>メーカー品番: <?= e($productIdDisplay !== '' ? $productIdDisplay : '―') ?></li>
      </ul>
    </div>
  </section>