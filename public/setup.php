<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/local_config_writer.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/partials/_helpers.php';

$lockPath = __DIR__ . '/../storage/setup.lock';
if (is_file($lockPath)) {
    http_response_code(403);
    echo 'セットアップは完了済みです。';
    exit;
}

function split_sql_statements_for_setup(string $sql): array
{
    $statements = [];
    $buf = '';
    $inString = false;
    $stringChar = '';
    $len = strlen($sql);
    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];
        if ($inString) {
            if ($ch === $stringChar && ($i === 0 || $sql[$i - 1] !== '\\')) {
                $inString = false;
            }
            $buf .= $ch;
            continue;
        }
        if ($ch === '\'' || $ch === '"') {
            $inString = true;
            $stringChar = $ch;
            $buf .= $ch;
            continue;
        }
        if ($ch === ';') {
            $stmt = trim($buf);
            if ($stmt !== '') {
                $statements[] = $stmt;
            }
            $buf = '';
            continue;
        }
        $buf .= $ch;
    }
    $stmt = trim($buf);
    if ($stmt !== '') {
        $statements[] = $stmt;
    }
    return $statements;
}

$defaults = [
    'db_host' => (string)(getenv('DB_HOST') ?: '127.0.0.1'),
    'db_port' => (string)(getenv('DB_PORT') ?: '3306'),
    'db_name' => (string)(getenv('DB_NAME') ?: ''),
    'db_user' => (string)(getenv('DB_USER') ?: ''),
    'db_pass' => (string)(getenv('DB_PASS') ?: ''),
];

$error = '';
$done = false;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_verify((string)($_POST['_token'] ?? ''))) {
        $error = 'CSRFトークンが不正です。';
    } else {
        $defaults['db_host'] = trim((string)($_POST['db_host'] ?? ''));
        $defaults['db_port'] = trim((string)($_POST['db_port'] ?? '3306'));
        $defaults['db_name'] = trim((string)($_POST['db_name'] ?? ''));
        $defaults['db_user'] = trim((string)($_POST['db_user'] ?? ''));
        $defaults['db_pass'] = (string)($_POST['db_pass'] ?? '');

        try {
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $defaults['db_host'], $defaults['db_port'], $defaults['db_name']);
            $pdo = new PDO($dsn, $defaults['db_user'], $defaults['db_pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            local_config_write([
                'db' => [
                    'host' => $defaults['db_host'],
                    'port' => (int)$defaults['db_port'],
                    'name' => $defaults['db_name'],
                    'user' => $defaults['db_user'],
                    'pass' => $defaults['db_pass'],
                    'charset' => 'utf8mb4',
                ],
            ]);

            $schemaPath = __DIR__ . '/../sql/schema.sql';
            $sql = is_file($schemaPath) ? (string)file_get_contents($schemaPath) : '';
            if ($sql === '') {
                throw new RuntimeException('schema.sql が読み込めません。');
            }

            $sql = (string)preg_replace('/\/\*.*?\*\//s', '', $sql);
            $lines = preg_split('/\R/', $sql) ?: [];
            $clean = [];
            foreach ($lines as $line) {
                $trim = ltrim($line);
                if ($trim === '' || str_starts_with($trim, '--') || str_starts_with($trim, '#')) {
                    continue;
                }
                $clean[] = $line;
            }
            $statements = split_sql_statements_for_setup(implode("\n", $clean));
            foreach ($statements as $statement) {
                if (preg_match('/\bDROP\b/i', $statement) === 1) {
                    continue;
                }
                $pdo->exec($statement);
            }

            $stmt = $pdo->prepare('SELECT id FROM admin_users WHERE username=:u LIMIT 1');
            $stmt->execute([':u' => 'admin']);
            $exists = $stmt->fetchColumn();
            if (!$exists) {
                $ins = $pdo->prepare('INSERT INTO admin_users(username,password_hash,email,is_active,is_verified,created_at,updated_at) VALUES(:u,:p,:e,1,1,NOW(),NOW())');
                $ins->execute([
                    ':u' => 'admin',
                    ':p' => password_hash('password', PASSWORD_DEFAULT),
                    ':e' => 'admin@example.com',
                ]);
            }

            $storageDir = dirname($lockPath);
            if (!is_dir($storageDir)) {
                mkdir($storageDir, 0775, true);
            }
            file_put_contents($lockPath, date('c') . "\n", LOCK_EX);
            $done = true;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}
?><!doctype html>
<html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>セットアップ</title></head>
<body style="font-family:sans-serif;max-width:720px;margin:24px auto;padding:0 12px;">
<h1>PinkClub-FANZA セットアップ</h1>
<?php if ($done) : ?>
<p>セットアップが完了しました。<a href="<?php echo e(base_url() . '/login0718.php'); ?>">管理ログイン</a>へ進んでください。</p>
<?php else : ?>
<?php if ($error !== '') : ?><p style="color:#b00020;white-space:pre-wrap;"><?php echo e($error); ?></p><?php endif; ?>
<form method="post">
    <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
    <p><label>DB Host <input name="db_host" value="<?php echo e($defaults['db_host']); ?>" required></label></p>
    <p><label>DB Port <input name="db_port" value="<?php echo e($defaults['db_port']); ?>" required></label></p>
    <p><label>DB Name <input name="db_name" value="<?php echo e($defaults['db_name']); ?>" required></label></p>
    <p><label>DB User <input name="db_user" value="<?php echo e($defaults['db_user']); ?>" required></label></p>
    <p><label>DB Pass <input type="password" name="db_pass" value="<?php echo e($defaults['db_pass']); ?>"></label></p>
    <button type="submit">セットアップ実行</button>
</form>
<?php endif; ?>
</body></html>
