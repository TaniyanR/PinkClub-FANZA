SET NAMES utf8mb4;
SET time_zone = '+00:00';

INSERT INTO admins (username, password_hash, created_at, updated_at)
VALUES ('admin', '$2y$12$8ojYs4GFspFJ0CHiQd9AquvWPlh40c6n.upb1u/bdJwTH.TSBgTHe', NOW(), NOW())
ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at);

INSERT INTO app_settings (setting_key, setting_value, updated_at) VALUES
('dmm_api_id', '', NOW()),
('dmm_affiliate_id', '', NOW()),
('default_site', 'FANZA', NOW()),
('default_service', 'digital', NOW()),
('default_floor', 'videoa', NOW()),
('sync_hits_default', '20', NOW()),
('sync_enabled_articles', '["genre","actress","maker","series","author"]', NOW())
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = VALUES(updated_at);
