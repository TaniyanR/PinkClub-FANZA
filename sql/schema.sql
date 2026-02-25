SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS admins (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS app_settings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(100) NOT NULL UNIQUE,
  setting_value LONGTEXT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS dmm_sites (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  site_code VARCHAR(50) NOT NULL,
  site_name VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_site_code (site_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS dmm_services (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  site_id BIGINT UNSIGNED NOT NULL,
  service_code VARCHAR(100) NOT NULL,
  service_name VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_site_service (site_id, service_code),
  CONSTRAINT fk_services_site FOREIGN KEY (site_id) REFERENCES dmm_sites(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS dmm_floors (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  dmm_floor_id INT NOT NULL,
  service_id BIGINT UNSIGNED NOT NULL,
  floor_code VARCHAR(100) NOT NULL,
  floor_name VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_service_floorid (service_id, dmm_floor_id),
  KEY idx_floor_code (floor_code),
  CONSTRAINT fk_floors_service FOREIGN KEY (service_id) REFERENCES dmm_services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS actresses (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actress_id BIGINT NOT NULL,
  name VARCHAR(255) NOT NULL,
  ruby VARCHAR(255) NULL,
  bust INT NULL,
  cup VARCHAR(10) NULL,
  waist INT NULL,
  hip INT NULL,
  height INT NULL,
  birthday DATE NULL,
  blood_type VARCHAR(10) NULL,
  hobby TEXT NULL,
  prefectures VARCHAR(255) NULL,
  image_small TEXT NULL,
  image_large TEXT NULL,
  list_url_digital TEXT NULL,
  list_url_monthly TEXT NULL,
  list_url_mono TEXT NULL,
  is_active TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_actress_id (actress_id),
  KEY idx_actress_name (name),
  KEY idx_actress_ruby (ruby)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS genres (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  genre_id BIGINT NOT NULL,
  dmm_floor_id INT NOT NULL,
  site_code VARCHAR(50) NOT NULL,
  service_code VARCHAR(100) NOT NULL,
  floor_code VARCHAR(100) NOT NULL,
  name VARCHAR(255) NOT NULL,
  ruby VARCHAR(255) NULL,
  list_url TEXT NULL,
  is_active TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_genre_floor (genre_id, dmm_floor_id),
  KEY idx_genre_name (name),
  KEY idx_genre_ruby (ruby),
  KEY idx_genre_floor_code (floor_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS makers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  maker_id BIGINT NOT NULL,
  dmm_floor_id INT NOT NULL,
  site_code VARCHAR(50) NOT NULL,
  service_code VARCHAR(100) NOT NULL,
  floor_code VARCHAR(100) NOT NULL,
  name VARCHAR(255) NOT NULL,
  ruby VARCHAR(255) NULL,
  list_url TEXT NULL,
  is_active TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_maker_floor (maker_id, dmm_floor_id),
  KEY idx_maker_name (name),
  KEY idx_maker_ruby (ruby)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS series_master (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  series_id BIGINT NOT NULL,
  dmm_floor_id INT NOT NULL,
  site_code VARCHAR(50) NOT NULL,
  service_code VARCHAR(100) NOT NULL,
  floor_code VARCHAR(100) NOT NULL,
  name VARCHAR(255) NOT NULL,
  ruby VARCHAR(255) NULL,
  list_url TEXT NULL,
  is_active TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_series_floor (series_id, dmm_floor_id),
  KEY idx_series_name (name),
  KEY idx_series_ruby (ruby)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS authors (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  author_id BIGINT NOT NULL,
  dmm_floor_id INT NOT NULL,
  site_code VARCHAR(50) NOT NULL,
  service_code VARCHAR(100) NOT NULL,
  floor_code VARCHAR(100) NOT NULL,
  name VARCHAR(255) NOT NULL,
  ruby VARCHAR(255) NULL,
  another_name TEXT NULL,
  list_url TEXT NULL,
  is_active TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_author_floor (author_id, dmm_floor_id),
  KEY idx_author_name (name),
  KEY idx_author_ruby (ruby)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  content_id VARCHAR(100) NOT NULL,
  product_id VARCHAR(100) NULL,
  site_code VARCHAR(50) NOT NULL,
  service_code VARCHAR(100) NOT NULL,
  floor_code VARCHAR(100) NOT NULL,
  service_name VARCHAR(255) NULL,
  floor_name VARCHAR(255) NULL,
  category_name VARCHAR(255) NULL,
  title TEXT NOT NULL,
  volume VARCHAR(50) NULL,
  number_text VARCHAR(50) NULL,
  review_count INT NULL,
  review_average DECIMAL(4,2) NULL,
  url TEXT NULL,
  affiliate_url TEXT NULL,
  image_list TEXT NULL,
  image_small TEXT NULL,
  image_large TEXT NULL,
  sample_image_s_json LONGTEXT NULL,
  sample_image_l_json LONGTEXT NULL,
  sample_movie_476 TEXT NULL,
  sample_movie_560 TEXT NULL,
  sample_movie_644 TEXT NULL,
  sample_movie_720 TEXT NULL,
  sample_movie_pc_flag TINYINT NULL,
  sample_movie_sp_flag TINYINT NULL,
  price_text VARCHAR(100) NULL,
  list_price_text VARCHAR(100) NULL,
  deliveries_json LONGTEXT NULL,
  item_date DATETIME NULL,
  stock_text VARCHAR(50) NULL,
  jancode VARCHAR(50) NULL,
  maker_product VARCHAR(100) NULL,
  isbn VARCHAR(50) NULL,
  raw_iteminfo_json LONGTEXT NULL,
  raw_campaign_json LONGTEXT NULL,
  raw_directory_json LONGTEXT NULL,
  last_synced_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_content_id (content_id),
  KEY idx_site_service_floor (site_code, service_code, floor_code),
  KEY idx_item_date (item_date),
  FULLTEXT KEY ft_items_title (title)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS item_actresses (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  item_id BIGINT UNSIGNED NOT NULL,
  actress_id BIGINT UNSIGNED NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_item_actress (item_id, actress_id),
  KEY idx_item_actress_id (actress_id),
  CONSTRAINT fk_item_actresses_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
  CONSTRAINT fk_item_actresses_actress FOREIGN KEY (actress_id) REFERENCES actresses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS item_genres (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  item_id BIGINT UNSIGNED NOT NULL,
  genre_id BIGINT UNSIGNED NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_item_genre (item_id, genre_id),
  KEY idx_item_genre_id (genre_id),
  CONSTRAINT fk_item_genres_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
  CONSTRAINT fk_item_genres_genre FOREIGN KEY (genre_id) REFERENCES genres(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS item_makers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  item_id BIGINT UNSIGNED NOT NULL,
  maker_id BIGINT UNSIGNED NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_item_maker (item_id, maker_id),
  KEY idx_item_maker_id (maker_id),
  CONSTRAINT fk_item_makers_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
  CONSTRAINT fk_item_makers_maker FOREIGN KEY (maker_id) REFERENCES makers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS item_series (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  item_id BIGINT UNSIGNED NOT NULL,
  series_id BIGINT UNSIGNED NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_item_series (item_id, series_id),
  KEY idx_item_series_id (series_id),
  CONSTRAINT fk_item_series_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
  CONSTRAINT fk_item_series_series FOREIGN KEY (series_id) REFERENCES series_master(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS item_authors (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  item_id BIGINT UNSIGNED NOT NULL,
  author_id BIGINT UNSIGNED NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_item_author (item_id, author_id),
  KEY idx_item_author_id (author_id),
  CONSTRAINT fk_item_authors_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
  CONSTRAINT fk_item_authors_author FOREIGN KEY (author_id) REFERENCES authors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS item_labels (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  item_id BIGINT UNSIGNED NOT NULL,
  label_dmm_id BIGINT NULL,
  name VARCHAR(255) NOT NULL,
  ruby VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  KEY idx_item_labels_item (item_id),
  KEY idx_item_labels_dmmid (label_dmm_id),
  CONSTRAINT fk_item_labels_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS item_directors (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  item_id BIGINT UNSIGNED NOT NULL,
  director_dmm_id BIGINT NULL,
  name VARCHAR(255) NOT NULL,
  ruby VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  KEY idx_item_directors_item (item_id),
  CONSTRAINT fk_item_directors_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS item_campaigns (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  item_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  date_begin DATETIME NULL,
  date_end DATETIME NULL,
  sort_order INT NOT NULL DEFAULT 0,
  KEY idx_item_campaigns_item (item_id),
  KEY idx_item_campaigns_period (date_begin, date_end),
  CONSTRAINT fk_item_campaigns_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sync_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sync_type VARCHAR(50) NOT NULL,
  target_site VARCHAR(50) NULL,
  target_service VARCHAR(100) NULL,
  target_floor VARCHAR(100) NULL,
  request_params_json LONGTEXT NULL,
  status VARCHAR(20) NOT NULL,
  fetched_count INT NOT NULL DEFAULT 0,
  saved_count INT NOT NULL DEFAULT 0,
  message TEXT NULL,
  started_at DATETIME NOT NULL,
  finished_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  KEY idx_sync_logs_type_created (sync_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
