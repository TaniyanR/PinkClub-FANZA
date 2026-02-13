<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/partials/_helpers.php';

$msg=''; $err='';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_verify((string)($_POST['_token'] ?? ''))) {
        $err='CSRFエラーです。';
    } else {
        $name=trim((string)($_POST['site_name'] ?? ''));
        $url=trim((string)($_POST['url'] ?? ''));
        $rss=trim((string)($_POST['rss_url'] ?? ''));
        if($name==='' || !filter_var($url, FILTER_VALIDATE_URL)) {
            $err='入力内容を確認してください。';
        } else {
            db()->prepare('INSERT INTO mutual_links(site_name,site_url,link_url,rss_url,status,display_position,rss_enabled,created_at,updated_at) VALUES(:n,:u,:lu,:rss,"pending","sidebar",0,NOW(),NOW())')
                ->execute([':n'=>$name, ':u'=>$url, ':lu'=>$url, ':rss'=>$rss!==''?$rss:null]);
            $msg='申請を受け付けました。管理者承認後に掲載されます。';
        }
    }
}

$pageTitle='相互リンク申請'; include __DIR__.'/partials/header.php'; include __DIR__.'/partials/nav_search.php'; echo '<div class="layout">'; include __DIR__.'/partials/sidebar.php'; ?>
<main class="main-content"><section class="block"><h1 class="section-title">相互リンク申請</h1><?php if($msg!==''): ?><p><?php echo e($msg); ?></p><?php endif; ?><?php if($err!==''): ?><p><?php echo e($err); ?></p><?php endif; ?><form method="post"><input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>"><label>サイト名</label><input name="site_name" required><label>URL</label><input name="url" type="url" required><label>RSS URL(任意)</label><input name="rss_url" type="url"><button>申請する</button></form></section></main></div><?php include __DIR__.'/partials/footer.php';
