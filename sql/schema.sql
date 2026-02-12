CREATE TABLE IF NOT EXISTS items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_id VARCHAR(64) NOT NULL,
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
    UNIQUE KEY uq_items_content_id (content_id),
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

CREATE TABLE IF NOT EXISTS page_views (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    viewed_at DATETIME NOT NULL,
    path VARCHAR(255) NOT NULL,
    referrer VARCHAR(500) DEFAULT NULL,
    ip_hash CHAR(64) NOT NULL,
    ua_hash CHAR(64) NOT NULL,
    session_id_hash CHAR(64) NOT NULL,
    item_cid VARCHAR(64) DEFAULT NULL,
    user_id INT DEFAULT NULL,
    INDEX idx_page_views_viewed_at (viewed_at),
    INDEX idx_page_views_item_cid (item_cid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS daily_stats (
    ymd DATE PRIMARY KEY,
    pv_total INT NOT NULL DEFAULT 0,
    uu_total INT NOT NULL DEFAULT 0,
    pv_top_json JSON DEFAULT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(128) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    content LONGTEXT NOT NULL,
    meta_title VARCHAR(255) DEFAULT NULL,
    meta_description VARCHAR(255) DEFAULT NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mail_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    created_at DATETIME NOT NULL,
    from_email VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    sent_ok TINYINT(1) NOT NULL DEFAULT 0,
    error_message TEXT DEFAULT NULL,
    INDEX idx_mail_logs_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mutual_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_name VARCHAR(255) NOT NULL,
    site_url VARCHAR(500) NOT NULL,
    link_url VARCHAR(500) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    note TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_mutual_links_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rss_sources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    feed_url VARCHAR(500) NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    last_fetched_at DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rss_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    url VARCHAR(500) NOT NULL,
    published_at DATETIME DEFAULT NULL,
    summary TEXT DEFAULT NULL,
    guid VARCHAR(500) NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_rss_items_guid (guid),
    INDEX idx_rss_items_pub (published_at),
    CONSTRAINT fk_rss_items_source FOREIGN KEY (source_id) REFERENCES rss_sources(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(30) NOT NULL DEFAULT 'admin',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL,
    last_login_at DATETIME DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
