-- ============================================================================
-- Flood Risk Prediction & Relief Camp Locator
-- Database Migration v2.0 (National Rivers, Dams, Citizen SOS & Missing Persons)
-- ============================================================================

USE `flood_relief_db`;

SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 1. RIVERS TABLE
-- Key flood-prone Indian river basins and water level monitoring marks
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `rivers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `basin_name` VARCHAR(100) NOT NULL,
    `states_covered` VARCHAR(255) NOT NULL,
    `warning_level_meters` DECIMAL(6, 2) NOT NULL,
    `danger_mark_meters` DECIMAL(6, 2) NOT NULL,
    `current_level_meters` DECIMAL(6, 2) NOT NULL,
    `status` ENUM('Normal', 'Warning', 'Danger') DEFAULT 'Normal',
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_river_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2. DAMS TABLE
-- Major reservoirs, gross storage capacity (TMC), live storage %, and discharge
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `dams` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `river_id` INT NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `state` VARCHAR(100) NOT NULL,
    `district` VARCHAR(100) NOT NULL,
    `gross_capacity_tmc` DECIMAL(8, 2) NOT NULL,
    `current_storage_tmc` DECIMAL(8, 2) NOT NULL,
    `live_storage_pct` DECIMAL(5, 2) NOT NULL,
    `water_level_meters` DECIMAL(6, 2) NOT NULL,
    `fsl_meters` DECIMAL(6, 2) NOT NULL COMMENT 'Full Supply Level',
    `inflow_cusecs` INT NOT NULL DEFAULT 0,
    `outflow_cusecs` INT NOT NULL DEFAULT 0 COMMENT 'Discharge / release rate',
    `gate_status` VARCHAR(150) DEFAULT 'All Gates Closed',
    `latitude` DECIMAL(10, 7) NOT NULL,
    `longitude` DECIMAL(10, 7) NOT NULL,
    `last_updated` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_dams_river` FOREIGN KEY (`river_id`) REFERENCES `rivers` (`id`) ON DELETE CASCADE,
    INDEX `idx_dam_river` (`river_id`),
    INDEX `idx_dam_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 3. CITIZEN PROFILES TABLE
-- Bookmarked village, nearby river, and upstream dam for personalized watch
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `citizen_profiles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL UNIQUE,
    `saved_village_city` VARCHAR(150) NULL,
    `saved_river_id` INT NULL,
    `saved_dam_id` INT NULL,
    `alert_notifications_enabled` TINYINT(1) DEFAULT 1,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_citizen_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_citizen_river` FOREIGN KEY (`saved_river_id`) REFERENCES `rivers` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_citizen_dam` FOREIGN KEY (`saved_dam_id`) REFERENCES `dams` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 4. MISSING & FOUND PERSONS REGISTRY TABLE
-- Public community directory for flood rescue and missing family tracing
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `missing_persons` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `report_type` ENUM('Missing', 'Found') NOT NULL DEFAULT 'Missing',
    `person_name` VARCHAR(120) NOT NULL,
    `gender` ENUM('Male', 'Female', 'Child', 'Other') NOT NULL,
    `age` INT NULL,
    `last_seen_or_found_location` VARCHAR(255) NOT NULL,
    `last_seen_or_found_date` DATE NOT NULL,
    `photo_url` VARCHAR(255) NULL,
    `contact_name` VARCHAR(120) NOT NULL,
    `contact_phone` VARCHAR(20) NOT NULL,
    `contact_relation` VARCHAR(100) NULL,
    `camp_id` INT NULL COMMENT 'Set if currently accommodated at a relief camp',
    `status` ENUM('Reported', 'Verified', 'Reunited', 'Closed') DEFAULT 'Reported',
    `reported_by_user_id` INT NULL,
    `notes` TEXT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_missing_camp` FOREIGN KEY (`camp_id`) REFERENCES `camps` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_missing_user` FOREIGN KEY (`reported_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    INDEX `idx_missing_status` (`status`, `report_type`),
    INDEX `idx_missing_name` (`person_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 5. SIMULATION LOGS TABLE
-- Records administrator viva simulation runs and parameter triggers
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `simulation_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `triggered_by` INT NOT NULL,
    `dam_id` INT NOT NULL,
    `simulated_rainfall_mm` DECIMAL(6, 2) NOT NULL,
    `simulated_discharge_cusecs` INT NOT NULL,
    `action` ENUM('Triggered', 'Reset') NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_sim_user` FOREIGN KEY (`triggered_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sim_dam` FOREIGN KEY (`dam_id`) REFERENCES `dams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 6. ALTER EXISTING TABLES
-- ----------------------------------------------------------------------------
-- Enhance help_requests with water level severity (Ankle, Waist, Roof-Level) and verification
ALTER TABLE `help_requests` 
    ADD COLUMN IF NOT EXISTS `water_severity` ENUM('Ankle-Level', 'Waist-Level', 'Roof-Level', 'Dry/Threatened') NOT NULL DEFAULT 'Waist-Level' AFTER `urgency`,
    ADD COLUMN IF NOT EXISTS `mobile_verified` TINYINT(1) DEFAULT 1 AFTER `guest_phone`;

-- Enhance zones with river basin and primary upstream dam associations
ALTER TABLE `zones`
    ADD COLUMN IF NOT EXISTS `river_id` INT NULL AFTER `boundary_geojson`,
    ADD COLUMN IF NOT EXISTS `primary_dam_id` INT NULL AFTER `river_id`;

SET FOREIGN_KEY_CHECKS = 1;
