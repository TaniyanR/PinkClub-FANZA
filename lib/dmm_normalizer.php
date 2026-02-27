<?php

declare(strict_types=1);

class DmmNormalizer
{
    public static function toList(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        if (is_array($value)) {
            if (array_is_list($value)) {
                return $value;
            }
            return [$value];
        }

        return [];
    }

    public static function normalizeItemsResponse(array $response): array
    {
        $items = $response['result']['items'] ?? $response['result']['item'] ?? [];
        if (isset($items['item'])) {
            $items = $items['item'];
        }

        $rows = self::toList($items);
        $normalized = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $info = $row['iteminfo'] ?? [];
            $sampleMovie = $row['sampleMovieURL'] ?? [];
            $delivery = $row['prices']['deliveries']['delivery'] ?? [];
            $deliveryList = self::toList($delivery);
            $priceMin = $deliveryList[0]['price'] ?? null;
            $listPrice = $deliveryList[0]['list_price'] ?? null;

            $normalized[] = [
                'raw' => $row,
                'content_id' => $row['content_id'] ?? null,
                'product_id' => $row['product_id'] ?? null,
                'title' => $row['title'] ?? '',
                'service_code' => $row['service_code'] ?? '',
                'service_name' => $row['service_name'] ?? '',
                'floor_code' => $row['floor_code'] ?? '',
                'floor_name' => $row['floor_name'] ?? '',
                'category_name' => $row['category_name'] ?? null,
                'volume' => $row['volume'] ?? null,
                'review_count' => isset($row['review']['count']) ? (int) $row['review']['count'] : null,
                'review_average' => isset($row['review']['average']) ? (float) $row['review']['average'] : null,
                'url' => $row['URL'] ?? null,
                'affiliate_url' => $row['affiliateURL'] ?? null,
                'image_list' => $row['imageURL']['list'] ?? null,
                'image_small' => $row['imageURL']['small'] ?? null,
                'image_large' => $row['imageURL']['large'] ?? null,
                'sample_movie_url_476' => $sampleMovie['size_476_306'] ?? null,
                'sample_movie_url_560' => $sampleMovie['size_560_360'] ?? null,
                'sample_movie_url_644' => $sampleMovie['size_644_414'] ?? null,
                'sample_movie_url_720' => $sampleMovie['size_720_480'] ?? null,
                'sample_movie_pc_flag' => isset($sampleMovie['pc_flag']) ? (int) $sampleMovie['pc_flag'] : 0,
                'sample_movie_sp_flag' => isset($sampleMovie['sp_flag']) ? (int) $sampleMovie['sp_flag'] : 0,
                'price_min_text' => $priceMin,
                'list_price_text' => $listPrice,
                'release_date' => !empty($row['date']) ? $row['date'] : null,
                'actresses' => self::toList($info['actress'] ?? []),
                'genres' => self::toList($info['genre'] ?? []),
                'makers' => self::toList($info['maker'] ?? []),
                'series' => self::toList($info['series'] ?? []),
                'authors' => self::toList($info['author'] ?? []),
                'directors' => self::toList($info['director'] ?? []),
                'labels' => self::toList($info['label'] ?? []),
                'campaigns' => self::toList($info['campaign'] ?? []),
                'actors' => self::toList($info['actor'] ?? []),
            ];
        }

        return $normalized;
    }
}
