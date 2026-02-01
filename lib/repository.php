<?php
require_once __DIR__ . '/db.php';

function fetch_items(string $orderBy = 'date_published DESC', int $limit = 10): array
{
    $stmt = db()->prepare("SELECT * FROM items ORDER BY {$orderBy} LIMIT :limit");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll();

    if (!$items) {
        return dummy_items($limit);
    }

    return $items;
}

function fetch_item_by_content_id(string $contentId): ?array
{
    $stmt = db()->prepare('SELECT * FROM items WHERE content_id = :cid');
    $stmt->execute([':cid' => $contentId]);
    $item = $stmt->fetch();
    return $item ?: null;
}

function fetch_actresses(int $limit = 50, int $offset = 0): array
{
    $stmt = db()->prepare('SELECT * FROM actresses ORDER BY name ASC LIMIT :limit OFFSET :offset');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $data = $stmt->fetchAll();

    if (!$data) {
        return dummy_actresses($limit);
    }

    return $data;
}

function fetch_actress(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM actresses WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $actress = $stmt->fetch();
    return $actress ?: null;
}

function fetch_genres(int $limit = 50, string $orderBy = 'name ASC'): array
{
    $stmt = db()->prepare("SELECT * FROM genres ORDER BY {$orderBy} LIMIT :limit");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $data = $stmt->fetchAll();

    if (!$data) {
        return dummy_taxonomies($limit, 'genre');
    }

    return $data;
}

function fetch_makers(int $limit = 50, string $orderBy = 'name ASC'): array
{
    $stmt = db()->prepare("SELECT * FROM makers ORDER BY {$orderBy} LIMIT :limit");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $data = $stmt->fetchAll();

    if (!$data) {
        return dummy_taxonomies($limit, 'maker');
    }

    return $data;
}

function fetch_series(int $limit = 50, string $orderBy = 'name ASC'): array
{
    $stmt = db()->prepare("SELECT * FROM series ORDER BY {$orderBy} LIMIT :limit");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $data = $stmt->fetchAll();

    if (!$data) {
        return dummy_taxonomies($limit, 'series');
    }

    return $data;
}

function fetch_items_by_actress(int $actressId, int $limit, int $offset): array
{
    $stmt = db()->prepare(
        'SELECT items.* FROM items INNER JOIN item_actresses ON items.id = item_actresses.item_id WHERE item_actresses.actress_id = :id ORDER BY date_published DESC LIMIT :limit OFFSET :offset'
    );
    $stmt->bindValue(':id', $actressId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll();

    if (!$items) {
        return dummy_items($limit);
    }

    return $items;
}

function fetch_items_by_genre(int $genreId, int $limit, int $offset): array
{
    $stmt = db()->prepare(
        'SELECT items.* FROM items INNER JOIN item_genres ON items.id = item_genres.item_id WHERE item_genres.genre_id = :id ORDER BY date_published DESC LIMIT :limit OFFSET :offset'
    );
    $stmt->bindValue(':id', $genreId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll();

    if (!$items) {
        return dummy_items($limit);
    }

    return $items;
}

function fetch_items_by_maker(int $makerId, int $limit, int $offset): array
{
    $stmt = db()->prepare(
        'SELECT items.* FROM items INNER JOIN item_makers ON items.id = item_makers.item_id WHERE item_makers.maker_id = :id ORDER BY date_published DESC LIMIT :limit OFFSET :offset'
    );
    $stmt->bindValue(':id', $makerId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll();

    if (!$items) {
        return dummy_items($limit);
    }

    return $items;
}

function fetch_items_by_series(int $seriesId, int $limit, int $offset): array
{
    $stmt = db()->prepare(
        'SELECT items.* FROM items INNER JOIN item_series ON items.id = item_series.item_id WHERE item_series.series_id = :id ORDER BY date_published DESC LIMIT :limit OFFSET :offset'
    );
    $stmt->bindValue(':id', $seriesId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll();

    if (!$items) {
        return dummy_items($limit);
    }

    return $items;
}

function fetch_taxonomy_by_id(string $table, string $idField, int $id): ?array
{
    $stmt = db()->prepare("SELECT * FROM {$table} WHERE {$idField} = :id");
    $stmt->execute([':id' => $id]);
    $data = $stmt->fetch();
    return $data ?: null;
}

function upsert_item(array $item): array
{
    $pdo = db();
    $now = now();

    $stmt = $pdo->prepare('SELECT id FROM items WHERE content_id = :content_id');
    $stmt->execute([':content_id' => $item['content_id']]);
    $existingId = $stmt->fetchColumn();

    if ($existingId) {
        $sql = 'UPDATE items SET product_id = :product_id, title = :title, url = :url, affiliate_url = :affiliate_url, image_list = :image_list, image_small = :image_small, image_large = :image_large, date_published = :date_published, service_code = :service_code, floor_code = :floor_code, category_name = :category_name, price_min = :price_min, updated_at = :updated_at WHERE id = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':product_id' => $item['product_id'],
            ':title' => $item['title'],
            ':url' => $item['url'],
            ':affiliate_url' => $item['affiliate_url'],
            ':image_list' => $item['image_list'],
            ':image_small' => $item['image_small'],
            ':image_large' => $item['image_large'],
            ':date_published' => $item['date_published'],
            ':service_code' => $item['service_code'],
            ':floor_code' => $item['floor_code'],
            ':category_name' => $item['category_name'],
            ':price_min' => $item['price_min'],
            ':updated_at' => $now,
            ':id' => $existingId,
        ]);

        return ['id' => (int)$existingId, 'status' => 'updated'];
    }

    $sql = 'INSERT INTO items (content_id, product_id, title, url, affiliate_url, image_list, image_small, image_large, date_published, service_code, floor_code, category_name, price_min, created_at, updated_at) VALUES (:content_id, :product_id, :title, :url, :affiliate_url, :image_list, :image_small, :image_large, :date_published, :service_code, :floor_code, :category_name, :price_min, :created_at, :updated_at)';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':content_id' => $item['content_id'],
        ':product_id' => $item['product_id'],
        ':title' => $item['title'],
        ':url' => $item['url'],
        ':affiliate_url' => $item['affiliate_url'],
        ':image_list' => $item['image_list'],
        ':image_small' => $item['image_small'],
        ':image_large' => $item['image_large'],
        ':date_published' => $item['date_published'],
        ':service_code' => $item['service_code'],
        ':floor_code' => $item['floor_code'],
        ':category_name' => $item['category_name'],
        ':price_min' => $item['price_min'],
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);

    return ['id' => (int)$pdo->lastInsertId(), 'status' => 'inserted'];
}

function upsert_actress(array $actress): string
{
    $pdo = db();
    $now = now();

    $stmt = $pdo->prepare('SELECT id FROM actresses WHERE id = :id');
    $stmt->execute([':id' => $actress['id']]);
    $exists = $stmt->fetchColumn();

    if ($exists) {
        $sql = 'UPDATE actresses SET name = :name, ruby = :ruby, bust = :bust, cup = :cup, waist = :waist, hip = :hip, height = :height, birthday = :birthday, image_small = :image_small, image_large = :image_large, listurl_digital = :listurl_digital, listurl_monthly = :listurl_monthly, listurl_mono = :listurl_mono, updated_at = :updated_at WHERE id = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id' => $actress['id'],
            ':name' => $actress['name'],
            ':ruby' => $actress['ruby'],
            ':bust' => $actress['bust'],
            ':cup' => $actress['cup'],
            ':waist' => $actress['waist'],
            ':hip' => $actress['hip'],
            ':height' => $actress['height'],
            ':birthday' => $actress['birthday'],
            ':image_small' => $actress['image_small'],
            ':image_large' => $actress['image_large'],
            ':listurl_digital' => $actress['listurl_digital'],
            ':listurl_monthly' => $actress['listurl_monthly'],
            ':listurl_mono' => $actress['listurl_mono'],
            ':updated_at' => $now,
        ]);
        return 'updated';
    }

    $sql = 'INSERT INTO actresses (id, name, ruby, bust, cup, waist, hip, height, birthday, image_small, image_large, listurl_digital, listurl_monthly, listurl_mono, created_at, updated_at) VALUES (:id, :name, :ruby, :bust, :cup, :waist, :hip, :height, :birthday, :image_small, :image_large, :listurl_digital, :listurl_monthly, :listurl_mono, :created_at, :updated_at)';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':id' => $actress['id'],
        ':name' => $actress['name'],
        ':ruby' => $actress['ruby'],
        ':bust' => $actress['bust'],
        ':cup' => $actress['cup'],
        ':waist' => $actress['waist'],
        ':hip' => $actress['hip'],
        ':height' => $actress['height'],
        ':birthday' => $actress['birthday'],
        ':image_small' => $actress['image_small'],
        ':image_large' => $actress['image_large'],
        ':listurl_digital' => $actress['listurl_digital'],
        ':listurl_monthly' => $actress['listurl_monthly'],
        ':listurl_mono' => $actress['listurl_mono'],
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);
    return 'inserted';
}

function upsert_taxonomy(string $table, string $idField, array $data): string
{
    $pdo = db();
    $now = now();
    $stmt = $pdo->prepare("SELECT {$idField} FROM {$table} WHERE {$idField} = :id");
    $stmt->execute([':id' => $data[$idField]]);
    $exists = $stmt->fetchColumn();

    if ($exists) {
        $sql = "UPDATE {$table} SET name = :name, ruby = :ruby, list_url = :list_url, site_code = :site_code, service_code = :service_code, floor_id = :floor_id, floor_code = :floor_code, updated_at = :updated_at WHERE {$idField} = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id' => $data[$idField],
            ':name' => $data['name'],
            ':ruby' => $data['ruby'],
            ':list_url' => $data['list_url'],
            ':site_code' => $data['site_code'],
            ':service_code' => $data['service_code'],
            ':floor_id' => $data['floor_id'],
            ':floor_code' => $data['floor_code'],
            ':updated_at' => $now,
        ]);
        return 'updated';
    }

    $sql = "INSERT INTO {$table} ({$idField}, name, ruby, list_url, site_code, service_code, floor_id, floor_code, created_at, updated_at) VALUES (:id, :name, :ruby, :list_url, :site_code, :service_code, :floor_id, :floor_code, :created_at, :updated_at)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':id' => $data[$idField],
        ':name' => $data['name'],
        ':ruby' => $data['ruby'],
        ':list_url' => $data['list_url'],
        ':site_code' => $data['site_code'],
        ':service_code' => $data['service_code'],
        ':floor_id' => $data['floor_id'],
        ':floor_code' => $data['floor_code'],
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);
    return 'inserted';
}

function link_item_relations(int $itemId, array $relationIds, string $table, string $column): void
{
    if (!$relationIds) {
        return;
    }

    $pdo = db();
    $sql = "INSERT IGNORE INTO {$table} (item_id, {$column}) VALUES (:item_id, :rel_id)";
    $stmt = $pdo->prepare($sql);
    foreach ($relationIds as $id) {
        $stmt->execute([
            ':item_id' => $itemId,
            ':rel_id' => $id,
        ]);
    }
}

function dummy_items(int $count): array
{
    $items = [];
    for ($i = 1; $i <= $count; $i++) {
        $items[] = [
            'content_id' => 'dummy-' . $i,
            'title' => 'ダミー作品 ' . $i,
            'url' => '#',
            'affiliate_url' => '#',
            'image_small' => '',
            'image_large' => '',
            'image_list' => '',
            'date_published' => date('Y-m-d', strtotime("-{$i} days")),
            'price_min' => 0,
        ];
    }
    return $items;
}

function dummy_actresses(int $count): array
{
    $items = [];
    for ($i = 1; $i <= $count; $i++) {
        $items[] = [
            'id' => $i,
            'name' => 'ダミー女優 ' . $i,
            'ruby' => '',
            'image_small' => '',
            'image_large' => '',
        ];
    }
    return $items;
}

function dummy_taxonomies(int $count, string $label): array
{
    $items = [];
    for ($i = 1; $i <= $count; $i++) {
        $items[] = [
            $label . '_id' => $i,
            'name' => 'ダミー' . $label . ' ' . $i,
            'ruby' => '',
            'list_url' => '#',
        ];
    }
    return $items;
}
