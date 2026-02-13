<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
admin_trace_push('page:start:analytics.php');

$days = 30;

$dates = [];
for ($i = $days - 1; $i >= 0; $i--) {
    $dates[] = date('Y-m-d', strtotime('-' . $i . ' day'));
}

$pvRows = db()->query("SELECT DATE(viewed_at) d, COUNT(*) c FROM page_views WHERE viewed_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY) GROUP BY DATE(viewed_at)")->fetchAll(PDO::FETCH_ASSOC);
$pvMap = [];
foreach ($pvRows as $row) {
    $pvMap[(string)$row['d']] = (int)$row['c'];
}

$uuRows = db()->query("SELECT DATE(viewed_at) d, COUNT(*) c FROM (SELECT DATE(viewed_at) d, ip_hash, ua_hash FROM page_views WHERE viewed_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY) GROUP BY DATE(viewed_at), ip_hash, ua_hash) t GROUP BY d")->fetchAll(PDO::FETCH_ASSOC);
$uuMap = [];
foreach ($uuRows as $row) {
    $uuMap[(string)$row['d']] = (int)$row['c'];
}

$inRows = db()->query("SELECT DATE(event_at) d, COUNT(*) c FROM access_events WHERE event_type='in' AND event_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY) GROUP BY DATE(event_at)")->fetchAll(PDO::FETCH_ASSOC);
$inMap = [];
foreach ($inRows as $row) {
    $inMap[(string)$row['d']] = (int)$row['c'];
}

$outRows = db()->query("SELECT DATE(event_at) d, COUNT(*) c FROM access_events WHERE event_type='out' AND event_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY) GROUP BY DATE(event_at)")->fetchAll(PDO::FETCH_ASSOC);
$outMap = [];
foreach ($outRows as $row) {
    $outMap[(string)$row['d']] = (int)$row['c'];
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

$pageTitle = 'アクセス解析';
ob_start();
?>
<h1>アクセス解析</h1>
<div class="admin-status-grid">
    <div class="admin-card admin-status-card"><strong>30日PV</strong><p><?php echo e((string)$sumPv); ?></p></div>
    <div class="admin-card admin-status-card"><strong>30日UU</strong><p><?php echo e((string)$sumUu); ?></p></div>
    <div class="admin-card admin-status-card"><strong>30日IN</strong><p><?php echo e((string)$sumIn); ?></p></div>
    <div class="admin-card admin-status-card"><strong>30日OUT</strong><p><?php echo e((string)$sumOut); ?></p></div>
</div>

<div class="admin-card">
    <h2>PV / UU（日別折れ線・過去30日）</h2>
    <canvas id="pvUuChart" width="960" height="300" style="width:100%;max-width:960px;border:1px solid #dcdcde;background:#fff"></canvas>
</div>

<div class="admin-card">
    <h2>IN / OUT（日別棒グラフ・過去30日）</h2>
    <canvas id="inOutChart" width="960" height="320" style="width:100%;max-width:960px;border:1px solid #dcdcde;background:#fff"></canvas>
</div>

<script>
(function () {
    const lineData = <?php echo json_encode($lineChartData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const barData = <?php echo json_encode($barChartData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    function drawAxes(ctx, w, h, pad) {
        ctx.strokeStyle = '#c3c4c7';
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(pad, pad);
        ctx.lineTo(pad, h - pad);
        ctx.lineTo(w - pad, h - pad);
        ctx.stroke();
    }

    function drawLineChart(canvas, labels, a, b) {
        const ctx = canvas.getContext('2d');
        const w = canvas.width;
        const h = canvas.height;
        const pad = 28;
        ctx.clearRect(0, 0, w, h);
        drawAxes(ctx, w, h, pad);

        const max = Math.max(1, ...a, ...b);
        const stepX = (w - pad * 2) / Math.max(1, labels.length - 1);
        const scaleY = (h - pad * 2) / max;

        function drawSeries(values, color) {
            ctx.strokeStyle = color;
            ctx.lineWidth = 2;
            ctx.beginPath();
            values.forEach((v, i) => {
                const x = pad + i * stepX;
                const y = h - pad - (v * scaleY);
                if (i === 0) {
                    ctx.moveTo(x, y);
                } else {
                    ctx.lineTo(x, y);
                }
            });
            ctx.stroke();
        }

        drawSeries(a, '#2271b1');
        drawSeries(b, '#00a32a');
        ctx.fillStyle = '#1d2327';
        ctx.font = '12px sans-serif';
        ctx.fillText('PV', pad, 14);
        ctx.fillStyle = '#2271b1';
        ctx.fillRect(pad + 22, 6, 14, 8);
        ctx.fillStyle = '#1d2327';
        ctx.fillText('UU', pad + 48, 14);
        ctx.fillStyle = '#00a32a';
        ctx.fillRect(pad + 70, 6, 14, 8);
    }

    function drawBarChart(canvas, labels, inValues, outValues) {
        const ctx = canvas.getContext('2d');
        const w = canvas.width;
        const h = canvas.height;
        const pad = 28;
        ctx.clearRect(0, 0, w, h);
        drawAxes(ctx, w, h, pad);

        const max = Math.max(1, ...inValues, ...outValues);
        const groupW = (w - pad * 2) / Math.max(1, labels.length);
        const barW = Math.max(2, groupW * 0.35);
        const scaleY = (h - pad * 2) / max;

        labels.forEach((_, i) => {
            const baseX = pad + i * groupW;
            const inH = inValues[i] * scaleY;
            const outH = outValues[i] * scaleY;

            ctx.fillStyle = '#8e44ad';
            ctx.fillRect(baseX + 1, h - pad - inH, barW, inH);
            ctx.fillStyle = '#d63638';
            ctx.fillRect(baseX + barW + 3, h - pad - outH, barW, outH);
        });

        ctx.fillStyle = '#1d2327';
        ctx.font = '12px sans-serif';
        ctx.fillText('IN', pad, 14);
        ctx.fillStyle = '#8e44ad';
        ctx.fillRect(pad + 18, 6, 14, 8);
        ctx.fillStyle = '#1d2327';
        ctx.fillText('OUT', pad + 44, 14);
        ctx.fillStyle = '#d63638';
        ctx.fillRect(pad + 74, 6, 14, 8);
    }

    const lineCanvas = document.getElementById('pvUuChart');
    if (lineCanvas) {
        drawLineChart(lineCanvas, lineData.labels, lineData.pv, lineData.uu);
    }

    const barCanvas = document.getElementById('inOutChart');
    if (barCanvas) {
        drawBarChart(barCanvas, barData.labels, barData.in, barData.out);
    }
})();
</script>
<?php
$content = (string)ob_get_clean();
admin_trace_push('page:content:ready');
admin_trace_push('page:render:layout');
include __DIR__ . '/../partials/admin_layout.php';
