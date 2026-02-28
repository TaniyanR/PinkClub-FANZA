-- 初期管理者: admin / password
INSERT INTO admins (username, password_hash)
VALUES ('admin', '$2y$12$ptQDVasJVLSGY8k1mhmvtOfkp.4EiOtQHxvs4Mux4ZJrh0RjpRIPm')
ON DUPLICATE KEY UPDATE username = VALUES(username);

-- API設定の初期キー（空値）
INSERT INTO settings (setting_key, setting_value)
VALUES
  ('fanza_api_id', ''),
  ('fanza_affiliate_id', ''),
  ('installer.ready', '1')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
