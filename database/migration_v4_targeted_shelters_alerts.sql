-- =====================================================================
-- Database Migration v4: Targeted Citizen Registration, Dam-Linked Shelters,
-- and Geofenced Emergency Alert Email Pipeline
-- =====================================================================

USE `flood_relief_db`;

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Create `river_basins` Table
CREATE TABLE IF NOT EXISTS `river_basins` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `river_id` INT NULL,
    `description` TEXT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_basin_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Populate `river_basins` from existing `rivers` table
INSERT IGNORE INTO `river_basins` (`id`, `name`, `river_id`, `description`)
SELECT `id`, `basin_name`, `id`, CONCAT(`name`, ' catchment and drainage basin') 
FROM `rivers`;

-- 2. Enhance `users` Table for Geofenced Targeting
ALTER TABLE `users`
  MODIFY COLUMN `email` VARCHAR(150) NOT NULL,
  ADD COLUMN IF NOT EXISTS `district` VARCHAR(100) NULL AFTER `phone`,
  ADD COLUMN IF NOT EXISTS `river_basin_id` INT NULL AFTER `district`,
  ADD COLUMN IF NOT EXISTS `monitored_dam_id` INT NULL AFTER `river_basin_id`,
  ADD COLUMN IF NOT EXISTS `address` TEXT NULL AFTER `monitored_dam_id`;

-- Add foreign key constraints to `users` if not already present
-- (Using conditional check via stored procedure or direct safe alter)

-- 3. Enhance `camps` Table with Dam and Basin Associations
ALTER TABLE `camps`
  ADD COLUMN IF NOT EXISTS `dam_id` INT NULL AFTER `zone_id`,
  ADD COLUMN IF NOT EXISTS `river_basin_id` INT NULL AFTER `dam_id`,
  ADD COLUMN IF NOT EXISTS `supply_status` ENUM('Abundant', 'Sufficient', 'Critical', 'Replenishment Needed') DEFAULT 'Sufficient' AFTER `status`;

-- Ensure UNIQUE index on camp name so seeding is 100% idempotent
ALTER TABLE `camps` ADD UNIQUE INDEX IF NOT EXISTS `idx_camps_name_uniq` (`name`);

-- 4. Create or replace `relief_camps` View for 100% Schema Compatibility
CREATE OR REPLACE VIEW `relief_camps` AS 
SELECT c.*, 
       c.id AS camp_id,
       COALESCE(c.camp_name, c.name) AS display_name,
       (c.total_capacity - c.current_occupancy) AS available_beds
FROM `camps` c;

-- 5. Seed / Update Dedicated Primary Relief Shelters for Key Maharashtra Dams
-- 5.1 Gangapur Dam (dam_id: 9, river_basin_id: 1) -> Nashik Municipal Relief Shelter
INSERT INTO `camps` (`name`, `camp_name`, `address`, `district`, `latitude`, `longitude`, `contact_person`, `contact_phone`, `total_capacity`, `current_occupancy`, `status`, `supply_status`, `dam_id`, `river_basin_id`, `active`)
VALUES (
    'Nashik Municipal Relief Shelter',
    'Nashik Municipal Relief Shelter',
    'Near Gangapur Dam Road, Anandwalli, Nashik, Maharashtra',
    'Nashik',
    19.9985000,
    73.7250000,
    'Sunil Gaikwad (Tehsildar / Relief Incharge)',
    '+91 94231 88765',
    600,
    210,
    'Operational',
    'Sufficient',
    9,
    1,
    1
)
ON DUPLICATE KEY UPDATE 
    `camp_name` = VALUES(`camp_name`),
    `address` = VALUES(`address`),
    `district` = VALUES(`district`),
    `latitude` = VALUES(`latitude`),
    `longitude` = VALUES(`longitude`),
    `dam_id` = VALUES(`dam_id`),
    `river_basin_id` = VALUES(`river_basin_id`),
    `supply_status` = VALUES(`supply_status`);

-- 5.2 Bhandardara Dam (dam_id: 11, river_basin_id: 1) -> Akole / Sangamner Emergency Hall
INSERT INTO `camps` (`name`, `camp_name`, `address`, `district`, `latitude`, `longitude`, `contact_person`, `contact_phone`, `total_capacity`, `current_occupancy`, `status`, `supply_status`, `dam_id`, `river_basin_id`, `active`)
VALUES (
    'Akole / Sangamner Emergency Hall',
    'Akole / Sangamner Emergency Hall',
    'Pravara Riverbank Road, Akole Sub-Division, Ahilyanagar, Maharashtra',
    'Ahilyanagar',
    19.5412000,
    73.9920000,
    'Dr. Rameshwar Tambe (Sub-Divisional Officer)',
    '+91 98224 44551',
    450,
    120,
    'Operational',
    'Abundant',
    11,
    1,
    1
)
ON DUPLICATE KEY UPDATE 
    `camp_name` = VALUES(`camp_name`),
    `address` = VALUES(`address`),
    `district` = VALUES(`district`),
    `latitude` = VALUES(`latitude`),
    `longitude` = VALUES(`longitude`),
    `dam_id` = VALUES(`dam_id`),
    `river_basin_id` = VALUES(`river_basin_id`),
    `supply_status` = VALUES(`supply_status`);

-- 5.3 Jayakwadi Dam (dam_id: 1, river_basin_id: 1) -> Paithan / Chhatrapati Sambhajinagar Shelter
INSERT INTO `camps` (`name`, `camp_name`, `address`, `district`, `latitude`, `longitude`, `contact_person`, `contact_phone`, `total_capacity`, `current_occupancy`, `status`, `supply_status`, `dam_id`, `river_basin_id`, `active`)
VALUES (
    'Paithan / Chhatrapati Sambhajinagar Shelter',
    'Paithan / Chhatrapati Sambhajinagar Shelter',
    'Sant Eknath Rangmandir Compound, Paithan, Chhatrapati Sambhajinagar, Maharashtra',
    'Chhatrapati Sambhajinagar',
    19.4890000,
    75.3810000,
    'Anil Deshmukh (Municipal Chief Officer)',
    '+91 94222 33110',
    1000,
    410,
    'Operational',
    'Sufficient',
    1,
    1,
    1
)
ON DUPLICATE KEY UPDATE 
    `camp_name` = VALUES(`camp_name`),
    `address` = VALUES(`address`),
    `district` = VALUES(`district`),
    `latitude` = VALUES(`latitude`),
    `longitude` = VALUES(`longitude`),
    `dam_id` = VALUES(`dam_id`),
    `river_basin_id` = VALUES(`river_basin_id`),
    `supply_status` = VALUES(`supply_status`);

-- 5.4 Koyna Dam (dam_id: 2, river_basin_id: 2) -> Karad / Satara Relief Camp
INSERT INTO `camps` (`name`, `camp_name`, `address`, `district`, `latitude`, `longitude`, `contact_person`, `contact_phone`, `total_capacity`, `current_occupancy`, `status`, `supply_status`, `dam_id`, `river_basin_id`, `active`)
VALUES (
    'Karad / Satara Relief Camp',
    'Karad / Satara Relief Camp',
    'Karad Town Hall Evacuation Ground, NH 48, Satara District, Maharashtra',
    'Satara',
    17.2885000,
    74.1840000,
    'Vijayrao Patil (Disaster Response Cell)',
    '+91 98500 77123',
    800,
    280,
    'Operational',
    'Abundant',
    2,
    2,
    1
)
ON DUPLICATE KEY UPDATE 
    `camp_name` = VALUES(`camp_name`),
    `address` = VALUES(`address`),
    `district` = VALUES(`district`),
    `latitude` = VALUES(`latitude`),
    `longitude` = VALUES(`longitude`),
    `dam_id` = VALUES(`dam_id`),
    `river_basin_id` = VALUES(`river_basin_id`),
    `supply_status` = VALUES(`supply_status`);

-- 5.5 Ujani Dam (dam_id: 14, river_basin_id: 11) -> Daund / Pandharpur Community Shelter
INSERT INTO `camps` (`name`, `camp_name`, `address`, `district`, `latitude`, `longitude`, `contact_person`, `contact_phone`, `total_capacity`, `current_occupancy`, `status`, `supply_status`, `dam_id`, `river_basin_id`, `active`)
VALUES (
    'Daund / Pandharpur Community Shelter',
    'Daund / Pandharpur Community Shelter',
    'Bhima River Flood Relief Pavilion, Pandharpur Road, Solapur District, Maharashtra',
    'Solapur',
    17.6780000,
    75.3250000,
    'Sandeep Shinde (Circle Inspector)',
    '+91 94230 99882',
    750,
    190,
    'Operational',
    'Sufficient',
    14,
    11,
    1
)
ON DUPLICATE KEY UPDATE 
    `camp_name` = VALUES(`camp_name`),
    `address` = VALUES(`address`),
    `district` = VALUES(`district`),
    `latitude` = VALUES(`latitude`),
    `longitude` = VALUES(`longitude`),
    `dam_id` = VALUES(`dam_id`),
    `river_basin_id` = VALUES(`river_basin_id`),
    `supply_status` = VALUES(`supply_status`);

-- 5.6 Hatnur Dam (dam_id: 18, river_basin_id: 12) -> Jalgaon Flood Relief Camp
INSERT INTO `camps` (`name`, `camp_name`, `address`, `district`, `latitude`, `longitude`, `contact_person`, `contact_phone`, `total_capacity`, `current_occupancy`, `status`, `supply_status`, `dam_id`, `river_basin_id`, `active`)
VALUES (
    'Jalgaon Flood Relief Camp',
    'Jalgaon Flood Relief Camp',
    'Tapi Flood Evacuation Center, Near Civil Hospital, Jalgaon, Maharashtra',
    'Jalgaon',
    21.0120000,
    75.5680000,
    'Mahesh Chaudhari (Disaster Management Cell)',
    '+91 98233 11244',
    550,
    140,
    'Operational',
    'Sufficient',
    18,
    12,
    1
)
ON DUPLICATE KEY UPDATE 
    `camp_name` = VALUES(`camp_name`),
    `address` = VALUES(`address`),
    `district` = VALUES(`district`),
    `latitude` = VALUES(`latitude`),
    `longitude` = VALUES(`longitude`),
    `dam_id` = VALUES(`dam_id`),
    `river_basin_id` = VALUES(`river_basin_id`),
    `supply_status` = VALUES(`supply_status`);

-- 6. Link other pre-existing shelters to nearby dams / basins if unlinked
UPDATE `camps` SET `dam_id` = 2, `river_basin_id` = 2 WHERE `name` LIKE '%Sangli%' AND (`dam_id` IS NULL OR `dam_id` = 0);
UPDATE `camps` SET `river_basin_id` = 9 WHERE `name` LIKE '%Supaul%' OR `name` LIKE '%Birpur%';
UPDATE `camps` SET `river_basin_id` = 6 WHERE `name` LIKE '%Guwahati%' OR `name` LIKE '%Kaziranga%';
UPDATE `camps` SET `river_basin_id` = 7 WHERE `name` LIKE '%Cuttack%' OR `name` LIKE '%Mahanadi%';

-- 7. Seed Sample Registered Citizens under Maharashtra Dam Watersheds for Targeted Alert Testing
INSERT INTO `users` (`id`, `role_id`, `full_name`, `email`, `phone`, `password_hash`, `district`, `river_basin_id`, `monitored_dam_id`, `address`, `status`)
VALUES 
(101, 3, 'Pooja Kulkarni', 'pooja.kulkarni@example.com', '9822101010', '$2y$10$wT0lVl8dF9L4C3gL7.x3gO7b/Vv2A0FpG9X6f5B4d1C0e8a7b6c5d', 'Nashik', 1, 9, 'Gangapur Road, Anandwalli, Nashik', 'active'),
(102, 3, 'Amol Deshmukh', 'amol.deshmukh@example.com', '9822202020', '$2y$10$wT0lVl8dF9L4C3gL7.x3gO7b/Vv2A0FpG9X6f5B4d1C0e8a7b6c5d', 'Ahilyanagar', 1, 11, 'Pravara Colony, Akole, Ahilyanagar', 'active'),
(103, 3, 'Sachin Patil', 'sachin.patil@example.com', '9822303030', '$2y$10$wT0lVl8dF9L4C3gL7.x3gO7b/Vv2A0FpG9X6f5B4d1C0e8a7b6c5d', 'Chhatrapati Sambhajinagar', 1, 1, 'Near Paithan Gate, Chhatrapati Sambhajinagar', 'active'),
(104, 3, 'Ramesh Jadhav', 'ramesh.jadhav@example.com', '9822404040', '$2y$10$wT0lVl8dF9L4C3gL7.x3gO7b/Vv2A0FpG9X6f5B4d1C0e8a7b6c5d', 'Satara', 2, 2, 'Malkapur Road, Karad, Satara', 'active'),
(105, 3, 'Nitin Shinde', 'nitin.shinde@example.com', '9822505050', '$2y$10$wT0lVl8dF9L4C3gL7.x3gO7b/Vv2A0FpG9X6f5B4d1C0e8a7b6c5d', 'Solapur', 11, 14, 'Station Road, Pandharpur, Solapur', 'active'),
(106, 3, 'Ganesh Chaudhari', 'ganesh.chaudhari@example.com', '9822606060', '$2y$10$wT0lVl8dF9L4C3gL7.x3gO7b/Vv2A0FpG9X6f5B4d1C0e8a7b6c5d', 'Jalgaon', 12, 18, 'Civil Lines, Jalgaon', 'active')
ON DUPLICATE KEY UPDATE 
    `district` = VALUES(`district`),
    `river_basin_id` = VALUES(`river_basin_id`),
    `monitored_dam_id` = VALUES(`monitored_dam_id`),
    `address` = VALUES(`address`);

-- Sync citizen_profiles for seeded citizens
INSERT INTO `citizen_profiles` (`user_id`, `saved_village_city`, `saved_river_id`, `saved_dam_id`, `alert_notifications_enabled`)
VALUES
(101, 'Gangapur Road, Nashik', 1, 9, 1),
(102, 'Pravara Colony, Akole', 1, 11, 1),
(103, 'Paithan Gate, Sambhajinagar', 1, 1, 1),
(104, 'Malkapur Road, Karad', 2, 2, 1),
(105, 'Station Road, Pandharpur', 11, 14, 1),
(106, 'Civil Lines, Jalgaon', 12, 18, 1)
ON DUPLICATE KEY UPDATE 
    `saved_village_city` = VALUES(`saved_village_city`),
    `saved_river_id` = VALUES(`saved_river_id`),
    `saved_dam_id` = VALUES(`saved_dam_id`);

SET FOREIGN_KEY_CHECKS = 1;
