<?php

declare(strict_types=1);

require_once __DIR__ . '/_stub.php';

admin_render_stub_page('RSS管理', [
    'RSSソース追加・編集・有効/無効化',
    '手動/定期フェッチと重複除外',
    '取得ログと失敗時リトライ制御',
], '次期リリースで段階実装');
