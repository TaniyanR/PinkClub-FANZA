<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/dmm_api_client.php';
require_once __DIR__ . '/dmm_normalizer.php';

class DmmSyncService
{
    public function __construct(private readonly DmmApiClient $client = new DmmApiClient()) {}

    private function startLog(string $type, array $params): int
    {
        $stmt = db()->prepare('INSERT INTO sync_logs (sync_type,target_site,target_service,target_floor,request_params_json,status,started_at,created_at)
         VALUES (:t,:s,:sv,:f,:p,:st,NOW(),NOW())');
        $stmt->execute([
            ':t' => $type,
            ':s' => $params['site'] ?? null,
            ':sv' => $params['service'] ?? null,
            ':f' => $params['floor'] ?? null,
            ':p' => json_encode($params, JSON_UNESCAPED_UNICODE),
            ':st' => 'running',
        ]);
        return (int)db()->lastInsertId();
    }

    private function finishLog(int $id, string $status, int $fetched, int $saved, string $message = ''): void
    {
        db()->prepare('UPDATE sync_logs SET status=:st,fetched_count=:f,saved_count=:s,message=:m,finished_at=NOW() WHERE id=:id')
            ->execute([':st'=>$status,':f'=>$fetched,':s'=>$saved,':m'=>$message,':id'=>$id]);
    }

    public function resolveFloorPair(string $siteCode, string $serviceCode, string $floorCode): array
    {
        $sql = 'SELECT f.dmm_floor_id,s.site_code,sv.service_code,f.floor_code
                FROM dmm_floors f JOIN dmm_services sv ON sv.id=f.service_id JOIN dmm_sites s ON s.id=sv.site_id
                WHERE s.site_code=:site AND sv.service_code=:service AND f.floor_code=:floor LIMIT 1';
        $stmt = db()->prepare($sql);
        $stmt->execute([':site'=>$siteCode,':service'=>$serviceCode,':floor'=>$floorCode]);
        $row = $stmt->fetch();
        if (!$row) throw new RuntimeException('Floor未同期です。まずFloor同期を実行してください。');
        return $row;
    }

    public function syncFloorList(): array
    {
        $logId = $this->startLog('floor', []);
        try {
            $response = $this->client->floorList();
            $floors = normalize_to_array($response['result']['site'] ?? []);
            $counts = ['sites'=>0,'services'=>0,'floors'=>0];
            foreach ($floors as $site) {
                $siteCode = $site['name'] ?? $site['site'] ?? '';
                if ($siteCode === '') continue;
                db()->prepare('INSERT INTO dmm_sites(site_code,site_name,created_at,updated_at) VALUES(:c,:n,NOW(),NOW())
                ON DUPLICATE KEY UPDATE site_name=VALUES(site_name),updated_at=VALUES(updated_at)')
                    ->execute([':c'=>$siteCode, ':n'=>$siteCode]);
                $siteId = (int)db()->query("SELECT id FROM dmm_sites WHERE site_code=" . db()->quote($siteCode))->fetchColumn();
                $counts['sites']++;
                foreach (normalize_to_array($site['service'] ?? []) as $service) {
                    $sCode = $service['service'] ?? $service['name'] ?? '';
                    if ($sCode === '') continue;
                    db()->prepare('INSERT INTO dmm_services(site_id,service_code,service_name,created_at,updated_at) VALUES(:sid,:c,:n,NOW(),NOW())
                    ON DUPLICATE KEY UPDATE service_name=VALUES(service_name),updated_at=VALUES(updated_at)')
                        ->execute([':sid'=>$siteId,':c'=>$sCode,':n'=>($service['name'] ?? $sCode)]);
                    $serviceId = (int)db()->query("SELECT id FROM dmm_services WHERE site_id={$siteId} AND service_code=" . db()->quote($sCode))->fetchColumn();
                    $counts['services']++;
                    foreach (normalize_to_array($service['floor'] ?? []) as $floor) {
                        db()->prepare('INSERT INTO dmm_floors(dmm_floor_id,service_id,floor_code,floor_name,created_at,updated_at)
                        VALUES(:fid,:sid,:fc,:fn,NOW(),NOW())
                        ON DUPLICATE KEY UPDATE floor_code=VALUES(floor_code),floor_name=VALUES(floor_name),updated_at=VALUES(updated_at)')
                            ->execute([':fid'=>(int)($floor['id'] ?? 0),':sid'=>$serviceId,':fc'=>($floor['code'] ?? ''),':fn'=>($floor['name'] ?? '')]);
                        $counts['floors']++;
                    }
                }
            }
            $this->finishLog($logId, 'success', $counts['floors'], $counts['floors'], 'Floor同期完了');
            return $counts;
        } catch (Throwable $e) {
            $this->finishLog($logId, 'error', 0, 0, $e->getMessage());
            throw $e;
        }
    }

    public function syncMasterCommon(string $type, array $params): array
    {
        $site = $params['site'] ?? get_setting('default_site', 'FANZA');
        $service = $params['service'] ?? get_setting('default_service', 'digital');
        $floor = $params['floor'] ?? get_setting('default_floor', 'videoa');
        $floorPair = $this->resolveFloorPair($site, $service, $floor);
        $params['floor_id'] = (int)$floorPair['dmm_floor_id'];
        $params['hits'] = min((int)($params['hits'] ?? 100), $type === 'genre' ? 500 : 100);

        $endpointMap = ['actress'=>'actressSearch','genre'=>'genreSearch','maker'=>'makerSearch','series'=>'seriesSearch','author'=>'authorSearch'];
        $tableMap = ['actress'=>'actresses','genre'=>'genres','maker'=>'makers','series'=>'series_master','author'=>'authors'];

        $logId = $this->startLog($type, $params);
        $offset = 1; $fetched = 0; $saved = 0;
        try {
            while (true) {
                $query = ['floor_id'=>$params['floor_id'],'hits'=>$params['hits'],'offset'=>$offset];
                if (!empty($params['initial'])) $query['initial'] = $params['initial'];
                if ($type === 'actress' && !empty($params['keyword'])) $query['keyword'] = $params['keyword'];
                $response = $this->client->{$endpointMap[$type]}($query);
                $items = normalize_to_array($response['result'][$type] ?? $response['result'][$type . 'es'] ?? []);
                if ($items === []) break;
                foreach ($items as $row) {
                    $fetched++;
                    $saved += $this->upsertMaster($type, $row, $params, (int)$params['floor_id']);
                }
                if (count($items) < $params['hits']) break;
                $offset += $params['hits'];
            }
            $this->finishLog($logId, 'success', $fetched, $saved, 'master sync done');
            return compact('fetched', 'saved');
        } catch (Throwable $e) {
            $this->finishLog($logId, 'error', $fetched, $saved, $e->getMessage());
            throw $e;
        }
    }

    public function syncActresses(array $params): array { return $this->syncMasterCommon('actress', $params); }
    public function syncGenres(array $params): array { return $this->syncMasterCommon('genre', $params); }
    public function syncMakers(array $params): array { return $this->syncMasterCommon('maker', $params); }
    public function syncSeries(array $params): array { return $this->syncMasterCommon('series', $params); }
    public function syncAuthors(array $params): array { return $this->syncMasterCommon('author', $params); }

    private function upsertMaster(string $type, array $row, array $params, int $floorId): int
    {
        $site = $params['site'] ?? 'FANZA'; $service = $params['service'] ?? 'digital'; $floor = $params['floor'] ?? 'videoa';
        if ($type === 'actress') {
            $sql = 'INSERT INTO actresses(actress_id,name,ruby,bust,cup,waist,hip,height,birthday,blood_type,hobby,prefectures,image_small,image_large,list_url_digital,list_url_monthly,list_url_mono,created_at,updated_at)
            VALUES(:id,:name,:ruby,:bust,:cup,:waist,:hip,:height,:birthday,:blood,:hobby,:pref,:is,:il,:ld,:lm,:lmo,NOW(),NOW())
            ON DUPLICATE KEY UPDATE name=VALUES(name),ruby=VALUES(ruby),updated_at=VALUES(updated_at)';
            db()->prepare($sql)->execute([
                ':id'=>(int)($row['id'] ?? 0),':name'=>($row['name'] ?? ''),':ruby'=>($row['ruby'] ?? null),':bust'=>$row['bust'] ?? null,':cup'=>$row['cup'] ?? null,
                ':waist'=>$row['waist'] ?? null,':hip'=>$row['hip'] ?? null,':height'=>$row['height'] ?? null,':birthday'=>safe_date($row['birthday'] ?? null),
                ':blood'=>$row['blood_type'] ?? null,':hobby'=>$row['hobby'] ?? null,':pref'=>$row['prefectures'] ?? null,
                ':is'=>$row['imageURL']['small'] ?? null,':il'=>$row['imageURL']['large'] ?? null,
                ':ld'=>$row['listURL']['digital'] ?? null,':lm'=>$row['listURL']['monthly'] ?? null,':lmo'=>$row['listURL']['mono'] ?? null,
            ]);
            return 1;
        }

        $idKey = $type . '_id'; if ($type === 'genre') $idKey = 'genre_id';
        $sql = sprintf('INSERT INTO %s(%s,dmm_floor_id,site_code,service_code,floor_code,name,ruby,list_url%s,created_at,updated_at)
        VALUES(:id,:fid,:site,:service,:floor,:name,:ruby,:url%s,NOW(),NOW())
        ON DUPLICATE KEY UPDATE name=VALUES(name),ruby=VALUES(ruby),updated_at=VALUES(updated_at)',
            $type === 'series' ? 'series_master' : $type . 's', $idKey,
            $type === 'author' ? ',another_name' : '', $type === 'author' ? ',:another_name' : '');
        $bind = [':id'=>(int)($row['id'] ?? $row[$idKey] ?? 0),':fid'=>$floorId,':site'=>$site,':service'=>$service,':floor'=>$floor,':name'=>($row['name'] ?? ''),':ruby'=>($row['ruby'] ?? null),':url'=>$row['list_url'] ?? ($row['listURL'] ?? null)];
        if ($type === 'author') $bind[':another_name'] = is_array($row['another_name'] ?? null) ? implode(',', $row['another_name']) : ($row['another_name'] ?? null);
        db()->prepare($sql)->execute($bind);
        return 1;
    }

    public function syncItems(array $params): array
    {
        $params = array_merge([
            'site' => get_setting('default_site', 'FANZA'),
            'service' => get_setting('default_service', 'digital'),
            'floor' => get_setting('default_floor', 'videoa'),
            'hits' => (int)get_setting('sync_hits_default', '20'),
            'offset' => 1,
        ], $params);

        $logId = $this->startLog('item', $params);
        $fetched = 0; $saved = 0;
        try {
            $apiParams = [
                'site' => $params['site'],'service' => $params['service'],'floor' => $params['floor'],'hits' => min((int)$params['hits'],100),'offset' => (int)$params['offset'],
                'keyword' => $params['keyword'] ?? null,'sort' => $params['sort'] ?? null,'gte_date' => $params['gte_date'] ?? null,'lte_date' => $params['lte_date'] ?? null,
            ];
            foreach (['article','article_id'] as $key) if (isset($params[$key])) $apiParams[$key] = $params[$key];
            $apiParams = array_filter($apiParams, static fn($v) => $v !== null && $v !== '');

            $response = $this->client->itemList($apiParams);
            $items = normalize_items_response($response);
            foreach ($items as $item) {
                $fetched++;
                $saved += $this->saveItem($item, $params);
            }
            $this->finishLog($logId, 'success', $fetched, $saved, 'item sync done');
            return compact('fetched', 'saved');
        } catch (Throwable $e) {
            $this->finishLog($logId, 'error', $fetched, $saved, $e->getMessage());
            throw $e;
        }
    }

    private function saveItem(array $item, array $params): int
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $sample = normalize_sample_images($item['sampleImageURL'] ?? []);
            $deliveries = normalize_deliveries($item['prices'] ?? []);
            $campaigns = normalize_campaigns($item['campaign'] ?? null);
            $info = $item['iteminfo'] ?? [];

            $sql = 'INSERT INTO items(content_id,product_id,site_code,service_code,floor_code,service_name,floor_name,category_name,title,volume,number_text,review_count,review_average,url,affiliate_url,image_list,image_small,image_large,sample_image_s_json,sample_image_l_json,sample_movie_476,sample_movie_560,sample_movie_644,sample_movie_720,sample_movie_pc_flag,sample_movie_sp_flag,price_text,list_price_text,deliveries_json,item_date,stock_text,jancode,maker_product,isbn,raw_iteminfo_json,raw_campaign_json,raw_directory_json,last_synced_at,created_at,updated_at)
            VALUES(:content_id,:product_id,:site_code,:service_code,:floor_code,:service_name,:floor_name,:category_name,:title,:volume,:number_text,:review_count,:review_average,:url,:affiliate_url,:image_list,:image_small,:image_large,:sample_s,:sample_l,:m476,:m560,:m644,:m720,:pc,:sp,:price,:list_price,:deliveries,:item_date,:stock,:jancode,:maker_product,:isbn,:raw_iteminfo,:raw_campaign,:raw_directory,NOW(),NOW(),NOW())
            ON DUPLICATE KEY UPDATE title=VALUES(title),review_count=VALUES(review_count),review_average=VALUES(review_average),updated_at=VALUES(updated_at),last_synced_at=VALUES(last_synced_at),raw_iteminfo_json=VALUES(raw_iteminfo_json),raw_campaign_json=VALUES(raw_campaign_json),raw_directory_json=VALUES(raw_directory_json)';
            $pdo->prepare($sql)->execute([
                ':content_id'=>$item['content_id'] ?? '',':product_id'=>$item['product_id'] ?? null,':site_code'=>$params['site'],':service_code'=>$params['service'],':floor_code'=>$params['floor'],':service_name'=>$item['service_name'] ?? null,':floor_name'=>$item['floor_name'] ?? null,':category_name'=>$item['category_name'] ?? null,
                ':title'=>$item['title'] ?? '',':volume'=>$item['volume'] ?? null,':number_text'=>$item['number'] ?? null,':review_count'=>$item['review']['count'] ?? null,':review_average'=>$item['review']['average'] ?? null,':url'=>$item['URL'] ?? null,':affiliate_url'=>$item['affiliateURL'] ?? null,
                ':image_list'=>$item['imageURL']['list'] ?? null,':image_small'=>$item['imageURL']['small'] ?? null,':image_large'=>$item['imageURL']['large'] ?? null,
                ':sample_s'=>json_encode($sample['sample_s'], JSON_UNESCAPED_UNICODE),':sample_l'=>json_encode($sample['sample_l'], JSON_UNESCAPED_UNICODE),
                ':m476'=>$item['sampleMovieURL']['size_476_306'] ?? null,':m560'=>$item['sampleMovieURL']['size_560_360'] ?? null,':m644'=>$item['sampleMovieURL']['size_644_414'] ?? null,':m720'=>$item['sampleMovieURL']['size_720_480'] ?? null,
                ':pc'=>isset($item['sampleMovieURL']) ? 1 : 0,':sp'=>isset($item['sampleMovieURL']['sp']) ? 1 : 0,
                ':price'=>$item['prices']['price'] ?? null,':list_price'=>$item['prices']['list_price'] ?? null,':deliveries'=>json_encode($deliveries, JSON_UNESCAPED_UNICODE),
                ':item_date'=>safe_datetime($item['date'] ?? null),':stock'=>$item['stock'] ?? null,':jancode'=>$item['jancode'] ?? null,':maker_product'=>$item['maker_product'] ?? null,':isbn'=>$item['isbn'] ?? null,
                ':raw_iteminfo'=>json_encode($info, JSON_UNESCAPED_UNICODE),':raw_campaign'=>json_encode($campaigns, JSON_UNESCAPED_UNICODE),':raw_directory'=>json_encode($item['iteminfo']['directory'] ?? null, JSON_UNESCAPED_UNICODE),
            ]);

            $itemId = (int)$pdo->query('SELECT id FROM items WHERE content_id=' . $pdo->quote((string)$item['content_id']))->fetchColumn();
            $this->syncRelations($itemId, 'genre', normalize_iteminfo_list($info, 'genre'), $params);
            $this->syncRelations($itemId, 'maker', normalize_iteminfo_list($info, 'maker'), $params);
            $this->syncRelations($itemId, 'series', normalize_iteminfo_list($info, 'series'), $params);
            $this->syncRelations($itemId, 'actress', normalize_iteminfo_list($info, 'actress'), $params);
            $this->syncRelations($itemId, 'author', normalize_iteminfo_list($info, 'author'), $params);

            foreach (['item_campaigns','item_directors','item_labels'] as $table) {
                $pdo->prepare("DELETE FROM {$table} WHERE item_id=:id")->execute([':id'=>$itemId]);
            }
            foreach ($campaigns as $i => $c) {
                $pdo->prepare('INSERT INTO item_campaigns(item_id,title,date_begin,date_end,sort_order) VALUES(:item,:title,:b,:e,:sort)')
                    ->execute([':item'=>$itemId,':title'=>$c['title'] ?? '-',':b'=>safe_datetime($c['date_begin'] ?? null),':e'=>safe_datetime($c['date_end'] ?? null),':sort'=>$i]);
            }
            foreach (normalize_iteminfo_list($info, 'director') as $i => $d) {
                $pdo->prepare('INSERT INTO item_directors(item_id,director_dmm_id,name,ruby,sort_order) VALUES(:item,:id,:name,:ruby,:sort)')
                    ->execute([':item'=>$itemId,':id'=>$d['id'] ?? null,':name'=>$d['name'] ?? '',':ruby'=>$d['ruby'] ?? null,':sort'=>$i]);
            }
            foreach (normalize_iteminfo_list($info, 'label') as $i => $d) {
                $pdo->prepare('INSERT INTO item_labels(item_id,label_dmm_id,name,ruby,sort_order) VALUES(:item,:id,:name,:ruby,:sort)')
                    ->execute([':item'=>$itemId,':id'=>$d['id'] ?? null,':name'=>$d['name'] ?? '',':ruby'=>$d['ruby'] ?? null,':sort'=>$i]);
            }

            $pdo->commit();
            return 1;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    private function syncRelations(int $itemId, string $type, array $rows, array $params): void
    {
        $map = [
            'actress'=>['table'=>'actresses','key'=>'actress_id','join'=>'item_actresses','col'=>'actress_id'],
            'genre'=>['table'=>'genres','key'=>'genre_id','join'=>'item_genres','col'=>'genre_id'],
            'maker'=>['table'=>'makers','key'=>'maker_id','join'=>'item_makers','col'=>'maker_id'],
            'series'=>['table'=>'series_master','key'=>'series_id','join'=>'item_series','col'=>'series_id'],
            'author'=>['table'=>'authors','key'=>'author_id','join'=>'item_authors','col'=>'author_id'],
        ];
        $m = $map[$type];
        $floorId = (int)$this->resolveFloorPair($params['site'], $params['service'], $params['floor'])['dmm_floor_id'];
        db()->prepare("DELETE FROM {$m['join']} WHERE item_id=:item")->execute([':item'=>$itemId]);

        foreach ($rows as $i => $row) {
            $extId = (int)($row['id'] ?? 0); if ($extId <= 0) continue;
            if ($type === 'actress') {
                db()->prepare('INSERT INTO actresses(actress_id,name,ruby,created_at,updated_at) VALUES(:id,:name,:ruby,NOW(),NOW()) ON DUPLICATE KEY UPDATE name=VALUES(name),ruby=VALUES(ruby),updated_at=VALUES(updated_at)')
                    ->execute([':id'=>$extId,':name'=>$row['name'] ?? '',':ruby'=>$row['ruby'] ?? null]);
            } else {
                db()->prepare("INSERT INTO {$m['table']}({$m['key']},dmm_floor_id,site_code,service_code,floor_code,name,ruby,created_at,updated_at)
                VALUES(:id,:fid,:site,:service,:floor,:name,:ruby,NOW(),NOW()) ON DUPLICATE KEY UPDATE name=VALUES(name),ruby=VALUES(ruby),updated_at=VALUES(updated_at)")
                    ->execute([':id'=>$extId,':fid'=>$floorId,':site'=>$params['site'],':service'=>$params['service'],':floor'=>$params['floor'],':name'=>$row['name'] ?? '',':ruby'=>$row['ruby'] ?? null]);
            }
            $localId = (int)db()->query("SELECT id FROM {$m['table']} WHERE {$m['key']}={$extId} LIMIT 1")->fetchColumn();
            db()->prepare("INSERT INTO {$m['join']}(item_id,{$m['col']},sort_order) VALUES(:item,:mid,:sort)")
                ->execute([':item'=>$itemId,':mid'=>$localId,':sort'=>$i]);
        }
    }
}
