CREATE DATABASE IF NOT EXISTS fanza CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fanza;

CREATE TABLE IF NOT EXISTS items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_id VARCHAR(64) NOT NULL UNIQUE,
    product_id VARCHAR(64) DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    url TEXT,
    affiliate_url TEXT,
    image_list TEXT,
    image_small TEXT,
    image_large TEXT,
    date_published DATETIME DEFAULT NULL,
    service_code VARCHAR(64) DEFAULT NULL,
    floor_code VARCHAR(64) DEFAULT NULL,
    category_name VARCHAR(128) DEFAULT NULL,
    price_min INT DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_items_date (date_published)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS actresses (
    id INT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    ruby VARCHAR(255) DEFAULT NULL,
    bust VARCHAR(32) DEFAULT NULL,
    cup VARCHAR(32) DEFAULT NULL,
    waist VARCHAR(32) DEFAULT NULL,
    hip VARCHAR(32) DEFAULT NULL,
    height VARCHAR(32) DEFAULT NULL,
    birthday VARCHAR(32) DEFAULT NULL,
    image_small TEXT,
    image_large TEXT,
    listurl_digital TEXT,
    listurl_monthly TEXT,
    listurl_mono TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS genres (
    genre_id INT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    ruby VARCHAR(255) DEFAULT NULL,
    list_url TEXT,
    site_code VARCHAR(64) DEFAULT NULL,
    service_code VARCHAR(64) DEFAULT NULL,
    floor_id VARCHAR(64) DEFAULT NULL,
    floor_code VARCHAR(64) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS makers (
    maker_id INT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    ruby VARCHAR(255) DEFAULT NULL,
    list_url TEXT,
    site_code VARCHAR(64) DEFAULT NULL,
    service_code VARCHAR(64) DEFAULT NULL,
    floor_id VARCHAR(64) DEFAULT NULL,
    floor_code VARCHAR(64) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS series (
    series_id INT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    ruby VARCHAR(255) DEFAULT NULL,
    list_url TEXT,
    site_code VARCHAR(64) DEFAULT NULL,
    service_code VARCHAR(64) DEFAULT NULL,
    floor_id VARCHAR(64) DEFAULT NULL,
    floor_code VARCHAR(64) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS item_actresses (
    item_id INT NOT NULL,
    actress_id INT NOT NULL,
    PRIMARY KEY (item_id, actress_id),
    INDEX idx_item_actresses_actress (actress_id),
    CONSTRAINT fk_item_actresses_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    CONSTRAINT fk_item_actresses_actress FOREIGN KEY (actress_id) REFERENCES actresses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS item_genres (
    item_id INT NOT NULL,
    genre_id INT NOT NULL,
    PRIMARY KEY (item_id, genre_id),
    INDEX idx_item_genres_genre (genre_id),
    CONSTRAINT fk_item_genres_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    CONSTRAINT fk_item_genres_genre FOREIGN KEY (genre_id) REFERENCES genres(genre_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS item_makers (
    item_id INT NOT NULL,
    maker_id INT NOT NULL,
    PRIMARY KEY (item_id, maker_id),
    INDEX idx_item_makers_maker (maker_id),
    CONSTRAINT fk_item_makers_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    CONSTRAINT fk_item_makers_maker FOREIGN KEY (maker_id) REFERENCES makers(maker_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS item_series (
    item_id INT NOT NULL,
    series_id INT NOT NULL,
    PRIMARY KEY (item_id, series_id),
    INDEX idx_item_series_series (series_id),
    CONSTRAINT fk_item_series_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    CONSTRAINT fk_item_series_series FOREIGN KEY (series_id) REFERENCES series(series_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS import_state (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(32) NOT NULL,
    last_offset INT NOT NULL DEFAULT 1,
    last_param VARCHAR(255) DEFAULT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uniq_import_state_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
