<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/dmm_api_client.php';
require_once __DIR__ . '/../lib/dmm_sync_service.php';
require_admin();

try {
    verify_csrf();
    foreach (['dmm_api_id','dmm_affiliate_id','default_site','default_service','default_floor','sync_hits_default'] as $k) {
        if (isset($_POST[$k])) set_setting($k, trim((string)$_POST[$k]));
    }

    $client = new DmmApiClient();
    $sync = new DmmSyncService($client);

    if (isset($_POST['test_connection'])) {
        $res = $client->itemList(['site'=>get_setting('default_site','FANZA'),'service'=>get_setting('default_service','digital'),'floor'=>get_setting('default_floor','videoa'),'hits'=>1,'offset'=>1]);
        $_SESSION['sync_result'] = ['type'=>'test_connection','status'=>'ok','status_code'=>$res['result']['status'] ?? 200];
    } elseif (isset($_POST['sync_execute'])) {
        $_SESSION['sync_result'] = ['type'=>'item_sync'] + $sync->syncItems(['hits'=>(int)get_setting('sync_hits_default','20')]);
    } elseif (isset($_POST['sync_floor'])) {
        $_SESSION['sync_result'] = ['type'=>'floor_sync'] + $sync->syncFloorList();
    } else {
        $_SESSION['sync_result'] = ['type'=>'save','status'=>'saved'];
    }
} catch (Throwable $e) {
    $_SESSION['sync_error'] = $e->getMessage();
}
header('Location: /admin/settings.php?tab=api&synced=1');
