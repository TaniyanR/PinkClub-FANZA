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
    view_count INT DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_items_content_id (content_id),
    INDEX idx_items_date_published (date_published),
    INDEX idx_items_price_min (price_min),
    INDEX idx_items_category_name (category_name),
    INDEX idx_items_view_count (view_count)
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
    direction ENUM('in','out') NOT NULL DEFAULT 'in',
    from_name VARCHAR(255) DEFAULT NULL,
    from_email VARCHAR(255) DEFAULT NULL,
    to_email VARCHAR(255) DEFAULT NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    status ENUM('received','sent','failed') NOT NULL DEFAULT 'received',
    last_error TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL,
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

CREATE TABLE IF NOT EXISTS api_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    created_at DATETIME NOT NULL,
    endpoint VARCHAR(255) NOT NULL,
    params_json TEXT DEFAULT NULL,
    status VARCHAR(50) NOT NULL,
    http_code INT DEFAULT NULL,
    item_count INT DEFAULT 0,
    error_message TEXT DEFAULT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    INDEX idx_api_logs_created (created_at),
    INDEX idx_api_logs_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dmm_api (
    id INT AUTO_INCREMENT PRIMARY KEY,
    api_id VARCHAR(255) NOT NULL,
    affiliate_id VARCHAR(255) NOT NULL,
    site VARCHAR(100) DEFAULT 'FANZA',
    service VARCHAR(100) DEFAULT 'digital',
    floor VARCHAR(100) DEFAULT 'videoa',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS api_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    interval_minutes INT NOT NULL DEFAULT 60,
    last_run DATETIME DEFAULT NULL,
    lock_until DATETIME DEFAULT NULL,
    fail_count INT NOT NULL DEFAULT 0,
    last_error TEXT DEFAULT NULL,
    last_success_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    created_at DATETIME NOT NULL,
    INDEX idx_tags_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS item_tags (
    item_content_id VARCHAR(64) NOT NULL,
    tag_id INT NOT NULL,
    PRIMARY KEY (item_content_id, tag_id),
    INDEX idx_item_tags_tag (tag_id),
    CONSTRAINT fk_item_tags_content
        FOREIGN KEY (item_content_id) REFERENCES items(content_id) ON DELETE CASCADE,
    CONSTRAINT fk_item_tags_tag
        FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS access_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(50) NOT NULL,
    event_at DATETIME NOT NULL,
    path VARCHAR(500) DEFAULT NULL,
    referrer VARCHAR(500) DEFAULT NULL,
    link_id INT DEFAULT NULL,
    ip_hash CHAR(64) DEFAULT NULL,
    INDEX idx_access_events_event_at (event_at),
    INDEX idx_access_events_type (event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(190) NOT NULL UNIQUE,
    setting_value LONGTEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_site_settings_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS code_snippets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slot_key VARCHAR(100) NOT NULL UNIQUE,
    snippet_html LONGTEXT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fixed_pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(190) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    body LONGTEXT NOT NULL,
    seo_title VARCHAR(255) DEFAULT NULL,
    seo_description VARCHAR(255) DEFAULT NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_fixed_pages_published (is_published)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_password_resets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_user_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_admin_password_resets_token_hash (token_hash),
    INDEX idx_admin_password_resets_expires_at (expires_at),
    CONSTRAINT fk_admin_password_resets_user FOREIGN KEY (admin_user_id) REFERENCES admin_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE admin_users ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE admin_users ADD COLUMN IF NOT EXISTS email VARCHAR(255) NULL;
ALTER TABLE mutual_links ADD COLUMN IF NOT EXISTS rss_url VARCHAR(500) NULL;
ALTER TABLE mutual_links ADD COLUMN IF NOT EXISTS display_position VARCHAR(40) NOT NULL DEFAULT 'sidebar';
ALTER TABLE mutual_links ADD COLUMN IF NOT EXISTS rss_enabled TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE rss_sources ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE rss_sources ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE rss_items ADD COLUMN IF NOT EXISTS image_url VARCHAR(500) NULL;
ALTER TABLE rss_items ADD COLUMN IF NOT EXISTS payload_json JSON NULL;
ALTER TABLE api_schedules ADD COLUMN IF NOT EXISTS fail_count INT NOT NULL DEFAULT 0;
ALTER TABLE api_schedules ADD COLUMN IF NOT EXISTS last_error TEXT NULL;
ALTER TABLE api_schedules ADD COLUMN IF NOT EXISTS max_items INT NOT NULL DEFAULT 100;
ALTER TABLE api_schedules ADD COLUMN IF NOT EXISTS interval_hours INT NOT NULL DEFAULT 1;
ALTER TABLE api_schedules ADD COLUMN IF NOT EXISTS last_run DATETIME NULL;
ALTER TABLE api_schedules ADD COLUMN IF NOT EXISTS last_success_at DATETIME NULL;
ALTER TABLE api_schedules ADD COLUMN IF NOT EXISTS lock_until DATETIME NULL;
ALTER TABLE api_schedules ADD COLUMN IF NOT EXISTS interval_minutes INT NOT NULL DEFAULT 60;


ALTER TABLE admin_users ADD COLUMN IF NOT EXISTS display_name VARCHAR(255) NULL;
ALTER TABLE admin_users ADD COLUMN IF NOT EXISTS pending_email VARCHAR(255) NULL;
ALTER TABLE admin_users ADD COLUMN IF NOT EXISTS email_verified_at DATETIME NULL;
ALTER TABLE admin_users ADD COLUMN IF NOT EXISTS login_mode VARCHAR(20) NOT NULL DEFAULT 'username';
ALTER TABLE mutual_links ADD COLUMN IF NOT EXISTS display_order INT NOT NULL DEFAULT 0;
ALTER TABLE mutual_links ADD COLUMN IF NOT EXISTS approved_at DATETIME NULL;
ALTER TABLE access_events ADD COLUMN IF NOT EXISTS from_id INT NULL;
CREATE INDEX idx_mutual_links_status_approved_order ON mutual_links(status, approved_at, display_order);
CREATE INDEX idx_access_events_created_type_from ON access_events(event_at, event_type, from_id);
CREATE INDEX idx_rss_items_source_created ON rss_items(source_id, created_at);
CREATE TABLE IF NOT EXISTS admin_email_verifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    consumed_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_admin_email_verifications_user (user_id),
    INDEX idx_admin_email_verifications_token (token_hash),
    INDEX idx_admin_email_verifications_expires (expires_at),
    CONSTRAINT fk_admin_email_verifications_user FOREIGN KEY (user_id) REFERENCES admin_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
