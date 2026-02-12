<?php

declare(strict_types=1);

require_once __DIR__ . '/_stub.php';

admin_render_stub_page('メール', [
    '配信テンプレート管理',
    '送信キュー・配信結果の確認',
    'エラーメール再送と監査ログ',
], '次期リリースで段階実装');
