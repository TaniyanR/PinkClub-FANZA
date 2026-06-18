<?php

declare(strict_types=1);

require_once __DIR__ . '/app_features.php';
require_once __DIR__ . '/csrf.php';
app_session_start();

function favorite_types(): array
{
    return ['item', 'actress', 'genre', 'maker', 'series'];
}

function favorite_type_labels(): array
{
    return [
        'item' => '商品',
        'actress' => '女優',
        'genre' => 'ジャンル',
        'maker' => 'メーカー',
        'series' => 'シリーズ',
    ];
}

function favorites_all(): array
{
    app_session_start();
    $favorites = $_SESSION['favorites'] ?? [];
    if (!is_array($favorites)) {
        $favorites = [];
    }
    foreach (favorite_types() as $type) {
        if (!isset($favorites[$type]) || !is_array($favorites[$type])) {
            $favorites[$type] = [];
        }
    }
    $_SESSION['favorites'] = $favorites;
    return $favorites;
}

function favorite_is_added(string $type, int $id): bool
{
    $favorites = favorites_all();
    return isset($favorites[$type][(string)$id]);
}

function favorite_add(string $type, int $id, string $title, string $url): void
{
    if (!in_array($type, favorite_types(), true) || $id <= 0) {
        return;
    }

    app_session_start();
    $favorites = favorites_all();
    $favorites[$type][(string)$id] = [
        'id' => $id,
        'title' => mb_strimwidth(trim($title), 0, 160, '…', 'UTF-8'),
        'url' => $url,
        'added_at' => date('Y-m-d H:i:s'),
    ];
    $_SESSION['favorites'] = $favorites;
}

function favorite_remove(string $type, int $id): void
{
    if (!in_array($type, favorite_types(), true) || $id <= 0) {
        return;
    }

    app_session_start();
    $favorites = favorites_all();
    unset($favorites[$type][(string)$id]);
    $_SESSION['favorites'] = $favorites;
}

function favorite_render_button(string $type, int $id, string $title, string $url): void
{
    if (!in_array($type, favorite_types(), true) || $id <= 0) {
        return;
    }

    $added = favorite_is_added($type, $id);
    $label = $added ? '★ お気に入り解除' : '☆ お気に入り';
    $action = $added ? 'remove' : 'add';
    ?>
    <form method="post" action="<?= e(public_url('favorite.php')) ?>" style="margin:8px 0 16px;">
      <input type="hidden" name="type" value="<?= e($type) ?>">
      <input type="hidden" name="id" value="<?= e((string)$id) ?>">
      <input type="hidden" name="title" value="<?= e($title) ?>">
      <input type="hidden" name="url" value="<?= e($url) ?>">
      <input type="hidden" name="action" value="<?= e($action) ?>">
      <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="return" value="<?= e((string)($_SERVER['REQUEST_URI'] ?? $url)) ?>">
      <button class="pcf-btn" type="submit" style="border:2px solid #f0b400;background:#fff7d6;color:#7a5200;font-weight:700;"> <?= e($label) ?></button>
    </form>
    <?php
}
