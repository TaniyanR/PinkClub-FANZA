<?php
declare(strict_types=1);
require_once __DIR__ . '/../public/_bootstrap.php';
auth_require_admin();

$title = '広告コード';
$message = null;
$error = null;

$positions = [
    'header_left_728x90' => 'PCヘッダー（728x90）',
    'sidebar_bottom' => 'PCサイドバー下',
    'content_top' => 'PC本文上',
    'content_bottom' => 'PC本文下',
    'sp_header_below' => 'SPヘッダー下',
    'sp_footer_above' => 'SPフッター上',
];

/**
 * @return array<string, array{snippet_html: string, is_enabled: int}>
 */
function ads_code_rows_from_settings(array $positions): array
{
    $rows = [];
    foreach ($positions as $slot => $_label) {
        $html = trim(site_setting_get($slot . '_html', site_setting_get($slot, '')));
        $enabledSetting = trim(site_setting_get($slot . '_enabled', ''));
        if ($enabledSetting === '') {
            $enabled = $html !== '' ? 1 : 0;
        } else {
            $enabled = $enabledSetting === '1' ? 1 : 0;
        }

        $rows[$slot] = [
            'snippet_html' => $html,
            'is_enabled' => $enabled,
        ];
    }

    return $rows;
}

function ads_code_ensure_table(): void
{
    db()->exec('CREATE TABLE IF NOT EXISTS code_snippets (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        slot_key VARCHAR(100) NOT NULL UNIQUE,
        snippet_html MEDIUMTEXT NOT NULL,
        is_enabled TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}

function ads_code_mirror_to_code_snippets(PDO $pdo, array $rows): void
{
    ads_code_ensure_table();

    $stmt = $pdo->prepare('INSERT INTO code_snippets(slot_key,snippet_html,is_enabled,created_at,updated_at) VALUES(:slot,:html,:enabled,NOW(),NOW()) ON DUPLICATE KEY UPDATE snippet_html=VALUES(snippet_html),is_enabled=VALUES(is_enabled),updated_at=NOW()');
    foreach ($rows as $slot => $row) {
        $stmt->execute([
            ':slot' => $slot,
            ':html' => (string)($row['snippet_html'] ?? ''),
            ':enabled' => (int)($row['is_enabled'] ?? 0),
        ]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_fail((string)post('_csrf', ''));
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $settingsUpdates = [];
        $rows = [];
        foreach ($positions as $slot => $label) {
            $html = trim((string)post('code_' . $slot, ''));
            $enabled = post('enabled_' . $slot, '0') === '1' ? 1 : 0;
            $rows[$slot] = [
                'snippet_html' => $html,
                'is_enabled' => $enabled,
            ];
            $settingsUpdates[$slot . '_html'] = $html;
            $settingsUpdates[$slot] = $html;
            $settingsUpdates[$slot . '_enabled'] = (string)$enabled;
        }

        site_setting_set_many($settingsUpdates);
        ads_code_mirror_to_code_snippets($pdo, $rows);
        $pdo->commit();
        $message = '広告コードを保存しました。';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = '広告コードの保存に失敗しました。';
    }
}

try {
    $rows = ads_code_rows_from_settings($positions);
} catch (Throwable $e) {
    $rows = [];
    $error = '広告コードの読み込みに失敗しました。';
}

require __DIR__ . '/includes/header.php';
?>
<section class="admin-card admin-card--form">
  <h1>広告コード</h1>
  <p class="admin-form-note">各枠に広告タグを設定できます。不要な枠は無効のままにしてください。</p>
  <?php if ($message !== null): ?><p class="flash success"><?= e($message) ?></p><?php endif; ?>
  <?php if ($error !== null): ?><p class="flash error"><?= e($error) ?></p><?php endif; ?>

  <form method="post">
    <?= csrf_input() ?>
    <?php foreach ($positions as $slot => $label):
      $current = $rows[$slot] ?? ['snippet_html' => '', 'is_enabled' => 0];
    ?>
      <label><?= e($label) ?>
        <textarea name="code_<?= e($slot) ?>" rows="4" placeholder="広告コードを貼り付けてください"><?= e((string)($current['snippet_html'] ?? '')) ?></textarea>
      </label>
      <label>表示設定
        <select name="enabled_<?= e($slot) ?>">
          <option value="1" <?= ((int)($current['is_enabled'] ?? 0) === 1) ? 'selected' : '' ?>>有効</option>
          <option value="0" <?= ((int)($current['is_enabled'] ?? 0) !== 1) ? 'selected' : '' ?>>無効</option>
        </select>
      </label>
      <hr>
    <?php endforeach; ?>
    <div class="admin-actions">
      <button type="submit">保存</button>
    </div>
  </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
