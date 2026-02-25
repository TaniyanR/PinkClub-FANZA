<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/helpers.php';

class DmmApiClient
{
    private string $apiId;
    private string $affiliateId;

    public function __construct(?string $apiId = null, ?string $affiliateId = null)
    {
        $this->apiId = $apiId ?? (string)get_setting('dmm_api_id', '');
        $this->affiliateId = $affiliateId ?? (string)get_setting('dmm_affiliate_id', '');
    }

    public function floorList(array $params = []): array { return $this->request(DMM_FLOOR_LIST_ENDPOINT, $params); }
    public function itemList(array $params = []): array { return $this->request(DMM_ITEM_LIST_ENDPOINT, $params); }
    public function actressSearch(array $params = []): array { return $this->request(DMM_ACTRESS_SEARCH_ENDPOINT, $params); }
    public function genreSearch(array $params = []): array { return $this->request(DMM_GENRE_SEARCH_ENDPOINT, $params); }
    public function makerSearch(array $params = []): array { return $this->request(DMM_MAKER_SEARCH_ENDPOINT, $params); }
    public function seriesSearch(array $params = []): array { return $this->request(DMM_SERIES_SEARCH_ENDPOINT, $params); }
    public function authorSearch(array $params = []): array { return $this->request(DMM_AUTHOR_SEARCH_ENDPOINT, $params); }

    public function request(string $endpoint, array $params): array
    {
        if ($this->apiId === '' || $this->affiliateId === '') {
            throw new RuntimeException('API ID / Affiliate ID が未設定です。');
        }

        $params = array_merge($params, [
            'api_id' => $this->apiId,
            'affiliate_id' => $this->affiliateId,
            'output' => 'json',
        ]);

        $url = DMM_API_BASE_URL . $endpoint . '?' . http_build_query($params);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FAILONERROR => false,
        ]);
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            throw new RuntimeException('cURLエラー: ' . $error);
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('HTTPエラー: ' . $status);
        }

        $json = json_decode((string)$body, true);
        if (!is_array($json)) {
            throw new RuntimeException('JSON decode 失敗');
        }

        $apiStatus = (int)($json['result']['status'] ?? 200);
        if ($apiStatus !== 200) {
            $message = $json['result']['message'] ?? 'APIエラー';
            throw new RuntimeException('DMM APIエラー: ' . $apiStatus . ' ' . $message);
        }

        return $json;
    }
}
