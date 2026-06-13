</main>
</div>
<?php if (function_exists('settings_bool') && settings_bool('item_sync_enabled', false)): ?>
<script>
(function () {
  var status = document.getElementById('auto-timer-status');
  var endpoint = <?= json_encode(admin_url('timer_tick.php'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  var csrf = <?= json_encode(csrf_token(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  var running = false;
  var timerId = null;
  var updateStatus = function (message) {
    if (!status) {
      return;
    }
    var paragraph = status.querySelector('p');
    if (paragraph) {
      paragraph.textContent = message;
    }
  };
  var scheduleNext = function () {
    if (timerId !== null) {
      clearTimeout(timerId);
    }
    timerId = setTimeout(tick, 60000);
  };
  var tick = function () {
    if (running || !endpoint || !csrf) {
      scheduleNext();
      return;
    }
    running = true;
    var body = new FormData();
    body.append('_csrf', csrf);
    fetch(endpoint, {
      method: 'POST',
      credentials: 'same-origin',
      body: body
    }).then(function (response) {
      return response.json();
    }).then(function (data) {
      var message = data && data.message ? data.message : '自動更新を確認しました';
      updateStatus(message + '（' + new Date().toLocaleString() + '）');
    }).catch(function () {
      updateStatus('自動更新の確認に失敗しました（' + new Date().toLocaleString() + '）');
    }).finally(function () {
      running = false;
      scheduleNext();
    });
  };
  window.addEventListener('focus', tick);
  window.addEventListener('pageshow', tick);
  tick();
}());
</script>
<?php endif; ?>
</body>
</html>
