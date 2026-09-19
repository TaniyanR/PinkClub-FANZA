SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS item_reviews (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  item_id INT NOT NULL,
  reviewer_name VARCHAR(100) NOT NULL DEFAULT '名無しファン',
  rating TINYINT UNSIGNED NOT NULL DEFAULT 5,
  review_title VARCHAR(255) NOT NULL DEFAULT '',
  review_body TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status VARCHAR(20) NOT NULL DEFAULT 'approved',
  PRIMARY KEY (id),
  KEY idx_item_reviews_item_id_created (item_id, created_at),
  KEY idx_item_reviews_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
