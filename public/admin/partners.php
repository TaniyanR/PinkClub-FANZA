<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/config.php';
require_once __DIR__ . '/../../lib/admin_auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/partners.php';
require_once __DIR__ . '/../../lib/rss.php';

admin_basic_auth_required();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$partners = fetch_partners();
$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editPartner = $editId > 0 ? fetch_partner_by_id($editId) : null;
$cfg = rss_config();
$windowHours = (int)($cfg['access_window_hours'] ?? 24);

include __DIR__ . '/../partials/header.php';
?>
<main>
    <h1>提携先管理</h1>

    <?php if (($_GET['saved'] ?? '') === '1') : ?>
        <div class="admin-card">
            <p>保存しました。</p>
        </div>
    <?php endif; ?>

    <?php if (($_GET['error'] ?? '') !== '') : ?>
        <div class="admin-card">
            <p>エラーが発生しました: <?php echo e((string)($_GET['error'] ?? '')); ?></p>
        </div>
    <?php endif; ?>

    <div class="admin-card">
        <h2>提携先一覧</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>名称</th>
                    <th>サイトURL</th>
                    <th>RSS URL</th>
                    <th>画像判定</th>
                    <th>INアクセス(<?php echo e((string)$windowHours); ?>h)</th>
                    <th>最終チェック</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($partners as $partner) : ?>
                    <?php
                    $count = count_in_access((int)$partner['id'], $windowHours);
                    $final = supports_images_final($partner) ? '画像あり' : '画像なし';
                    $detected = (int)($partner['supports_images_detected'] ?? 1) === 1 ? '画像あり' : '画像なし';
                    $override = $partner['supports_images_override'] === null
                        ? '自動'
                        : ((int)$partner['supports_images_override'] === 1 ? '画像あり' : '画像なし');
                    ?>
                    <tr>
                        <td><?php echo e((string)$partner['id']); ?></td>
                        <td><?php echo e((string)$partner['name']); ?></td>
                        <td><?php echo e((string)$partner['site_url']); ?></td>
                        <td><?php echo e((string)$partner['rss_url']); ?></td>
                        <td><?php echo e($final); ?><br><small>自動: <?php echo e($detected); ?> / 上書き: <?php echo e($override); ?></small></td>
                        <td><?php echo e((string)$count); ?></td>
                        <td><?php echo e((string)($partner['last_checked_at'] ?? '')); ?></td>
                        <td><a href="/admin/partners.php?id=<?php echo e((string)$partner['id']); ?>">編集</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <form class="admin-card" method="post" action="/admin/save_partner.php">
        <h2><?php echo $editPartner ? '提携先編集' : '提携先追加'; ?></h2>
        <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
        <input type="hidden" name="id" value="<?php echo e((string)($editPartner['id'] ?? '')); ?>">

        <label>名称</label>
        <input type="text" name="name" required value="<?php echo e((string)($editPartner['name'] ?? '')); ?>">

        <label>サイトURL</label>
        <input type="url" name="site_url" value="<?php echo e((string)($editPartner['site_url'] ?? '')); ?>">

        <label>RSS URL</label>
        <input type="url" name="rss_url" value="<?php echo e((string)($editPartner['rss_url'] ?? '')); ?>">

        <label>トークン</label>
        <input type="text" name="token" value="<?php echo e((string)($editPartner['token'] ?? '')); ?>" placeholder="空の場合は自動生成">

        <label>画像対応(上書き)</label>
        <select name="supports_images_override">
            <option value="auto" <?php echo ($editPartner['supports_images_override'] ?? null) === null ? 'selected' : ''; ?>>自動判定に従う</option>
            <option value="yes" <?php echo (int)($editPartner['supports_images_override'] ?? 0) === 1 ? 'selected' : ''; ?>>画像あり</option>
            <option value="no" <?php echo (int)($editPartner['supports_images_override'] ?? 0) === 0 && ($editPartner['supports_images_override'] ?? null) !== null ? 'selected' : ''; ?>>画像なし</option>
        </select>

        <button type="submit">保存</button>
    </form>
</main>
<?php include __DIR__ . '/../partials/sidebar.php'; ?>
<?php include __DIR__ . '/../partials/footer.php'; ?>
