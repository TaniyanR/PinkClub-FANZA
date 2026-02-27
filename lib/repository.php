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
        'date_published_desc' => 'release_date DESC, id DESC',
        'date_published_asc' => 'release_date ASC, id ASC',
        'price_min_desc' => 'price_min DESC',
        'price_min_asc' => 'price_min ASC',
        'popularity_desc' => 'view_count DESC, id DESC',
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

    $stmt = db()->prepare('SELECT * FROM items WHERE title LIKE :q ORDER BY release_date DESC, id DESC LIMIT :limit OFFSET :offset');
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

/**
 * Update view count for items based on page_views data
 * This should be run periodically (e.g., via cron or manually)
 */
function update_items_view_count(): int
{
    try {
        $pdo = db();
        $pdo->exec('UPDATE items SET view_count = 0');
        $stmt = $pdo->prepare(
            'UPDATE items i
             INNER JOIN (
                SELECT item_id, COUNT(*) AS view_count
                FROM page_views
                GROUP BY item_id
             ) pv ON pv.item_id = i.id
             SET i.view_count = pv.view_count'
        );
        $stmt->execute();
        return $stmt->rowCount();
    } catch (PDOException $e) {
        error_log('update_items_view_count error: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Fetch related items based on genre, actress, and series matches
 * Returns items sorted by relevance score
 * Optimized version using JOINs instead of correlated subqueries
 */
function fetch_related_items(string $contentId, int $limit = 6): array
{
    $cid = normalize_content_id($contentId);
    if ($cid === '') {
        return [];
    }

    $limit = normalize_int($limit, 1, 50);

    try {
        $itemStmt = db()->prepare('SELECT id, release_date FROM items WHERE content_id = :cid LIMIT 1');
        $itemStmt->execute([':cid' => $cid]);
        $baseItem = $itemStmt->fetch();
        if (!is_array($baseItem)) {
            return [];
        }

        $stmt = db()->prepare(
            'SELECT i.*,
                    (
                        COALESCE(ac.match_count, 0) * 5 +
                        COALESCE(sr.match_count, 0) * 4 +
                        COALESCE(gn.match_count, 0) * 3
                    ) AS relevance_score
             FROM items i
             LEFT JOIN (
                SELECT ia.item_id, COUNT(*) AS match_count
                FROM item_actresses ia
                INNER JOIN item_actresses base
                    ON (
                        (base.dmm_id IS NOT NULL AND base.dmm_id != \'\' AND ia.dmm_id = base.dmm_id)
                        OR ia.actress_name = base.actress_name
                    )
                WHERE base.item_id = :item_id1
                GROUP BY ia.item_id
             ) ac ON ac.item_id = i.id
             LEFT JOIN (
                SELECT isr.item_id, COUNT(*) AS match_count
                FROM item_series isr
                INNER JOIN item_series base
                    ON (
                        (base.dmm_id IS NOT NULL AND base.dmm_id != \'\' AND isr.dmm_id = base.dmm_id)
                        OR isr.series_name = base.series_name
                    )
                WHERE base.item_id = :item_id2
                GROUP BY isr.item_id
             ) sr ON sr.item_id = i.id
             LEFT JOIN (
                SELECT ig.item_id, COUNT(*) AS match_count
                FROM item_genres ig
                INNER JOIN item_genres base
                    ON (
                        (base.dmm_id IS NOT NULL AND base.dmm_id != \'\' AND ig.dmm_id = base.dmm_id)
                        OR ig.genre_name = base.genre_name
                    )
                WHERE base.item_id = :item_id3
                GROUP BY ig.item_id
             ) gn ON gn.item_id = i.id
             WHERE i.id != :item_id4
             HAVING relevance_score > 0
             ORDER BY relevance_score DESC, i.release_date DESC, i.id DESC
             LIMIT :limit'
        );
        $itemId = (int)$baseItem['id'];
        $stmt->bindValue(':item_id1', $itemId, PDO::PARAM_INT);
        $stmt->bindValue(':item_id2', $itemId, PDO::PARAM_INT);
        $stmt->bindValue(':item_id3', $itemId, PDO::PARAM_INT);
        $stmt->bindValue(':item_id4', $itemId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $related = $stmt->fetchAll() ?: [];

        // If we don't have enough related items, fill with recent items
        if (count($related) < $limit) {
            $remaining = $limit - count($related);
            $stmt2 = db()->prepare(
                'SELECT * FROM items 
                 WHERE id != :item_id
                 ORDER BY release_date DESC, id DESC
                 LIMIT :limit'
            );
            $stmt2->bindValue(':item_id', $itemId, PDO::PARAM_INT);
            $stmt2->bindValue(':limit', $remaining, PDO::PARAM_INT);
            $stmt2->execute();
            $newItems = $stmt2->fetchAll() ?: [];
            
            // Merge and deduplicate
            $existingIds = array_column($related, 'content_id');
            foreach ($newItems as $item) {
                if (!in_array($item['content_id'], $existingIds, true)) {
                    $related[] = $item;
                }
            }
        }

        return array_slice($related, 0, $limit);
    } catch (PDOException $e) {
        error_log('fetch_related_items error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Generate and save tags for an item based on title and category
 */
function generate_item_tags(string $contentId, string $title, string $category = ''): void
{
    $cid = normalize_content_id($contentId);
    if ($cid === '') {
        return;
    }

    try {
        $pdo = db();
        
        // Extract keywords from title and category
        $text = $title . ' ' . $category;
        $keywords = extract_tag_keywords($text);
        
        if (empty($keywords)) {
            return;
        }
        
        // Insert tags and get their IDs
        $tagIds = [];
        foreach ($keywords as $keyword) {
            // Insert or get existing tag
            $stmt = $pdo->prepare(
                'INSERT INTO tags (name, created_at) 
                 VALUES (:name, NOW()) 
                 ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)'
            );
            $stmt->execute([':name' => $keyword]);
            $tagId = (int)$pdo->lastInsertId();
            
            if ($tagId > 0) {
                $tagIds[] = $tagId;
            }
        }
        
        $itemStmt = $pdo->prepare('SELECT id FROM items WHERE content_id = :cid LIMIT 1');
        $itemStmt->execute([':cid' => $cid]);
        $itemId = (int)$itemStmt->fetchColumn();
        if ($itemId <= 0) {
            return;
        }

        // Delete existing tag associations for this item
        $stmt = $pdo->prepare('DELETE FROM item_tags WHERE item_id = :item_id');
        $stmt->execute([':item_id' => $itemId]);
        
        // Insert new tag associations
        if (!empty($tagIds)) {
            $stmt = $pdo->prepare(
                'INSERT IGNORE INTO item_tags (item_id, tag_id) 
                 VALUES (:item_id, :tag_id)'
            );
            
            foreach ($tagIds as $tagId) {
                $stmt->execute([':item_id' => $itemId, ':tag_id' => $tagId]);
            }
        }
    } catch (PDOException $e) {
        error_log('generate_item_tags error: ' . $e->getMessage());
    }
}

/**
 * Extract keywords from text for tag generation
 * Simple implementation - extracts common patterns
 */
function extract_tag_keywords(string $text): array
{
    $text = mb_strtolower($text);
    $keywords = [];
    
    // Common patterns for adult content tags (basic version)
    $patterns = [
        '巨乳', '爆乳', '美乳', '貧乳',
        '痴女', '人妻', '熟女', '若妻',
        'OL', 'JK', '女子校生', '制服',
        'メイド', 'ナース', 'CA',
        'ハメ撮り', 'ごっくん', '中出し',
        '潮吹き', 'フェラ', 'パイズリ',
        '3P', '4P', '乱交',
        'SM', '緊縛', '拘束',
        'アナル', '顔射', 'ぶっかけ',
        'バイブ', 'ローター',
        '野外', '露出', '温泉',
        'コスプレ', 'レズ', '女優',
    ];
    
    foreach ($patterns as $pattern) {
        if (mb_strpos($text, $pattern) !== false) {
            $keywords[] = $pattern;
        }
    }
    
    // Limit to top 10 tags
    return array_slice(array_unique($keywords), 0, 10);
}

/**
 * Fetch tags for an item
 */
function fetch_item_tags(string $contentId): array
{
    $cid = normalize_content_id($contentId);
    if ($cid === '') {
        return [];
    }

    try {
        $stmt = db()->prepare(
            'SELECT tags.* 
             FROM tags
             INNER JOIN item_tags ON tags.id = item_tags.tag_id
             INNER JOIN items ON items.id = item_tags.item_id
             WHERE items.content_id = :cid
             ORDER BY tags.name ASC'
        );
        $stmt->execute([':cid' => $cid]);
        return $stmt->fetchAll() ?: [];
    } catch (PDOException $e) {
        error_log('fetch_item_tags error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Fetch all tags with item counts
 */
function fetch_all_tags(int $limit = 100, int $offset = 0): array
{
    $limit = normalize_int($limit, 1, 500);
    $offset = max(0, $offset);

    try {
        $stmt = db()->prepare(
            'SELECT tags.*, COUNT(item_tags.item_id) as item_count
             FROM tags
             LEFT JOIN item_tags ON tags.id = item_tags.tag_id
             GROUP BY tags.id
             ORDER BY item_count DESC, tags.name ASC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    } catch (PDOException $e) {
        error_log('fetch_all_tags error: ' . $e->getMessage());
        return [];
    }
}

function generate_tags_for_item(array $item): void
{
    $contentId = (string)($item['content_id'] ?? '');
    $title = (string)($item['title'] ?? '');
    $category = (string)($item['category_name'] ?? '');
    generate_item_tags($contentId, $title, $category);
}

function delete_tag(int $tagId): bool
{
    $tagId = max(1, $tagId);
    $stmt = db()->prepare('DELETE FROM tags WHERE id = :id');
    return $stmt->execute([':id' => $tagId]);
}
