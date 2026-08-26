<?php
declare(strict_types=1);

require_once __DIR__ . '/rss_access_trade.php';

function rss_trade_normalize_host(string $value): string
{
    $value = trim(strtolower($value));
    if ($value === '') {
        return '';
    }
    if (str_contains($value, '://')) {
        $value = (string)(parse_url($value, PHP_URL_HOST) ?: '');
    }
    $value = preg_replace('/:\d+$/', '', $value) ?? $value;
    $value = rtrim($value, '.');
    return preg_replace('/^www\./', '', $value) ?? $value;
}

function rss_trade_metrics_host_aware(array $items, int $days = 30): array
{
    $days = max(1, min(365, $days));
    $refs = [];
    $hostsByRef = [];
    $refByHost = [];

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $ref = trim((string)($item['partner_ref_code'] ?? ''));
        if ($ref === '') {
            continue;
        }
        $refs[$ref] = true;
        foreach (['partner_site_url', 'link'] as $field) {
            $host = rss_trade_normalize_host((string)($item[$field] ?? ''));
            if ($host === '') {
                continue;
            }
            $hostsByRef[$ref][$host] = true;
            $refByHost[$host][$ref] = true;
        }
    }

    $metrics = [];
    foreach (array_keys($refs) as $ref) {
        $metrics[$ref] = ['in' => 0, 'out' => 0];
    }
    if ($metrics === []) {
        return $metrics;
    }

    try {
        $stmt = db()->query(
            'SELECT ref_code, referer_host, COUNT(*) AS c FROM in_logs '
            . 'WHERE created_at >= DATE_SUB(NOW(), INTERVAL ' . $days . ' DAY) '
            . 'GROUP BY ref_code, referer_host'
        );
        foreach ($stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [] as $row) {
            $ref = trim((string)($row['ref_code'] ?? ''));
            $count = (int)($row['c'] ?? 0);
            if ($count <= 0) {
                continue;
            }
            if ($ref !== '' && isset($metrics[$ref])) {
                $metrics[$ref]['in'] += $count;
                continue;
            }
            if ($ref !== '') {
                continue;
            }
            $host = rss_trade_normalize_host((string)($row['referer_host'] ?? ''));
            if ($host === '' || !isset($refByHost[$host])) {
                continue;
            }
            foreach (array_keys($refByHost[$host]) as $matchedRef) {
                if (isset($metrics[$matchedRef])) {
                    $metrics[$matchedRef]['in'] += $count;
                }
            }
        }

        $refList = array_keys($metrics);
        $placeholders = implode(',', array_fill(0, count($refList), '?'));
        $stmt = db()->prepare(
            'SELECT ref_code, COUNT(*) AS c FROM out_logs '
            . 'WHERE created_at >= DATE_SUB(NOW(), INTERVAL ' . $days . ' DAY) '
            . 'AND ref_code IN (' . $placeholders . ') GROUP BY ref_code'
        );
        $stmt->execute($refList);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $ref = trim((string)($row['ref_code'] ?? ''));
            if (isset($metrics[$ref])) {
                $metrics[$ref]['out'] = (int)($row['c'] ?? 0);
            }
        }
    } catch (Throwable $e) {
        error_log('[rss] host-aware access-trade metrics lookup failed: ' . $e->getMessage());
    }

    return $metrics;
}

function rss_trade_select_host_aware(array $items, int $maxTotal, int $hardPerSiteCap, int $days = 30): array
{
    if ($maxTotal <= 0 || $items === []) {
        return [];
    }

    $maxTotal = max(1, $maxTotal);
    $hardPerSiteCap = max(1, $hardPerSiteCap);
    $items = rss_trade_enrich_items($items);

    $seenKeys = [];
    $seenTitles = [];
    $buckets = [];

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $key = function_exists('rss_normalize_display_key')
            ? rss_normalize_display_key($item)
            : trim((string)($item['link'] ?? ''));
        if ($key === '') {
            $key = mb_strtolower(trim((string)($item['title'] ?? '')));
        }
        if ($key !== '' && isset($seenKeys[$key])) {
            continue;
        }
        $titleKey = mb_strtolower(preg_replace('/\s+/u', ' ', trim((string)($item['title'] ?? ''))) ?? '');
        if ($titleKey !== '' && isset($seenTitles[$titleKey])) {
            continue;
        }
        if ($key !== '') {
            $seenKeys[$key] = true;
        }
        if ($titleKey !== '') {
            $seenTitles[$titleKey] = true;
        }

        $siteKey = rss_partner_display_source_key($item);
        if ($siteKey === '') {
            $siteKey = 'unknown:' . (string)($item['source_id'] ?? '0');
        }
        $buckets[$siteKey][] = $item;
    }

    if ($buckets === []) {
        return [];
    }

    foreach ($buckets as &$bucket) {
        if (count($bucket) > 1) {
            shuffle($bucket);
        }
    }
    unset($bucket);

    $flat = [];
    foreach ($buckets as $bucket) {
        foreach ($bucket as $item) {
            $flat[] = $item;
        }
    }
    $metrics = rss_trade_metrics_host_aware($flat, $days);

    $state = [];
    foreach ($buckets as $siteKey => $bucket) {
        $first = $bucket[0] ?? [];
        $ref = trim((string)($first['partner_ref_code'] ?? ''));
        $m = $ref !== '' ? ($metrics[$ref] ?? ['in' => 0, 'out' => 0]) : ['in' => 0, 'out' => 0];
        $state[$siteKey] = [
            'weight' => rss_trade_weight((int)$m['in'], (int)$m['out']),
            'current' => 0.0,
            'picked' => 0,
        ];
    }

    $result = [];
    $siteOrder = array_keys($buckets);
    shuffle($siteOrder);
    foreach ($siteOrder as $siteKey) {
        if (count($result) >= $maxTotal) {
            break;
        }
        $item = array_shift($buckets[$siteKey]);
        if (!is_array($item)) {
            continue;
        }
        $result[] = $item;
        $state[$siteKey]['picked']++;
    }

    $lastKey = $result !== [] ? rss_partner_display_source_key($result[count($result) - 1]) : null;
    while (count($result) < $maxTotal) {
        $active = [];
        $totalWeight = 0.0;
        foreach ($buckets as $siteKey => $bucket) {
            if ($bucket === [] || $state[$siteKey]['picked'] >= $hardPerSiteCap) {
                continue;
            }
            $state[$siteKey]['current'] += $state[$siteKey]['weight'];
            $active[$siteKey] = $state[$siteKey]['current'];
            $totalWeight += $state[$siteKey]['weight'];
        }
        if ($active === []) {
            break;
        }

        arsort($active, SORT_NUMERIC);
        $orderedKeys = array_keys($active);
        $chosenKey = $orderedKeys[0];
        if ($chosenKey === $lastKey && count($orderedKeys) > 1) {
            $chosenKey = $orderedKeys[1];
        }

        $item = array_shift($buckets[$chosenKey]);
        if (!is_array($item)) {
            continue;
        }
        $state[$chosenKey]['current'] -= max(0.0001, $totalWeight);
        $state[$chosenKey]['picked']++;
        $result[] = $item;
        $lastKey = $chosenKey;
    }

    return function_exists('rss_spread_items_by_partner_site')
        ? rss_spread_items_by_partner_site($result)
        : $result;
}
