<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/db.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(404); exit('Not Found'); }
$st=db()->prepare('SELECT site_url FROM mutual_links WHERE id=:id AND status="approved" LIMIT 1');
$st->execute([':id'=>$id]);
$url=(string)($st->fetchColumn() ?: '');
if($url===''){ http_response_code(404); exit('Not Found'); }
$ipHash = hash('sha256', ((string)($_SERVER['REMOTE_ADDR'] ?? '')) . (string)config_get('security.ip_hash_salt', 'pinkclub-default-salt'));
db()->prepare('INSERT INTO access_events(event_type,event_at,path,referrer,link_id,ip_hash) VALUES("link_out",NOW(),:p,:r,:id,:ip)')->execute([':p'=>(string)($_SERVER['REQUEST_URI'] ?? '/out.php'),':r'=>(string)($_SERVER['HTTP_REFERER'] ?? ''),':id'=>$id,':ip'=>$ipHash]);
header('Location: '.$url, true, 302);
exit;
