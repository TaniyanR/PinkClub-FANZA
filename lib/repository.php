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

    // 空・長すぎは拒否
    if ($contentId === '' || strlen($contentId) > 64) {
        return '';
    }

    // 許容文字（最低限）：英数 + _ - .
    if (!preg_match('/^[A-Za-z0-9._-]+$/', $contentId)) {
        return '';
    }

    return $contentId;
}

function fetch_items(string $orderBy = 'date_published_desc', int $limit = 10, int $offset = 0): array
{
    $allowedOrders = [
        'date_published_desc' => 'date_published DESC',
        'date_published_asc' => 'date_published ASC',
        'price_min_desc' => 'price_min DESC',
        'price_min_asc' => 'price_min ASC',
        'random' => 'RAND()',
    ];
    if (array_key_exists($orderBy, $allowedOrders)) {
        $orderBySql = $allowedOrders[$orderBy];
    } else {
        $orderBySql = normalize_order($orderBy, array_values($allowedOrders), $allowedOrders['date_published_desc']);
    }

    $limit = normalize_int($limit, 1, 100);
    $offset = max(0, $offset);

    $stmt = db()->prepare("SELECT * FROM items ORDER BY {$orderBySql} LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
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

    $stmt = db()->prepare('SELECT * FROM items WHERE content_id = :cid LIMIT 1');
    $stmt->execute([':cid' => $cid]);
    $item = $stmt->fetch();
    return $item ?: null;
}

function fetch_item_by_cid(string $cid): ?array
{
    return fetch_item_by_content_id($cid);
}

/**
 * （任意）検索関数：index.php が存在チェックして呼ぶ
 * タイトル部分一致（MySQL想定）。SQLiteなら LIKE はそのまま動きます。
 */
function search_items(string $q, int $limit = 10, int $offset = 0): array
{
    $q = trim($q);
    if ($q === '') {
        return [];
    }
    if (mb_strlen($q) > 100) {
        $q = mb_substr($q, 0, 100);
    }

    $limit = normalize_int($limit, 1, 100);
    $offset = max(0, $offset);

    $stmt = db()->prepare('SELECT * FROM items WHERE title LIKE :q ORDER BY date_published DESC LIMIT :limit OFFSET :offset');
    $stmt->bindValue(':q', '%' . $q . '%', PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll() ?: [];
}

function fetch_actresses(int $limit = 50, int $offset = 0, string $order = 'name'): array
{
    $orderBy = normalize_order($order, ['name', 'created_at', 'updated_at'], 'name');
    $limit = normalize_int($limit, 1, 200);
    $offset = max(0, $offset);

    $stmt = db()->prepare("SELECT * FROM actresses ORDER BY {$orderBy} ASC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll() ?: [];
}

function fetch_actress(int $id): ?array
{
    $id = max(1, $id);
    $stmt = db()->prepare('SELECT * FROM actresses WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $actress = $stmt->fetch();
    return $actress ?: null;
}

function fetch_related_series_by_actress(int $actressId, int $limit = 50): array
{
    $actressId = max(1, $actressId);
    $limit = normalize_int($limit, 1, 200);

    $stmt = db()->prepare(
        'SELECT DISTINCT series.* 
         FROM series
         INNER JOIN item_series ON series.id = item_series.series_id
         INNER JOIN item_actresses ON item_series.content_id = item_actresses.content_id
         WHERE item_actresses.actress_id = :id
         ORDER BY series.name ASC
         LIMIT :limit'
    );
    $stmt->bindValue(':id', $actressId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll() ?: [];
}

function fetch_related_makers_by_actress(int $actressId, int $limit = 50): array
{
    $actressId = max(1, $actressId);
    $limit = normalize_int($limit, 1, 200);

    $stmt = db()->prepare(
        'SELECT DISTINCT makers.* 
         FROM makers
         INNER JOIN item_makers ON makers.id = item_makers.maker_id
         INNER JOIN item_actresses ON item_makers.content_id = item_actresses.content_id
         WHERE item_actresses.actress_id = :id
         ORDER BY makers.name ASC
         LIMIT :limit'
    );
    $stmt->bindValue(':id', $actressId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll() ?: [];
}

function fetch_genre(int $genreId): ?array
{
    $genreId = max(1, $genreId);
    $stmt = db()->prepare('SELECT * FROM genres WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $genreId]);
    $genre = $stmt->fetch();
    return $genre ?: null;
}

function fetch_maker(int $makerId): ?array
{
    $makerId = max(1, $makerId);
    $stmt = db()->prepare('SELECT * FROM makers WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $makerId]);
    $maker = $stmt->fetch();
    return $maker ?: null;
}

function fetch_series_one(int $seriesId): ?array
{
    $seriesId = max(1, $seriesId);
    $stmt = db()->prepare('SELECT * FROM series WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $seriesId]);
    $series = $stmt->fetch();
    return $series ?: null;
}

function fetch_genres(int $limit = 50, int $offset = 0, string $order = 'name'): array
{
    $orderBy = normalize_order($order, ['name', 'created_at', 'updated_at'], 'name');
    $limit = normalize_int($limit, 1, 200);
    $offset = max(0, $offset);

    $stmt = db()->prepare("SELECT * FROM genres ORDER BY {$orderBy} ASC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll() ?: [];
}

function fetch_makers(int $limit = 50, int $offset = 0, string $order = 'name'): array
{
    $orderBy = normalize_order($order, ['name', 'created_at', 'updated_at'], 'name');
    $limit = normalize_int($limit, 1, 200);
    $offset = max(0, $offset);

    $stmt = db()->prepare("SELECT * FROM makers ORDER BY {$orderBy} ASC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll() ?: [];
}

function fetch_series(int $limit = 50, int $offset = 0, string $order = 'name'): array
{
    $orderBy = normalize_order($order, ['name', 'created_at', 'updated_at'], 'name');
    $limit = normalize_int($limit, 1, 200);
    $offset = max(0, $offset);

    $stmt = db()->prepare("SELECT * FROM series ORDER BY {$orderBy} ASC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll() ?: [];
}


function fetch_labels(int $limit = 50, int $offset = 0): array
{
    $limit = normalize_int($limit, 1, 200);
    $offset = max(0, $offset);

    $stmt = db()->prepare(
        'SELECT label_id AS id, label_name AS name, MAX(label_ruby) AS ruby, COUNT(*) AS item_count
         FROM item_labels
         GROUP BY label_id, label_name
         ORDER BY label_name ASC
         LIMIT :limit OFFSET :offset'
    );
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll() ?: [];
}

function fetch_items_by_label_name(string $labelName, int $limit, int $offset = 0): array
{
    $labelName = trim($labelName);
    if ($labelName === '') {
        return [];
    }

    $limit = normalize_int($limit, 1, 100);
    $offset = max(0, $offset);

    $stmt = db()->prepare(
        'SELECT items.*
         FROM items
         INNER JOIN item_labels ON items.content_id = item_labels.content_id
         WHERE item_labels.label_name = :label_name
         ORDER BY items.date_published DESC
         LIMIT :limit OFFSET :offset'
    );
    $stmt->bindValue(':label_name', $labelName, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll() ?: [];
}

function fetch_items_by_actress(int $actressId, int $limit, int $offset = 0): array
{
    $actressId = max(1, $actressId);
    $limit = normalize_int($limit, 1, 100);
    $offset = max(0, $offset);

    $stmt = db()->prepare(
        'SELECT items.* 
         FROM items 
         INNER JOIN item_actresses ON items.content_id = item_actresses.content_id
         WHERE item_actresses.actress_id = :id 
         ORDER BY date_published DESC
         LIMIT :limit OFFSET :offset'
    );
    $stmt->bindValue(':id', $actressId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll() ?: [];
}

function fetch_items_by_genre(int $genreId, int $limit, int $offset = 0): array
{
    $genreId = max(1, $genreId);
    $limit = normalize_int($limit, 1, 100);
    $offset = max(0, $offset);

    $stmt = db()->prepare(
        'SELECT items.* 
         FROM items 
         INNER JOIN item_genres ON items.content_id = item_genres.content_id
         WHERE item_genres.genre_id = :id
         ORDER BY date_published DESC
         LIMIT :limit OFFSET :offset'
    );
    $stmt->bindValue(':id', $genreId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll() ?: [];
}

function fetch_items_by_maker(int $makerId, int $limit, int $offset = 0): array
{
    $makerId = max(1, $makerId);
    $limit = normalize_int($limit, 1, 100);
    $offset = max(0, $offset);

    $stmt = db()->prepare(
        'SELECT items.* 
         FROM items 
         INNER JOIN item_makers ON items.content_id = item_makers.content_id
         WHERE item_makers.maker_id = :id
         ORDER BY date_published DESC
         LIMIT :limit OFFSET :offset'
    );
    $stmt->bindValue(':id', $makerId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll() ?: [];
}

function fetch_items_by_series(int $seriesId, int $limit, int $offset = 0): array
{
    $seriesId = max(1, $seriesId);
    $limit = normalize_int($limit, 1, 100);
    $offset = max(0, $offset);

    $stmt = db()->prepare(
        'SELECT items.* 
         FROM items
         INNER JOIN item_series ON items.content_id = item_series.content_id
         WHERE item_series.series_id = :id
         ORDER BY date_published DESC
         LIMIT :limit OFFSET :offset'
    );
    $stmt->bindValue(':id', $seriesId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll() ?: [];
}


function fetch_item_actresses(string $contentId): array
{
    $cid = normalize_content_id($contentId);
    if ($cid === '') {
        return [];
    }

    $stmt = db()->prepare(
        'SELECT actresses.*
         FROM actresses
         INNER JOIN item_actresses ON actresses.id = item_actresses.actress_id
         WHERE item_actresses.content_id = :cid
         ORDER BY actresses.name ASC'
    );
    $stmt->execute([':cid' => $cid]);
    return $stmt->fetchAll() ?: [];
}

function fetch_item_genres(string $contentId): array
{
    $cid = normalize_content_id($contentId);
    if ($cid === '') {
        return [];
    }

    $stmt = db()->prepare(
        'SELECT genres.*
         FROM genres
         INNER JOIN item_genres ON genres.id = item_genres.genre_id
         WHERE item_genres.content_id = :cid
         ORDER BY genres.name ASC'
    );
    $stmt->execute([':cid' => $cid]);
    return $stmt->fetchAll() ?: [];
}

function fetch_item_makers(string $contentId): array
{
    $cid = normalize_content_id($contentId);
    if ($cid === '') {
        return [];
    }

    $stmt = db()->prepare(
        'SELECT makers.*
         FROM makers
         INNER JOIN item_makers ON makers.id = item_makers.maker_id
         WHERE item_makers.content_id = :cid
         ORDER BY makers.name ASC'
    );
    $stmt->execute([':cid' => $cid]);
    return $stmt->fetchAll() ?: [];
}

function fetch_item_series(string $contentId): array
{
    $cid = normalize_content_id($contentId);
    if ($cid === '') {
        return [];
    }

    $stmt = db()->prepare(
        'SELECT series.*
         FROM series
         INNER JOIN item_series ON series.id = item_series.series_id
         WHERE item_series.content_id = :cid
         ORDER BY series.name ASC'
    );
    $stmt->execute([':cid' => $cid]);
    return $stmt->fetchAll() ?: [];
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
    $table = normalize_table($table, ['genres', 'makers', 'series']);
    $idField = normalize_order($idField, ['id'], 'id'); // いまは 'id' のみ許可
    $id = max(1, $id);

    $stmt = db()->prepare("SELECT * FROM {$table} WHERE {$idField} = :id LIMIT 1");
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

    // 必須キー（無ければ空文字/NULLに寄せる）
    $payload = [
        'content_id' => $contentId,
        'product_id' => (string)($item['product_id'] ?? ''),
        'title' => (string)($item['title'] ?? ''),
        'url' => (string)($item['url'] ?? ''),
        'affiliate_url' => (string)($item['affiliate_url'] ?? ''),
        'image_list' => (string)($item['image_list'] ?? ''),
        'image_small' => (string)($item['image_small'] ?? ''),
        'image_large' => (string)($item['image_large'] ?? ''),
        'date_published' => $item['date_published'] ?? null,
        'service_code' => (string)($item['service_code'] ?? ''),
        'floor_code' => (string)($item['floor_code'] ?? ''),
        'category_name' => (string)($item['category_name'] ?? ''),
        'price_min' => (isset($item['price_min']) && is_numeric($item['price_min'])) ? (int)$item['price_min'] : null,
    ];

    $stmt = $pdo->prepare('SELECT id FROM items WHERE content_id = :content_id');
    $stmt->execute([':content_id' => $payload['content_id']]);
    $existingId = $stmt->fetchColumn();

    if ($existingId) {
        $sql = 'UPDATE items
                SET product_id = :product_id,
                    title = :title,
                    url = :url,
                    affiliate_url = :affiliate_url,
                    image_list = :image_list,
                    image_small = :image_small,
                    image_large = :image_large,
                    date_published = :date_published,
                    service_code = :service_code,
                    floor_code = :floor_code,
                    category_name = :category_name,
                    price_min = :price_min,
                    updated_at = :updated_at
                WHERE id = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':product_id' => $payload['product_id'],
            ':title' => $payload['title'],
            ':url' => $payload['url'],
            ':affiliate_url' => $payload['affiliate_url'],
            ':image_list' => $payload['image_list'],
            ':image_small' => $payload['image_small'],
            ':image_large' => $payload['image_large'],
            ':date_published' => $payload['date_published'],
            ':service_code' => $payload['service_code'],
            ':floor_code' => $payload['floor_code'],
            ':category_name' => $payload['category_name'],
            ':price_min' => $payload['price_min'],
            ':updated_at' => $now,
            ':id' => (int)$existingId,
        ]);

        return ['id' => (int)$existingId, 'status' => 'updated'];
    }

    $sql = 'INSERT INTO items
            (content_id, product_id, title, url, affiliate_url, image_list, image_small, image_large, date_published, service_code, floor_code, category_name, price_min, created_at, updated_at)
            VALUES
            (:content_id, :product_id, :title, :url, :affiliate_url, :image_list, :image_small, :image_large, :date_published, :service_code, :floor_code, :category_name, :price_min, :created_at, :updated_at)';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':content_id' => $payload['content_id'],
        ':product_id' => $payload['product_id'],
        ':title' => $payload['title'],
        ':url' => $payload['url'],
        ':affiliate_url' => $payload['affiliate_url'],
        ':image_list' => $payload['image_list'],
        ':image_small' => $payload['image_small'],
        ':image_large' => $payload['image_large'],
        ':date_published' => $payload['date_published'],
        ':service_code' => $payload['service_code'],
        ':floor_code' => $payload['floor_code'],
        ':category_name' => $payload['category_name'],
        ':price_min' => $payload['price_min'],
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);

    return ['id' => (int)$pdo->lastInsertId(), 'status' => 'inserted'];
}

function upsert_actress(array $actress): string
{
    $pdo = db();
    $now = now();

    $id = (int)($actress['id'] ?? 0);
    $name = (string)($actress['name'] ?? '');
    if ($id <= 0 || $name === '') {
        throw new InvalidArgumentException('actress id/name required');
    }

    $stmt = $pdo->prepare('SELECT id FROM actresses WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $exists = $stmt->fetchColumn();

    $payload = [
        ':id' => $id,
        ':name' => $name,
        ':ruby' => $actress['ruby'] ?? null,
        ':bust' => $actress['bust'] ?? null,
        ':cup' => $actress['cup'] ?? null,
        ':waist' => $actress['waist'] ?? null,
        ':hip' => $actress['hip'] ?? null,
        ':height' => $actress['height'] ?? null,
        ':birthday' => $actress['birthday'] ?? null,
        ':blood_type' => $actress['blood_type'] ?? null,
        ':hobby' => $actress['hobby'] ?? null,
        ':prefectures' => $actress['prefectures'] ?? null,
        ':image_small' => $actress['image_small'] ?? null,
        ':image_large' => $actress['image_large'] ?? null,
        ':listurl_digital' => $actress['listurl_digital'] ?? null,
        ':listurl_monthly' => $actress['listurl_monthly'] ?? null,
        ':listurl_mono' => $actress['listurl_mono'] ?? null,
        ':updated_at' => $now,
    ];

    if ($exists) {
        $sql = 'UPDATE actresses
                SET name = :name, ruby = :ruby, bust = :bust, cup = :cup, waist = :waist, hip = :hip, height = :height,
                    birthday = :birthday, blood_type = :blood_type, hobby = :hobby, prefectures = :prefectures,
                    image_small = :image_small, image_large = :image_large,
                    listurl_digital = :listurl_digital, listurl_monthly = :listurl_monthly, listurl_mono = :listurl_mono,
                    updated_at = :updated_at
                WHERE id = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($payload);
        return 'updated';
    }

    $sql = 'INSERT INTO actresses
            (id, name, ruby, bust, cup, waist, hip, height, birthday, blood_type, hobby, prefectures, image_small, image_large, listurl_digital, listurl_monthly, listurl_mono, created_at, updated_at)
            VALUES
            (:id, :name, :ruby, :bust, :cup, :waist, :hip, :height, :birthday, :blood_type, :hobby, :prefectures, :image_small, :image_large, :listurl_digital, :listurl_monthly, :listurl_mono, :created_at, :updated_at)';
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

    $id = (int)($data['id'] ?? 0);
    $name = (string)($data['name'] ?? '');
    if ($id <= 0 || $name === '') {
        throw new InvalidArgumentException('taxonomy id/name required');
    }

    $stmt = $pdo->prepare("SELECT {$idField} FROM {$table} WHERE {$idField} = :id");
    $stmt->execute([':id' => $id]);
    $exists = $stmt->fetchColumn();

    $payload = [
        ':id' => $id,
        ':name' => $name,
        ':ruby' => $data['ruby'] ?? null,
        ':list_url' => $data['list_url'] ?? null,
        ':site_code' => $data['site_code'] ?? null,
        ':service_code' => $data['service_code'] ?? null,
        ':floor_id' => $data['floor_id'] ?? null,
        ':floor_code' => $data['floor_code'] ?? null,
        ':updated_at' => $now,
    ];

    if ($exists) {
        $sql = "UPDATE {$table}
                SET name = :name, ruby = :ruby, list_url = :list_url,
                    site_code = :site_code, service_code = :service_code, floor_id = :floor_id, floor_code = :floor_code,
                    updated_at = :updated_at
                WHERE {$idField} = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($payload);
        return 'updated';
    }

    $sql = "INSERT INTO {$table}
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

    $table = normalize_table($table, ['item_actresses', 'item_genres', 'item_makers', 'item_series']);
    $column = normalize_order($column, ['actress_id', 'genre_id', 'maker_id', 'series_id'], $column);

    $pdo = db();

    $delete = $pdo->prepare("DELETE FROM {$table} WHERE content_id = :content_id");
    $delete->execute([':content_id' => $contentId]);

    if (!$relationIds) {
        return;
    }

    $sql = "INSERT INTO {$table} (content_id, {$column}) VALUES (:content_id, :rel_id)";
    $stmt = $pdo->prepare($sql);

    foreach ($relationIds as $id) {
        $id = (int)$id;
        if ($id <= 0) {
            continue;
        }
        $stmt->execute([
            ':content_id' => $contentId,
            ':rel_id' => $id,
        ]);
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
            ':label_id' => $labelId,
            ':label_name' => $name,
            ':label_ruby' => ($label['ruby'] ?? null),
        ]);
    }
}
