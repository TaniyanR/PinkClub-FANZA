<?php
declare(strict_types=1);

function fanza_floor_definitions(): array
{
    return [
        // 動画
        'digital:videoa' => ['label' => 'FANZA（アダルト）- 動画 - ビデオ', 'service' => 'digital', 'floor' => 'videoa'],
        'digital:videoc' => ['label' => 'FANZA（アダルト）- 動画 - 成人映画', 'service' => 'digital', 'floor' => 'videoc'],
        'digital:amateur' => ['label' => 'FANZA（アダルト）- 動画 - 素人', 'service' => 'digital', 'floor' => 'amateur'],
        'digital:nikkatsu' => ['label' => 'FANZA（アダルト）- 動画 - 日活', 'service' => 'digital', 'floor' => 'nikkatsu'],
        'digital:anime' => ['label' => 'FANZA（アダルト）- 動画 - アニメ', 'service' => 'digital', 'floor' => 'anime'],

        // 月額動画
        'monthly:monthlies' => ['label' => 'FANZA（アダルト）- 月額動画 - 見放題ch', 'service' => 'monthly', 'floor' => 'monthlies'],

        // 通販
        'mono:dvd' => ['label' => 'FANZA（アダルト）- 通販 - DVD', 'service' => 'mono', 'floor' => 'dvd'],
        'mono:book' => ['label' => 'FANZA（アダルト）- 通販 - 大人向け書籍', 'service' => 'mono', 'floor' => 'book'],
        'mono:anime' => ['label' => 'FANZA（アダルト）- 通販 - アニメ', 'service' => 'mono', 'floor' => 'anime'],
        'mono:pcgame' => ['label' => 'FANZA（アダルト）- 通販 - PCゲーム', 'service' => 'mono', 'floor' => 'pcgame'],
        'mono:hobby' => ['label' => 'FANZA（アダルト）- 通販 - ホビー', 'service' => 'mono', 'floor' => 'hobby'],

        // 同人
        'doujin:doujin' => ['label' => 'FANZA（アダルト）- 同人 - 同人', 'service' => 'doujin', 'floor' => 'doujin'],

        // FANZAブックス
        'book:comic' => ['label' => 'FANZA（アダルト）- FANZAブックス - コミック', 'service' => 'book', 'floor' => 'comic'],
        'book:novel' => ['label' => 'FANZA（アダルト）- FANZAブックス - 小説', 'service' => 'book', 'floor' => 'novel'],
        'book:photo' => ['label' => 'FANZA（アダルト）- FANZAブックス - 写真集', 'service' => 'book', 'floor' => 'photo'],

        // PCゲーム
        'pcgame:pcgame' => ['label' => 'FANZA（アダルト）- PCゲーム - アダルトPCゲーム', 'service' => 'pcgame', 'floor' => 'pcgame'],
    ];
}

function fanza_default_floor_pair(): string
{
    return 'digital:videoa';
}

function fanza_find_floor_pair_by_service_floor(string $service, string $floor): ?string
{
    $service = trim($service);
    $floor = trim($floor);
    if ($service === '' || $floor === '') {
        return null;
    }

    $pair = $service . ':' . $floor;
    $definitions = fanza_floor_definitions();

    return isset($definitions[$pair]) ? $pair : null;
}

function fanza_parse_floor_pair(string $pair): ?array
{
    $pair = trim($pair);
    if ($pair === '') {
        return null;
    }

    $definitions = fanza_floor_definitions();
    if (!isset($definitions[$pair])) {
        return null;
    }

    return [
        'pair' => $pair,
        'service' => $definitions[$pair]['service'],
        'floor' => $definitions[$pair]['floor'],
    ];
}

function fanza_resolve_floor_pair(?string $pairValue, ?string $serviceValue, ?string $floorValue): array
{
    $parsedPair = fanza_parse_floor_pair((string)$pairValue);
    if (is_array($parsedPair)) {
        $parsedPair['valid'] = true;
        return $parsedPair;
    }

    $legacyPair = fanza_find_floor_pair_by_service_floor((string)$serviceValue, (string)$floorValue);
    if ($legacyPair !== null) {
        $parsedLegacy = fanza_parse_floor_pair($legacyPair);
        if (is_array($parsedLegacy)) {
            $parsedLegacy['valid'] = true;
            return $parsedLegacy;
        }
    }

    $definitions = fanza_floor_definitions();
    $defaultPair = fanza_default_floor_pair();
    return [
        'pair' => $defaultPair,
        'service' => $definitions[$defaultPair]['service'],
        'floor' => $definitions[$defaultPair]['floor'],
        'valid' => false,
    ];
}

function fanza_normalize_api_config(array $apiConfig): array
{
    $resolved = fanza_resolve_floor_pair(null, (string)($apiConfig['service'] ?? ''), (string)($apiConfig['floor'] ?? ''));

    $apiConfig['site'] = 'FANZA';
    $apiConfig['service'] = $resolved['service'];
    $apiConfig['floor'] = $resolved['floor'];
    $apiConfig['floor_pair'] = $resolved['pair'];

    return $apiConfig;
}
