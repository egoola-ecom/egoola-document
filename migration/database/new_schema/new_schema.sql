-- =============================================================================
-- Egoola redesigned schema (32 tables) — DDL for the NEW database.
--
-- Generated from the single source of truth used for the redesign proposal:
--   scratchpad/egoola_review/schema_data.js  (module/table/field definitions)
--
-- Run this against an EMPTY target database before running the data-migration
-- artisan command (php artisan db:migrate-to-new). It is also runnable
-- directly with the mysql CLI:
--   mysql -u root -p your_new_db_name < database/new_schema/new_schema.sql
--
-- Every business table (all except `notifications`, which is Laravel's own
-- unchanged polymorphic table) carries the same 8-column audit block:
--   created_by, creator_type, creator_name, updated_by, updater_type,
--   updater_name, created_at, updated_at
-- createdBy/updatedBy are NOT real foreign keys — they point into admins,
-- sellers or users depending on *_type, so they stay plain BIGINT columns.
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------
-- 1. Geography reference data
-- -----------------------------------------------------------------------

DROP TABLE IF EXISTS `countries`;
CREATE TABLE `countries` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `country_code` VARCHAR(20) NOT NULL,
  `flag_path` VARCHAR(255) NULL,
  `flag_url` VARCHAR(255) NULL,
  `created_by` BIGINT NULL,
  `creator_type` ENUM('admin','seller','user','system') NULL,
  `creator_name` VARCHAR(255) NULL,
  `updated_by` BIGINT NULL,
  `updater_type` ENUM('admin','seller','user','system') NULL,
  `updater_name` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `countries_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `states`;
CREATE TABLE `states` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `country_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `state_code` VARCHAR(20) NOT NULL,
  `flag_path` VARCHAR(255) NULL,
  `flag_url` VARCHAR(255) NULL,
  `created_by` BIGINT NULL,
  `creator_type` ENUM('admin','seller','user','system') NULL,
  `creator_name` VARCHAR(255) NULL,
  `updated_by` BIGINT NULL,
  `updater_type` ENUM('admin','seller','user','system') NULL,
  `updater_name` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `states_slug_unique` (`slug`),
  KEY `states_country_id_index` (`country_id`),
  CONSTRAINT `states_country_id_fk` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `cities`;
CREATE TABLE `cities` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `country_id` BIGINT UNSIGNED NOT NULL,
  `state_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `city_code` VARCHAR(20) NOT NULL,
  `flag_path` VARCHAR(255) NULL,
  `flag_url` VARCHAR(255) NULL,
  `created_by` BIGINT NULL,
  `creator_type` ENUM('admin','seller','user','system') NULL,
  `creator_name` VARCHAR(255) NULL,
  `updated_by` BIGINT NULL,
  `updater_type` ENUM('admin','seller','user','system') NULL,
  `updater_name` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `cities_slug_unique` (`slug`),
  KEY `cities_state_id_index` (`state_id`),
  CONSTRAINT `cities_country_id_fk` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`),
  CONSTRAINT `cities_state_id_fk` FOREIGN KEY (`state_id`) REFERENCES `states` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `thanas`;
CREATE TABLE `thanas` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `country_id` BIGINT UNSIGNED NOT NULL,
  `state_id` BIGINT UNSIGNED NOT NULL,
  `city_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `thana_code` VARCHAR(20) NOT NULL,
  `flag_path` VARCHAR(255) NULL,
  `flag_url` VARCHAR(255) NULL,
  `created_by` BIGINT NULL,
  `creator_type` ENUM('admin','seller','user','system') NULL,
  `creator_name` VARCHAR(255) NULL,
  `updated_by` BIGINT NULL,
  `updater_type` ENUM('admin','seller','user','system') NULL,
  `updater_name` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `thanas_slug_unique` (`slug`),
  KEY `thanas_city_id_index` (`city_id`),
  CONSTRAINT `thanas_country_id_fk` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`),
  CONSTRAINT `thanas_state_id_fk` FOREIGN KEY (`state_id`) REFERENCES `states` (`id`),
  CONSTRAINT `thanas_city_id_fk` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- 2. Identity & authentication
-- -----------------------------------------------------------------------

DROP TABLE IF EXISTS `admins`;
CREATE TABLE `admins` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `mobile` VARCHAR(50) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `profile_pic_path` VARCHAR(255) NULL,
  `profile_pic_url` VARCHAR(255) NULL,
  `type` ENUM('super_admin','admin','moderator','support') NOT NULL DEFAULT 'admin',
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `skills` JSON NULL,
  `experiences` JSON NULL,
  `interests` JSON NULL,
  `educations` JSON NULL,
  `present_address` TEXT NULL,
  `permanent_address` TEXT NULL,
  `country_id` BIGINT UNSIGNED NULL,
  `state_id` BIGINT UNSIGNED NULL,
  `city_id` BIGINT UNSIGNED NULL,
  `thana_id` BIGINT UNSIGNED NULL,
  `last_logged_at` TIMESTAMP NULL,
  `created_by` BIGINT NULL,
  `creator_type` ENUM('admin','seller','user','system') NULL,
  `creator_name` VARCHAR(255) NULL,
  `updated_by` BIGINT NULL,
  `updater_type` ENUM('admin','seller','user','system') NULL,
  `updater_name` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `admins_email_unique` (`email`),
  UNIQUE KEY `admins_mobile_unique` (`mobile`),
  CONSTRAINT `admins_country_id_fk` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`),
  CONSTRAINT `admins_state_id_fk` FOREIGN KEY (`state_id`) REFERENCES `states` (`id`),
  CONSTRAINT `admins_city_id_fk` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`),
  CONSTRAINT `admins_thana_id_fk` FOREIGN KEY (`thana_id`) REFERENCES `thanas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `admin_media`;
CREATE TABLE `admin_media` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `admin_id` BIGINT UNSIGNED NOT NULL,
  `media_type` ENUM('image','video','document') NOT NULL,
  `media_for` ENUM('nid','birth_certificate','profile_document','other') NOT NULL,
  `media_path` VARCHAR(255) NOT NULL,
  `media_url` VARCHAR(255) NOT NULL,
  `created_by` BIGINT NULL,
  `creator_type` ENUM('admin','seller','user','system') NULL,
  `creator_name` VARCHAR(255) NULL,
  `updated_by` BIGINT NULL,
  `updater_type` ENUM('admin','seller','user','system') NULL,
  `updater_name` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `admin_media_admin_id_index` (`admin_id`),
  CONSTRAINT `admin_media_admin_id_fk` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `sellers`;
CREATE TABLE `sellers` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(50) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `profile_pic_path` VARCHAR(255) NULL,
  `profile_pic_url` VARCHAR(255) NULL,
  `email_verified_at` TIMESTAMP NULL,
  `phone_verified_at` TIMESTAMP NULL,
  `email_otp_code` VARCHAR(20) NULL,
  `email_otp_expires_at` TIMESTAMP NULL,
  `phone_otp_code` VARCHAR(20) NULL,
  `phone_otp_expires_at` TIMESTAMP NULL,
  `phone_otp_attempts` INT NOT NULL DEFAULT 0,
  `password_reset_token` VARCHAR(255) NULL,
  `password_reset_expires_at` TIMESTAMP NULL,
  `remember_token` VARCHAR(255) NULL,
  `verification_status` ENUM('unverified','pending','verified','rejected') NOT NULL DEFAULT 'unverified',
  `verification_note` TEXT NULL,
  `skills` JSON NULL,
  `education` JSON NULL,
  `experience` JSON NULL,
  `interest` JSON NULL,
  `present_address` TEXT NULL,
  `permanent_address` TEXT NULL,
  `country_id` BIGINT UNSIGNED NULL,
  `state_id` BIGINT UNSIGNED NULL,
  `city_id` BIGINT UNSIGNED NULL,
  `thana_id` BIGINT UNSIGNED NULL,
  `wallet_balance` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `bonus_balance` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `status` ENUM('active','suspended') NOT NULL DEFAULT 'active',
  `last_logged_at` TIMESTAMP NULL,
  `created_by` BIGINT NULL,
  `creator_type` ENUM('admin','seller','user','system') NULL,
  `creator_name` VARCHAR(255) NULL,
  `updated_by` BIGINT NULL,
  `updater_type` ENUM('admin','seller','user','system') NULL,
  `updater_name` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `sellers_email_unique` (`email`),
  UNIQUE KEY `sellers_phone_unique` (`phone`),
  CONSTRAINT `sellers_country_id_fk` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`),
  CONSTRAINT `sellers_state_id_fk` FOREIGN KEY (`state_id`) REFERENCES `states` (`id`),
  CONSTRAINT `sellers_city_id_fk` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`),
  CONSTRAINT `sellers_thana_id_fk` FOREIGN KEY (`thana_id`) REFERENCES `thanas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `seller_media`;
CREATE TABLE `seller_media` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `seller_id` BIGINT UNSIGNED NOT NULL,
  `media_type` ENUM('image','video','document') NOT NULL,
  `media_for` ENUM('nid','birth_certificate','certificate','gallery_image','gallery_video','other') NOT NULL,
  `media_path` VARCHAR(255) NOT NULL,
  `media_url` VARCHAR(255) NOT NULL,
  `created_by` BIGINT NULL,
  `creator_type` ENUM('admin','seller','user','system') NULL,
  `creator_name` VARCHAR(255) NULL,
  `updated_by` BIGINT NULL,
  `updater_type` ENUM('admin','seller','user','system') NULL,
  `updater_name` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `seller_media_seller_id_index` (`seller_id`),
  CONSTRAINT `seller_media_seller_id_fk` FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(50) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `profile_pic_path` VARCHAR(255) NULL,
  `profile_pic_url` VARCHAR(255) NULL,
  `description` TEXT NULL,
  `skills` JSON NULL,
  `experiences` JSON NULL,
  `interests` JSON NULL,
  `educations` JSON NULL,
  `email_verified_at` TIMESTAMP NULL,
  `phone_verified_at` TIMESTAMP NULL,
  `email_otp_code` VARCHAR(20) NULL,
  `email_otp_expires_at` TIMESTAMP NULL,
  `phone_otp_code` VARCHAR(20) NULL,
  `phone_otp_expires_at` TIMESTAMP NULL,
  `phone_otp_attempts` INT NOT NULL DEFAULT 0,
  `password_reset_token` VARCHAR(255) NULL,
  `password_reset_expires_at` TIMESTAMP NULL,
  `remember_token` VARCHAR(255) NULL,
  `address_line` VARCHAR(255) NULL,
  `country_id` BIGINT UNSIGNED NULL,
  `state_id` BIGINT UNSIGNED NULL,
  `city_id` BIGINT UNSIGNED NULL,
  `thana_id` BIGINT UNSIGNED NULL,
  `wallet_balance` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `status` ENUM('active','suspended') NOT NULL DEFAULT 'active',
  `created_by` BIGINT NULL,
  `creator_type` ENUM('admin','seller','user','system') NULL,
  `creator_name` VARCHAR(255) NULL,
  `updated_by` BIGINT NULL,
  `updater_type` ENUM('admin','seller','user','system') NULL,
  `updater_name` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_phone_unique` (`phone`),
  CONSTRAINT `users_country_id_fk` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`),
  CONSTRAINT `users_state_id_fk` FOREIGN KEY (`state_id`) REFERENCES `states` (`id`),
  CONSTRAINT `users_city_id_fk` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`),
  CONSTRAINT `users_thana_id_fk` FOREIGN KEY (`thana_id`) REFERENCES `thanas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `buyer_media`;
CREATE TABLE `buyer_media` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `buyer_id` BIGINT UNSIGNED NOT NULL,
  `media_type` ENUM('image','video','document') NOT NULL,
  `media_for` ENUM('nid','birth_certificate','profile_document','other') NOT NULL,
  `media_path` VARCHAR(255) NOT NULL,
  `media_url` VARCHAR(255) NOT NULL,
  `created_by` BIGINT NULL,
  `creator_type` ENUM('admin','seller','user','system') NULL,
  `creator_name` VARCHAR(255) NULL,
  `updated_by` BIGINT NULL,
  `updater_type` ENUM('admin','seller','user','system') NULL,
  `updater_name` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `buyer_media_buyer_id_index` (`buyer_id`),
  CONSTRAINT `buyer_media_buyer_id_fk` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- 3. Seller business profile
-- -----------------------------------------------------------------------

DROP TABLE IF EXISTS `seller_infos`;
CREATE TABLE `seller_infos` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `seller_id` BIGINT UNSIGNED NOT NULL,
  `business_type` VARCHAR(255) NOT NULL,
  `main_product` VARCHAR(255) NOT NULL,
  `owner_name` VARCHAR(255) NOT NULL,
  `employees_range` VARCHAR(50) NOT NULL,
  `annual_revenue` VARCHAR(100) NOT NULL,
  `established_year` VARCHAR(10) NOT NULL,
  `description` TEXT NULL,
  `public_email` VARCHAR(255) NULL,
  `whatsapp` VARCHAR(50) NULL,
  `facebook` VARCHAR(255) NULL,
  `wechat` VARCHAR(100) NULL,
  `skype` VARCHAR(100) NULL,
  `country_id` BIGINT UNSIGNED NULL,
  `state_id` BIGINT UNSIGNED NULL,
  `city_id` BIGINT UNSIGNED NULL,
  `thana_id` BIGINT UNSIGNED NULL,
  `created_by` BIGINT NULL,
  `creator_type` ENUM('admin','seller','user','system') NULL,
  `creator_name` VARCHAR(255) NULL,
  `updated_by` BIGINT NULL,
  `updater_type` ENUM('admin','seller','user','system') NULL,
  `updater_name` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `seller_infos_seller_id_unique` (`seller_id`),
  CONSTRAINT `seller_infos_seller_id_fk` FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `seller_infos_country_id_fk` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`),
  CONSTRAINT `seller_infos_state_id_fk` FOREIGN KEY (`state_id`) REFERENCES `states` (`id`),
  CONSTRAINT `seller_infos_city_id_fk` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`),
  CONSTRAINT `seller_infos_thana_id_fk` FOREIGN KEY (`thana_id`) REFERENCES `thanas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- 4. Categories (single self-referencing tree, product+service)
-- -----------------------------------------------------------------------

DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `parent_id` BIGINT UNSIGNED NULL,
  `type` ENUM('product','service') NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `name_bn` VARCHAR(255) NULL,
  `slug` VARCHAR(255) NOT NULL,
  `image_path` VARCHAR(255) NULL,
  `image_url` VARCHAR(255) NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` BIGINT NULL,
  `creator_type` ENUM('admin','seller','user','system') NULL,
  `creator_name` VARCHAR(255) NULL,
  `updated_by` BIGINT NULL,
  `updater_type` ENUM('admin','seller','user','system') NULL,
  `updater_name` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `categories_slug_unique` (`slug`),
  KEY `categories_parent_id_index` (`parent_id`),
  CONSTRAINT `categories_parent_id_fk` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- 5. Catalog / listings
-- -----------------------------------------------------------------------

DROP TABLE IF EXISTS `measurements`;
CREATE TABLE `measurements` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `symbol` VARCHAR(20) NOT NULL,
  `created_by` BIGINT NULL,
  `creator_type` ENUM('admin','seller','user','system') NULL,
  `creator_name` VARCHAR(255) NULL,
  `updated_by` BIGINT NULL,
  `updater_type` ENUM('admin','seller','user','system') NULL,
  `updater_name` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `listings`;
CREATE TABLE `listings` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `seller_id` BIGINT UNSIGNED NULL,
  `buyer_id` BIGINT UNSIGNED NULL,
  `category_id` BIGINT UNSIGNED NOT NULL,
  `catalog_type` ENUM('product','service') NOT NULL,
  `listing_type` ENUM('general','used','village','retail','wholesale','brand') NULL,
  `posted_by_role` ENUM('seller_offer','buyer_request') NULL,
  `title` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `brand` VARCHAR(255) NULL,
  `model` VARCHAR(255) NULL,
  `color` VARCHAR(100) NULL,
  `origin` VARCHAR(100) NULL,
  `warranty` VARCHAR(255) NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `old_price` DECIMAL(10,2) NULL,
  `currency` VARCHAR(10) NOT NULL DEFAULT 'BDT',
  `price_type` ENUM('fixed','hourly') NULL,
  `hourly_rate` DECIMAL(10,2) NULL,
  `delivery_days` INT NULL,
  `urgent` TINYINT(1) NULL,
  `package_includes` TEXT NULL,
  `available_from` TIMESTAMP NULL,
  `available_to` TIMESTAMP NULL,
  `min_order_qty` INT NULL,
  `stock_qty` BIGINT NULL,
  `measurement_id` BIGINT UNSIGNED NULL,
  `colors` JSON NULL,
  `sizes` JSON NULL,
  `reserved_qty` INT NULL,
  `condition_note` TEXT NULL,
  `attributes` JSON NULL,
  `country_id` BIGINT UNSIGNED NULL,
  `state_id` BIGINT UNSIGNED NULL,
  `city_id` BIGINT UNSIGNED NULL,
  `thana_id` BIGINT UNSIGNED NULL,
  `status` ENUM('draft','pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_by` BIGINT NULL,
  `creator_type` ENUM('admin','seller','user','system') NULL,
  `creator_name` VARCHAR(255) NULL,
  `updated_by` BIGINT NULL,
  `updater_type` ENUM('admin','seller','user','system') NULL,
  `updater_name` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL,
  UNIQUE KEY `listings_slug_unique` (`slug`),
  KEY `listings_seller_id_index` (`seller_id`),
  KEY `listings_buyer_id_index` (`buyer_id`),
  KEY `listings_category_id_index` (`category_id`),
  CONSTRAINT `listings_seller_id_fk` FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`),
  CONSTRAINT `listings_buyer_id_fk` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`),
  CONSTRAINT `listings_category_id_fk` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  CONSTRAINT `listings_measurement_id_fk` FOREIGN KEY (`measurement_id`) REFERENCES `measurements` (`id`),
  CONSTRAINT `listings_country_id_fk` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`),
  CONSTRAINT `listings_state_id_fk` FOREIGN KEY (`state_id`) REFERENCES `states` (`id`),
  CONSTRAINT `listings_city_id_fk` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`),
  CONSTRAINT `listings_thana_id_fk` FOREIGN KEY (`thana_id`) REFERENCES `thanas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `listing_price_tiers`;
CREATE TABLE `listing_price_tiers` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `listing_id` BIGINT UNSIGNED NOT NULL,
  `min_qty` INT NOT NULL,
  `max_qty` INT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `created_by` BIGINT NULL,
  `creator_type` ENUM('admin','seller','user','system') NULL,
  `creator_name` VARCHAR(255) NULL,
  `updated_by` BIGINT NULL,
  `updater_type` ENUM('admin','seller','user','system') NULL,
  `updater_name` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `listing_price_tiers_listing_id_index` (`listing_id`),
  CONSTRAINT `listing_price_tiers_listing_id_fk` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `listing_media`;
CREATE TABLE `listing_media` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `listing_id` BIGINT UNSIGNED NOT NULL,
  `media_type` ENUM('image','video','document') NOT NULL,
  `media_path` VARCHAR(255) NOT NULL,
  `media_url` VARCHAR(255) NOT NULL,
  `created_by` BIGINT NULL,
  `creator_type` ENUM('admin','seller','user','system') NULL,
  `creator_name` VARCHAR(255) NULL,
  `updated_by` BIGINT NULL,
  `updater_type` ENUM('admin','seller','user','system') NULL,
  `updater_name` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `listing_media_listing_id_index` (`listing_id`),
  CONSTRAINT `listing_media_listing_id_fk` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- 6. Bidding
-- -----------------------------------------------------------------------

DROP TABLE IF EXISTS `bids`;
CREATE TABLE `bids` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `listing_id` BIGINT UNSIGNED NOT NULL,
  `bidder_id` BIGINT UNSIGNED NOT NULL,
  `price_type` ENUM('fixed','hourly') NOT NULL DEFAULT 'fixed',
  `price` DECIMAL(10,2) NULL,
  `hourly_rate` DECIMAL(10,2) NULL,
  `hours` INT NULL,
  `description` VARCHAR(300) NOT NULL,
  `proposed_start` TIMESTAMP NULL,
  `proposed_end` TIMESTAMP NULL,
  `status` ENUM('pending','accepted','rejected','withdrawn') NOT NULL DEFAULT 'pending',
  `created_by` BIGINT NULL,
  `creator_type` ENUM('admin','seller','user','system') NULL,
  `creator_name` VARCHAR(255) NULL,
  `updated_by` BIGINT NULL,
  `updater_type` ENUM('admin','seller','user','system') NULL,
  `updater_name` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `bids_listing_id_bidder_id_unique` (`listing_id`,`bidder_id`),
  KEY `bids_bidder_id_index` (`bidder_id`),
  CONSTRAINT `bids_listing_id_fk` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bids_bidder_id_fk` FOREIGN KEY (`bidder_id`) REFERENCES `sellers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- 7. Orders, order items & payments
-- -----------------------------------------------------------------------

DROP TABLE IF EXISTS `carts`;
CREATE TABLE `carts` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `buyer_id` BIGINT UNSIGNED NOT NULL,
  `created_by` BIGINT NULL,
  `creator_type` ENUM('admin','seller','user','system') NULL,
  `creator_name` VARCHAR(255) NULL,
  `updated_by` BIGINT NULL,
  `updater_type` ENUM('admin','seller','user','system') NULL,
  `updater_name` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `carts_buyer_id_unique` (`buyer_id`),
  CONSTRAINT `carts_buyer_id_fk` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `cart_items`;
CREATE TABLE `cart_items` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `cart_id` BIGINT UNSIGNED NOT NULL,
  `listing_id` BIGINT UNSIGNED NOT NULL,
  `qty` INT NOT NULL DEFAULT 1,
  `color` VARCHAR(100) NULL,
  `size` VARCHAR(100) NULL,
  `created_by` BIGINT NULL,
  `creator_type` ENUM('admin','seller','user','system') NULL,
  `creator_name` VARCHAR(255) NULL,
  `updated_by` BIGINT NULL,
  `updater_type` ENUM('admin','seller','user','system') NULL,
  `updater_name` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `cart_items_cart_id_index` (`cart_id`),
  KEY `cart_items_listing_id_index` (`listing_id`),
  CONSTRAINT `cart_items_cart_id_fk` FOREIGN KEY (`cart_id`) REFERENCES `carts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cart_items_listing_id_fk` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `addresses`;
CREATE TABLE `addresses` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `addressable_type` ENUM('order','user') NOT NULL,
  `addressable_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(50) NOT NULL,
  `country_id` BIGINT UNSIGNED NOT NULL,
  `state_id` BIGINT UNSIGNED NOT NULL,
  `city_id` BIGINT UNSIGNED NOT NULL,
  `thana_id` BIGINT UNSIGNED NOT NULL,
  `address_line1` VARCHAR(255) NOT NULL,
  `address_line2` VARCHAR(255) NULL,
  `zip` VARCHAR(20) NULL,
  `created_by` BIGINT NULL,
  `creator_type` ENUM('admin','seller','user','system') NULL,
  `creator_name` VARCHAR(255) NULL,
  `updated_by` BIGINT NULL,
  `updater_type` ENUM('admin','seller','user','system') NULL,
  `updater_name` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `addresses_addressable_index` (`addressable_type`,`addressable_id`),
  CONSTRAINT `addresses_country_id_fk` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`),
  CONSTRAINT `addresses_state_id_fk` FOREIGN KEY (`state_id`) REFERENCES `states` (`id`),
  CONSTRAINT `addresses_city_id_fk` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`),
  CONSTRAINT `addresses_thana_id_fk` FOREIGN KEY (`thana_id`) REFERENCES `thanas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `buyer_id` BIGINT UNSIGNED NOT NULL,
  `seller_id` BIGINT UNSIGNED NOT NULL,
  `order_type` ENUM('product','service','job') NOT NULL,
  `listing_id` BIGINT UNSIGNED NULL,
  `bid_id` BIGINT UNSIGNED NULL,
  `address_id` BIGINT UNSIGNED NULL,
  `status` ENUM('pending','accepted','in_progress','delivered','completed','cancelled','disputed') NOT NULL DEFAULT 'pending',
  `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `delivery_cost` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `discount` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `total` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `commission_rate` DECIMAL(5,2) NOT NULL DEFAULT 0,
  `commission_amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `expected_delivery_at` TIMESTAMP NULL,
  `completed_at` TIMESTAMP NULL,
  `cancelled_reason` TEXT NULL,
  `confirmation_secret` VARCHAR(100) NULL,
  `created_by` BIGINT NULL,
  `creator_type` ENUM('admin','seller','user','system') NULL,
  `creator_name` VARCHAR(255) NULL,
  `updated_by` BIGINT NULL,
  `updater_type` ENUM('admin','seller','user','system') NULL,
  `updater_name` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `orders_buyer_id_index` (`buyer_id`),
  KEY `orders_seller_id_index` (`seller_id`),
  KEY `orders_listing_id_index` (`listing_id`),
  KEY `orders_bid_id_index` (`bid_id`),
  CONSTRAINT `orders_buyer_id_fk` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`),
  CONSTRAINT `orders_seller_id_fk` FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`),
  CONSTRAINT `orders_listing_id_fk` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`),
  CONSTRAINT `orders_bid_id_fk` FOREIGN KEY (`bid_id`) REFERENCES `bids` (`id`),
  CONSTRAINT `orders_address_id_fk` FOREIGN KEY (`address_id`) REFERENCES `addresses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `order_items`;
CREATE TABLE `order_items` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id` BIGINT UNSIGNED NOT NULL,
  `listing_id` BIGINT UNSIGNED NOT NULL,
  `title_snapshot` VARCHAR(255) NOT NULL,
  `price_snapshot` DECIMAL(10,2) NOT NULL,
  `qty` INT NOT NULL DEFAULT 1,
  `color` VARCHAR(100) NULL,
  `size` VARCHAR(100) NULL,
  `status` ENUM('pending','shipped','delivered') NULL,
  `deliverable_path` VARCHAR(255) NULL,
  `subtotal` DECIMAL(10,2) NOT NULL,
  `created_by` BIGINT NULL,
  `creator_type` ENUM('admin','seller','user','system') NULL,
  `creator_name` VARCHAR(255) NULL,
  `updated_by` BIGINT NULL,
  `updater_type` ENUM('admin','seller','user','system') NULL,
  `updater_name` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `order_items_order_id_index` (`order_id`),
  KEY `order_items_listing_id_index` (`listing_id`),
  CONSTRAINT `order_items_order_id_fk` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_items_listing_id_fk` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id` BIGINT UNSIGNED NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `user_type` ENUM('seller','user') NOT NULL,
  `purpose` ENUM('checkout_payment','seller_payout','commission_payment','refund') NOT NULL,
  `direction` ENUM('credit','debit') NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `method` VARCHAR(100) NOT NULL,
  `gateway_txn_id` VARCHAR(191) NULL,
  `payout_account_number` VARCHAR(100) NULL,
  `status` ENUM('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending',
  `meta` JSON NULL,
  `created_by` BIGINT NULL,
  `creator_type` ENUM('admin','seller','user','system') NULL,
  `creator_name` VARCHAR(255) NULL,
  `updated_by` BIGINT NULL,
  `updater_type` ENUM('admin','seller','user','system') NULL,
  `updater_name` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `payments_order_id_index` (`order_id`),
  KEY `payments_user_index` (`user_id`,`user_type`),
  KEY `payments_purpose_index` (`purpose`),
  CONSTRAINT `payments_order_id_fk` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- 8. Buyer inquiries (quote requests)
-- -----------------------------------------------------------------------

DROP TABLE IF EXISTS `quote_requests`;
CREATE TABLE `quote_requests` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `listing_id` BIGINT UNSIGNED NOT NULL,
  `buyer_id` BIGINT UNSIGNED NULL,
  `seller_id` BIGINT UNSIGNED NOT NULL,
  `qty` BIGINT NOT NULL,
  `message` TEXT NOT NULL,
  `contact_name` VARCHAR(255) NULL,
  `contact_email` VARCHAR(255) NULL,
  `contact_phone` VARCHAR(50) NULL,
  `contact_company` VARCHAR(255) NULL,
  `contact_city` VARCHAR(255) NULL,
  `contact_road` VARCHAR(255) NULL,
  `status` ENUM('new','responded','closed') NOT NULL DEFAULT 'new',
  `created_by` BIGINT NULL,
  `creator_type` ENUM('admin','seller','user','system') NULL,
  `creator_name` VARCHAR(255) NULL,
  `updated_by` BIGINT NULL,
  `updater_type` ENUM('admin','seller','user','system') NULL,
  `updater_name` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `quote_requests_listing_id_index` (`listing_id`),
  KEY `quote_requests_seller_id_index` (`seller_id`),
  CONSTRAINT `quote_requests_listing_id_fk` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`),
  CONSTRAINT `quote_requests_buyer_id_fk` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`),
  CONSTRAINT `quote_requests_seller_id_fk` FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- 9. Messaging
-- -----------------------------------------------------------------------

DROP TABLE IF EXISTS `conversations`;
CREATE TABLE `conversations` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `buyer_id` BIGINT UNSIGNED NOT NULL,
  `seller_id` BIGINT UNSIGNED NOT NULL,
  `listing_id` BIGINT UNSIGNED NULL,
  `created_by` BIGINT NULL,
  `creator_type` ENUM('admin','seller','user','system') NULL,
  `creator_name` VARCHAR(255) NULL,
  `updated_by` BIGINT NULL,
  `updater_type` ENUM('admin','seller','user','system') NULL,
  `updater_name` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `conversations_buyer_id_index` (`buyer_id`),
  KEY `conversations_seller_id_index` (`seller_id`),
  CONSTRAINT `conversations_buyer_id_fk` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`),
  CONSTRAINT `conversations_seller_id_fk` FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`),
  CONSTRAINT `conversations_listing_id_fk` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `messages`;
CREATE TABLE `messages` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `conversation_id` BIGINT UNSIGNED NOT NULL,
  `sender_id` BIGINT UNSIGNED NOT NULL,
  `sender_type` ENUM('buyer','seller') NOT NULL,
  `body` TEXT NULL,
  `attachment_path` VARCHAR(255) NULL,
  `attachment_url` VARCHAR(255) NULL,
  `seen_at` TIMESTAMP NULL,
  `created_by` BIGINT NULL,
  `creator_type` ENUM('admin','seller','user','system') NULL,
  `creator_name` VARCHAR(255) NULL,
  `updated_by` BIGINT NULL,
  `updater_type` ENUM('admin','seller','user','system') NULL,
  `updater_name` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `messages_conversation_id_index` (`conversation_id`),
  CONSTRAINT `messages_conversation_id_fk` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- 10. Reviews & favourites
-- -----------------------------------------------------------------------

DROP TABLE IF EXISTS `reviews`;
CREATE TABLE `reviews` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id` BIGINT UNSIGNED NOT NULL,
  `reviewer_id` BIGINT UNSIGNED NOT NULL,
  `listing_id` BIGINT UNSIGNED NOT NULL,
  `rating` TINYINT UNSIGNED NOT NULL,
  `content` TEXT NULL,
  `seller_reply` TEXT NULL,
  `created_by` BIGINT NULL,
  `creator_type` ENUM('admin','seller','user','system') NULL,
  `creator_name` VARCHAR(255) NULL,
  `updated_by` BIGINT NULL,
  `updater_type` ENUM('admin','seller','user','system') NULL,
  `updater_name` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `reviews_order_id_index` (`order_id`),
  KEY `reviews_listing_id_index` (`listing_id`),
  CONSTRAINT `reviews_order_id_fk` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  CONSTRAINT `reviews_reviewer_id_fk` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`),
  CONSTRAINT `reviews_listing_id_fk` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `favourites`;
CREATE TABLE `favourites` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `listing_id` BIGINT UNSIGNED NOT NULL,
  `created_by` BIGINT NULL,
  `creator_type` ENUM('admin','seller','user','system') NULL,
  `creator_name` VARCHAR(255) NULL,
  `updated_by` BIGINT NULL,
  `updater_type` ENUM('admin','seller','user','system') NULL,
  `updater_name` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `favourites_user_id_listing_id_unique` (`user_id`,`listing_id`),
  CONSTRAINT `favourites_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `favourites_listing_id_fk` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- 11. Notifications (Laravel's own polymorphic table — unchanged)
-- -----------------------------------------------------------------------

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` CHAR(36) NOT NULL PRIMARY KEY,
  `type` VARCHAR(255) NOT NULL,
  `notifiable_type` VARCHAR(255) NOT NULL,
  `notifiable_id` BIGINT UNSIGNED NOT NULL,
  `data` TEXT NOT NULL,
  `read_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `notifications_notifiable_index` (`notifiable_type`,`notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- 12. CMS & site settings
-- -----------------------------------------------------------------------

DROP TABLE IF EXISTS `banners`;
CREATE TABLE `banners` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `placement` ENUM('hero','sidebar','category') NOT NULL,
  `heading` VARCHAR(255) NULL,
  `small_heading` VARCHAR(255) NULL,
  `link_url` VARCHAR(255) NULL,
  `image_path` VARCHAR(255) NULL,
  `image_url` VARCHAR(255) NULL,
  `category_id` BIGINT UNSIGNED NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `sort_order` INT NOT NULL DEFAULT 0,
  `starts_at` TIMESTAMP NULL,
  `ends_at` TIMESTAMP NULL,
  `created_by` BIGINT NULL,
  `creator_type` ENUM('admin','seller','user','system') NULL,
  `creator_name` VARCHAR(255) NULL,
  `updated_by` BIGINT NULL,
  `updater_type` ENUM('admin','seller','user','system') NULL,
  `updater_name` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `banners_category_id_fk` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `email_templates`;
CREATE TABLE `email_templates` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `key` VARCHAR(191) NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `content` TEXT NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_by` BIGINT NULL,
  `creator_type` ENUM('admin','seller','user','system') NULL,
  `creator_name` VARCHAR(255) NULL,
  `updated_by` BIGINT NULL,
  `updater_type` ENUM('admin','seller','user','system') NULL,
  `updater_name` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `email_templates_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `key` VARCHAR(191) NOT NULL PRIMARY KEY,
  `value` JSON NOT NULL,
  `created_by` BIGINT NULL,
  `creator_type` ENUM('admin','seller','user','system') NULL,
  `creator_name` VARCHAR(255) NULL,
  `updated_by` BIGINT NULL,
  `updater_type` ENUM('admin','seller','user','system') NULL,
  `updater_name` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
