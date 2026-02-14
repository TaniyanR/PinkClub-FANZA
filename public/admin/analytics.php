<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
admin_trace_push('page:start:analytics.php');

$days = 30;
$daysWindow = $days - 1;

$dates = [];
for ($i = $daysWindow; $i >= 0; $i--) {
    $dates[] = date('Y-m-d', strtotime('-' . $i . ' day'));
}

$hasPageViews = admin_table_exists('page_views');
$hasAccessEvents = admin_table_exists('access_events');

$fetchDailyCount = static function (string $sql, array $params = []): array {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $map = [];
    foreach ($rows as $row) {
        $day = (string)($row['d'] ?? '');
        if ($day === '') {
            continue;
        }
        $map[$day] = (int)($row['c'] ?? 0);
    }

    return $map;
};

$pvMap = [];
$uuMap = [];
$inMap = [];
$outMap = [];

if ($hasPageViews) {
    $pvMap = $fetchDailyCount(
        'SELECT DATE(viewed_at) d, COUNT(*) c
         FROM page_views
         WHERE viewed_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
         GROUP BY DATE(viewed_at)',
        [':days' => $daysWindow]
    );

    $uuMap = $fetchDailyCount(
        'SELECT d, COUNT(*) c
         FROM (
             SELECT DATE(viewed_at) d, ip_hash, ua_hash
             FROM page_views
             WHERE viewed_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
             GROUP BY DATE(viewed_at), ip_hash, ua_hash
         ) t
         GROUP BY d',
        [':days' => $daysWindow]
    );
}

if ($hasAccessEvents) {
    $inMap = $fetchDailyCount(
        "SELECT DATE(event_at) d, COUNT(*) c
         FROM access_events
         WHERE event_type = 'in' AND event_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
         GROUP BY DATE(event_at)",
        [':days' => $daysWindow]
    );

    $outMap = $fetchDailyCount(
        "SELECT DATE(event_at) d, COUNT(*) c
         FROM access_events
         WHERE event_type = 'out' AND event_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
         GROUP BY DATE(event_at)",
        [':days' => $daysWindow]
    );
}

$pvSeries = [];
$uuSeries = [];
$inSeries = [];
$outSeries = [];
foreach ($dates as $d) {
    $pvSeries[] = $pvMap[$d] ?? 0;
    $uuSeries[] = $uuMap[$d] ?? 0;
    $inSeries[] = $inMap[$d] ?? 0;
    $outSeries[] = $outMap[$d] ?? 0;
}

$sumPv = array_sum($pvSeries);
$sumUu = array_sum($uuSeries);
$sumIn = array_sum($inSeries);
$sumOut = array_sum($outSeries);

$lineChartData = [
    'labels' => $dates,
    'pv' => $pvSeries,
    'uu' => $uuSeries,
];

$barChartData = [
    'labels' => $dates,
    'in' => $inSeries,
    'out' => $outSeries,
];

$lineChartJson = json_encode($lineChartData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$barChartJson = json_encode($barChartData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$pageTitle = 'アクセス解析';
ob_start();
?>
<h1>アクセス解析</h1>
<?php if (!$hasPageViews || !$hasAccessEvents): ?>
    <div class="notice notice-warning">
        <p>
            一部の集計テーブルが未作成のため、利用可能なデータのみ表示しています。
            （page_views: <?php echo $hasPageViews ? 'OK' : '未作成'; ?> / access_events: <?php echo $hasAccessEvents ? 'OK' : '未作成'; ?>）
        </p>
    </div>
<?php endif; ?>
<div class="admin-status-grid">
    <div class="admin-card admin-status-card"><strong>30日PV</strong><p><?php echo e((string)$sumPv); ?></p></div>
    <div class="admin-card admin-status-card"><strong>30日UU</strong><p><?php echo e((string)$sumUu); ?></p></div>
    <div class="admin-card admin-status-card"><strong>30日IN</strong><p><?php echo e((string)$sumIn); ?></p></div>
    <div class="admin-card admin-status-card"><strong>30日OUT</strong><p><?php echo e((string)$sumOut); ?></p></div>
</div>

<div class="admin-card">
    <h2>PV / UU（日別折れ線・過去30日）</h2>
    <canvas id="pvUuChart" height="300" style="width:100%;max-width:960px"></canvas>
</div>

<div class="admin-card">
    <h2>IN / OUT（日別棒グラフ・過去30日）</h2>
    <canvas id="inOutChart" height="320" style="width:100%;max-width:960px"></canvas>
</div>

<script src="/public/assets/vendor/chart.js/chart.umd.min.js"></script>
<script>
(function () {
    const lineData = <?php echo $lineChartJson !== false ? $lineChartJson : '{"labels":[],"pv":[],"uu":[]}'; ?>;
    const barData = <?php echo $barChartJson !== false ? $barChartJson : '{"labels":[],"in":[],"out":[]}'; ?>;

    if (typeof window.Chart !== 'function') {
        console.warn('Chart.js not loaded');
        return;
    }

    const commonOptions = {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
            mode: 'index',
            intersect: false
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0
                }
            }
        }
    };

    const lineCanvas = document.getElementById('pvUuChart');
    if (lineCanvas) {
        new Chart(lineCanvas, {
            type: 'line',
            data: {
                labels: lineData.labels,
                datasets: [
                    {
                        label: 'PV',
                        data: lineData.pv,
                        borderColor: '#2271b1',
                        backgroundColor: 'rgba(34, 113, 177, 0.2)',
                        borderWidth: 2,
                        pointRadius: 2,
                        tension: 0.25
                    },
                    {
                        label: 'UU',
                        data: lineData.uu,
                        borderColor: '#00a32a',
                        backgroundColor: 'rgba(0, 163, 42, 0.2)',
                        borderWidth: 2,
                        pointRadius: 2,
                        tension: 0.25
                    }
                ]
            },
            options: commonOptions
        });
    }

    const barCanvas = document.getElementById('inOutChart');
    if (barCanvas) {
        new Chart(barCanvas, {
            type: 'bar',
            data: {
                labels: barData.labels,
                datasets: [
                    {
                        label: 'IN',
                        data: barData.in,
                        backgroundColor: '#8e44ad'
                    },
                    {
                        label: 'OUT',
                        data: barData.out,
                        backgroundColor: '#d63638'
                    }
                ]
            },
            options: commonOptions
        });
    }
})();
</script>
<?php
$main = (string)ob_get_clean();
require_once __DIR__ . '/_page.php';
admin_render($pageTitle, static function () use ($main): void {
    echo $main;
});
