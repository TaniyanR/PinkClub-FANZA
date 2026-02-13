<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$id = (int)($_GET['id'] ?? 0);
$to = '/';
if ($id > 0) {
    $st = db()->prepare('SELECT site_url FROM mutual_links WHERE id=:id AND status="approved" LIMIT 1');
    $st->execute([':id' => $id]);
    $url = $st->fetchColumn();
    if (is_string($url) && $url !== '') {
        $to = $url;
    }

    db()->prepare('INSERT INTO access_events(event_type,event_at,path,referrer,link_id,ip_hash) VALUES("out",NOW(),:path,:ref,:link_id,:ip_hash)')
        ->execute([
            ':path' => (string)($_SERVER['REQUEST_URI'] ?? ''),
            ':ref' => (string)($_SERVER['HTTP_REFERER'] ?? ''),
            ':link_id' => $id,
            ':ip_hash' => hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? '')),
        ]);
}
header('Location: ' . $to, true, 302);
exit;
