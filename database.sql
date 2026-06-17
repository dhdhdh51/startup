-- Bharat SEO Lead Finder & Auto Audit CRM Tool
-- Database Schema
-- Version: 1.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+05:30";

CREATE DATABASE IF NOT EXISTS `bharat_seo_crm` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `bharat_seo_crm`;

-- --------------------------------------------------------
-- Table: admin_users
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(100) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default admin user: admin / admin123
INSERT INTO `admin_users` (`username`, `password_hash`, `created_at`) VALUES
('admin', '$2y$12$KDW/pc65N21NprIAsTHHDOfapximDZv0pt2g2AQcWV/Yfs8RuTA.y', NOW());

-- --------------------------------------------------------
-- Table: leads
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `leads` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `business_name` VARCHAR(255) NOT NULL,
  `category` VARCHAR(255) DEFAULT NULL,
  `phone` VARCHAR(50) DEFAULT NULL,
  `email` VARCHAR(255) DEFAULT NULL,
  `website_url` VARCHAR(500) DEFAULT NULL,
  `google_maps_url` VARCHAR(500) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `city` VARCHAR(100) DEFAULT NULL,
  `state` VARCHAR(100) DEFAULT NULL,
  `country` VARCHAR(100) DEFAULT 'India',
  `rating` DECIMAL(2,1) DEFAULT NULL,
  `review_count` INT(11) DEFAULT 0,
  `latitude` DECIMAL(10,7) DEFAULT NULL,
  `longitude` DECIMAL(10,7) DEFAULT NULL,
  `source` VARCHAR(50) DEFAULT NULL,
  `source_id` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('New','Audited','Contacted','Interested','Follow-up','Converted','Not interested') NOT NULL DEFAULT 'New',
  `opportunity_level` VARCHAR(50) DEFAULT NULL,
  `audit_score` INT(11) DEFAULT NULL,
  `recommended_package` VARCHAR(255) DEFAULT NULL,
  `outreach_message` TEXT DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `follow_up_date` DATE DEFAULT NULL,
  `last_audited_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_city` (`city`),
  INDEX `idx_category` (`category`),
  INDEX `idx_status` (`status`),
  INDEX `idx_source_id` (`source_id`),
  INDEX `idx_phone` (`phone`),
  INDEX `idx_audit_score` (`audit_score`),
  INDEX `idx_opportunity` (`opportunity_level`),
  INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: lead_audits
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `lead_audits` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `lead_id` INT(11) UNSIGNED NOT NULL,
  `website_status` VARCHAR(50) DEFAULT NULL,
  `http_status` INT(11) DEFAULT NULL,
  `has_https` TINYINT(1) DEFAULT 0,
  `has_title` TINYINT(1) DEFAULT 0,
  `title_text` VARCHAR(500) DEFAULT NULL,
  `has_meta_description` TINYINT(1) DEFAULT 0,
  `meta_description` TEXT DEFAULT NULL,
  `has_h1` TINYINT(1) DEFAULT 0,
  `h1_text` VARCHAR(500) DEFAULT NULL,
  `has_viewport` TINYINT(1) DEFAULT 0,
  `has_canonical` TINYINT(1) DEFAULT 0,
  `has_robots` TINYINT(1) DEFAULT 0,
  `has_og_tags` TINYINT(1) DEFAULT 0,
  `has_twitter_tags` TINYINT(1) DEFAULT 0,
  `has_schema` TINYINT(1) DEFAULT 0,
  `has_local_schema` TINYINT(1) DEFAULT 0,
  `has_whatsapp` TINYINT(1) DEFAULT 0,
  `has_phone` TINYINT(1) DEFAULT 0,
  `has_email` TINYINT(1) DEFAULT 0,
  `has_contact_form` TINYINT(1) DEFAULT 0,
  `has_google_map` TINYINT(1) DEFAULT 0,
  `has_social_links` TINYINT(1) DEFAULT 0,
  `instagram_link` VARCHAR(500) DEFAULT NULL,
  `facebook_link` VARCHAR(500) DEFAULT NULL,
  `youtube_link` VARCHAR(500) DEFAULT NULL,
  `page_size` INT(11) DEFAULT NULL,
  `load_time` DECIMAL(5,2) DEFAULT NULL,
  `audit_json` LONGTEXT DEFAULT NULL,
  `problems` TEXT DEFAULT NULL,
  `recommendations` TEXT DEFAULT NULL,
  `score` INT(11) DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_lead_id` (`lead_id`),
  CONSTRAINT `fk_audit_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: lead_searches
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `lead_searches` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `niche` VARCHAR(255) NOT NULL,
  `city` VARCHAR(100) DEFAULT NULL,
  `state` VARCHAR(100) DEFAULT NULL,
  `country` VARCHAR(100) DEFAULT 'India',
  `source` VARCHAR(50) NOT NULL,
  `max_results` INT(11) DEFAULT 20,
  `total_found` INT(11) DEFAULT 0,
  `total_saved` INT(11) DEFAULT 0,
  `total_duplicates` INT(11) DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: settings
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default settings
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('agency_name', 'Bharat SEO'),
('whatsapp_number', ''),
('phone_number', ''),
('email', ''),
('default_city', 'Agra'),
('google_places_api_key', ''),
('serpapi_key', ''),
('apify_api_token', ''),
('default_search_source', 'google_places'),
('curl_timeout', '30'),
('max_leads_per_search', '100'),
('delay_between_audits', '3'),
('package_starter', 'Bharat Starter Package ₹2,999'),
('package_growth', 'Bharat Growth Package ₹6,999 + ₹999/month'),
('package_seo_fix', 'Bharat Starter SEO Fix Package ₹2,999'),
('package_pro', 'Bharat Pro Package ₹14,999 + ₹2,999/month'),
('outreach_template_no_website', 'Namaste sir, main Shivam Bharat SEO se hoon.\nMaine aapke business ka online presence check kiya. Aapka Google profile hai lekin website nahi mili.\nAgar website + WhatsApp enquiry button + Google Map SEO setup ho jaaye to customers direct call/WhatsApp kar sakte hain.\nStarter setup ₹2,999 me available hai. Demo free dikha sakta hoon.'),
('outreach_template_weak_website', 'Namaste sir, main Shivam Bharat SEO se hoon.\nMaine aapki website aur Google profile check ki. Kuch issues mile:\n{problems}\nMain ye complete fix ₹2,999 se start kar sakta hoon. Free demo dikha sakta hoon.'),
('outreach_template_good_website', 'Namaste sir, main Shivam Bharat SEO se hoon.\nMaine aapki website check ki. Overall kaafi achhi hai lekin kuch advanced SEO improvements se aur growth ho sakti hai.\nPro package ₹14,999 + ₹2,999/month me full SEO + lead generation setup milega. Free consultation available hai.');

-- --------------------------------------------------------
-- Table: activity_logs
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `action` VARCHAR(255) NOT NULL,
  `details` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_action` (`action`),
  INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
