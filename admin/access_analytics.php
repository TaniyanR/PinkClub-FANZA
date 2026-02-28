<?php
declare(strict_types=1);
require_once __DIR__ . '/../public/_bootstrap.php';
auth_require_admin();
$title = 'アクセス解析';

$periodKey = (string)get('period', 'week');
$days = match ($periodKey) {
    'day' => 1,
    'month' => 30,
    default => 7,
};

$to = new DateTimeImmutable('today');
$from = $to->sub(new DateInterval('P' . ($days - 1) . 'D'));
$prevFrom = $from->sub(new DateInterval('P' . $days . 'D'));
$prevTo = $from->sub(new DateInterval('P1D'));

$stmt = db()->prepare('SELECT stat_date,pv,uu,in_count,out_count FROM daily_stats WHERE stat_date BETWEEN :from AND :to ORDER BY stat_date ASC');
$stmt->execute([':from' => $from->format('Y-m-d'), ':to' => $to->format('Y-m-d')]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$prevStmt = db()->prepare('SELECT COALESCE(SUM(pv),0) pv,COALESCE(SUM(uu),0) uu,COALESCE(SUM(in_count),0) in_count,COALESCE(SUM(out_count),0) out_count FROM daily_stats WHERE stat_date BETWEEN :from AND :to');
$prevStmt->execute([':from' => $prevFrom->format('Y-m-d'), ':to' => $prevTo->format('Y-m-d')]);
$prev = $prevStmt->fetch(PDO::FETCH_ASSOC) ?: ['pv'=>0,'uu'=>0,'in_count'=>0,'out_count'=>0];

$sum = ['pv'=>0,'uu'=>0,'in_count'=>0,'out_count'=>0];
foreach ($rows as $r) { $sum['pv']+=(int)$r['pv']; $sum['uu']+=(int)$r['uu']; $sum['in_count']+=(int)$r['in_count']; $sum['out_count']+=(int)$r['out_count']; }

require __DIR__ . '/includes/header.php';
?>
<section class="admin-card">
  <h1>アクセス解析</h1>
  <p>
    <a href="?period=day">1日</a> / <a href="?period=week">1週</a> / <a href="?period=month">1ヶ月</a>
  </p>
  <div class="admin-status-grid">
    <article class="admin-card admin-status-card"><strong>PV</strong><p><?= e((string)$sum['pv']) ?></p></article>
    <article class="admin-card admin-status-card"><strong>UU</strong><p><?= e((string)$sum['uu']) ?></p></article>
    <article class="admin-card admin-status-card"><strong>IN</strong><p><?= e((string)$sum['in_count']) ?></p></article>
    <article class="admin-card admin-status-card"><strong>OUT</strong><p><?= e((string)$sum['out_count']) ?></p></article>
  </div>
  <p>前期間比較: PV <?= e((string)($sum['pv'] - (int)$prev['pv'])) ?> / UU <?= e((string)($sum['uu'] - (int)$prev['uu'])) ?> / IN <?= e((string)($sum['in_count'] - (int)$prev['in_count'])) ?> / OUT <?= e((string)($sum['out_count'] - (int)$prev['out_count'])) ?></p>
  <table class="admin-table"><tr><th>日付</th><th>PV</th><th>UU</th><th>IN</th><th>OUT</th></tr>
    <?php foreach ($rows as $r): ?>
      <tr><td><?= e((string)$r['stat_date']) ?></td><td><?= e((string)$r['pv']) ?></td><td><?= e((string)$r['uu']) ?></td><td><?= e((string)$r['in_count']) ?></td><td><?= e((string)$r['out_count']) ?></td></tr>
    <?php endforeach; ?>
  </table>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
