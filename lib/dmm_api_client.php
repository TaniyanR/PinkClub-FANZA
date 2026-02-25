<?php

declare(strict_types=1);

class DmmApiClient
{
    public function __construct(
        private readonly string $apiId,
        private readonly string $affiliateId,
        private readonly string $endpoint
    ) {
    }

    public function fetchFloorList(): array
    {
        return $this->request('FloorList');
    }

    public function searchActresses(array $params = []): array
    {
        return $this->request('ActressSearch', $params);
    }

    public function searchGenres(array $params = []): array
    {
        return $this->request('GenreSearch', $params);
    }

    public function searchMakers(array $params = []): array
    {
        return $this->request('MakerSearch', $params);
    }

    public function searchSeries(array $params = []): array
    {
        return $this->request('SeriesSearch', $params);
    }

    public function searchAuthors(array $params = []): array
    {
        return $this->request('AuthorSearch', $params);
    }

    public function fetchItems(string $service, string $floor, array $params = []): array
    {
        return $this->request('ItemList', array_merge($params, ['service' => $service, 'floor' => $floor]));
    }

    private function request(string $operation, array $params = []): array
    {
        $query = array_filter(array_merge($params, [
            'api_id' => $this->apiId,
            'affiliate_id' => $this->affiliateId,
            'output' => 'json',
        ]), static fn ($v) => $v !== null && $v !== '');

        $url = rtrim($this->endpoint, '/') . '/' . $operation . '?' . http_build_query($query);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FAILONERROR => false,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            throw new RuntimeException('cURL error: ' . curl_error($ch));
        }

        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode >= 400) {
            throw new RuntimeException('HTTP error: ' . $httpCode);
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('JSON decode failed.');
        }

        if (isset($decoded['result']['status']) && (int) $decoded['result']['status'] !== 200) {
            throw new RuntimeException('API error status: ' . $decoded['result']['status']);
        }

        return $decoded;
    }
}
