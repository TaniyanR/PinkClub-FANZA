<?php

declare(strict_types=1);
require_once __DIR__ . '/../public/_bootstrap.php';
auth_require_admin();

$title = 'タイマー稼働';
$settings = settings_get();
require __DIR__ . '/includes/header.php';
?>
<section class="admin-card">
  <h1>タイマー稼働</h1>
  <p>このページを開いている間、60秒ごとに自動取得tickを実行します（cron不要）。</p>
  <p>自動取得: <strong><?= ((int)($settings['item_sync_enabled'] ?? 0) === 1) ? 'ON' : 'OFF' ?></strong></p>
  <p>直近結果: <span id="timer-message">待機中</span></p>
  <p>最終実行: <span id="timer-last">-</span></p>
</section>
<script>
(() => {
  const csrf = <?= json_encode(csrf_token(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const msgEl = document.getElementById('timer-message');
  const lastEl = document.getElementById('timer-last');

  const tick = async () => {
    try {
      const body = new URLSearchParams();
      body.set('_csrf', csrf);
      const res = await fetch('<?= e(admin_url('timer_tick.php')) ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: body.toString(),
      });
      const json = await res.json();
      msgEl.textContent = json.message || '待機中';
      lastEl.textContent = json.at || '-';
    } catch (e) {
      msgEl.textContent = 'タイマー通信に失敗しました。';
    }
  };

  tick();
  setInterval(tick, 60000);
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
