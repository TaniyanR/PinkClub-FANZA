<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Build the access-trade candidate pool per partner site, not from one global
 * newest-first LIMIT. This prevents high-volume feeds or registration order
 * from crowding other partner sites out before the IN/OUT weighting runs.
 */
function rss_trade_candidate_pool(int $perSiteLimit = 40, bool $requireImage = false, int $days = 14): array
{
    $perSiteLimit = max(1, min(200, $perSiteLimit));
    $days = max(1, min(365, $days));

    try {
        $sites = db()->query(
            'SELECT DISTINCT ps.id AS partner_site_id '
            . 'FROM partner_sites ps '
            . 'INNER JOIN partner_rss pr ON pr.partner_site_id = ps.id '
            . 'INNER JOIN rss_sources rs ON rs.source_ref_id = pr.id AND rs.source_type = "partner_link" '
            . 'WHERE ps.is_enabled = 1 AND COALESCE(pr.show_rss, pr.is_enabled, 1) = 1 AND rs.is_enabled = 1'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('[rss] per-site candidate list failed: ' . $e->getMessage());
        return [];
    }

    if ($sites === []) {
        return [];
    }

    $siteIds = array_values(array_filter(array_map(
        static fn(array $row): int => (int)($row['partner_site_id'] ?? 0),
        $sites
    ), static fn(int $id): bool => $id > 0));
    if ($siteIds === []) {
        return [];
    }
    shuffle($siteIds);

    $all = [];
    $seen = [];
    foreach ($siteIds as $partnerSiteId) {
        try {
            $sourceStmt = db()->prepare(
                'SELECT rs.id '
                . 'FROM rss_sources rs '
                . 'INNER JOIN partner_rss pr ON pr.id = rs.source_ref_id '
                . 'WHERE pr.partner_site_id = :site_id '
                . 'AND rs.source_type = "partner_link" AND rs.is_enabled = 1 '
                . 'AND COALESCE(pr.show_rss, pr.is_enabled, 1) = 1'
            );
            $sourceStmt->execute([':site_id' => $partnerSiteId]);
            $sourceIds = array_values(array_filter(array_map('intval', $sourceStmt->fetchAll(PDO::FETCH_COLUMN) ?: []), static fn(int $id): bool => $id > 0));
            if ($sourceIds === []) {
                continue;
            }

            $placeholders = implode(',', array_fill(0, count($sourceIds), '?'));
            $imageClause = $requireImage ? " AND COALESCE(NULLIF(TRIM(ri.image_url), ''), '') <> ''" : '';
            $sql = 'SELECT ri.source_id, rs.name AS source_name, ri.title, ri.url, ri.guid, ri.published_at, ri.image_url '
                . 'FROM rss_items ri INNER JOIN rss_sources rs ON rs.id = ri.source_id '
                . 'WHERE ri.source_id IN (' . $placeholders . ') '
                . 'AND ri.published_at >= DATE_SUB(NOW(), INTERVAL ' . $days . ' DAY)'
                . $imageClause
                . ' ORDER BY ri.published_at DESC, ri.id DESC LIMIT ' . $perSiteLimit;
            $stmt = db()->prepare($sql);
            $stmt->execute($sourceIds);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($rows as $row) {
                $url = trim((string)($row['url'] ?? ''));
                $guid = trim((string)($row['guid'] ?? ''));
                $dedupe = $url !== '' ? 'url|' . mb_strtolower($url) : ($guid !== '' ? 'guid|' . mb_strtolower($guid) : '');
                if ($dedupe !== '' && isset($seen[$dedupe])) {
                    continue;
                }
                if ($dedupe !== '') {
                    $seen[$dedupe] = true;
                }
                $all[] = [
                    'title' => (string)($row['title'] ?? ''),
                    'link' => $url,
                    'guid' => $guid,
                    'published_at' => (string)($row['published_at'] ?? ''),
                    'image_url' => trim((string)($row['image_url'] ?? '')),
                    'source_id' => (int)($row['source_id'] ?? 0),
                    'source_name' => (string)($row['source_name'] ?? ''),
                    'partner_site_id' => $partnerSiteId,
                ];
            }
        } catch (Throwable $e) {
            error_log('[rss] per-site candidate fetch failed for partner ' . $partnerSiteId . ': ' . $e->getMessage());
        }
    }

    if (count($all) > 1) {
        shuffle($all);
    }
    return $all;
}
