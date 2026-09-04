CREATE TABLE IF NOT EXISTS `jyp_profiles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(191) NOT NULL,
  `source_locale` VARCHAR(12) NOT NULL DEFAULT 'en',
  `status` VARCHAR(20) NOT NULL DEFAULT 'draft',
  `photo_url` VARCHAR(2048) DEFAULT NULL,
  `public_email` VARCHAR(254) DEFAULT NULL,
  `staff_identifier` VARCHAR(100) DEFAULT NULL,
  `display_order` INT NOT NULL DEFAULT 100,
  `version` INT UNSIGNED NOT NULL DEFAULT 1,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `updated_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `jyp_profiles_slug_unique` (`slug`),
  KEY `jyp_profiles_public_order` (`status`, `display_order`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `jyp_profile_texts` (
  `profile_id` BIGINT UNSIGNED NOT NULL,
  `locale` VARCHAR(12) NOT NULL,
  `display_name` VARCHAR(191) NOT NULL,
  `credentials` VARCHAR(191) DEFAULT NULL,
  `position_title` VARCHAR(191) DEFAULT NULL,
  `organization_unit` VARCHAR(191) DEFAULT NULL,
  `headline` VARCHAR(500) DEFAULT NULL,
  `biography` TEXT DEFAULT NULL,
  `translation_status` VARCHAR(20) NOT NULL DEFAULT 'draft',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`profile_id`, `locale`),
  KEY `jyp_profile_texts_locale_status` (`locale`, `translation_status`),
  CONSTRAINT `jyp_profile_texts_profile_fk` FOREIGN KEY (`profile_id`) REFERENCES `jyp_profiles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `jyp_terms` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `taxonomy` VARCHAR(50) NOT NULL,
  `slug` VARCHAR(100) NOT NULL,
  `name` VARCHAR(191) NOT NULL,
  `display_order` INT NOT NULL DEFAULT 100,
  PRIMARY KEY (`id`),
  UNIQUE KEY `jyp_terms_taxonomy_slug_unique` (`taxonomy`, `slug`),
  KEY `jyp_terms_order` (`taxonomy`, `display_order`, `name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `jyp_profile_terms` (
  `profile_id` BIGINT UNSIGNED NOT NULL,
  `term_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`profile_id`, `term_id`),
  KEY `jyp_profile_terms_term` (`term_id`, `profile_id`),
  CONSTRAINT `jyp_profile_terms_profile_fk` FOREIGN KEY (`profile_id`) REFERENCES `jyp_profiles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jyp_profile_terms_term_fk` FOREIGN KEY (`term_id`) REFERENCES `jyp_terms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `jyp_links` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `profile_id` BIGINT UNSIGNED NOT NULL,
  `link_type` VARCHAR(50) NOT NULL,
  `label` VARCHAR(100) NOT NULL,
  `url` VARCHAR(2048) NOT NULL,
  `display_order` INT NOT NULL DEFAULT 100,
  `is_public` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `jyp_links_profile_order` (`profile_id`, `is_public`, `display_order`, `id`),
  CONSTRAINT `jyp_links_profile_fk` FOREIGN KEY (`profile_id`) REFERENCES `jyp_profiles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `jyp_entries` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `profile_id` BIGINT UNSIGNED NOT NULL,
  `entry_type` VARCHAR(50) NOT NULL,
  `year` SMALLINT UNSIGNED DEFAULT NULL,
  `started_on` DATE DEFAULT NULL,
  `ended_on` DATE DEFAULT NULL,
  `external_url` VARCHAR(2048) DEFAULT NULL,
  `display_order` INT NOT NULL DEFAULT 100,
  `status` VARCHAR(20) NOT NULL DEFAULT 'draft',
  PRIMARY KEY (`id`),
  KEY `jyp_entries_profile_type_year` (`profile_id`, `entry_type`, `status`, `year`, `display_order`),
  CONSTRAINT `jyp_entries_profile_fk` FOREIGN KEY (`profile_id`) REFERENCES `jyp_profiles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `jyp_entry_texts` (
  `entry_id` BIGINT UNSIGNED NOT NULL,
  `locale` VARCHAR(12) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `summary` TEXT DEFAULT NULL,
  `translation_status` VARCHAR(20) NOT NULL DEFAULT 'draft',
  PRIMARY KEY (`entry_id`, `locale`),
  KEY `jyp_entry_texts_locale_status` (`locale`, `translation_status`),
  CONSTRAINT `jyp_entry_texts_entry_fk` FOREIGN KEY (`entry_id`) REFERENCES `jyp_entries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
