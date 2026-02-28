</main>
</div>
<script>
(function () {
  const tickUrl = <?= json_encode(admin_url('api_timer.php'), JSON_UNESCAPED_SLASHES) ?>;
  const statusElm = document.getElementById('api-timer-status');
  const runTick = function () {
    fetch(tickUrl, { credentials: 'same-origin' })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (statusElm) {
          statusElm.textContent = 'タイマー: ' + (data.status || 'ok') + ' ' + (data.schedule_type || '');
        }
      })
      .catch(function () {
        if (statusElm) {
          statusElm.textContent = 'タイマー通信失敗';
        }
      });
  };
  runTick();
  setInterval(runTick, 60000);
})();
</script>
</body>
</html>
