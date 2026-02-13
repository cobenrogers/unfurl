-- Unfurl Database Schema
-- Version: 1.0.0
-- Date: 2026-02-07

-- ============================================================================
-- Feeds Table
-- ============================================================================
CREATE TABLE IF NOT EXISTS feeds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    topic VARCHAR(255) NOT NULL UNIQUE,
    url TEXT NOT NULL,
    result_limit INT DEFAULT 10,
    enabled TINYINT(1) DEFAULT 1,
    last_processed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_enabled (enabled),
    INDEX idx_topic (topic),
    INDEX idx_last_processed (last_processed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Articles Table
-- ============================================================================
CREATE TABLE IF NOT EXISTS articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    feed_id INT NOT NULL,
    topic VARCHAR(255) NOT NULL,

    -- Original RSS data
    google_news_url TEXT NOT NULL,
    rss_title TEXT,
    pub_date TIMESTAMP NULL,
    rss_description TEXT,
    rss_source VARCHAR(255),

    -- Resolved data
    final_url TEXT,
    status ENUM('pending', 'success', 'failed') DEFAULT 'pending',

    -- Metadata
    page_title TEXT,
    og_title TEXT,
    og_description TEXT,
    og_image TEXT,
    og_url TEXT,
    og_site_name VARCHAR(255),
    twitter_image TEXT,
    twitter_card VARCHAR(50),
    author VARCHAR(255),

    -- Article content
    article_content MEDIUMTEXT,
    word_count INT,
    categories TEXT,  -- JSON array

    -- Processing info
    error_message TEXT,
    retry_count INT DEFAULT 0,
    next_retry_at TIMESTAMP NULL,
    last_error TEXT NULL,
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (feed_id) REFERENCES feeds(id) ON DELETE RESTRICT,
    INDEX idx_feed_id (feed_id),
    INDEX idx_topic (topic),
    INDEX idx_status (status),
    INDEX idx_processed_at (processed_at),
    INDEX idx_google_news_url (google_news_url(255)),
    UNIQUE INDEX idx_final_url_unique (final_url(500)),
    INDEX idx_retry (status, retry_count, next_retry_at),
    FULLTEXT idx_search (rss_title, page_title, og_title, og_description, author)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- API Keys Table
-- ============================================================================
CREATE TABLE IF NOT EXISTS api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    key_name VARCHAR(255) NOT NULL,
    key_value VARCHAR(64) NOT NULL UNIQUE,
    description TEXT,
    enabled TINYINT(1) DEFAULT 1,
    last_used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_key_value (key_value),
    INDEX idx_enabled (enabled),
    INDEX idx_key_name (key_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Logs Table
-- ============================================================================
CREATE TABLE IF NOT EXISTS logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    level ENUM('DEBUG', 'INFO', 'WARNING', 'ERROR', 'CRITICAL') NOT NULL,
    category VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    context JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_level (level),
    INDEX idx_category (category),
    INDEX idx_created_at (created_at),
    INDEX idx_level_category (level, category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Migrations Table (track applied migrations)
-- ============================================================================
CREATE TABLE IF NOT EXISTS migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    migration_name VARCHAR(255) NOT NULL UNIQUE,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_migration_name (migration_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Metrics Table (optional - for monitoring)
-- ============================================================================
CREATE TABLE IF NOT EXISTS metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    metric_name VARCHAR(255) NOT NULL,
    metric_value DECIMAL(10,2) NOT NULL,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_name_time (metric_name, recorded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
