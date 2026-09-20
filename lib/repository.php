<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * ここでSQLに埋め込む文字列は必ず許可リストで制限する。
 */
function normalize_order(string $value, array $allowed, string $default): string
{
    return in_array($value, $allowed, true) ? $value : $default;
}

/**
 * テーブル名を受け取る系は必ず許可リストで制限（SQLインジェクション防止）
 */
function normalize_table(string $table, array $allowed): string
{
    if (!in_array($table, $allowed, true)) {
        throw new InvalidArgumentException('Invalid table name');
    }
    return $table;
}

function normalize_int(int $value, int $min, int $max): int
{
    return max($min, min($max, $value));
}

function normalize_content_id(string $contentId): string
{
    $contentId = trim($contentId);

    if ($contentId === '' || strlen($contentId) > 64) {
        return '';
    }

    if (!preg_match('/^[A-Za-z0-9._-]+$/', $contentId)) {
        return '';
    }

    return $contentId;
}

function backfill_master_from_relation(string $masterTable, string $relationTable, string $nameColumn): void
{
    static $backfilled = [];

    $masterTable  = normalize_table($masterTable,  ['actresses', 'genres', 'makers', 'series_master', 'authors']);
    $relationTable = normalize_table($relationTable, ['item_actresses', 'item_genres', 'item_makers', 'item_series', 'item_authors']);
    $nameColumn   = normalize_order($nameColumn, ['actress_name', 'genre_name', 'maker_name', 'series_name', 'author_name'], $nameColumn);
    $cacheKey = $masterTable . ':' . $relationTable . ':' . $nameColumn;
    if (isset($backfilled[$cacheKey])) {
        return;
    }
    $backfilled[$cacheKey] = true;

    $sql = "INSERT INTO {$masterTable}(dmm_id,name,created_at,updated_at)
             SELECT
               CASE
                 WHEN TRIM(COALESCE(r.{$nameColumn}, '')) = '' THEN NULL
                 WHEN TRIM(COALESCE(r.dmm_id, '')) <> '' THEN TRIM(r.dmm_id)
                 ELSE CONCAT('name:', SHA1(LOWER(TRIM(r.{$nameColumn}))))
               END AS mapped_dmm_id,
               TRIM(r.{$nameColumn}) AS mapped_name,
               NOW(), NOW()
             FROM {$relationTable} r
             WHERE TRIM(COALESCE(r.{$nameColumn}, '')) <> ''
             ON DUPLICATE KEY UPDATE name=VALUES(name), updated_at=NOW();";

    try {
        db()->exec($sql);
    } catch (Throwable) {
    }
}

function fetch_items(string $orderBy = 'date_published_desc', int $limit = 10, int $offset = 0): array
{
    $allowedOrders = [
        'date_published_desc' => 'release_date DESC, id DESC',
        'date_published_asc'  => 'release_date ASC, id ASC',
        'price_min_desc'      => 'price_min DESC',
        'price_min_asc'       => 'price_min ASC',
        'popularity_desc'     => 'view_count DESC, id DESC',
        'random'              => 'RAND()',
    ];
    if (array_key_exists($orderBy, $allowedOrders)) {
        $orderBySql = $allowedOrders[$orderBy];
    } else {
        $orderBySql = normalize_order($orderBy, array_values($allowedOrders), $allowedOrders['date_published_desc']);
    }

    $limit  = normalize_int($limit, 1, 100);
    $offset = max(0, $offset);

    $sourceWhere = items_product_source_where();
    $whereSql = $sourceWhere !== '' ? ' WHERE ' . $sourceWhere : '';
    $stmt = db()->prepare("SELECT * FROM items{$whereSql} ORDER BY {$orderBySql} LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll() ?: [];
}

function fetch_item_by_content_id(string $contentId): ?array
{
    $cid = normalize_content_id($contentId);
    if ($cid === '') {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT * FROM items
         WHERE content_id = :cid
           AND ' . items_product_source_where() . '
         ORDER BY
           CASE
             WHEN title LIKE "%お問い合わせ%" OR title LIKE "%問合せ%" OR title = "Privacy Policy" OR title = "サイトについて" THEN 1
             ELSE 0
           END ASC,
           id DESC
         LIMIT 1'
    );
    $stmt->execute([':cid' => $cid]);
    $item = $stmt->fetch();
    return $item ?: null;
}

function fetch_item_by_cid(string $cid): ?array
{
    return fetch_item_by_content_id($cid);
}

function items_table_exists(string $table): bool
{
    static $cache = [];

    if (!in_array($table, ['items', 'rss_items', 'rss_sources'], true)) {
        return false;
    }
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    try {
        $stmt = db()->prepare('SHOW TABLES LIKE :table_name');
        $stmt->execute([':table_name' => $table]);
        $cache[$table] = (bool)$stmt->fetch(PDO::FETCH_NUM);
        return $cache[$table];
    } catch (Throwable) {
        $cache[$table] = false;
        return false;
    }
}

function items_column_exists(string $column, string $table = 'items'): bool
{
    static $cache = [];

    if (!in_array($table, ['items', 'rss_sources'], true)) {
        return false;
    }
    $cacheKey = $table . '.' . $column;
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    try {
        $stmt = db()->prepare('SHOW COLUMNS FROM ' . $table . ' LIKE :column');
        $stmt->execute([':column' => $column]);
        $cache[$cacheKey] = (bool)$stmt->fetch(PDO::FETCH_ASSOC);
        return $cache[$cacheKey];
    } catch (Throwable) {
        $cache[$cacheKey] = false;
        return false;
    }
}

function ensure_items_item_source_column(): void
{
    if (!items_column_exists('item_source')) {
        db()->exec('ALTER TABLE items ADD COLUMN item_source VARCHAR(32) NOT NULL DEFAULT "unknown" AFTER product_id');
    }
}


function items_front_release_where(string $alias = ''): string
{
    $prefix = $alias !== '' ? $alias . '.' : 'items.';
    return '(' . $prefix . 'release_date IS NULL OR ' . $prefix . 'release_date = "" OR DATE(' . $prefix . 'release_date) <= CURDATE() OR ' . $prefix . 'release_date <= NOW())';
}

function items_product_source_where(string $alias = ''): string
{
    static $cache = [];

    if (array_key_exists($alias, $cache)) {
        return $cache[$alias];
    }

    $outerPrefix = $alias !== '' ? $alias : 'items';
    $where = [];

    if (items_column_exists('item_source')) {
        $where[] = '(' . $outerPrefix . '.item_source IN ("fanza_product", "unknown", "dmm", "") OR ' . $outerPrefix . '.item_source IS NULL)';
    }

    $where[] = items_front_release_where($outerPrefix);

    if (items_table_exists('rss_items') && items_table_exists('rss_sources') && items_column_exists('source_type', 'rss_sources')) {
        $where[] = 'NOT EXISTS (SELECT 1 FROM rss_items ri INNER JOIN rss_sources rs ON rs.id = ri.source_id WHERE rs.source_type = "partner_link" AND (ri.title = ' . $outerPrefix . '.title OR ri.url = ' . $outerPrefix . '.url OR ri.url = ' . $outerPrefix . '.affiliate_url))';
    }

    $cache[$alias] = implode(' AND ', $where);
    return $cache[$alias];
}

function fetch_actresses(int $limit = 50, int $offset = 0, string $order = 'name'): array
{
    $orderBy = normalize_order($order, ['name', 'created_at', 'updated_at'], 'name');
    $limit   = normalize_int($limit, 1, 200);
    $offset  = max(0, $offset);

    try {
        $stmt = db()->prepare("SELECT * FROM actresses ORDER BY {$orderBy} ASC LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll() ?: [];
        if ($rows !== []) {
            return $rows;
        }
    } catch (Throwable) {
    }

    backfill_master_from_relation('actresses', 'item_actresses', 'actress_name');

    try {
        $stmt = db()->prepare("SELECT * FROM actresses ORDER BY {$orderBy} ASC LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    } catch (Throwable) {
        return [];
    }
}

function fetch_public_actresses(int $limit = 10000, int $offset = 0, string $order = 'name'): array
{
    $orderBy = normalize_order($order, ['name', 'created_at', 'updated_at'], 'name');
    $limit = normalize_int($limit, 1, 10000);
    $offset = max(0, $offset);

    try {
        $stmt = db()->prepare(
            "SELECT actresses.*
             FROM actresses
             WHERE EXISTS (
               SELECT 1
               FROM item_actresses
               INNER JOIN items ON items.id = item_actresses.item_id
               WHERE item_actresses.dmm_id = actresses.dmm_id
                 AND " . items_product_source_where('items') . "
             )
             ORDER BY actresses.{$orderBy} ASC
             LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    } catch (Throwable) {
        return [];
    }
}

function fetch_actress(int $id): ?array
{
    $id   = max(1, $id);
    $stmt = db()->prepare('SELECT * FROM actresses WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $actress = $stmt->fetch();
    return $actress ?: null;
}

function fetch_related_series_by_actress(int $actressId, int $limit = 50): array
{
    $actressId = max(1, $actressId);
    $limit     = normalize_int($limit, 1, 200);

    try {
        $stmt = db()->prepare(
            'SELECT DISTINCT series_master.id, series_master.name, series_master.ruby
             FROM actresses
             INNER JOIN item_actresses ON item_actresses.dmm_id = actresses.dmm_id
             INNER JOIN item_series    ON item_series.item_id   = item_actresses.item_id
             INNER JOIN series_master  ON series_master.dmm_id  = item_series.dmm_id
             WHERE actresses.id = :id
             ORDER BY series_master.name ASC
             LIMIT :limit'
        );
        $stmt->bindValue(':id',    $actressId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit,     PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    } catch (Throwable) {
        return [];
    }
}

function fetch_related_makers_by_actress(int $actressId, int $limit = 50): array
{
    $actressId = max(1, $actressId);
    $limit     = normalize_int($limit, 1, 200);

    try {
        $sql = db_column_exists('item_makers', 'item_id')
            ? 'SELECT DISTINCT makers.id, makers.name, makers.ruby
               FROM actresses
               INNER JOIN item_actresses ON item_actresses.dmm_id = actresses.dmm_id
               INNER JOIN item_makers    ON item_makers.item_id   = item_actresses.item_id
               INNER JOIN makers         ON makers.dmm_id         = item_makers.dmm_id
               WHERE actresses.id = :id
               ORDER BY makers.name ASC
               LIMIT :limit'
            : 'SELECT DISTINCT makers.*
               FROM makers
               INNER JOIN item_makers    ON makers.id = item_makers.maker_id
               INNER JOIN item_actresses ON item_makers.content_id = item_actresses.content_id
               INNER JOIN items          ON items.content_id = item_makers.content_id
               WHERE item_actresses.actress_id = :id
                 AND ' . items_front_release_where('items') . '
               ORDER BY makers.name ASC
               LIMIT :limit';
        $stmt = db()->prepare($sql);
        $stmt->bindValue(':id',    $actressId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit,     PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    } catch (Throwable) {
        return [];
    }
}

function fetch_genre(int $genreId): ?array
{
    $genreId = max(1, $genreId);
    $stmt    = db()->prepare('SELECT * FROM genres WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $genreId]);
    $genre = $stmt->fetch();
    return $genre ?: null;
}

function fetch_maker(int $makerId): ?array
{
    $makerId = max(1, $makerId);
    $stmt    = db()->prepare('SELECT * FROM makers WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $makerId]);
    $maker = $stmt->fetch();
    return $maker ?: null;
}

function fetch_series_one(int $seriesId): ?array
{
    $seriesId = max(1, $seriesId);

    try {
        $stmt = db()->prepare('SELECT * FROM series_master WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $seriesId]);
        $series = $stmt->fetch();
        if ($series) {
            return $series;
        }
    } catch (Throwable) {
    }

    try {
        $stmt = db()->prepare('SELECT * FROM series WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $seriesId]);
        $series = $stmt->fetch();
        return $series ?: null;
    } catch (Throwable) {
        return null;
    }
}

/**
 * Search Console-confirmed taxonomy duplicates.
 *
 * Keep the source records intact for administration/API sync while routing the
 * public duplicate URL to the already indexed canonical maker page.
 *
 * @return array<int,int> series ID => maker ID
 */
function series_canonical_maker_redirects(): array
{
    return [
        5214 => 7681,
    ];
}

function fetch_genres(int $limit = 50, int $offset = 0, string $order = 'name'): array
{
    $orderBy = normalize_order($order, ['name', 'created_at', 'updated_at'], 'name');
    $limit   = normalize_int($limit, 1, 200);
    $offset  = max(0, $offset);

    try {
        $stmt = db()->prepare("SELECT * FROM genres ORDER BY {$orderBy} ASC LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll() ?: [];
        if ($rows !== []) {
            return $rows;
        }
    } catch (Throwable) {
    }

    backfill_master_from_relation('genres', 'item_genres', 'genre_name');

    try {
        $stmt = db()->prepare("SELECT * FROM genres ORDER BY {$orderBy} ASC LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    } catch (Throwable) {
        return [];
    }
}

function fetch_makers(int $limit = 50, int $offset = 0, string $order = 'name'): array
{
    $orderBy = normalize_order($order, ['name', 'created_at', 'updated_at'], 'name');
    $limit   = normalize_int($limit, 1, 200);
    $offset  = max(0, $offset);

    try {
        $stmt = db()->prepare("SELECT * FROM makers ORDER BY {$orderBy} ASC LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll() ?: [];
        if ($rows !== []) {
            return $rows;
        }
    } catch (Throwable) {
    }

    backfill_master_from_relation('makers', 'item_makers', 'maker_name');

    try {
        $stmt = db()->prepare("SELECT * FROM makers ORDER BY {$orderBy} ASC LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    } catch (Throwable) {
        return [];
    }
}

function fetch_series(int $limit = 50, int $offset = 0, string $order = 'name'): array
{
    $orderBy = normalize_order($order, ['name', 'created_at', 'updated_at'], 'name');
    $limit   = normalize_int($limit, 1, 200);
    $offset  = max(0, $offset);
    $redirectSeriesIds = array_keys(series_canonical_maker_redirects());
    $redirectWhere = $redirectSeriesIds !== []
        ? ' AND series_master.id NOT IN (' . implode(',', array_map('intval', $redirectSeriesIds)) . ')'
        : '';

    try {
        $stmt = db()->prepare(
            "SELECT series_master.*
             FROM series_master
             WHERE EXISTS (
               SELECT 1
               FROM item_series
               INNER JOIN items ON items.id = item_series.item_id
               WHERE item_series.dmm_id = series_master.dmm_id
                 AND " . items_product_source_where('items') . "
             )
             {$redirectWhere}
             ORDER BY series_master.{$orderBy} ASC
             LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll() ?: [];
        if ($rows !== []) {
            return $rows;
        }
    } catch (Throwable) {
    }

    try {
        $stmt = db()->prepare(
            "SELECT series.*
             FROM series
             WHERE EXISTS (
               SELECT 1
               FROM item_series
               INNER JOIN items ON items.content_id = item_series.content_id
               WHERE item_series.series_id = series.id
                 AND " . items_product_source_where('items') . "
             )
             ORDER BY series.{$orderBy} ASC
             LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll() ?: [];
        if ($rows !== []) {
            return $rows;
        }
    } catch (Throwable) {
    }

    return [];
}

function fetch_labels(int $limit = 50, int $offset = 0): array
{
    $limit  = normalize_int($limit, 1, 200);
    $offset = max(0, $offset);

    $sql = db_column_exists('item_labels', 'item_id')
        ? 'SELECT COALESCE(NULLIF(dmm_id, ""), label_name) AS id, label_name AS name, "" AS ruby, COUNT(*) AS item_count
           FROM item_labels
           INNER JOIN items ON items.id = item_labels.item_id
           WHERE ' . items_product_source_where('items') . '
           GROUP BY COALESCE(NULLIF(dmm_id, ""), label_name), label_name
           ORDER BY label_name ASC
           LIMIT :limit OFFSET :offset'
        : 'SELECT label_id AS id, label_name AS name, MAX(label_ruby) AS ruby, COUNT(*) AS item_count
           FROM item_labels
           INNER JOIN items ON items.content_id = item_labels.content_id
           WHERE ' . items_product_source_where('items') . '
           GROUP BY label_id, label_name
           ORDER BY label_name ASC
           LIMIT :limit OFFSET :offset';
    try {
        $stmt = db()->prepare($sql);
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    } catch (Throwable) {
        return [];
    }
}

function fetch_label(string $labelId, string $labelName = ''): ?array
{
    $labelId = trim($labelId);
    $labelName = trim($labelName);
    if ($labelId === '' && $labelName === '') {
        return null;
    }

    $usesItemId = db_column_exists('item_labels', 'item_id');
    $queries = $usesItemId
        ? [
            ['sql' => 'SELECT COALESCE(NULLIF(dmm_id, ""), label_name) AS id, label_name AS name, "" AS ruby, COUNT(*) AS item_count FROM item_labels WHERE dmm_id = :label_id OR label_name = :label_id GROUP BY COALESCE(NULLIF(dmm_id, ""), label_name), label_name ORDER BY item_count DESC LIMIT 1', 'params' => [':label_id' => $labelId]],
            ['sql' => 'SELECT COALESCE(NULLIF(dmm_id, ""), label_name) AS id, label_name AS name, "" AS ruby, COUNT(*) AS item_count FROM item_labels WHERE label_name = :label_name GROUP BY COALESCE(NULLIF(dmm_id, ""), label_name), label_name ORDER BY item_count DESC LIMIT 1', 'params' => [':label_name' => $labelName]],
        ]
        : [
            ['sql' => 'SELECT label_id AS id, label_name AS name, MAX(label_ruby) AS ruby, COUNT(*) AS item_count FROM item_labels WHERE label_id = :label_id GROUP BY label_id, label_name ORDER BY item_count DESC LIMIT 1', 'params' => [':label_id' => $labelId]],
            ['sql' => 'SELECT label_id AS id, label_name AS name, MAX(label_ruby) AS ruby, COUNT(*) AS item_count FROM item_labels WHERE label_name = :label_name GROUP BY label_id, label_name ORDER BY item_count DESC LIMIT 1', 'params' => [':label_name' => $labelName]],
        ];
    foreach ($queries as $query) {
        if (in_array('', $query['params'], true)) {
            continue;
        }
        try {
            $stmt = db()->prepare($query['sql']);
            $stmt->execute($query['params']);
            $row = $stmt->fetch();
            if ($row) {
                return $row;
            }
        } catch (Throwable) {
        }
    }

    return null;
}

function fetch_items_by_label_name(string $labelName, int $limit, int $offset = 0): array
{
    $labelName = trim($labelName);
    if ($labelName === '') {
        return [];
    }

    $limit  = normalize_int($limit, 1, 100);
    $offset = max(0, $offset);

    $sql = db_column_exists('item_labels', 'item_id')
        ? 'SELECT DISTINCT items.*
           FROM items
           INNER JOIN item_labels ON items.id = item_labels.item_id
           WHERE item_labels.label_name = :label_name
             AND ' . items_product_source_where('items') . '
           ORDER BY items.release_date DESC, items.id DESC
           LIMIT :limit OFFSET :offset'
        : 'SELECT DISTINCT items.*
           FROM items
           INNER JOIN item_labels ON items.content_id = item_labels.content_id
           WHERE item_labels.label_name = :label_name
             AND ' . items_product_source_where('items') . '
           ORDER BY items.date_published DESC
           LIMIT :limit OFFSET :offset';
    try {
        $stmt = db()->prepare($sql);
        $stmt->bindValue(':label_name', $labelName, PDO::PARAM_STR);
        $stmt->bindValue(':limit',      $limit,     PDO::PARAM_INT);
        $stmt->bindValue(':offset',     $offset,    PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    } catch (Throwable) {
        return [];
    }
}

function count_items_by_actress(int $actressId, string $actressName = ''): int
{
    $actressId = max(1, $actressId);
    $actress = fetch_actress($actressId);
    $name = trim($actressName !== '' ? $actressName : (string)($actress['name'] ?? ''));
    $dmmId = trim((string)($actress['dmm_id'] ?? ''));

    try {
        $conditions = [];
        $params = [];
        if ($dmmId !== '') {
            $conditions[] = 'ia.dmm_id = ?';
            $params[] = $dmmId;
        }
        if ($name !== '') {
            $conditions[] = 'ia.actress_name = ? OR TRIM(ia.actress_name) = ?';
            $params[] = $name;
            $params[] = $name;
        }
        $conditions[] = 'ia.actress_id = ?';
        $params[] = $actressId;

        $sql = 'SELECT COUNT(DISTINCT items.id)
                FROM items
                INNER JOIN item_actresses ia ON (ia.item_id = items.id OR (items.content_id IS NOT NULL AND items.content_id = ia.content_id))
                WHERE (' . implode(' OR ', $conditions) . ')';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $count = (int)$stmt->fetchColumn();
        if ($count > 0) {
            return $count;
        }
    } catch (Throwable) {
    }

    if ($name !== '') {
        try {
            $sql = 'SELECT COUNT(DISTINCT items.id)
                    FROM items
                    INNER JOIN item_actresses ia ON (ia.item_id = items.id OR (items.content_id IS NOT NULL AND items.content_id = ia.content_id))
                    WHERE ia.actress_name LIKE ?';
            $stmt = db()->prepare($sql);
            $stmt->execute(['%' . $name . '%']);
            $count = (int)$stmt->fetchColumn();
            if ($count > 0) {
                return $count;
            }
        } catch (Throwable) {
        }
    }

    return 0;
}

function fetch_items_by_actress(int $actressId, int $limit, int $offset = 0, string $actressName = ''): array
{
    $actressId = max(1, $actressId);
    $limit     = normalize_int($limit, 1, 100);
    $offset    = max(0, $offset);
    $actress   = fetch_actress($actressId);
    $name      = trim($actressName !== '' ? $actressName : (string)($actress['name'] ?? ''));
    $dmmId     = trim((string)($actress['dmm_id'] ?? ''));

    // Step 1: Matching with dmm_id / name / actress_id with source filter
    try {
        $conditions = [];
        $params = [];
        if ($dmmId !== '') {
            $conditions[] = 'ia.dmm_id = ?';
            $params[] = $dmmId;
        }
        if ($name !== '') {
            $conditions[] = 'ia.actress_name = ? OR TRIM(ia.actress_name) = ?';
            $params[] = $name;
            $params[] = $name;
        }
        $conditions[] = 'ia.actress_id = ?';
        $params[] = $actressId;

        $sourceWhere = items_product_source_where('items');
        $whereSql = $sourceWhere !== '' ? ' AND ' . $sourceWhere : '';

        $sql = 'SELECT DISTINCT items.*
                FROM items
                INNER JOIN item_actresses ia ON (ia.item_id = items.id OR (items.content_id IS NOT NULL AND items.content_id = ia.content_id))
                WHERE (' . implode(' OR ', $conditions) . ')' . $whereSql . '
                ORDER BY items.release_date DESC, items.id DESC
                LIMIT ? OFFSET ?';
        $execParams = array_merge($params, [$limit, $offset]);
        $stmt = db()->prepare($sql);
        $stmt->execute($execParams);
        $results = $stmt->fetchAll() ?: [];
        if ($results !== []) {
            return $results;
        }
    } catch (Throwable) {
    }

    // Step 2: Same match without source filter
    try {
        $conditions = [];
        $params = [];
        if ($dmmId !== '') {
            $conditions[] = 'ia.dmm_id = ?';
            $params[] = $dmmId;
        }
        if ($name !== '') {
            $conditions[] = 'ia.actress_name = ? OR TRIM(ia.actress_name) = ?';
            $params[] = $name;
            $params[] = $name;
        }
        $conditions[] = 'ia.actress_id = ?';
        $params[] = $actressId;

        $sql = 'SELECT DISTINCT items.*
                FROM items
                INNER JOIN item_actresses ia ON (ia.item_id = items.id OR (items.content_id IS NOT NULL AND items.content_id = ia.content_id))
                WHERE (' . implode(' OR ', $conditions) . ')
                ORDER BY items.release_date DESC, items.id DESC
                LIMIT ? OFFSET ?';
        $execParams = array_merge($params, [$limit, $offset]);
        $stmt = db()->prepare($sql);
        $stmt->execute($execParams);
        $results = $stmt->fetchAll() ?: [];
        if ($results !== []) {
            return $results;
        }
    } catch (Throwable) {
    }

    // Step 3: Partial name search on item_actresses or items
    if ($name !== '') {
        try {
            $sql = 'SELECT DISTINCT items.*
                    FROM items
                    INNER JOIN item_actresses ia ON (ia.item_id = items.id OR (items.content_id IS NOT NULL AND items.content_id = ia.content_id))
                    WHERE ia.actress_name LIKE ?
                    ORDER BY items.release_date DESC, items.id DESC
                    LIMIT ? OFFSET ?';
            $stmt = db()->prepare($sql);
            $stmt->execute(['%' . $name . '%', $limit, $offset]);
            $results = $stmt->fetchAll() ?: [];
            if ($results !== []) {
                return $results;
            }
        } catch (Throwable) {
        }
    }

    return [];
}

function count_items_by_genre(int $genreId, string $genreName = ''): int
{
    $genreId = max(1, $genreId);
    $genre = fetch_genre($genreId);
    $name = trim($genreName !== '' ? $genreName : (string)($genre['name'] ?? ''));
    $dmmId = trim((string)($genre['dmm_id'] ?? ''));

    try {
        $conditions = [];
        $params = [];
        if ($dmmId !== '') {
            $conditions[] = 'ig.dmm_id = ?';
            $params[] = $dmmId;
        }
        if ($name !== '') {
            $conditions[] = 'ig.genre_name = ? OR TRIM(ig.genre_name) = ?';
            $params[] = $name;
            $params[] = $name;
        }
        $conditions[] = 'ig.genre_id = ?';
        $params[] = $genreId;

        $sql = 'SELECT COUNT(DISTINCT items.id)
                FROM items
                INNER JOIN item_genres ig ON (ig.item_id = items.id OR (items.content_id IS NOT NULL AND items.content_id = ig.content_id))
                WHERE (' . implode(' OR ', $conditions) . ')';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $count = (int)$stmt->fetchColumn();
        if ($count > 0) {
            return $count;
        }
    } catch (Throwable) {
    }

    if ($name !== '') {
        try {
            $sql = 'SELECT COUNT(DISTINCT items.id)
                    FROM items
                    INNER JOIN item_genres ig ON (ig.item_id = items.id OR (items.content_id IS NOT NULL AND items.content_id = ig.content_id))
                    WHERE ig.genre_name LIKE ?';
            $stmt = db()->prepare($sql);
            $stmt->execute(['%' . $name . '%']);
            $count = (int)$stmt->fetchColumn();
            if ($count > 0) {
                return $count;
            }
        } catch (Throwable) {
        }
    }

    return 0;
}

function fetch_items_by_genre(int $genreId, int $limit, int $offset = 0, string $genreName = ''): array
{
    $genreId = max(1, $genreId);
    $limit   = normalize_int($limit, 1, 100);
    $offset  = max(0, $offset);
    $genre   = fetch_genre($genreId);
    $name    = trim($genreName !== '' ? $genreName : (string)($genre['name'] ?? ''));
    $dmmId   = trim((string)($genre['dmm_id'] ?? ''));

    // Step 1: Try with source filter
    try {
        $conditions = [];
        $params = [];
        if ($dmmId !== '') {
            $conditions[] = 'ig.dmm_id = ?';
            $params[] = $dmmId;
        }
        if ($name !== '') {
            $conditions[] = 'ig.genre_name = ? OR TRIM(ig.genre_name) = ?';
            $params[] = $name;
            $params[] = $name;
        }
        $conditions[] = 'ig.genre_id = ?';
        $params[] = $genreId;

        $sourceWhere = items_product_source_where('items');
        $whereSql = $sourceWhere !== '' ? ' AND ' . $sourceWhere : '';

        $sql = 'SELECT DISTINCT items.*
                FROM items
                INNER JOIN item_genres ig ON (ig.item_id = items.id OR (items.content_id IS NOT NULL AND items.content_id = ig.content_id))
                WHERE (' . implode(' OR ', $conditions) . ')' . $whereSql . '
                ORDER BY items.release_date DESC, items.id DESC
                LIMIT ? OFFSET ?';
        $execParams = array_merge($params, [$limit, $offset]);
        $stmt = db()->prepare($sql);
        $stmt->execute($execParams);
        $results = $stmt->fetchAll() ?: [];
        if ($results !== []) {
            return $results;
        }
    } catch (Throwable) {
    }

    // Step 2: Without source filter
    try {
        $conditions = [];
        $params = [];
        if ($dmmId !== '') {
            $conditions[] = 'ig.dmm_id = ?';
            $params[] = $dmmId;
        }
        if ($name !== '') {
            $conditions[] = 'ig.genre_name = ? OR TRIM(ig.genre_name) = ?';
            $params[] = $name;
            $params[] = $name;
        }
        $conditions[] = 'ig.genre_id = ?';
        $params[] = $genreId;

        $sql = 'SELECT DISTINCT items.*
                FROM items
                INNER JOIN item_genres ig ON (ig.item_id = items.id OR (items.content_id IS NOT NULL AND items.content_id = ig.content_id))
                WHERE (' . implode(' OR ', $conditions) . ')
                ORDER BY items.release_date DESC, items.id DESC
                LIMIT ? OFFSET ?';
        $execParams = array_merge($params, [$limit, $offset]);
        $stmt = db()->prepare($sql);
        $stmt->execute($execParams);
        $results = $stmt->fetchAll() ?: [];
        if ($results !== []) {
            return $results;
        }
    } catch (Throwable) {
    }

    // Step 3: Partial name match
    if ($name !== '') {
        try {
            $sql = 'SELECT DISTINCT items.*
                    FROM items
                    INNER JOIN item_genres ig ON (ig.item_id = items.id OR (items.content_id IS NOT NULL AND items.content_id = ig.content_id))
                    WHERE ig.genre_name LIKE ?
                    ORDER BY items.release_date DESC, items.id DESC
                    LIMIT ? OFFSET ?';
            $stmt = db()->prepare($sql);
            $stmt->execute(['%' . $name . '%', $limit, $offset]);
            $results = $stmt->fetchAll() ?: [];
            if ($results !== []) {
                return $results;
            }
        } catch (Throwable) {
        }
    }

    return [];
}

function count_items_by_maker(int $makerId, string $makerName = ''): int
{
    $makerId = max(1, $makerId);
    $maker = fetch_maker($makerId);
    $name = trim($makerName !== '' ? $makerName : (string)($maker['name'] ?? ''));
    $dmmId = trim((string)($maker['dmm_id'] ?? ''));

    // Try to resolve name from item_makers if empty
    if ($name === '') {
        try {
            $findSql = "SELECT maker_name FROM item_makers WHERE maker_id = :id OR dmm_id = :dmm_id GROUP BY maker_name ORDER BY COUNT(*) DESC LIMIT 1";
            $fStmt = db()->prepare($findSql);
            $fStmt->execute([':id' => $makerId, ':dmm_id' => $dmmId]);
            $name = trim((string)($fStmt->fetchColumn() ?: ''));
        } catch (Throwable) {
        }
    }

    try {
        $conditions = [];
        $params = [];
        if ($dmmId !== '') {
            $conditions[] = 'im.dmm_id = ?';
            $params[] = $dmmId;
        }
        if ($name !== '') {
            $conditions[] = 'im.maker_name = ? OR TRIM(im.maker_name) = ?';
            $params[] = $name;
            $params[] = $name;
        }
        $conditions[] = 'im.maker_id = ?';
        $params[] = $makerId;

        $sql = 'SELECT COUNT(DISTINCT items.id)
                FROM items
                INNER JOIN item_makers im ON (im.item_id = items.id OR (items.content_id IS NOT NULL AND items.content_id = im.content_id))
                WHERE (' . implode(' OR ', $conditions) . ')';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $count = (int)$stmt->fetchColumn();
        if ($count > 0) {
            return $count;
        }
    } catch (Throwable) {
    }

    if ($name !== '') {
        try {
            $sql = 'SELECT COUNT(DISTINCT items.id)
                    FROM items
                    INNER JOIN item_makers im ON (im.item_id = items.id OR (items.content_id IS NOT NULL AND items.content_id = im.content_id))
                    WHERE im.maker_name LIKE ?';
            $stmt = db()->prepare($sql);
            $stmt->execute(['%' . $name . '%']);
            $count = (int)$stmt->fetchColumn();
            if ($count > 0) {
                return $count;
            }
        } catch (Throwable) {
        }
    }

    return 0;
}

function fetch_items_by_maker(int $makerId, int $limit, int $offset = 0, string $makerName = ''): array
{
    $makerId = max(1, $makerId);
    $limit   = normalize_int($limit, 1, 100);
    $offset  = max(0, $offset);
    $maker   = fetch_maker($makerId);
    $name    = trim($makerName !== '' ? $makerName : (string)($maker['name'] ?? ''));
    $dmmId   = trim((string)($maker['dmm_id'] ?? ''));

    // Try to resolve name from item_makers if empty
    if ($name === '') {
        try {
            $findSql = "SELECT maker_name FROM item_makers WHERE maker_id = :id OR dmm_id = :dmm_id GROUP BY maker_name ORDER BY COUNT(*) DESC LIMIT 1";
            $fStmt = db()->prepare($findSql);
            $fStmt->execute([':id' => $makerId, ':dmm_id' => $dmmId]);
            $name = trim((string)($fStmt->fetchColumn() ?: ''));
        } catch (Throwable) {
        }
    }

    // Step 1: Try with source filter
    try {
        $conditions = [];
        $params = [];
        if ($dmmId !== '') {
            $conditions[] = 'im.dmm_id = ?';
            $params[] = $dmmId;
        }
        if ($name !== '') {
            $conditions[] = 'im.maker_name = ? OR TRIM(im.maker_name) = ?';
            $params[] = $name;
            $params[] = $name;
        }
        $conditions[] = 'im.maker_id = ?';
        $params[] = $makerId;

        $sourceWhere = items_product_source_where('items');
        $whereSql = $sourceWhere !== '' ? ' AND ' . $sourceWhere : '';

        $sql = 'SELECT DISTINCT items.*
                FROM items
                INNER JOIN item_makers im ON (im.item_id = items.id OR (items.content_id IS NOT NULL AND items.content_id = im.content_id))
                WHERE (' . implode(' OR ', $conditions) . ')' . $whereSql . '
                ORDER BY items.release_date DESC, items.id DESC
                LIMIT ? OFFSET ?';
        $execParams = array_merge($params, [$limit, $offset]);
        $stmt = db()->prepare($sql);
        $stmt->execute($execParams);
        $results = $stmt->fetchAll() ?: [];
        if ($results !== []) {
            return $results;
        }
    } catch (Throwable) {
    }

    // Step 2: Without source filter
    try {
        $conditions = [];
        $params = [];
        if ($dmmId !== '') {
            $conditions[] = 'im.dmm_id = ?';
            $params[] = $dmmId;
        }
        if ($name !== '') {
            $conditions[] = 'im.maker_name = ? OR TRIM(im.maker_name) = ?';
            $params[] = $name;
            $params[] = $name;
        }
        $conditions[] = 'im.maker_id = ?';
        $params[] = $makerId;

        $sql = 'SELECT DISTINCT items.*
                FROM items
                INNER JOIN item_makers im ON (im.item_id = items.id OR (items.content_id IS NOT NULL AND items.content_id = im.content_id))
                WHERE (' . implode(' OR ', $conditions) . ')
                ORDER BY items.release_date DESC, items.id DESC
                LIMIT ? OFFSET ?';
        $execParams = array_merge($params, [$limit, $offset]);
        $stmt = db()->prepare($sql);
        $stmt->execute($execParams);
        $results = $stmt->fetchAll() ?: [];
        if ($results !== []) {
            return $results;
        }
    } catch (Throwable) {
    }

    // Step 3: Partial name search on item_makers
    if ($name !== '') {
        try {
            $sql = 'SELECT DISTINCT items.*
                    FROM items
                    INNER JOIN item_makers im ON (im.item_id = items.id OR (items.content_id IS NOT NULL AND items.content_id = im.content_id))
                    WHERE im.maker_name LIKE ?
                    ORDER BY items.release_date DESC, items.id DESC
                    LIMIT ? OFFSET ?';
            $stmt = db()->prepare($sql);
            $stmt->execute(['%' . $name . '%', $limit, $offset]);
            $results = $stmt->fetchAll() ?: [];
            if ($results !== []) {
                return $results;
            }
        } catch (Throwable) {
        }
    }

    return [];
}

function count_items_by_series(int $seriesId): int
{
    $seriesId = max(1, $seriesId);

    try {
        $series = fetch_series_one($seriesId);
        $seriesName = trim((string)($series['name'] ?? ''));
        $seriesDmmId = trim((string)($series['dmm_id'] ?? ''));

        $sql = 'SELECT COUNT(DISTINCT items.id)
                FROM items
                INNER JOIN item_series ON (
                    (item_series.dmm_id IS NOT NULL AND item_series.dmm_id <> "" AND item_series.dmm_id = :dmm_id)
                    OR (item_series.series_name IS NOT NULL AND item_series.series_name <> "" AND item_series.series_name = :name)
                    OR (item_series.series_id IS NOT NULL AND item_series.series_id = :id)
                )
                WHERE (items.id = item_series.item_id OR (items.content_id IS NOT NULL AND items.content_id = item_series.content_id))';
        $stmt = db()->prepare($sql);
        $stmt->execute([
            ':id' => $seriesId,
            ':dmm_id' => $seriesDmmId,
            ':name' => $seriesName,
        ]);
        $count = (int)$stmt->fetchColumn();
        if ($count > 0) {
            return $count;
        }
    } catch (Throwable) {
    }

    try {
        $sql = 'SELECT COUNT(DISTINCT items.id)
                FROM items
                INNER JOIN item_series ON items.content_id = item_series.content_id
                WHERE item_series.series_id = :id';
        $stmt = db()->prepare($sql);
        $stmt->execute([':id' => $seriesId]);
        return (int)$stmt->fetchColumn();
    } catch (Throwable) {
        return 0;
    }
}

function fetch_items_by_series(int $seriesId, int $limit, int $offset = 0): array
{
    $seriesId = max(1, $seriesId);
    $limit    = normalize_int($limit, 1, 100);
    $offset   = max(0, $offset);

    try {
        $series = fetch_series_one($seriesId);
        $seriesName = trim((string)($series['name'] ?? ''));
        $seriesDmmId = trim((string)($series['dmm_id'] ?? ''));

        $sql = 'SELECT DISTINCT items.*
                FROM items
                INNER JOIN item_series ON (
                    (item_series.dmm_id IS NOT NULL AND item_series.dmm_id <> "" AND item_series.dmm_id = :dmm_id)
                    OR (item_series.series_name IS NOT NULL AND item_series.series_name <> "" AND item_series.series_name = :name)
                    OR (item_series.series_id IS NOT NULL AND item_series.series_id = :id)
                )
                WHERE (items.id = item_series.item_id OR (items.content_id IS NOT NULL AND items.content_id = item_series.content_id))
                ORDER BY items.release_date DESC, items.id DESC
                LIMIT :limit OFFSET :offset';
        $stmt = db()->prepare($sql);
        $stmt->bindValue(':id',      $seriesId,     PDO::PARAM_INT);
        $stmt->bindValue(':dmm_id',  $seriesDmmId,  PDO::PARAM_STR);
        $stmt->bindValue(':name',    $seriesName,   PDO::PARAM_STR);
        $stmt->bindValue(':limit',   $limit,        PDO::PARAM_INT);
        $stmt->bindValue(':offset',  $offset,       PDO::PARAM_INT);
        $stmt->execute();
        $results = $stmt->fetchAll() ?: [];
        if ($results !== []) {
            return $results;
        }
    } catch (Throwable) {
    }

    try {
        $sql = 'SELECT DISTINCT items.*
                FROM items
                INNER JOIN item_series ON items.content_id = item_series.content_id
                WHERE item_series.series_id = :id
                ORDER BY items.release_date DESC, items.id DESC
                LIMIT :limit OFFSET :offset';
        $stmt = db()->prepare($sql);
        $stmt->bindValue(':id',     $seriesId, PDO::PARAM_INT);
        $stmt->bindValue(':limit',  $limit,    PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,   PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    } catch (Throwable) {
        return [];
    }
}


function update_items_view_count(): void
{
    try {
        db()->exec('UPDATE items i SET i.view_count = (SELECT COUNT(*) FROM page_views pv WHERE pv.item_id = i.id)');
    } catch (Throwable) {
    }
}

function fetch_related_items(string $contentId, int $limit = 12): array
{
    $cid = normalize_content_id($contentId);
    if ($cid === '') {
        return [];
    }

    $limit = normalize_int($limit, 1, 50);

    try {
        $sql = db_column_exists('item_genres', 'item_id')
            ? 'SELECT i2.*
               FROM items i1
               INNER JOIN item_genres ig1 ON ig1.item_id = i1.id
               INNER JOIN item_genres ig2 ON ig2.dmm_id = ig1.dmm_id
               INNER JOIN items i2 ON i2.id = ig2.item_id
               WHERE i1.content_id = :cid AND i2.content_id <> :cid
                 AND ' . items_product_source_where('i2') . '
               GROUP BY i2.id
               ORDER BY i2.release_date DESC, i2.id DESC
               LIMIT :limit'
            : 'SELECT i2.*
               FROM items i1
               INNER JOIN item_genres ig1 ON ig1.content_id = i1.content_id
               INNER JOIN item_genres ig2 ON ig2.genre_id = ig1.genre_id
               INNER JOIN items i2 ON i2.content_id = ig2.content_id
               WHERE i1.content_id = :cid AND i2.content_id <> :cid
                 AND ' . items_product_source_where('i2') . '
               GROUP BY i2.id
               ORDER BY i2.release_date DESC, i2.id DESC
               LIMIT :limit';
        $stmt = db()->prepare($sql);
        $stmt->bindValue(':cid', $cid, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll() ?: [];
        if ($rows !== []) {
            return $rows;
        }
    } catch (Throwable) {
    }

    try {
        $stmt = db()->prepare(
            'SELECT * FROM items WHERE content_id <> :cid AND ' . items_product_source_where() . ' ORDER BY release_date DESC, id DESC LIMIT :limit'
        );
        $stmt->bindValue(':cid', $cid, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    } catch (Throwable) {
        return [];
    }
}

function fetch_item_actresses(string $contentId): array
{
    $cid = normalize_content_id($contentId);
    if ($cid === '') {
        return [];
    }

    try {
        $sql = db_column_exists('item_actresses', 'item_id')
            ? 'SELECT DISTINCT actresses.id, actresses.name, actresses.ruby, actresses.birthday, actresses.prefectures, actresses.image_url
               FROM items
               INNER JOIN item_actresses ON items.id         = item_actresses.item_id
               INNER JOIN actresses      ON actresses.dmm_id = item_actresses.dmm_id
               WHERE items.content_id = :cid
               ORDER BY actresses.name ASC'
            : 'SELECT actresses.*
               FROM actresses
               INNER JOIN item_actresses ON actresses.id = item_actresses.actress_id
               WHERE item_actresses.content_id = :cid
               ORDER BY actresses.name ASC';
        $stmt = db()->prepare($sql);
        $stmt->execute([':cid' => $cid]);
        return $stmt->fetchAll() ?: [];
    } catch (Throwable) {
        return [];
    }
}

function fetch_item_genres(string $contentId): array
{
    $cid = normalize_content_id($contentId);
    if ($cid === '') {
        return [];
    }

    try {
        $sql = db_column_exists('item_genres', 'item_id')
            ? 'SELECT DISTINCT genres.id, genres.name, genres.ruby
               FROM items
               INNER JOIN item_genres ON items.id      = item_genres.item_id
               INNER JOIN genres      ON genres.dmm_id = item_genres.dmm_id
               WHERE items.content_id = :cid
               ORDER BY genres.name ASC'
            : 'SELECT genres.*
               FROM genres
               INNER JOIN item_genres ON genres.id = item_genres.genre_id
               WHERE item_genres.content_id = :cid
               ORDER BY genres.name ASC';
        $stmt = db()->prepare($sql);
        $stmt->execute([':cid' => $cid]);
        return $stmt->fetchAll() ?: [];
    } catch (Throwable) {
        return [];
    }
}

function fetch_item_makers(string $contentId): array
{
    $cid = normalize_content_id($contentId);
    if ($cid === '') {
        return [];
    }

    try {
        $sql = db_column_exists('item_makers', 'item_id')
            ? 'SELECT DISTINCT makers.id, makers.name, makers.ruby
               FROM items
               INNER JOIN item_makers ON items.id      = item_makers.item_id
               INNER JOIN makers      ON makers.dmm_id = item_makers.dmm_id
               WHERE items.content_id = :cid
               ORDER BY makers.name ASC'
            : 'SELECT makers.*
               FROM makers
               INNER JOIN item_makers ON makers.id = item_makers.maker_id
               WHERE item_makers.content_id = :cid
               ORDER BY makers.name ASC';
        $stmt = db()->prepare($sql);
        $stmt->execute([':cid' => $cid]);
        return $stmt->fetchAll() ?: [];
    } catch (Throwable) {
        return [];
    }
}

function fetch_item_series(string $contentId): array
{
    $cid = normalize_content_id($contentId);
    if ($cid === '') {
        return [];
    }

    try {
        $sql = db_column_exists('item_series', 'item_id')
            ? 'SELECT DISTINCT series_master.id, series_master.name, series_master.ruby
               FROM items
               INNER JOIN item_series   ON items.id             = item_series.item_id
               INNER JOIN series_master ON series_master.dmm_id = item_series.dmm_id
               WHERE items.content_id = :cid
               ORDER BY series_master.name ASC'
            : 'SELECT series.*
               FROM series
               INNER JOIN item_series ON series.id = item_series.series_id
               WHERE item_series.content_id = :cid
               ORDER BY series.name ASC';
        $stmt = db()->prepare($sql);
        $stmt->execute([':cid' => $cid]);
        return $stmt->fetchAll() ?: [];
    } catch (Throwable) {
        return [];
    }
}

function fetch_item_labels(string $contentId): array
{
    $cid = normalize_content_id($contentId);
    if ($cid === '') {
        return [];
    }

    $stmt = db()->prepare(
        'SELECT label_id, label_name, label_ruby
         FROM item_labels
         WHERE content_id = :cid
         ORDER BY label_name ASC'
    );
    $stmt->execute([':cid' => $cid]);
    return $stmt->fetchAll() ?: [];
}

function fetch_taxonomy_by_id(string $table, string $idField, int $id): ?array
{
    $table   = normalize_table($table, ['genres', 'makers', 'series']);
    $idField = normalize_order($idField, ['id'], 'id');
    $id      = max(1, $id);

    $stmt = db()->prepare("SELECT * FROM {
        $table} WHERE {
        $idField} = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $data = $stmt->fetch();
    return $data ?: null;
}

function upsert_item(array $item): array
{
    $pdo = db();
    $now = now();

    $contentId = normalize_content_id((string)($item['content_id'] ?? ''));
    if ($contentId === '') {
        throw new InvalidArgumentException('content_id is required');
    }

    $payload = [
        'content_id'           => $contentId,
        'product_id'           => (string)($item['product_id'] ?? ''),
        'title'                => (string)($item['title'] ?? ''),
        'item_source'          => 'fanza_product',
        'url'                  => (string)($item['url'] ?? ''),
        'affiliate_url'        => (string)($item['affiliate_url'] ?? ''),
        'image_list'           => (string)($item['image_list'] ?? ''),
        'image_small'          => (string)($item['image_small'] ?? ''),
        'image_large'          => (string)($item['image_large'] ?? ''),
        'sample_movie_url_476' => (string)($item['sample_movie_url_476'] ?? ''),
        'sample_movie_url_560' => (string)($item['sample_movie_url_560'] ?? ''),
        'sample_movie_url_644' => (string)($item['sample_movie_url_644'] ?? ''),
        'sample_movie_url_720' => (string)($item['sample_movie_url_720'] ?? ''),
        'raw_json'             => is_string($item['raw_json'] ?? null) ? (string)$item['raw_json'] : null,
        'date_published'       => $item['date_published'] ?? null,
        'service_code'         => (string)($item['service_code'] ?? ''),
        'floor_code'           => (string)($item['floor_code'] ?? ''),
        'category_name'        => (string)($item['category_name'] ?? ''),
        'price_min'            => (isset($item['price_min']) && is_numeric($item['price_min'])) ? (int)$item['price_min'] : null,
    ];

    ensure_items_item_source_column();

    $stmt = $pdo->prepare('SELECT id FROM items WHERE content_id = :content_id');
    $stmt->execute([':content_id' => $payload['content_id']]);
    $existingId = $stmt->fetchColumn();

    if ($existingId) {
        $sql = 'UPDATE items
                SET product_id           = :product_id,
                    item_source          = :item_source,
                    title                = :title,
                    url                  = :url,
                    affiliate_url        = :affiliate_url,
                    image_list           = :image_list,
                    image_small          = :image_small,
                    image_large          = :image_large,
                    sample_movie_url_476 = :sample_movie_url_476,
                    sample_movie_url_560 = :sample_movie_url_560,
                    sample_movie_url_644 = :sample_movie_url_644,
                    sample_movie_url_720 = :sample_movie_url_720,
                    raw_json             = :raw_json,
                    date_published       = :date_published,
                    service_code         = :service_code,
                    floor_code           = :floor_code,
                    category_name        = :category_name,
                    price_min            = :price_min,
                    updated_at           = :updated_at
                WHERE id = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':product_id'           => $payload['product_id'],
            ':item_source'          => $payload['item_source'],
            ':title'                => $payload['title'],
            ':url'                  => $payload['url'],
            ':affiliate_url'        => $payload['affiliate_url'],
            ':image_list'           => $payload['image_list'],
            ':image_small'          => $payload['image_small'],
            ':image_large'          => $payload['image_large'],
            ':sample_movie_url_476' => $payload['sample_movie_url_476'],
            ':sample_movie_url_560' => $payload['sample_movie_url_560'],
            ':sample_movie_url_644' => $payload['sample_movie_url_644'],
            ':sample_movie_url_720' => $payload['sample_movie_url_720'],
            ':raw_json'             => $payload['raw_json'],
            ':date_published'       => $payload['date_published'],
            ':service_code'         => $payload['service_code'],
            ':floor_code'           => $payload['floor_code'],
            ':category_name'        => $payload['category_name'],
            ':price_min'            => $payload['price_min'],
            ':updated_at'           => $now,
            ':id'                   => (int)$existingId,
        ]);

        return ['id' => (int)$existingId, 'status' => 'updated'];
    }

    $sql = 'INSERT INTO items
            (content_id, product_id, item_source, title, url, affiliate_url, image_list, image_small, image_large,
             sample_movie_url_476, sample_movie_url_560, sample_movie_url_644, sample_movie_url_720,
             raw_json, date_published, service_code, floor_code, category_name, price_min, created_at, updated_at)
            VALUES
            (:content_id, :product_id, :item_source, :title, :url, :affiliate_url, :image_list, :image_small, :image_large,
             :sample_movie_url_476, :sample_movie_url_560, :sample_movie_url_644, :sample_movie_url_720,
             :raw_json, :date_published, :service_code, :floor_code, :category_name, :price_min, :created_at, :updated_at)';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':content_id'           => $payload['content_id'],
        ':product_id'           => $payload['product_id'],
        ':item_source'          => $payload['item_source'],
        ':title'                => $payload['title'],
        ':url'                  => $payload['url'],
        ':affiliate_url'        => $payload['affiliate_url'],
        ':image_list'           => $payload['image_list'],
        ':image_small'          => $payload['image_small'],
        ':image_large'          => $payload['image_large'],
        ':sample_movie_url_476' => $payload['sample_movie_url_476'],
        ':sample_movie_url_560' => $payload['sample_movie_url_560'],
        ':sample_movie_url_644' => $payload['sample_movie_url_644'],
        ':sample_movie_url_720' => $payload['sample_movie_url_720'],
        ':raw_json'             => $payload['raw_json'],
        ':date_published'       => $payload['date_published'],
        ':service_code'         => $payload['service_code'],
        ':floor_code'           => $payload['floor_code'],
        ':category_name'        => $payload['category_name'],
        ':price_min'            => $payload['price_min'],
        ':created_at'           => $now,
        ':updated_at'           => $now,
    ]);

    return ['id' => (int)$pdo->lastInsertId(), 'status' => 'inserted'];
}

function upsert_actress(array $actress): string
{
    $pdo = db();
    $now = now();

    $dmm_id = (string)($actress['dmm_id'] ?? '');
    $name   = (string)($actress['name']   ?? '');
    if ($dmm_id === '' || $name === '') {
        throw new InvalidArgumentException('actress dmm_id/name required');
    }

    $stmt = $pdo->prepare('SELECT id FROM actresses WHERE dmm_id = :dmm_id');
    $stmt->execute([':dmm_id' => $dmm_id]);
    $exists = $stmt->fetchColumn();

    $payload = [
        ':dmm_id'      => $dmm_id,
        ':name'        => $name,
        ':ruby'        => $actress['ruby']        ?? null,
        ':birthday'    => $actress['birthday']    ?? null,
        ':prefectures' => $actress['prefectures'] ?? null,
        ':image_url'   => $actress['image_url']   ?? null,
        ':image_small' => $actress['image_small'] ?? null,
        ':image_large' => $actress['image_large'] ?? null,
        ':updated_at'  => $now,
    ];

    if ($exists) {
        $sql = 'UPDATE actresses
                SET name        = :name,
                    ruby        = :ruby,
                    birthday    = :birthday,
                    prefectures = :prefectures,
                    image_url   = :image_url,
                    image_small = :image_small,
                    image_large = :image_large,
                    updated_at  = :updated_at
                WHERE dmm_id = :dmm_id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($payload);
        return 'updated';
    }

    $sql = 'INSERT INTO actresses
            (dmm_id, name, ruby, birthday, prefectures,
             image_url, image_small, image_large, created_at, updated_at)
            VALUES
            (:dmm_id, :name, :ruby, :birthday, :prefectures,
             :image_url, :image_small, :image_large, :created_at, :updated_at)';
    $stmt = $pdo->prepare($sql);
    $payload[':created_at'] = $now;
    $payload[':updated_at'] = $now;
    $stmt->execute($payload);
    return 'inserted';
}

function upsert_taxonomy(string $table, string $idField, array $data): string
{
    $table = normalize_table($table, ['genres', 'makers', 'series']);
    if ($idField !== 'id') {
        throw new InvalidArgumentException('Invalid id field');
    }

    $pdo = db();
    $now = now();

    $id   = (int)($data['id'] ?? 0);
    $name = (string)($data['name'] ?? '');
    if ($id <= 0 || $name === '') {
        throw new InvalidArgumentException('taxonomy id/name required');
    }

    $stmt = $pdo->prepare("SELECT {$idField} FROM {
        $table} WHERE {
        $idField} = :id");
    $stmt->execute([':id' => $id]);
    $exists = $stmt->fetchColumn();

    $payload = [
        ':id'           => $id,
        ':name'         => $name,
        ':ruby'         => $data['ruby']         ?? null,
        ':list_url'     => $data['list_url']     ?? null,
        ':site_code'    => $data['site_code']    ?? null,
        ':service_code' => $data['service_code'] ?? null,
        ':floor_id'     => $data['floor_id']     ?? null,
        ':floor_code'   => $data['floor_code']   ?? null,
        ':updated_at'   => $now,
    ];

    if ($exists) {
        $sql = "UPDATE {
            $table}
                SET name         = :name,
                    ruby         = :ruby,
                    list_url     = :list_url,
                    site_code    = :site_code,
                    service_code = :service_code,
                    floor_id     = :floor_id,
                    floor_code   = :floor_code,
                    updated_at   = :updated_at
                WHERE {
            $idField} = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($payload);
        return 'updated';
    }

    $sql = "INSERT INTO {
        $table}
            ({$idField}, name, ruby, list_url, site_code, service_code, floor_id, floor_code, created_at, updated_at)
            VALUES
            (:id, :name, :ruby, :list_url, :site_code, :service_code, :floor_id, :floor_code, :created_at, :updated_at)";
    $stmt = $pdo->prepare($sql);
    $payload[':created_at'] = $now;
    $payload[':updated_at'] = $now;
    $stmt->execute($payload);
    return 'inserted';
}

function replace_item_relations(string $contentId, array $relationIds, string $table, string $column): void
{
    $contentId = normalize_content_id($contentId);
    if ($contentId === '') {
        return;
    }

    $table  = normalize_table($table, ['item_actresses', 'item_genres', 'item_makers', 'item_series']);
    $column = normalize_order($column, ['actress_id', 'genre_id', 'maker_id', 'series_id'], $column);

    $pdo = db();

    $delete = $pdo->prepare("DELETE FROM {
        $table} WHERE content_id = :content_id");
    $delete->execute([':content_id' => $contentId]);

    if (!$relationIds) {
        return;
    }

    $sql  = "INSERT INTO {
        $table} (content_id, {$column}) VALUES (:content_id, :rel_id)";
    $stmt = $pdo->prepare($sql);

    foreach ($relationIds as $id) {
        $id = (int)$id;
        if ($id <= 0) {
            continue;
        }
        $stmt->execute([':content_id' => $contentId, ':rel_id' => $id]);
    }
}

function replace_item_labels(string $contentId, array $labels): void
{
    $contentId = normalize_content_id($contentId);
    if ($contentId === '') {
        return;
    }

    $pdo = db();

    $delete = $pdo->prepare('DELETE FROM item_labels WHERE content_id = :content_id');
    $delete->execute([':content_id' => $contentId]);

    if (!$labels) {
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO item_labels (content_id, label_id, label_name, label_ruby)
         VALUES (:content_id, :label_id, :label_name, :label_ruby)'
    );

    foreach ($labels as $label) {
        if (!is_array($label)) {
            continue;
        }
        $name = trim((string)($label['name'] ?? ''));
        if ($name === '') {
            continue;
        }

        $labelId = $label['id'] ?? null;
        $labelId = is_numeric($labelId) ? (int)$labelId : null;

        $stmt->execute([
            ':content_id' => $contentId,
            ':label_id'   => $labelId,
            ':label_name' => $name,
            ':label_ruby' => ($label['ruby'] ?? null),
        ]);
    }
}
