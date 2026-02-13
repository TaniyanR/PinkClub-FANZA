ALTER TABLE mail_logs
    ADD COLUMN IF NOT EXISTS direction ENUM('in','out') NOT NULL DEFAULT 'in' AFTER id,
    ADD COLUMN IF NOT EXISTS from_name VARCHAR(255) DEFAULT NULL AFTER direction,
    MODIFY COLUMN from_email VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS to_email VARCHAR(255) DEFAULT NULL AFTER from_email,
    ADD COLUMN IF NOT EXISTS status ENUM('received','sent','failed') NOT NULL DEFAULT 'received' AFTER body,
    ADD COLUMN IF NOT EXISTS last_error TEXT DEFAULT NULL AFTER status,
    ADD COLUMN IF NOT EXISTS updated_at DATETIME DEFAULT NULL AFTER created_at;

UPDATE mail_logs
SET
    status = CASE WHEN sent_ok = 1 THEN 'sent' ELSE 'failed' END,
    last_error = error_message
WHERE (status IS NULL OR status = 'received')
  AND (sent_ok IS NOT NULL OR error_message IS NOT NULL);
