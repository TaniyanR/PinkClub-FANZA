-- 初期管理者: admin / password
INSERT INTO admins (username, password_hash)
VALUES ('admin', '$2y$12$ptQDVasJVLSGY8k1mhmvtOfkp.4EiOtQHxvs4Mux4ZJrh0RjpRIPm')
ON DUPLICATE KEY UPDATE username = VALUES(username);

-- API設定初期行（空値）
INSERT INTO settings (id, api_id, affiliate_id)
VALUES (1, '', '')
ON DUPLICATE KEY UPDATE id = VALUES(id);
