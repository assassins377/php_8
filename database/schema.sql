-- Secure Blog Database Schema
-- MySQL 8.0+ with InnoDB, UTF-8mb4

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================
-- USERS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NULL,
    `role` ENUM('admin', 'moderator', 'author', 'guest') NOT NULL DEFAULT 'guest',
    `status` ENUM('active', 'banned', 'pending') NOT NULL DEFAULT 'pending',
    `two_fa_secret` VARCHAR(255) NULL,
    `oauth_provider` VARCHAR(50) NULL,
    `oauth_uid` VARCHAR(255) NULL,
    `avatar_path` VARCHAR(255) NULL,
    `bio` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_email` (`email`),
    INDEX `idx_username` (`username`),
    INDEX `idx_role_status` (`role`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- POSTS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `posts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `author_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NOT NULL UNIQUE,
    `content` LONGTEXT NOT NULL,
    `preview` VARCHAR(500) NULL,
    `image_path` VARCHAR(255) NULL,
    `views` INT UNSIGNED NOT NULL DEFAULT 0,
    `rating_avg` DECIMAL(3,2) NOT NULL DEFAULT 0.00,
    `rating_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `is_published` TINYINT(1) NOT NULL DEFAULT 0,
    `published_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_posts_author` FOREIGN KEY (`author_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_slug` (`slug`),
    INDEX `idx_author` (`author_id`),
    INDEX `idx_published` (`is_published`),
    INDEX `idx_published_created` (`is_published`, `created_at`),
    FULLTEXT INDEX `ft_title_content` (`title`, `content`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- COMMENTS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `comments` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `post_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NULL,
    `parent_id` INT UNSIGNED NULL,
    `content` TEXT NOT NULL,
    `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    `ip_address` VARCHAR(45) NULL,
    `user_agent` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_comments_post` FOREIGN KEY (`post_id`) REFERENCES `posts`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_comments_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_comments_parent` FOREIGN KEY (`parent_id`) REFERENCES `comments`(`id`) ON DELETE CASCADE,
    INDEX `idx_post_id` (`post_id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_parent_id` (`parent_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TAGS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `tags` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `description` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- POST_TAGS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `post_tags` (
    `post_id` INT UNSIGNED NOT NULL,
    `tag_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`post_id`, `tag_id`),
    CONSTRAINT `fk_post_tags_post` FOREIGN KEY (`post_id`) REFERENCES `posts`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_post_tags_tag` FOREIGN KEY (`tag_id`) REFERENCES `tags`(`id`) ON DELETE CASCADE,
    INDEX `idx_tag_id` (`tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- RATINGS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `ratings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `post_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NULL,
    `value` TINYINT NOT NULL,
    `ip_address` VARCHAR(45) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_ratings_post` FOREIGN KEY (`post_id`) REFERENCES `posts`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ratings_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    UNIQUE KEY `unique_user_post_rating` (`post_id`, `user_id`),
    INDEX `idx_post_id` (`post_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- LOGS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `logs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NULL,
    `action` VARCHAR(100) NOT NULL,
    `entity_type` VARCHAR(50) NULL,
    `entity_id` INT UNSIGNED NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` VARCHAR(255) NULL,
    `details` JSON NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_action` (`action`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- LOGIN_ATTEMPTS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ip_address` VARCHAR(45) NOT NULL,
    `email` VARCHAR(255) NULL,
    `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
    `last_attempt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `locked_until` TIMESTAMP NULL,
    INDEX `idx_ip` (`ip_address`),
    INDEX `idx_locked` (`locked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SESSIONS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `sessions` (
    `id` VARCHAR(128) PRIMARY KEY,
    `user_id` INT UNSIGNED NULL,
    `ip_address` VARCHAR(45) NULL,
    `payload` TEXT NOT NULL,
    `last_activity` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_last_activity` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- PASSWORD_RESETS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `password_resets` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL,
    `token` VARCHAR(255) NOT NULL UNIQUE,
    `expires_at` TIMESTAMP NOT NULL,
    `used` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
