</main>
</div>
<script>
(function () {
  const tickUrl = <?= json_encode(admin_url('scheduler_tick.php'), JSON_UNESCAPED_SLASHES) ?>;
  const runTick = function () {
    fetch(tickUrl, { credentials: 'same-origin' })
      .then(function (res) { return res.json(); })
      .then(function (data) { console.log('[scheduler_tick]', data); })
      .catch(function (err) { console.warn('[scheduler_tick] failed', err); });
  };
  setInterval(runTick, 60000);
})();
</script>
</body>
</html>
