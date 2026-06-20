<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/repository.php';

function pcf_site_feed_xml(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
}

function pcf_site_feed_item_url(array $item): string
{
    $contentId = trim((string)($item['content_id'] ?? ''));
    if ($contentId !== '') {
        return public_url('item.php?cid=' . rawurlencode($contentId));
    }

    $id = (int)($item['id'] ?? 0);
    if ($id > 0) {
        return public_url('item.php?id=' . $id);
    }

    return public_url('');
}

function pcf_site_feed_items(): array
{
    try {
        $items = fetch_items('date_published_desc', 20, 0);
        if ($items !== []) {
            return $items;
        }

        $stmt = db()->query('SELECT * FROM items ORDER BY release_date DESC, id DESC LIMIT 20');
        return $stmt->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function pcf_site_feed_image_url(array $item): string
{
    foreach (['image_large', 'image_small'] as $key) {
        $url = trim((string)($item[$key] ?? ''));
        if ($url !== '') {
            return $url;
        }
    }

    return '';
}

function pcf_site_feed_description(array $item, string $itemLink): string
{
    $parts = [];
    $imageUrl = pcf_site_feed_image_url($item);
    $itemTitle = trim((string)($item['title'] ?? ''));
    if ($imageUrl !== '') {
        $parts[] = '<p><a href="' . htmlspecialchars($itemLink, ENT_QUOTES, 'UTF-8') . '"><img src="' . htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($itemTitle, ENT_QUOTES, 'UTF-8') . '"></a></p>';
    }

    if ($itemTitle !== '') {
        $parts[] = '<p>' . htmlspecialchars($itemTitle, ENT_QUOTES, 'UTF-8') . '</p>';
    }

    $categoryName = trim((string)($item['category_name'] ?? ''));
    if ($categoryName !== '') {
        $parts[] = '<p>' . htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8') . '</p>';
    }

    $parts[] = '<p><a href="' . htmlspecialchars($itemLink, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($itemLink, ENT_QUOTES, 'UTF-8') . '</a></p>';

    return implode("\n", $parts);
}

function pcf_site_feed_cdata(string $value): string
{
    return '<![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', $value) . ']]>';
}

function pcf_site_feed_date(?string $value): string
{
    $timestamp = $value !== null && trim($value) !== '' ? strtotime($value) : false;
    if ($timestamp === false) {
        $timestamp = time();
    }

    return date(DATE_RSS, $timestamp);
}

$siteTitle = trim(site_setting_get('site.title', site_setting_get('site.name', APP_NAME)));
if ($siteTitle === '') {
    $siteTitle = APP_NAME;
}

$siteUrl = trim(site_setting_get('site.url', app_url()));
if ($siteUrl === '') {
    $siteUrl = app_url();
}

$description = trim(site_setting_get('site.tagline', $siteTitle));
if ($description === '') {
    $description = $siteTitle;
}

$items = pcf_site_feed_items();

$lastBuildDate = date(DATE_RSS);
foreach ($items as $item) {
    if (!is_array($item)) {
        continue;
    }

    $updatedAt = trim((string)($item['updated_at'] ?? ''));
    if ($updatedAt !== '') {
        $lastBuildDate = pcf_site_feed_date($updatedAt);
        break;
    }

    $releaseDate = trim((string)($item['release_date'] ?? ''));
    if ($releaseDate !== '') {
        $lastBuildDate = pcf_site_feed_date($releaseDate);
        break;
    }
}

header('Content-Type: application/rss+xml; charset=UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:media="http://search.yahoo.com/mrss/" xmlns:atom="http://www.w3.org/2005/Atom">
  <channel>
    <title><?= pcf_site_feed_xml($siteTitle) ?></title>
    <link><?= pcf_site_feed_xml($siteUrl) ?></link>
    <description><?= pcf_site_feed_xml($description) ?></description>
    <language>ja</language>
    <lastBuildDate><?= pcf_site_feed_xml($lastBuildDate) ?></lastBuildDate>
    <atom:link href="<?= pcf_site_feed_xml(public_url('feed.php')) ?>" rel="self" type="application/rss+xml" />
<?php foreach ($items as $item): ?>
<?php
    if (!is_array($item)) {
        continue;
    }

    $itemTitle = trim((string)($item['title'] ?? ''));
    if ($itemTitle === '') {
        continue;
    }

    $itemLink = pcf_site_feed_item_url($item);
    $itemGuid = trim((string)($item['content_id'] ?? ''));
    if ($itemGuid === '') {
        $itemGuid = $itemLink;
    }

    $itemDate = trim((string)($item['release_date'] ?? ''));
    if ($itemDate === '') {
        $itemDate = trim((string)($item['updated_at'] ?? ''));
    }

    $itemDescription = pcf_site_feed_description($item, $itemLink);
    $itemImageUrl = pcf_site_feed_image_url($item);
?>
    <item>
      <title><?= pcf_site_feed_xml($itemTitle) ?></title>
      <link><?= pcf_site_feed_xml($itemLink) ?></link>
      <guid isPermaLink="false"><?= pcf_site_feed_xml($itemGuid) ?></guid>
      <pubDate><?= pcf_site_feed_xml(pcf_site_feed_date($itemDate)) ?></pubDate>
      <description><?= pcf_site_feed_cdata($itemDescription) ?></description>
      <content:encoded><?= pcf_site_feed_cdata($itemDescription) ?></content:encoded>
<?php if ($itemImageUrl !== ''): ?>
      <enclosure url="<?= pcf_site_feed_xml($itemImageUrl) ?>" type="image/jpeg" />
      <media:thumbnail url="<?= pcf_site_feed_xml($itemImageUrl) ?>" />
      <media:content url="<?= pcf_site_feed_xml($itemImageUrl) ?>" medium="image" />
<?php endif; ?>
    </item>
<?php endforeach; ?>
  </channel>
</rss>
