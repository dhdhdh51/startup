-- Bharat SEO CRM Database Schema
-- Version: 1.0
-- Default admin credentials: admin / admin123

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table: admin_users
-- ----------------------------
DROP TABLE IF EXISTS `admin_users`;
CREATE TABLE `admin_users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: leads
-- ----------------------------
DROP TABLE IF EXISTS `leads`;
CREATE TABLE `leads` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `business_name` VARCHAR(255) NOT NULL,
    `category` VARCHAR(255) DEFAULT NULL,
    `phone` VARCHAR(50) DEFAULT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `website_url` VARCHAR(500) DEFAULT NULL,
    `google_maps_url` VARCHAR(1000) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `city` VARCHAR(100) DEFAULT NULL,
    `state` VARCHAR(100) DEFAULT NULL,
    `country` VARCHAR(100) DEFAULT 'India',
    `rating` DECIMAL(2,1) DEFAULT NULL,
    `review_count` INT UNSIGNED DEFAULT 0,
    `latitude` DECIMAL(10,7) DEFAULT NULL,
    `longitude` DECIMAL(10,7) DEFAULT NULL,
    `source` VARCHAR(50) DEFAULT NULL,
    `source_id` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('new','contacted','interested','proposal_sent','converted','not_interested','follow_up') NOT NULL DEFAULT 'new',
    `opportunity_level` ENUM('low','medium','high','very_high') DEFAULT NULL,
    `audit_score` INT UNSIGNED DEFAULT NULL,
    `recommended_package` VARCHAR(255) DEFAULT NULL,
    `outreach_message` TEXT DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `follow_up_date` DATE DEFAULT NULL,
    `last_audited_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_opportunity` (`opportunity_level`),
    INDEX `idx_city` (`city`),
    INDEX `idx_source` (`source`),
    INDEX `idx_created` (`created_at`),
    UNIQUE INDEX `idx_source_id` (`source`, `source_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: lead_audits
-- ----------------------------
DROP TABLE IF EXISTS `lead_audits`;
CREATE TABLE `lead_audits` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `lead_id` INT UNSIGNED NOT NULL,
    `has_website` TINYINT(1) DEFAULT 0,
    `website_url` VARCHAR(500) DEFAULT NULL,
    `is_mobile_friendly` TINYINT(1) DEFAULT NULL,
    `has_ssl` TINYINT(1) DEFAULT NULL,
    `page_speed_score` INT UNSIGNED DEFAULT NULL,
    `has_meta_title` TINYINT(1) DEFAULT NULL,
    `has_meta_description` TINYINT(1) DEFAULT NULL,
    `has_h1_tag` TINYINT(1) DEFAULT NULL,
    `has_schema_markup` TINYINT(1) DEFAULT NULL,
    `has_sitemap` TINYINT(1) DEFAULT NULL,
    `has_robots_txt` TINYINT(1) DEFAULT NULL,
    `has_analytics` TINYINT(1) DEFAULT NULL,
    `has_google_tag_manager` TINYINT(1) DEFAULT NULL,
    `total_pages_indexed` INT UNSIGNED DEFAULT NULL,
    `domain_authority` INT UNSIGNED DEFAULT NULL,
    `backlink_count` INT UNSIGNED DEFAULT NULL,
    `has_gmb_listing` TINYINT(1) DEFAULT NULL,
    `gmb_is_verified` TINYINT(1) DEFAULT NULL,
    `gmb_has_posts` TINYINT(1) DEFAULT NULL,
    `gmb_has_products` TINYINT(1) DEFAULT NULL,
    `gmb_has_services` TINYINT(1) DEFAULT NULL,
    `gmb_has_reviews` TINYINT(1) DEFAULT NULL,
    `gmb_review_count` INT UNSIGNED DEFAULT NULL,
    `gmb_rating` DECIMAL(2,1) DEFAULT NULL,
    `gmb_photos_count` INT UNSIGNED DEFAULT NULL,
    `gmb_has_website_link` TINYINT(1) DEFAULT NULL,
    `gmb_has_phone` TINYINT(1) DEFAULT NULL,
    `gmb_has_hours` TINYINT(1) DEFAULT NULL,
    `gmb_has_description` TINYINT(1) DEFAULT NULL,
    `has_social_media` TINYINT(1) DEFAULT NULL,
    `social_facebook` VARCHAR(500) DEFAULT NULL,
    `social_instagram` VARCHAR(500) DEFAULT NULL,
    `social_twitter` VARCHAR(500) DEFAULT NULL,
    `social_linkedin` VARCHAR(500) DEFAULT NULL,
    `social_youtube` VARCHAR(500) DEFAULT NULL,
    `overall_score` INT UNSIGNED DEFAULT NULL,
    `recommendations` TEXT DEFAULT NULL,
    `raw_data` JSON DEFAULT NULL,
    `audited_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_lead_id` (`lead_id`),
    INDEX `idx_overall_score` (`overall_score`),
    CONSTRAINT `fk_audit_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: lead_searches
-- ----------------------------
DROP TABLE IF EXISTS `lead_searches`;
CREATE TABLE `lead_searches` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `niche` VARCHAR(255) NOT NULL,
    `city` VARCHAR(100) DEFAULT NULL,
    `state` VARCHAR(100) DEFAULT NULL,
    `country` VARCHAR(100) DEFAULT 'India',
    `source` VARCHAR(50) NOT NULL,
    `max_results` INT UNSIGNED DEFAULT 20,
    `total_found` INT UNSIGNED DEFAULT 0,
    `total_saved` INT UNSIGNED DEFAULT 0,
    `total_duplicates` INT UNSIGNED DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_niche` (`niche`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: settings
-- ----------------------------
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: activity_logs
-- ----------------------------
DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE `activity_logs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `action` VARCHAR(100) NOT NULL,
    `details` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_action` (`action`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ----------------------------
-- Default Data
-- ----------------------------

-- Default admin user (password: admin123)
INSERT INTO `admin_users` (`username`, `password_hash`, `created_at`) VALUES
('admin', '$2y$12$OhWWsA.lc7ivNe9k4mRF5utLNgsLXGl2pvPzcbikXV2kZQK0yr.fy', NOW());

-- Default settings
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('site_name', 'Bharat SEO'),
('site_tagline', 'Website + Google Map SEO + WhatsApp Leads for Local Businesses'),
('google_maps_api_key', ''),
('items_per_page', '25'),
('default_country', 'India'),
('whatsapp_message_template', 'Hi {business_name}, I noticed your business could benefit from improved online visibility. We specialize in Google Map SEO and website optimization for local businesses like yours. Would you like a free audit?'),
('outreach_delay_seconds', '30'),
('audit_auto_run', '1'),
('default_source', 'google_maps'),
('export_format', 'csv');
