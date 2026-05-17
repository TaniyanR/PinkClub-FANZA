<?php
declare(strict_types=1);
require_once __DIR__ . '/../public/_bootstrap.php';
auth_require_admin();
$title = 'アクセス解析';

$tab = (string)get('tab', 'graph');
$allowedTabs = ['graph','referrer','destination','engine','keyword','duration','settings'];
if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'graph';
}

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

$refRows = [];
$outRows = [];
$engineRows = [];
$keywordRows = [];
if ($tab === 'referrer' || $tab === 'engine' || $tab === 'keyword') {
    $refStmt = db()->prepare('SELECT referer_host,COUNT(*) cnt FROM in_logs WHERE created_at BETWEEN :from AND :to GROUP BY referer_host ORDER BY cnt DESC, referer_host ASC LIMIT 200');
    $refStmt->execute([':from' => $from->format('Y-m-d 00:00:00'), ':to' => $to->format('Y-m-d 23:59:59')]);
    $refRows = $refStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
if ($tab === 'destination') {
    $outStmt = db()->prepare('SELECT target_url,COUNT(*) cnt FROM out_logs WHERE created_at BETWEEN :from AND :to GROUP BY target_url ORDER BY cnt DESC, target_url ASC LIMIT 200');
    $outStmt->execute([':from' => $from->format('Y-m-d 00:00:00'), ':to' => $to->format('Y-m-d 23:59:59')]);
    $outRows = $outStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
if ($tab === 'engine') {
    foreach ($refRows as $row) {
        $host = strtolower((string)($row['referer_host'] ?? ''));
        $cnt = (int)($row['cnt'] ?? 0);
        if ($host === '') { continue; }
        if (str_contains($host, 'google.')) { $engine = 'Google'; }
        elseif (str_contains($host, 'yahoo.')) { $engine = 'Yahoo'; }
        elseif (str_contains($host, 'bing.')) { $engine = 'Bing'; }
        elseif (str_contains($host, 'duckduckgo.')) { $engine = 'DuckDuckGo'; }
        else { continue; }
        if (!isset($engineRows[$engine])) { $engineRows[$engine] = 0; }
        $engineRows[$engine] += $cnt;
    }
    arsort($engineRows);
}
if ($tab === 'keyword') {
    $kwStmt = db()->prepare('SELECT target_url,COUNT(*) cnt FROM out_logs WHERE path = :path AND created_at BETWEEN :from AND :to GROUP BY target_url ORDER BY cnt DESC, target_url ASC LIMIT 200');
    $kwStmt->execute([':path' => '/search', ':from' => $from->format('Y-m-d 00:00:00'), ':to' => $to->format('Y-m-d 23:59:59')]);
    $keywordRows = $kwStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

require __DIR__ . '/includes/header.php';
?>
<section class="admin-card">
  <h1>アクセス解析</h1>
  <p>
    <a href="?tab=graph&period=day">1日</a> / <a href="?tab=graph&period=week">1週</a> / <a href="?tab=graph&period=month">1ヶ月</a>
  </p>
  <p>
    <a href="?tab=graph">グラフ</a> |
    <a href="?tab=referrer">リンク元</a> |
    <a href="?tab=destination">クリック先</a> |
    <a href="?tab=engine">検索エンジン</a> |
    <a href="?tab=keyword">検索ワード</a> |
    <a href="?tab=duration">滞在時間</a> |
    <a href="?tab=settings">アクセス設定</a>
  </p>
  <?php if ($tab === 'graph'): ?>
  <div class="admin-status-grid">
    <article class="admin-card admin-status-card"><strong>ページビュー</strong><p><?= e((string)$sum['pv']) ?></p></article>
    <article class="admin-card admin-status-card"><strong>ユニークユーザー</strong><p><?= e((string)$sum['uu']) ?></p></article>
    <article class="admin-card admin-status-card"><strong>流入数</strong><p><?= e((string)$sum['in_count']) ?></p></article>
    <article class="admin-card admin-status-card"><strong>流出数</strong><p><?= e((string)$sum['out_count']) ?></p></article>
  </div>
  <p>前期間比較: ページビュー <?= e((string)($sum['pv'] - (int)$prev['pv'])) ?> / ユニークユーザー <?= e((string)($sum['uu'] - (int)$prev['uu'])) ?> / 流入数 <?= e((string)($sum['in_count'] - (int)$prev['in_count'])) ?> / 流出数 <?= e((string)($sum['out_count'] - (int)$prev['out_count'])) ?></p>
  <?php elseif ($tab === 'referrer'): ?>
  <table class="admin-table"><tr><th>リンク元</th><th>アクセス数</th></tr><?php foreach ($refRows as $r): ?><tr><td><?= e((string)$r['referer_host']) ?></td><td><?= e((string)$r['cnt']) ?></td></tr><?php endforeach; ?></table>
  <?php elseif ($tab === 'destination'): ?>
  <table class="admin-table"><tr><th>クリック先</th><th>クリック数</th></tr><?php foreach ($outRows as $r): ?><tr><td><?= e((string)$r['target_url']) ?></td><td><?= e((string)$r['cnt']) ?></td></tr><?php endforeach; ?></table>
  <?php elseif ($tab === 'engine'): ?>
  <table class="admin-table"><tr><th>検索エンジン</th><th>アクセス数</th></tr><?php foreach ($engineRows as $name => $cnt): ?><tr><td><?= e((string)$name) ?></td><td><?= e((string)$cnt) ?></td></tr><?php endforeach; ?></table>
  <?php elseif ($tab === 'keyword'): ?>
  <p>要確認: 既存ログテーブルに検索キーワードを保持するカラムが無いため、現在の実データ集計はできません。</p>
  <table class="admin-table"><tr><th>現状データ</th><th>件数</th></tr><?php foreach ($keywordRows as $r): ?><tr><td><?= e((string)$r['target_url']) ?></td><td><?= e((string)$r['cnt']) ?></td></tr><?php endforeach; ?></table>
  <?php elseif ($tab === 'duration'): ?>
  <p>要確認: 既存ログテーブルに滞在時間を計算する開始/終了時刻の対応データが無いため、現状では算出できません。</p>
  <?php elseif ($tab === 'settings'): ?>
  <p>要確認: 既存設定テーブルにアクセス除外URLを保存する項目が未確認のため、保存機能は未実装です。</p>
  <?php endif; ?>

  <?php if ($tab === 'graph'): ?>
  <h2>日別PV（棒グラフ）</h2><div class="analytics-bars analytics-bars--vertical"><?php $maxPv = 0; foreach ($rows as $barRow) { $maxPv = max($maxPv, (int)$barRow['pv']); } $maxPv = max($maxPv, 1); ?><?php foreach ($rows as $barRow): $pv = (int)$barRow['pv']; $ratio = (int)round(($pv / $maxPv) * 100); ?><div class="analytics-bars__col"><span class="analytics-bars__value"><?= e((string)$pv) ?></span><div class="analytics-bars__track"><span class="analytics-bars__fill" style="height: <?= e((string)$ratio) ?>%;"></span></div><span class="analytics-bars__date"><?= e((string)date('m/d', strtotime((string)$barRow['stat_date']))) ?></span></div><?php endforeach; ?></div>
  <table class="admin-table"><tr><th>日付</th><th>PV</th><th>UU</th><th>流入</th><th>流出</th></tr><?php foreach ($rows as $r): ?><tr><td><?= e((string)$r['stat_date']) ?></td><td><?= e((string)$r['pv']) ?></td><td><?= e((string)$r['uu']) ?></td><td><?= e((string)$r['in_count']) ?></td><td><?= e((string)$r['out_count']) ?></td></tr><?php endforeach; ?></table>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
