<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}


$apiConfig = config_get('dmm_api', []);

// 選択肢（UI用）
$siteOptions = ['FANZA', 'DMM'];
$serviceOptions = ['digital'];
$floorOptions = ['videoa'];

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
    <h1>API設定</h1>
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
        <input type="text" name="api_id" value="<?php echo e((string)(is_array($apiConfig) ? ($apiConfig['api_id'] ?? '') : '')); ?>">

        <label>Affiliate ID</label>
        <input type="text" name="affiliate_id" value="<?php echo e((string)(is_array($apiConfig) ? ($apiConfig['affiliate_id'] ?? '') : '')); ?>">

        <label>Site</label>
        <select name="site">
            <?php foreach ($siteOptions as $option) : ?>
                <option value="<?php echo e($option); ?>"<?php echo $option === $currentSite ? ' selected' : ''; ?>>
                    <?php echo e($option); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Service</label>
        <select name="service">
            <?php foreach ($serviceOptions as $option) : ?>
                <option value="<?php echo e($option); ?>"<?php echo $option === $currentService ? ' selected' : ''; ?>>
                    <?php echo e($option); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Floor</label>
        <select name="floor">
            <?php foreach ($floorOptions as $option) : ?>
                <option value="<?php echo e($option); ?>"<?php echo $option === $currentFloor ? ' selected' : ''; ?>>
                    <?php echo e($option); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>接続タイムアウト秒</label>
        <input type="number" name="connect_timeout" min="1" max="30" step="1" value="<?php echo e((string)$connectTimeout); ?>">
        <p class="admin-form-note">接続開始から確立までの最大秒数</p>

        <label>全体タイムアウト秒</label>
        <input type="number" name="timeout" min="5" max="60" step="1" value="<?php echo e((string)$timeout); ?>">
        <p class="admin-form-note">接続後、レスポンス完了までの最大秒数</p>

        <button type="submit">保存</button>
    </form>
<?php
$content = (string)ob_get_clean();
include __DIR__ . '/../partials/admin_layout.php';
