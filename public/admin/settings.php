<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../../lib/site_settings.php';
require_once __DIR__ . '/../../lib/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$tab = (string)($_GET['tab'] ?? 'api');
if (!in_array($tab, ['site', 'api'], true)) {
    $tab = 'api';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $tab === 'site') {
    if (!admin_post_csrf_valid()) {
        header('Location: ' . admin_url('settings.php?tab=site&err=csrf_invalid'));
        exit;
    }

    $siteName = trim((string)($_POST['site_name'] ?? ''));
    $siteUrl = trim((string)($_POST['site_url'] ?? ''));
    $siteTagline = trim((string)($_POST['site_tagline'] ?? ''));
    $adminUsername = trim((string)($_POST['admin_username'] ?? ''));
    $adminEmail = trim((string)($_POST['admin_email'] ?? ''));

    if ($siteName === '' || $siteUrl === '' || $adminUsername === '' || $adminEmail === '') {
        header('Location: ' . admin_url('settings.php?tab=site&err=required'));
        exit;
    }

    if (filter_var($siteUrl, FILTER_VALIDATE_URL) === false) {
        header('Location: ' . admin_url('settings.php?tab=site&err=invalid_url'));
        exit;
    }

    if (filter_var($adminEmail, FILTER_VALIDATE_EMAIL) === false) {
        header('Location: ' . admin_url('settings.php?tab=site&err=invalid_email'));
        exit;
    }

    if (!preg_match('/^[A-Za-z0-9_.@-]{3,100}$/', $adminUsername)) {
        header('Location: ' . admin_url('settings.php?tab=site&err=invalid_username'));
        exit;
    }

    try {
        $pdo = db();
        $pdo->beginTransaction();

        site_title_setting_set($siteName);
        site_setting_set_many([
            'site.tagline' => $siteTagline,
            'site.base_url' => $siteUrl,
            'site.admin_email' => $adminEmail,
        ]);

        $currentAdmin = admin_current_user();
        if (is_array($currentAdmin) && isset($currentAdmin['id']) && (int)$currentAdmin['id'] > 0) {
            $stmt = $pdo->prepare('SELECT id FROM admin_users WHERE username = :username AND id <> :id LIMIT 1');
            $stmt->execute([
                ':username' => $adminUsername,
                ':id' => (int)$currentAdmin['id'],
            ]);
            if ($stmt->fetchColumn() !== false) {
                $pdo->rollBack();
                header('Location: ' . admin_url('settings.php?tab=site&err=username_taken'));
                exit;
            }

            $update = $pdo->prepare('UPDATE admin_users SET username = :username, email = :email, updated_at = NOW() WHERE id = :id');
            $update->execute([
                ':username' => $adminUsername,
                ':email' => $adminEmail,
                ':id' => (int)$currentAdmin['id'],
            ]);

            $_SESSION['admin_user']['username'] = $adminUsername;
            $_SESSION['admin_user']['email'] = $adminEmail;
        }

        $pdo->commit();
        header('Location: ' . admin_url('settings.php?tab=site&saved=1'));
        exit;
    } catch (Throwable) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        header('Location: ' . admin_url('settings.php?tab=site&err=save_failed'));
        exit;
    }
}

$apiConfig = config_get('dmm_api', []);

$currentApiId = (string)(is_array($apiConfig) ? ($apiConfig['api_id'] ?? '') : '');
$currentAffiliateId = (string)(is_array($apiConfig) ? ($apiConfig['affiliate_id'] ?? '') : '');
$hasApiId = trim($currentApiId) !== '';
$hasAffiliateId = trim($currentAffiliateId) !== '';

// 選択肢（UI用）
$siteOptions = ['FANZA', 'DMM'];
$serviceOptions = ['digital'];
$floorOptions = ['videoa'];
$serviceLabels = [
    'digital' => 'デジタル',
];
$floorLabels = [
    'videoa' => '動画（AV）',
    'videoc' => 'ビデオ（一般）',
    'amateur' => '素人',
];

// 現在値（表示用）
$currentSite = 'FANZA';
$currentService = 'digital';
$currentFloor = 'videoa';

if (is_array($apiConfig)) {
    $siteValue = (string)($apiConfig['site'] ?? '');
    if (in_array($siteValue, $siteOptions, true)) {
        $currentSite = $siteValue;
    }

    $serviceValue = (string)($apiConfig['service'] ?? '');
    if (in_array($serviceValue, $serviceOptions, true)) {
        $currentService = $serviceValue;
    }

    $floorValue = (string)($apiConfig['floor'] ?? '');
    if (in_array($floorValue, $floorOptions, true)) {
        $currentFloor = $floorValue;
    }
}

// タイムアウト（表示用）
$connectTimeout = 10;
$timeout = 20;

if (is_array($apiConfig)) {
    $connectTimeoutValue = filter_var($apiConfig['connect_timeout'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 30],
    ]);
    if ($connectTimeoutValue !== false) {
        $connectTimeout = $connectTimeoutValue;
    }

    $timeoutValue = filter_var($apiConfig['timeout'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 5, 'max_range' => 60],
    ]);
    if ($timeoutValue !== false) {
        $timeout = $timeoutValue;
    }
}

$localPath = __DIR__ . '/../../config.local.php';

$errorMessages = [
    'missing_required'  => 'API ID / アフィリエイトIDが未入力です。対象ファイル: ' . $localPath,
    'csrf_failed'       => '不正なリクエストです。対象ファイル: ' . $localPath,
    'not_writable_dir'  => '設定ディレクトリに書き込めません。対象ファイル: ' . $localPath . '（ディレクトリ権限を確認してください）',
    'not_writable_file' => '設定ファイルに書き込めません。対象ファイル: ' . $localPath . '（ファイル権限を確認してください）',
    'write_failed'      => 'config.local.php に書き込めません。対象ファイル: ' . $localPath . '（権限を確認してください）',
    'rename_failed'     => '設定ファイルの更新に失敗しました。対象ファイル: ' . $localPath . '（ディスク/権限を確認してください）',
];

$pageTitle = '管理設定';
ob_start();
?>
    <h1><?php echo $tab === 'site' ? 'サイト設定' : 'API設定'; ?></h1>

<?php if ($tab === 'site') : ?>
    <?php
    $siteName = site_title_setting('');
    $siteTagline = site_setting_get('site.tagline', '');
    $siteUrl = site_setting_get('site.base_url', detect_base_url());
    $currentAdmin = admin_current_user();
    $adminUsername = is_array($currentAdmin) ? (string)($currentAdmin['username'] ?? '') : '';
    if ($adminUsername === '') {
        $adminUsername = 'admin';
    }
    $adminEmail = site_setting_get('site.admin_email', '');
    if ($adminEmail === '' && is_array($currentAdmin) && is_string($currentAdmin['email'] ?? null)) {
        $adminEmail = (string)$currentAdmin['email'];
    }
    $errorCode = (string)($_GET['err'] ?? '');
    $siteMessages = [
        'csrf_invalid' => 'CSRFトークンが無効です。再度お試しください。',
        'required' => 'すべての項目を入力してください。',
        'invalid_url' => 'サイトURLの形式が正しくありません。',
        'invalid_username' => 'ユーザー名は3〜100文字の英数字・_.@-で入力してください。',
        'username_taken' => 'そのユーザー名は既に使われています。別のユーザー名を指定してください。',
        'invalid_email' => '管理者メールアドレスの形式が正しくありません。',
        'save_failed' => '設定の保存に失敗しました。時間をおいて再度お試しください。',
    ];
    ?>

    <?php if (($_GET['saved'] ?? '') === '1') : ?>
        <div class="admin-card admin-notice admin-notice--success"><p>サイト設定を保存しました。</p></div>
    <?php endif; ?>

    <?php if ($errorCode !== '' && isset($siteMessages[$errorCode])) : ?>
        <div class="admin-card admin-notice admin-notice--error"><p><?php echo e($siteMessages[$errorCode]); ?></p></div>
    <?php endif; ?>

    <?php if (($_GET['password_changed'] ?? '') === '1') : ?>
        <div class="admin-card admin-notice admin-notice--success"><p>パスワードを変更しました。</p></div>
    <?php endif; ?>

    <form method="post" class="admin-card" action="<?php echo e(admin_url('settings.php?tab=site')); ?>">
        <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">

        <label for="site_name">サイト名</label>
        <input id="site_name" type="text" name="site_name" value="<?php echo e($siteName); ?>" placeholder="サイト名を入力" required>

        <label for="site_tagline">キャッチフレーズ</label>
        <input id="site_tagline" type="text" name="site_tagline" value="<?php echo e($siteTagline); ?>" placeholder="キャッチフレーズを入力（任意）">

        <label for="site_url">サイトURL</label>
        <input id="site_url" type="url" name="site_url" value="<?php echo e($siteUrl); ?>" required>

        <label for="admin_username">管理者ユーザー名</label>
        <input id="admin_username" type="text" name="admin_username" value="<?php echo e($adminUsername); ?>" autocomplete="username" required>

        <label for="admin_email">管理者メールアドレス</label>
        <input id="admin_email" type="email" name="admin_email" value="<?php echo e($adminEmail); ?>" required>

        <button type="submit">保存</button>
    </form>

    <form class="admin-card" method="post" action="<?php echo e(admin_url('change_password.php')); ?>">
        <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">

        <h2>管理者パスワード変更</h2>

        <label>現在のパスワード</label>
        <input type="password" name="current_password" autocomplete="current-password" required>

        <label>新しいパスワード</label>
        <input type="password" name="password" autocomplete="new-password" minlength="8" required>

        <label>確認</label>
        <input type="password" name="password_confirm" autocomplete="new-password" minlength="8" required>

        <button type="submit">パスワードを変更</button>
    </form>
<?php else : ?>
    <p class="admin-form-note">APIが一時的に失敗した場合、最大60分以内のキャッシュを表示します（空になりにくい）</p>


    <?php if (($_GET['password_changed'] ?? '') === '1') : ?>
        <div class="admin-card">
            <p>パスワードを変更しました。</p>
        </div>
    <?php endif; ?>
    <?php if (($_GET['saved'] ?? '') === '1') : ?>
        <div class="admin-card">
            <p>保存しました。</p>
        </div>
    <?php endif; ?>

    <?php if (($_GET['error'] ?? '') !== '') : ?>
        <div class="admin-card">
            <?php
            $errorCode = (string)($_GET['error'] ?? '');
            $errorMessage = $errorMessages[$errorCode] ?? ('エラーが発生しました。対象ファイル: ' . $localPath . '（' . $errorCode . '）');
            ?>
            <p><?php echo e($errorMessage); ?></p>
        </div>
    <?php endif; ?>

    <form class="admin-card" method="post" action="<?php echo e(admin_url('save_settings.php')); ?>">
        <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">

        <label>API ID</label>
        <input type="password" name="api_id" value="" placeholder="<?php echo $hasApiId ? '設定済み（変更時のみ入力）' : 'API IDを入力'; ?>" autocomplete="new-password">

        <label>アフィリエイトID</label>
        <input type="password" name="affiliate_id" value="" placeholder="<?php echo $hasAffiliateId ? '設定済み（変更時のみ入力）' : 'アフィリエイトIDを入力'; ?>" autocomplete="new-password">

        <label>サイト</label>
        <select name="site">
            <?php foreach ($siteOptions as $option) : ?>
                <option value="<?php echo e($option); ?>"<?php echo $option === $currentSite ? ' selected' : ''; ?>>
                    <?php echo e($option); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>サービス</label>
        <select name="service">
            <?php foreach ($serviceOptions as $option) : ?>
                <option value="<?php echo e($option); ?>"<?php echo $option === $currentService ? ' selected' : ''; ?>>
                    <?php echo e($serviceLabels[$option] ?? $option); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>フロア</label>
        <select name="floor">
            <?php foreach ($floorOptions as $option) : ?>
                <option value="<?php echo e($option); ?>"<?php echo $option === $currentFloor ? ' selected' : ''; ?>>
                    <?php echo e($floorLabels[$option] ?? $option); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>接続タイムアウト</label>
        <input type="number" name="connect_timeout" min="1" max="30" step="1" value="<?php echo e((string)$connectTimeout); ?>">
        <p class="admin-form-note">接続開始から確立までの最大秒数</p>

        <label>全体タイムアウト</label>
        <input type="number" name="timeout" min="5" max="60" step="1" value="<?php echo e((string)$timeout); ?>">
        <p class="admin-form-note">接続後、レスポンス完了までの最大秒数</p>

        <p class="admin-form-note">接続テストは APIログ から確認できます。</p>

        <button type="submit">保存</button>
    </form>
<?php endif; ?>
<?php
$main = (string)ob_get_clean();
require_once __DIR__ . '/_page.php';
admin_render($pageTitle, static function () use ($main): void {
    echo $main;
});
