<?php

declare(strict_types=1);

require_once __DIR__ . '/dmm_api_client.php';
require_once __DIR__ . '/dmm_normalizer.php';

class DmmSyncService
{
    public function __construct(private readonly DmmApiClient $client, private readonly PDO $pdo)
    {
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
            $this->logSync('floor', 1, $count, 'Floor sync completed.');
            return $count;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            $this->logSync('floor', 0, 0, $e->getMessage());
            throw $e;
        }
    }

    public function syncMaster(string $kind, ?string $floorId = null): int
    {
        $count = 0;
        $params = ['hits' => 100, 'offset' => 1];
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
        $table = $kind === 'series' ? 'series_master' : $kind . 'es';
        if ($kind === 'genre') {
            $table = 'genres';
        } elseif ($kind === 'maker') {
            $table = 'makers';
        } elseif ($kind === 'author') {
            $table = 'authors';
        } elseif ($kind === 'actress') {
            $table = 'actresses';
        }

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
            $this->logSync('master:' . $kind, 1, $count, 'Master sync completed.');
            return $count;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            $this->logSync('master:' . $kind, 0, 0, $e->getMessage());
            throw $e;
        }
    }

    public function syncItems(string $serviceCode, string $floorCode, array $params = []): int
    {
        $response = $this->client->fetchItems($serviceCode, $floorCode, array_merge(['hits' => 100, 'offset' => 1], $params));
        $items = DmmNormalizer::normalizeItemsResponse($response);
        $count = 0;
        $this->pdo->beginTransaction();
        try {
            foreach ($items as $item) {
                $itemId = $this->upsertItem($item);
                $this->rebuildItemRelations($itemId, $item);
                $count++;
            }
            $this->pdo->commit();
            $this->logSync('item', 1, $count, 'Item sync completed.');
            return $count;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            $this->logSync('item', 0, 0, $e->getMessage());
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
        $tables = ['item_actresses', 'item_genres', 'item_campaigns', 'item_labels', 'item_directors'];
        foreach ($tables as $table) {
            $this->pdo->prepare("DELETE FROM {$table} WHERE item_id = ?")->execute([$itemId]);
        }

        $this->insertRelation($itemId, 'item_actresses', 'actress_name', $item['actresses']);
        $this->insertRelation($itemId, 'item_genres', 'genre_name', $item['genres']);
        $this->insertRelation($itemId, 'item_campaigns', 'campaign_name', $item['campaigns']);
        $this->insertRelation($itemId, 'item_labels', 'label_name', $item['labels']);
        $this->insertRelation($itemId, 'item_directors', 'director_name', $item['directors']);
    }

    private function insertRelation(int $itemId, string $table, string $nameCol, array $rows): void
    {
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $dmmId = (string) ($row['id'] ?? '');
            $name = (string) ($row['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $this->pdo->prepare("INSERT INTO {$table}(item_id,dmm_id,{$nameCol}) VALUES(?,?,?)")
                ->execute([$itemId, $dmmId, $name]);
        }
    }

    public function logSync(string $type, int $isSuccess, int $count, string $message): void
    {
        $this->pdo->prepare('INSERT INTO sync_logs(sync_type,is_success,synced_count,message,created_at) VALUES(?,?,?,?,NOW())')
            ->execute([$type, $isSuccess, $count, mb_substr($message, 0, 1000)]);
    }
}
