-- MySQL 8.x / InnoDB / utf8mb4
-- 先にDBを作ってある前提：
--   CREATE DATABASE pinkclub_f DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
--   USE pinkclub_f;

SET NAMES utf8mb4;
SET time_zone = '+09:00';

-- =========
-- items
-- =========
CREATE TABLE IF NOT EXISTS items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  content_id VARCHAR(64) NOT NULL,
  product_id VARCHAR(64) NOT NULL DEFAULT '',
  title TEXT NOT NULL,
  url TEXT NOT NULL,
  affiliate_url TEXT NOT NULL,
  image_list TEXT NOT NULL,
  image_small TEXT NOT NULL,
  image_large TEXT NOT NULL,
  date_published DATETIME NULL,
  service_code VARCHAR(64) NOT NULL DEFAULT '',
  floor_code VARCHAR(64) NOT NULL DEFAULT '',
  category_name VARCHAR(255) NOT NULL DEFAULT '',
  price_min INT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_items_content_id (content_id),
  KEY idx_items_date_published (date_published),
  KEY idx_items_price_min (price_min),
  KEY idx_items_updated_at (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========
-- actresses
-- =========
CREATE TABLE IF NOT EXISTS actresses (
  id INT NOT NULL,
  name VARCHAR(255) NOT NULL,
  ruby VARCHAR(255) NULL,
  bust VARCHAR(32) NULL,
  cup VARCHAR(32) NULL,
  waist VARCHAR(32) NULL,
  hip VARCHAR(32) NULL,
  height VARCHAR(32) NULL,
  birthday VARCHAR(32) NULL,
  blood_type VARCHAR(32) NULL,
  hobby VARCHAR(255) NULL,
  prefectures VARCHAR(255) NULL,
  image_small TEXT NULL,
  image_large TEXT NULL,
  listurl_digital TEXT NULL,
  listurl_monthly TEXT NULL,
  listurl_mono TEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_actresses_name (name),
  KEY idx_actresses_updated_at (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========
-- taxonomies: genres / makers / series
-- =========
CREATE TABLE IF NOT EXISTS genres (
  id INT NOT NULL,
  name VARCHAR(255) NOT NULL,
  ruby VARCHAR(255) NULL,
  list_url TEXT NULL,
  site_code VARCHAR(64) NULL,
  service_code VARCHAR(64) NULL,
  floor_id VARCHAR(64) NULL,
  floor_code VARCHAR(64) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_genres_name (name),
  KEY idx_genres_updated_at (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS makers (
  id INT NOT NULL,
  name VARCHAR(255) NOT NULL,
  ruby VARCHAR(255) NULL,
  list_url TEXT NULL,
  site_code VARCHAR(64) NULL,
  service_code VARCHAR(64) NULL,
  floor_id VARCHAR(64) NULL,
  floor_code VARCHAR(64) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_makers_name (name),
  KEY idx_makers_updated_at (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS series (
  id INT NOT NULL,
  name VARCHAR(255) NOT NULL,
  ruby VARCHAR(255) NULL,
  list_url TEXT NULL,
  site_code VARCHAR(64) NULL,
  service_code VARCHAR(64) NULL,
  floor_id VARCHAR(64) NULL,
  floor_code VARCHAR(64) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_series_name (name),
  KEY idx_series_updated_at (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========
-- relations (FK: items.content_id -> unique)
-- =========
CREATE TABLE IF NOT EXISTS item_actresses (
  content_id VARCHAR(64) NOT NULL,
  actress_id INT NOT NULL,
  PRIMARY KEY (content_id, actress_id),
  KEY idx_item_actresses_actress_id (actress_id),
  CONSTRAINT fk_item_actresses_item
    FOREIGN KEY (content_id) REFERENCES items(content_id) ON DELETE CASCADE,
  CONSTRAINT fk_item_actresses_actress
    FOREIGN KEY (actress_id) REFERENCES actresses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS item_genres (
  content_id VARCHAR(64) NOT NULL,
  genre_id INT NOT NULL,
  PRIMARY KEY (content_id, genre_id),
  KEY idx_item_genres_genre_id (genre_id),
  CONSTRAINT fk_item_genres_item
    FOREIGN KEY (content_id) REFERENCES items(content_id) ON DELETE CASCADE,
  CONSTRAINT fk_item_genres_genre
    FOREIGN KEY (genre_id) REFERENCES genres(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS item_makers (
  content_id VARCHAR(64) NOT NULL,
  maker_id INT NOT NULL,
  PRIMARY KEY (content_id, maker_id),
  KEY idx_item_makers_maker_id (maker_id),
  CONSTRAINT fk_item_makers_item
    FOREIGN KEY (content_id) REFERENCES items(content_id) ON DELETE CASCADE,
  CONSTRAINT fk_item_makers_maker
    FOREIGN KEY (maker_id) REFERENCES makers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS item_series (
  content_id VARCHAR(64) NOT NULL,
  series_id INT NOT NULL,
  PRIMARY KEY (content_id, series_id),
  KEY idx_item_series_series_id (series_id),
  CONSTRAINT fk_item_series_item
    FOREIGN KEY (content_id) REFERENCES items(content_id) ON DELETE CASCADE,
  CONSTRAINT fk_item_series_series
    FOREIGN KEY (series_id) REFERENCES series(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========
-- labels (label_id は NULL あり)
-- =========
CREATE TABLE IF NOT EXISTS item_labels (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  content_id VARCHAR(64) NOT NULL,
  label_id INT NULL,
  label_name VARCHAR(255) NOT NULL,
  label_ruby VARCHAR(255) NULL,
  PRIMARY KEY (id),
  KEY idx_item_labels_content_id (content_id),
  KEY idx_item_labels_label_id (label_id),
  KEY idx_item_labels_name (label_name),
  CONSTRAINT fk_item_labels_item
    FOREIGN KEY (content_id) REFERENCES items(content_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
