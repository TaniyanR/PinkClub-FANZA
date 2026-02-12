<?php

declare(strict_types=1);

require_once __DIR__ . '/_stub.php';

admin_render_stub_page('バックアップ', [
    'DBと設定ファイルのスナップショット作成',
    '世代管理と自動削除ポリシー',
    'リストア手順の安全ガード',
], '次期リリースで段階実装');
