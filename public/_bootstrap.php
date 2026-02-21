<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/bootstrap.php';

function app_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

app_bootstrap_configure_php_errors();

function render_fatal_error_page(string $message, ?Throwable $exception = null): never
{
    $isDevelopment = app_is_development();
    if (headers_sent() === false) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }

    $detail = $isDevelopment && $exception !== null
        ? sprintf(
            "%s\n\n%s:%d\n\n%s",
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        )
        : '';

    $escape = static function (string $value): string {
        if (function_exists('e')) {
            return e($value);
        }

        return app_h($value);
    };

    try {
        ?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>エラーが発生しました</title>
</head>
<body>
<div class="wrap">
    <section class="card">
        <h1>エラーが発生しました</h1>
        <p><?php echo $escape($message); ?></p>
        <?php if ($detail !== '') : ?>
            <pre><?php echo $escape($detail); ?></pre>
        <?php endif; ?>
    </section>
</div>
</body>
</html>
<?php
    } catch (Throwable) {
        if (headers_sent() === false) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
        }

        echo '<!doctype html><html lang="ja"><head><meta charset="UTF-8"><title>エラーが発生しました</title></head><body>';
        echo '<h1>エラーが発生しました</h1>';
        echo '<p>' . app_h($message) . '</p>';
        if ($isDevelopment && $detail !== '') {
            echo '<pre>' . app_h($detail) . '</pre>';
        }
        echo '</body></html>';
    }

    exit;
}

app_register_error_handlers('render_fatal_error_page', 'front');

try {
    require_once __DIR__ . '/../lib/db.php';
    require_once __DIR__ . '/../lib/scheduler.php';
    require_once __DIR__ . '/partials/_helpers.php';
} catch (Throwable $e) {
    app_log_error('Require failed in front bootstrap', $e);

    if (!function_exists('db')) {
        function db(): PDO
        {
            throw new RuntimeException('DBは現在利用できません。');
        }
    }

    if (!function_exists('maybe_run_scheduled_jobs')) {
        function maybe_run_scheduled_jobs(): void
        {
        }
    }
}

function render_setup_needed(?Throwable $exception = null): never
{
    $isDevelopment = app_is_development();
    http_response_code(500);

    ?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>セットアップが必要です</title>
</head>
<body>
<div class="wrap">
    <section class="card">
        <h1>サイトのセットアップが必要です</h1>
        <p>データベース接続または初期化に失敗したため、フロント画面を表示できませんでした。</p>
        <ul>
            <li><code>config.local.php</code> が無い場合はデフォルト設定（localhost / root / 空パスワード / pinkclub_fanza）で自動初期化します。</li>
            <li><code>config.local.php</code> を使う場合は任意で新規作成して設定を上書きしてください。</li>
            <li>管理ログインは <a href="<?php echo e(base_url() . '/admin/login.php'); ?>">/public/admin/login.php</a> です。</li>
        </ul>
        <?php if ($exception !== null) : ?>
            <h2>DB初期化エラー詳細</h2>
            <pre><?php echo e($exception->getMessage()); ?></pre>
        <?php elseif ($isDevelopment) : ?>
            <p>詳細情報が取得できませんでした。</p>
        <?php endif; ?>
    </section>
</div>
</body>
</html>
<?php
    exit;
}

try {
    maybe_run_scheduled_jobs();
} catch (Throwable $e) {
    app_log_error('scheduler error', $e);
}
