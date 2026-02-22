<?php
declare(strict_types=1);

function fanza_floor_definitions(): array
{
    return [
        'digital:videoa' => ['label' => 'FANZA（アダルト）- 動画 - ビデオ', 'service' => 'digital', 'floor' => 'videoa'],
        'digital:videoc' => ['label' => 'FANZA（アダルト）- 動画 - ビデオ（カテゴリ）', 'service' => 'digital', 'floor' => 'videoc'],
        'digital:amateur' => ['label' => 'FANZA（アダルト）- 動画 - 素人', 'service' => 'digital', 'floor' => 'amateur'],
        'monthly:monthlies' => ['label' => 'FANZA（アダルト）- 月額動画 - 見放題ch', 'service' => 'monthly', 'floor' => 'monthlies'],
        'mono:dvd' => ['label' => 'FANZA（アダルト）- 通販 - DVD', 'service' => 'mono', 'floor' => 'dvd'],
        'doujin:doujin' => ['label' => 'FANZA（アダルト）- 同人 - 同人', 'service' => 'doujin', 'floor' => 'doujin'],
        'book:comic' => ['label' => 'FANZA（アダルト）- FANZAブックス - コミック', 'service' => 'book', 'floor' => 'comic'],
    ];
}

function fanza_default_floor_pair(): string
{
    return 'digital:videoa';
}

function fanza_resolve_floor_pair(?string $pairValue, ?string $serviceValue, ?string $floorValue): array
{
    $definitions = fanza_floor_definitions();
    $pair = trim((string)$pairValue);

    if ($pair !== '' && isset($definitions[$pair])) {
        return [
            'pair' => $pair,
            'service' => $definitions[$pair]['service'],
            'floor' => $definitions[$pair]['floor'],
            'valid' => true,
        ];
    }

    $service = trim((string)$serviceValue);
    $floor = trim((string)$floorValue);
    if ($service !== '' && $floor !== '') {
        $legacyPair = $service . ':' . $floor;
        if (isset($definitions[$legacyPair])) {
            return [
                'pair' => $legacyPair,
                'service' => $service,
                'floor' => $floor,
                'valid' => true,
            ];
        }
    }

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
