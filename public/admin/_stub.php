<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

function admin_render_stub_page(string $title, array $todos = [], string $eta = ''): void
{
    $pageTitle = $title;

    ob_start();
    ?>
    <h1><?php echo e($title); ?></h1>
    <div class="admin-card">
        <p><strong>準備中</strong></p>
        <p>この機能は現在実装中です。画面デザインは先行して公開しています。</p>

        <?php if ($eta !== '') : ?>
            <p>実装目安: <?php echo e($eta); ?></p>
        <?php endif; ?>

        <?php if ($todos !== []) : ?>
            <h2>実装予定</h2>
            <ul>
                <?php foreach ($todos as $todo) : ?>
                    <li><?php echo e((string)$todo); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <?php

    $content = (string)ob_get_clean();
    include __DIR__ . '/../partials/admin_layout.php';
}
