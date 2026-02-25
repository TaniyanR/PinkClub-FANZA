<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

function normalize_to_array($value): array
{
    if ($value === null || $value === '') {
        return [];
    }
    if (is_object($value)) {
        $value = (array)$value;
    }
    if (!is_array($value)) {
        return [$value];
    }
    if ($value === []) {
        return [];
    }
    return is_assoc_array($value) ? [$value] : $value;
}

function normalize_items_response(array $apiResponse): array
{
    $items = $apiResponse['result']['items'] ?? [];
    return normalize_to_array($items);
}

function normalize_iteminfo_list($iteminfo, string $key): array
{
    if (is_object($iteminfo)) {
        $iteminfo = (array)$iteminfo;
    }
    $target = is_array($iteminfo) ? ($iteminfo[$key] ?? null) : null;
    return normalize_to_array($target);
}

function normalize_campaigns($campaign): array
{
    return normalize_to_array($campaign);
}

function normalize_sample_images($sampleImageURL): array
{
    if (is_object($sampleImageURL)) {
        $sampleImageURL = (array)$sampleImageURL;
    }
    $s = normalize_to_array($sampleImageURL['sample_s']['image'] ?? null);
    $l = normalize_to_array($sampleImageURL['sample_l']['image'] ?? null);
    return ['sample_s' => $s, 'sample_l' => $l];
}

function normalize_deliveries($prices): array
{
    if (is_object($prices)) {
        $prices = (array)$prices;
    }
    return normalize_to_array($prices['deliveries']['delivery'] ?? null);
}
