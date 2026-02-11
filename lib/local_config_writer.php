<?php
declare(strict_types=1);

function local_config_path(): string
{
    return __DIR__ . '/../config.local.php';
}

function local_config_load(): array
{
    $path = local_config_path();
    if (!is_file($path)) {
        return [];
    }

    $loaded = require $path;
    return is_array($loaded) ? $loaded : [];
}

function local_config_write(array $local): void
{
    $path = local_config_path();
    $dir = dirname($path);
    $tmp = $path . '.tmp';

    if (!is_dir($dir) || !is_writable($dir)) {
        throw new RuntimeException('保存先ディレクトリに書き込みできません。');
    }

    if (is_file($path) && !is_writable($path)) {
        throw new RuntimeException('設定ファイルに書き込みできません。');
    }

    $export = "<?php\n";
    $export .= "declare(strict_types=1);\n\n";
    $export .= 'return ' . var_export($local, true) . ";\n";

    $result = @file_put_contents($tmp, $export, LOCK_EX);
    if ($result === false) {
        throw new RuntimeException('設定ファイルの書き込みに失敗しました。');
    }

    if (!@rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException('設定ファイルの反映に失敗しました。');
    }

    @chmod($path, 0640);
}
