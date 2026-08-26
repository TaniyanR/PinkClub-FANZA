<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Reorder partner RSS items in a site-level round-robin order.
 *
 * rss_pick_display_items() historically balances by rss_sources.id. A single
 * partner site can have multiple partner_rss/rss_sources rows, which gives that
 * site multiple turns. This helper resolves each source back to partner_site_id
 * and merges those sources into one bucket before shuffling/round-robin output.
 *
 * No stored RSS/partner data is modified.
 */
function rss_balance_items_by_partner_site(array $items): array
{
    if (count($items) <= 1) {
        return $items;
    }

    $sourceIds = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $sourceId = (int)($item['source_id'] ?? 0);
        if ($sourceId > 0) {
            $sourceIds[$sourceId] = true;
        }
    }

    if ($sourceIds === []) {
        shuffle($items);
        return $items;
    }

    $partnerBySource = [];
    try {
        $ids = array_keys($sourceIds);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = db()->prepare(
            'SELECT rs.id AS source_id, pr.partner_site_id '
            . 'FROM rss_sources rs '
            . 'LEFT JOIN partner_rss pr ON pr.id = rs.source_ref_id '
            . 'WHERE rs.id IN (' . $placeholders . ')'
        );
        $stmt->execute($ids);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $sourceId = (int)($row['source_id'] ?? 0);
            $partnerSiteId = (int)($row['partner_site_id'] ?? 0);
            if ($sourceId > 0) {
                $partnerBySource[$sourceId] = $partnerSiteId;
            }
        }
    } catch (Throwable $e) {
        error_log('[rss] partner-site balancing lookup failed: ' . $e->getMessage());
        shuffle($items);
        return $items;
    }

    $buckets = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $sourceId = (int)($item['source_id'] ?? 0);
        $partnerSiteId = (int)($partnerBySource[$sourceId] ?? 0);
        $bucketKey = $partnerSiteId > 0
            ? 'partner:' . $partnerSiteId
            : 'source:' . $sourceId;

        $item['partner_site_id'] = $partnerSiteId;
        $buckets[$bucketKey][] = $item;
    }

    if ($buckets === []) {
        return [];
    }

    $bucketOrder = array_keys($buckets);
    shuffle($bucketOrder);
    foreach ($buckets as &$bucketItems) {
        if (count($bucketItems) > 1) {
            shuffle($bucketItems);
        }
    }
    unset($bucketItems);

    $balanced = [];
    while (true) {
        $added = false;
        foreach ($bucketOrder as $bucketKey) {
            if (($buckets[$bucketKey] ?? []) === []) {
                continue;
            }
            $item = array_shift($buckets[$bucketKey]);
            if (is_array($item)) {
                $balanced[] = $item;
                $added = true;
            }
        }
        if (!$added) {
            break;
        }
    }

    return $balanced;
}

function rss_partner_display_source_key(array $item): string
{
    $partnerSiteId = (int)($item['partner_site_id'] ?? 0);
    if ($partnerSiteId > 0) {
        return 'partner:' . $partnerSiteId;
    }

    $sourceId = (int)($item['source_id'] ?? 0);
    if ($sourceId > 0) {
        return 'source:' . $sourceId;
    }

    return 'name:' . mb_strtolower(trim((string)($item['source_name'] ?? '')));
}
