<?php

declare(strict_types=1);

require_once __DIR__ . '/dmm_api_client.php';
require_once __DIR__ . '/dmm_normalizer.php';

class DmmSyncService
{
    public function __construct(private readonly DmmApiClient $client, private readonly PDO $pdo)
    {
        $this->ensureSchema();
    }

    public function syncFloors(): int
    {
        $response = $this->client->fetchFloorList();
        $siteList = DmmNormalizer::toList($response['result']['site'] ?? []);
        $count = 0;
        $this->pdo->beginTransaction();
        try {
            foreach ($siteList as $site) {
                $siteCode = $site['site'] ?? '';
                $siteName = $site['name'] ?? '';
                $this->upsertSimple('dmm_sites', 'site_code', $siteCode, $siteName);
                foreach (DmmNormalizer::toList($site['service'] ?? []) as $service) {
                    $serviceCode = $service['service'] ?? '';
                    $serviceName = $service['name'] ?? '';
                    $this->pdo->prepare('INSERT INTO dmm_services(site_code,service_code,name,updated_at) VALUES(?,?,?,NOW()) ON DUPLICATE KEY UPDATE name=VALUES(name),updated_at=NOW()')
                        ->execute([$siteCode, $serviceCode, $serviceName]);
                    foreach (DmmNormalizer::toList($service['floor'] ?? []) as $floor) {
                        $floorCode = $floor['floor'] ?? '';
                        $floorName = $floor['name'] ?? '';
                        $this->pdo->prepare('INSERT INTO dmm_floors(service_code,floor_code,name,updated_at) VALUES(?,?,?,NOW()) ON DUPLICATE KEY UPDATE name=VALUES(name),updated_at=NOW()')
                            ->execute([$serviceCode, $floorCode, $floorName]);
                        $count++;
                    }
                }
            }
            $this->pdo->commit();
            $this->logSync('floors', 1, $count, 'Floor sync completed.');
            return $count;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            $this->logSync('floors', 0, 0, $e->getMessage());
            throw $e;
        }
    }

    public function syncMaster(string $kind, ?string $floorId = null, int $offset = 1, int $hits = 100): int
    {
        $count = 0;
        $params = ['hits' => min(100, max(1, $hits)), 'offset' => max(1, $offset)];
        if ($floorId && $kind !== 'actress') {
            $params['floor_id'] = $floorId;
        }

        $response = match ($kind) {
            'actress' => $this->client->searchActresses($params),
            'genre' => $this->client->searchGenres($params),
            'maker' => $this->client->searchMakers($params),
            'series' => $this->client->searchSeries($params),
            'author' => $this->client->searchAuthors($params),
            default => throw new InvalidArgumentException('Unknown master type.'),
        };

        $key = $kind === 'series' ? 'series' : ($kind === 'actress' ? 'actress' : $kind);
        $rows = DmmNormalizer::toList($response['result'][$key] ?? []);
        $table = match ($kind) {
            'genre' => 'genres',
            'maker' => 'makers',
            'author' => 'authors',
            'actress' => 'actresses',
            'series' => 'series_master',
            default => throw new InvalidArgumentException('Unknown master type.'),
        };

        $this->pdo->beginTransaction();
        try {
            foreach ($rows as $r) {
                $id = (string) ($r['id'] ?? '');
                if ($id === '') {
                    continue;
                }
                $name = (string) ($r['name'] ?? '');
                $ruby = $r['ruby'] ?? null;
                $stmt = $this->pdo->prepare("INSERT INTO {$table}(dmm_id,name,ruby,birthday,prefectures,image_url,updated_at) VALUES(:id,:name,:ruby,:birthday,:pref,:img,NOW()) ON DUPLICATE KEY UPDATE name=VALUES(name),ruby=VALUES(ruby),birthday=VALUES(birthday),prefectures=VALUES(prefectures),image_url=VALUES(image_url),updated_at=NOW()");
                $stmt->execute([
                    'id' => $id,
                    'name' => $name,
                    'ruby' => $ruby,
                    'birthday' => $r['birthday'] ?? null,
                    'pref' => $r['prefectures'] ?? null,
                    'img' => $r['imageURL']['large'] ?? null,
                ]);
                $count++;
            }
            $this->pdo->commit();
            $this->logSync($kind . 's', 1, $count, 'Master sync completed.');
            return $count;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            $this->logSync($kind . 's', 0, 0, $e->getMessage());
            throw $e;
        }
    }

    public function syncItems(string $serviceCode, string $floorCode, array $params = []): int
    {
        $hits = min(100, max(1, (int)($params['hits'] ?? 100)));
        $offset = max(1, (int)($params['offset'] ?? 1));
        $response = $this->client->fetchItems($serviceCode, $floorCode, ['hits' => $hits, 'offset' => $offset]);
        $items = DmmNormalizer::normalizeItemsResponse($response);
        return $this->saveItems($items, 'items');
    }

    public function syncItemsBatch(string $serviceCode, string $floorCode, int $batch, int $offset = 1): array
    {
        $remaining = max(1, $batch);
        $currentOffset = max(1, $offset);
        $total = 0;

        while ($remaining > 0) {
            $hits = min(100, $remaining);
            $response = $this->client->fetchItems($serviceCode, $floorCode, ['hits' => $hits, 'offset' => $currentOffset]);
            $items = DmmNormalizer::normalizeItemsResponse($response);
            if ($items === []) {
                $currentOffset = 1;
                break;
            }

            $saved = $this->saveItems($items, 'items');
            $total += $saved;
            $remaining -= $hits;
            $currentOffset += 100;

            if (count($items) < $hits) {
                $currentOffset = 1;
                break;
            }
        }

        return ['synced_count' => $total, 'next_offset' => $currentOffset];
    }

    private function saveItems(array $items, string $logType): int
    {
        $count = 0;
        $this->pdo->beginTransaction();
        try {
            foreach ($items as $item) {
                $itemId = $this->upsertItem($item);
                $this->rebuildItemRelations($itemId, $item);
                $count++;
            }
            $this->pdo->commit();
            $this->logSync($logType, 1, $count, 'Item sync completed.');
            return $count;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            $this->logSync($logType, 0, 0, $e->getMessage());
            throw $e;
        }
    }

    private function upsertSimple(string $table, string $codeColumn, string $code, string $name): void
    {
        $this->pdo->prepare("INSERT INTO {$table}({$codeColumn},name,updated_at) VALUES(?,?,NOW()) ON DUPLICATE KEY UPDATE name=VALUES(name),updated_at=NOW()")
            ->execute([$code, $name]);
    }

    private function upsertItem(array $item): int
    {
        $sql = 'INSERT INTO items(content_id,product_id,title,service_code,service_name,floor_code,floor_name,category_name,volume,review_count,review_average,url,affiliate_url,image_list,image_small,image_large,sample_movie_url_476,sample_movie_url_560,sample_movie_url_644,sample_movie_url_720,sample_movie_pc_flag,sample_movie_sp_flag,price_min_text,list_price_text,release_date,raw_json,updated_at)
                VALUES(:content_id,:product_id,:title,:service_code,:service_name,:floor_code,:floor_name,:category_name,:volume,:review_count,:review_average,:url,:affiliate_url,:image_list,:image_small,:image_large,:u476,:u560,:u644,:u720,:pc,:sp,:price_min,:list_price,:release_date,:raw_json,NOW())
                ON DUPLICATE KEY UPDATE title=VALUES(title),service_name=VALUES(service_name),floor_name=VALUES(floor_name),category_name=VALUES(category_name),volume=VALUES(volume),review_count=VALUES(review_count),review_average=VALUES(review_average),url=VALUES(url),affiliate_url=VALUES(affiliate_url),image_list=VALUES(image_list),image_small=VALUES(image_small),image_large=VALUES(image_large),sample_movie_url_476=VALUES(sample_movie_url_476),sample_movie_url_560=VALUES(sample_movie_url_560),sample_movie_url_644=VALUES(sample_movie_url_644),sample_movie_url_720=VALUES(sample_movie_url_720),sample_movie_pc_flag=VALUES(sample_movie_pc_flag),sample_movie_sp_flag=VALUES(sample_movie_sp_flag),price_min_text=VALUES(price_min_text),list_price_text=VALUES(list_price_text),release_date=VALUES(release_date),raw_json=VALUES(raw_json),updated_at=NOW()';
        $this->pdo->prepare($sql)->execute([
            'content_id' => $item['content_id'], 'product_id' => $item['product_id'], 'title' => $item['title'],
            'service_code' => $item['service_code'], 'service_name' => $item['service_name'], 'floor_code' => $item['floor_code'], 'floor_name' => $item['floor_name'],
            'category_name' => $item['category_name'], 'volume' => $item['volume'], 'review_count' => $item['review_count'], 'review_average' => $item['review_average'],
            'url' => $item['url'], 'affiliate_url' => $item['affiliate_url'], 'image_list' => $item['image_list'], 'image_small' => $item['image_small'], 'image_large' => $item['image_large'],
            'u476' => $item['sample_movie_url_476'], 'u560' => $item['sample_movie_url_560'], 'u644' => $item['sample_movie_url_644'], 'u720' => $item['sample_movie_url_720'],
            'pc' => $item['sample_movie_pc_flag'], 'sp' => $item['sample_movie_sp_flag'], 'price_min' => $item['price_min_text'], 'list_price' => $item['list_price_text'],
            'release_date' => $item['release_date'], 'raw_json' => json_encode($item['raw'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $idStmt = $this->pdo->prepare('SELECT id FROM items WHERE content_id = ?');
        $idStmt->execute([$item['content_id']]);
        return (int) $idStmt->fetchColumn();
    }

    private function rebuildItemRelations(int $itemId, array $item): void
    {
        $tables = ['item_actresses', 'item_genres', 'item_campaigns', 'item_labels', 'item_directors', 'item_makers', 'item_series', 'item_authors', 'item_actors'];
        foreach ($tables as $table) {
            $this->pdo->prepare("DELETE FROM {$table} WHERE item_id = ?")->execute([$itemId]);
        }

        $this->insertRelation($itemId, 'item_actresses', 'actress_name', $item['actresses']);
        $this->insertRelation($itemId, 'item_genres', 'genre_name', $item['genres']);
        $this->insertRelation($itemId, 'item_campaigns', 'campaign_name', $item['campaigns']);
        $this->insertRelation($itemId, 'item_labels', 'label_name', $item['labels']);
        $this->insertRelation($itemId, 'item_directors', 'director_name', $item['directors']);
        $this->insertRelation($itemId, 'item_makers', 'maker_name', $item['makers']);
        $this->insertRelation($itemId, 'item_series', 'series_name', $item['series']);
        $this->insertRelation($itemId, 'item_authors', 'author_name', $item['authors']);
        $this->insertRelation($itemId, 'item_actors', 'actor_name', $item['actors'] ?? []);
    }

    private function insertRelation(int $itemId, string $table, string $nameCol, array $rows): void
    {
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $dmmId = (string) ($row['id'] ?? '0');
            $name = (string) ($row['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $this->pdo->prepare("INSERT IGNORE INTO {$table}(item_id,dmm_id,{$nameCol}) VALUES(?,?,?)")
                ->execute([$itemId, $dmmId, $name]);
        }
    }

    public function logSync(string $type, int $isSuccess, int $count, string $message): void
    {
        $this->pdo->prepare('INSERT INTO sync_logs(sync_type,is_success,synced_count,message,created_at) VALUES(?,?,?,?,NOW())')
            ->execute([$type, $isSuccess, $count, mb_substr($message, 0, 1000)]);
    }

    private function ensureSchema(): void
    {
        $columns = [];
        $stmt = $this->pdo->query('SHOW COLUMNS FROM settings');
        foreach (($stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : []) as $col) {
            $columns[(string)($col['Field'] ?? '')] = true;
        }
        if (!isset($columns['item_sync_batch'])) {
            $this->pdo->exec('ALTER TABLE settings ADD COLUMN item_sync_batch INT NOT NULL DEFAULT 100');
        }
        if (!isset($columns['master_floor_id'])) {
            $this->pdo->exec('ALTER TABLE settings ADD COLUMN master_floor_id INT NULL');
        }

        $this->pdo->exec('CREATE TABLE IF NOT EXISTS item_makers (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,item_id INT UNSIGNED NOT NULL,dmm_id VARCHAR(64) NULL,maker_name VARCHAR(255) NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uk_item_maker (item_id,dmm_id),CONSTRAINT fk_item_maker_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS item_series (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,item_id INT UNSIGNED NOT NULL,dmm_id VARCHAR(64) NULL,series_name VARCHAR(255) NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uk_item_series (item_id,dmm_id),CONSTRAINT fk_item_series_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS item_authors (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,item_id INT UNSIGNED NOT NULL,dmm_id VARCHAR(64) NULL,author_name VARCHAR(255) NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uk_item_author (item_id,dmm_id),CONSTRAINT fk_item_author_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS item_actors (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,item_id INT UNSIGNED NOT NULL,dmm_id VARCHAR(64) NULL,actor_name VARCHAR(255) NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uk_item_actor (item_id,dmm_id),CONSTRAINT fk_item_actor_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS sync_job_state (job_key VARCHAR(64) PRIMARY KEY,next_offset INT NOT NULL DEFAULT 1,next_initial VARCHAR(10) NULL,last_run_at DATETIME NULL,last_success TINYINT(1) NOT NULL DEFAULT 0,last_message TEXT NULL,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }
}
