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
    INDEX idx_items_date_published (date_published),
    INDEX idx_items_price_min (price_min),
    INDEX idx_items_category_name (category_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS actresses (
    id BIGINT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    ruby VARCHAR(255) DEFAULT NULL,
    bust INT DEFAULT NULL,
    cup VARCHAR(32) DEFAULT NULL,
    waist INT DEFAULT NULL,
    hip INT DEFAULT NULL,
    height INT DEFAULT NULL,
    birthday DATE DEFAULT NULL,
    blood_type VARCHAR(32) DEFAULT NULL,
    hobby TEXT DEFAULT NULL,
    prefectures VARCHAR(255) DEFAULT NULL,
    image_small TEXT,
    image_large TEXT,
    listurl_digital TEXT,
    listurl_monthly TEXT,
    listurl_mono TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_actresses_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS genres (
    id INT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    ruby VARCHAR(255) DEFAULT NULL,
    list_url TEXT,
    site_code VARCHAR(64) DEFAULT NULL,
    service_code VARCHAR(64) DEFAULT NULL,
    floor_id VARCHAR(64) DEFAULT NULL,
    floor_code VARCHAR(64) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_genres_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS makers (
    id INT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    ruby VARCHAR(255) DEFAULT NULL,
    list_url TEXT,
    site_code VARCHAR(64) DEFAULT NULL,
    service_code VARCHAR(64) DEFAULT NULL,
    floor_id VARCHAR(64) DEFAULT NULL,
    floor_code VARCHAR(64) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_makers_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS series (
    id INT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    ruby VARCHAR(255) DEFAULT NULL,
    list_url TEXT,
    site_code VARCHAR(64) DEFAULT NULL,
    service_code VARCHAR(64) DEFAULT NULL,
    floor_id VARCHAR(64) DEFAULT NULL,
    floor_code VARCHAR(64) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_series_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS item_actresses (
    content_id VARCHAR(64) NOT NULL,
    actress_id BIGINT NOT NULL,
    PRIMARY KEY (content_id, actress_id),
    INDEX idx_item_actresses_actress (actress_id),
    CONSTRAINT fk_item_actresses_content
        FOREIGN KEY (content_id) REFERENCES items (content_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_item_actresses_actress
        FOREIGN KEY (actress_id) REFERENCES actresses (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS item_genres (
    content_id VARCHAR(64) NOT NULL,
    genre_id INT NOT NULL,
    PRIMARY KEY (content_id, genre_id),
    INDEX idx_item_genres_genre (genre_id),
    CONSTRAINT fk_item_genres_content
        FOREIGN KEY (content_id) REFERENCES items (content_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_item_genres_genre
        FOREIGN KEY (genre_id) REFERENCES genres (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS item_makers (
    content_id VARCHAR(64) NOT NULL,
    maker_id INT NOT NULL,
    PRIMARY KEY (content_id, maker_id),
    INDEX idx_item_makers_maker (maker_id),
    CONSTRAINT fk_item_makers_content
        FOREIGN KEY (content_id) REFERENCES items (content_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_item_makers_maker
        FOREIGN KEY (maker_id) REFERENCES makers (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS item_series (
    content_id VARCHAR(64) NOT NULL,
    series_id INT NOT NULL,
    PRIMARY KEY (content_id, series_id),
    INDEX idx_item_series_series (series_id),
    CONSTRAINT fk_item_series_content
        FOREIGN KEY (content_id) REFERENCES items (content_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_item_series_series
        FOREIGN KEY (series_id) REFERENCES series (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS item_labels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_id VARCHAR(64) NOT NULL,
    label_id INT DEFAULT NULL,
    label_name VARCHAR(255) NOT NULL,
    label_ruby VARCHAR(255) DEFAULT NULL,
    INDEX idx_item_labels_label_id (label_id),
    INDEX idx_item_labels_label_name (label_name),
    CONSTRAINT fk_item_labels_content
        FOREIGN KEY (content_id) REFERENCES items (content_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
