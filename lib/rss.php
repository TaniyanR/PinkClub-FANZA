<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/partners.php';

function rss_config(): array
{
    return config_get('rss', []);
}

function clamp(int $value, int $min, int $max): int
{
    return max($min, min($max, $value));
}

function compute_items_count(int $partnerId): int
{
    $cfg = rss_config();
    $base = (int)($cfg['base_items'] ?? 5);
    $max = (int)($cfg['max_items'] ?? 30);
    $windowHours = (int)($cfg['access_window_hours'] ?? 24);
    $perItem = (int)($cfg['access_per_item'] ?? 20);
    $perItem = max(1, $perItem);

    $access = count_in_access($partnerId, $windowHours);
    $extra = intdiv($access, $perItem);
    return clamp($base + $extra, $base, $max);
}

function fetch_rss_items(int $count, float $weightNew): array
{
    $count = clamp($count, 1, 50);
    $weightNew = max(0.0, min(1.0, $weightNew));
    $newCount = (int)round($count * $weightNew);
    $randomCount = $count - $newCount;

    $items = [];
    $seen = [];

    if ($newCount > 0) {
        $stmt = db()->prepare('SELECT * FROM items ORDER BY date_published DESC LIMIT :limit');
        $stmt->bindValue(':limit', $newCount, PDO::PARAM_INT);
        $stmt->execute();
        foreach ($stmt->fetchAll() ?: [] as $row) {
            if (!empty($row['content_id'])) {
                $seen[$row['content_id']] = true;
            }
            $items[] = $row;
        }
    }

    if ($randomCount > 0) {
        $stmt = db()->prepare('SELECT * FROM items ORDER BY RAND() LIMIT :limit');
        $stmt->bindValue(':limit', $randomCount, PDO::PARAM_INT);
        $stmt->execute();
        foreach ($stmt->fetchAll() ?: [] as $row) {
            if (!empty($row['content_id']) && isset($seen[$row['content_id']])) {
                continue;
            }
            if (!empty($row['content_id'])) {
                $seen[$row['content_id']] = true;
            }
            $items[] = $row;
        }
    }

    if (count($items) < $count) {
        $needed = $count - count($items);
        $stmt = db()->prepare('SELECT * FROM items ORDER BY date_published DESC LIMIT :limit');
        $stmt->bindValue(':limit', $needed, PDO::PARAM_INT);
        $stmt->execute();
        foreach ($stmt->fetchAll() ?: [] as $row) {
            if (!empty($row['content_id']) && isset($seen[$row['content_id']])) {
                continue;
            }
            $items[] = $row;
        }
    }

    return array_slice($items, 0, $count);
}

function fetch_partner_rss(string $rssUrl, int $timeoutSeconds = 5): ?string
{
    $context = stream_context_create([
        'http' => [
            'timeout' => $timeoutSeconds,
            'user_agent' => 'PinkClub-F RSS Detector',
        ],
    ]);
    $content = @file_get_contents($rssUrl, false, $context);
    return $content === false ? null : $content;
}

function detect_supports_images_from_rss(string $rssXml): ?bool
{
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($rssXml, 'SimpleXMLElement', LIBXML_NOCDATA);
    if ($xml === false) {
        return null;
    }

    $namespaces = $xml->getNamespaces(true);
    $hasMedia = false;

    if (isset($namespaces['media'])) {
        foreach ($xml->channel->item ?? [] as $item) {
            $media = $item->children($namespaces['media']);
            if (!empty($media->content) || !empty($media->thumbnail)) {
                $hasMedia = true;
                break;
            }
        }
    }

    if ($hasMedia) {
        return true;
    }

    foreach ($xml->channel->item ?? [] as $item) {
        if (!empty($item->enclosure)) {
            return true;
        }
        if (!empty($item->{'content:encoded'})) {
            $content = (string)$item->{'content:encoded'};
            if (stripos($content, '<img') !== false) {
                return true;
            }
        }
    }

    return false;
}

function refresh_partner_image_support(array $partner): void
{
    $rssUrl = trim((string)($partner['rss_url'] ?? ''));
    if ($rssUrl === '') {
        return;
    }

    $cfg = rss_config();
    $refreshHours = (int)($cfg['detection_refresh_hours'] ?? 12);
    $refreshHours = max(1, $refreshHours);

    $lastChecked = $partner['last_checked_at'] ?? null;
    if (is_string($lastChecked) && $lastChecked !== '') {
        $last = strtotime($lastChecked);
        if ($last !== false && (time() - $last) < ($refreshHours * 3600)) {
            return;
        }
    }

    $rssXml = fetch_partner_rss($rssUrl);
    if ($rssXml === null) {
        return;
    }

    $supports = detect_supports_images_from_rss($rssXml);
    if ($supports === null) {
        return;
    }

    update_partner_supports_images_detected((int)$partner['id'], $supports);
}

function build_rss(array $partner, array $items, bool $supportsImages): string
{
    $siteTitle = (string)config_get('site.title', 'PinkClub-F');
    $baseUrl = base_url();
    $channelLink = $baseUrl !== '' ? $baseUrl . '/' : '';
    $now = date(DATE_RSS);

    $rss = new DOMDocument('1.0', 'UTF-8');
    $rss->formatOutput = true;

    $rssEl = $rss->createElement('rss');
    $rssEl->setAttribute('version', '2.0');
    $rssEl->setAttribute('xmlns:media', 'http://search.yahoo.com/mrss/');
    $rss->appendChild($rssEl);

    $channel = $rss->createElement('channel');
    $rssEl->appendChild($channel);

    $channel->appendChild($rss->createElement('title', $siteTitle));
    $channel->appendChild($rss->createElement('link', $channelLink));
    $channel->appendChild($rss->createElement('description', $siteTitle . ' RSS'));
    $channel->appendChild($rss->createElement('lastBuildDate', $now));

    foreach ($items as $item) {
        $itemEl = $rss->createElement('item');
        $title = (string)($item['title'] ?? '');
        $link = (string)($item['affiliate_url'] ?? ($item['url'] ?? ''));
        $desc = (string)($item['category_name'] ?? '');

        $itemEl->appendChild($rss->createElement('title', $title));
        $itemEl->appendChild($rss->createElement('link', $link));
        if ($desc !== '') {
            $itemEl->appendChild($rss->createElement('description', $desc));
        }

        if ($supportsImages) {
            $image = (string)($item['image_large'] ?? ($item['image_small'] ?? ($item['image_list'] ?? '')));
            if ($image !== '') {
                $enclosure = $rss->createElement('enclosure');
                $enclosure->setAttribute('url', $image);
                $enclosure->setAttribute('type', 'image/jpeg');
                $itemEl->appendChild($enclosure);

                $mediaContent = $rss->createElement('media:content');
                $mediaContent->setAttribute('url', $image);
                $mediaContent->setAttribute('medium', 'image');
                $itemEl->appendChild($mediaContent);

                $thumbnail = $rss->createElement('media:thumbnail');
                $thumbnail->setAttribute('url', $image);
                $itemEl->appendChild($thumbnail);
            }
        }

        $channel->appendChild($itemEl);
    }

    return $rss->saveXML();
}
