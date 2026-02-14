<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/db.php';

$id = (int)($_GET['id'] ?? 0);
$to = '/';

if ($id > 0) {
    try {
        $st = db()->prepare('SELECT link_url, site_url FROM mutual_links WHERE id = :id AND status = "approved" LIMIT 1');
        $st->execute([':id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        if (is_array($row)) {
            $url = (string)($row['link_url'] ?? $row['site_url'] ?? '');
            if ($url !== '') {
                $to = $url;
            }
        }

        $eventStmt = db()->prepare('INSERT INTO access_events(event_type,event_at,path,referrer,link_id,ip_hash) VALUES("out",NOW(),:path,:ref,:link_id,:ip_hash)');
        $eventStmt->execute([
            ':path' => (string)($_SERVER['REQUEST_URI'] ?? ''),
            ':ref' => (string)($_SERVER['HTTP_REFERER'] ?? ''),
            ':link_id' => $id,
            ':ip_hash' => hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? '')),
        ]);
    } catch (Throwable $e) {
        log_message('out.php error: ' . $e->getMessage());
    }
}

header('Location: ' . $to, true, 302);
exit;
