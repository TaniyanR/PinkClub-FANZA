<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/repository.php';

function feed_xml(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
}

function feed_item_url(array $item): string
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

function feed_date(?string $value): string
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

$items = [];
try {
    $items = fetch_items('date_published_desc', 20, 0);
} catch (Throwable $e) {
    $items = [];
}

$lastBuildDate = date(DATE_RSS);
foreach ($items as $item) {
    if (!is_array($item)) {
        continue;
    }
    $candidateDate = trim((string)($item['updated_at'] ?? ($item['release_date'] ?? '')));
    if ($candidateDate !== '') {
        $lastBuildDate = feed_date($candidateDate);
        break;
    }
}

header('Content-Type: application/rss+xml; charset=UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0">
  <channel>
    <title><?= feed_xml($siteTitle) ?></title>
    <link><?= feed_xml($siteUrl) ?></link>
    <description><?= feed_xml($description) ?></description>
    <language>ja</language>
    <lastBuildDate><?= feed_xml($lastBuildDate) ?></lastBuildDate>
<?php foreach ($items as $item): ?>
<?php
    if (!is_array($item)) {
        continue;
    }
    $title = trim((string)($item['title'] ?? ''));
    if ($title === '') {
        continue;
    }
    $link = feed_item_url($item);
    $pubDate = feed_date((string)($item['release_date'] ?? ($item['updated_at'] ?? '')));
    $guid = trim((string)($item['content_id'] ?? ''));
    if ($guid === '') {
        $guid = $link;
    }
    $descriptionText = trim((string)($item['category_name'] ?? ''));
?>
    <item>
      <title><?= feed_xml($title) ?></title>
      <link><?= feed_xml($link) ?></link>
      <guid isPermaLink="false"><?= feed_xml($guid) ?></guid>
      <pubDate><?= feed_xml($pubDate) ?></pubDate>
<?php if ($descriptionText !== ''): ?>
      <description><?= feed_xml($descriptionText) ?></description>
<?php endif; ?>
    </item>
<?php endforeach; ?>
  </channel>
</rss>
