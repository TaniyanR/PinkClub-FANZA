<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/favorites.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && csrf_verify((string)($_POST['_token'] ?? ''))) {
    $type = trim((string)($_POST['type'] ?? ''));
    $id = (int)($_POST['id'] ?? 0);
    $title = trim((string)($_POST['title'] ?? ''));
    $url = trim((string)($_POST['url'] ?? ''));
    $action = trim((string)($_POST['action'] ?? ''));

    if ($action === 'remove') {
        favorite_remove($type, $id);
    } elseif ($action === 'add') {
        favorite_add($type, $id, $title, $url);
    }
}

$return = (string)($_POST['return'] ?? public_url('mypage.php'));
if ($return === '' || str_starts_with($return, 'http://') || str_starts_with($return, 'https://')) {
    $return = public_url('mypage.php');
}
header('Location: ' . $return);
exit;
