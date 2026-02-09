<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/db.php';

function get_config(): array
{
    return config();
}

function get_pdo(): PDO
{
    // DB接続は一箇所（lib/db.php）に寄せて例外/オプション/互換を統一
    return db();
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}
