-- =====================================================================
-- Database Seed & Telemetry Migration v3: High-Impact Flood Regions
-- Bihar, Assam, Maharashtra, and Odisha Telemetry & Operational Camps
-- =====================================================================

USE `flood_relief_db`;

-- 1. Schema Enhancements: Compatibility Columns
ALTER TABLE `zones` 
  ADD COLUMN IF NOT EXISTS `zone_name` VARCHAR(150) NULL AFTER `name`,
  ADD COLUMN IF NOT EXISTS `river_basin` VARCHAR(100) NULL AFTER `longitude`,
  ADD COLUMN IF NOT EXISTS `warning_level_m` DECIMAL(6,2) NULL AFTER `river_basin`,
  ADD COLUMN IF NOT EXISTS `danger_level_m` DECIMAL(6,2) NULL AFTER `warning_level_m`,
  ADD COLUMN IF NOT EXISTS `current_water_level_m` DECIMAL(6,2) NULL AFTER `danger_level_m`,
  ADD COLUMN IF NOT EXISTS `rainfall_24h_mm` DECIMAL(6,2) NULL AFTER `current_water_level_m`,
  ADD COLUMN IF NOT EXISTS `risk_level` VARCHAR(50) NULL AFTER `rainfall_24h_mm`;

ALTER TABLE `zones` MODIFY COLUMN `name` VARCHAR(100) NOT NULL DEFAULT '';

ALTER TABLE `camps` 
  ADD COLUMN IF NOT EXISTS `camp_name` VARCHAR(150) NULL AFTER `name`,
  MODIFY COLUMN `status` ENUM('Operational','Full','Standby','Closed','Active') DEFAULT 'Operational',
  MODIFY COLUMN `name` VARCHAR(150) NOT NULL DEFAULT '';

-- Triggers for Name / Zone_Name Synchronization
DROP TRIGGER IF EXISTS `trg_zones_before_insert`;
DELIMITER $$
CREATE TRIGGER `trg_zones_before_insert` BEFORE INSERT ON `zones`
FOR EACH ROW
BEGIN
    IF (NEW.name IS NULL OR NEW.name = '') AND (NEW.zone_name IS NOT NULL AND NEW.zone_name != '') THEN
        SET NEW.name = SUBSTRING(NEW.zone_name, 1, 100);
    END IF;
    IF (NEW.zone_name IS NULL OR NEW.zone_name = '') AND (NEW.name IS NOT NULL AND NEW.name != '') THEN
        SET NEW.zone_name = NEW.name;
    END IF;
    IF NEW.district LIKE '%, Bihar' THEN
        SET NEW.district = TRIM(REPLACE(NEW.district, ', Bihar', ''));
        SET NEW.state = 'Bihar';
    ELSEIF NEW.district LIKE '%, Assam' THEN
        SET NEW.district = TRIM(REPLACE(NEW.district, ', Assam', ''));
        SET NEW.state = 'Assam';
    ELSEIF NEW.district LIKE '%, Maharashtra' THEN
        SET NEW.district = TRIM(REPLACE(NEW.district, ', Maharashtra', ''));
        SET NEW.state = 'Maharashtra';
    ELSEIF NEW.district LIKE '%, Odisha' THEN
        SET NEW.district = TRIM(REPLACE(NEW.district, ', Odisha', ''));
        SET NEW.state = 'Odisha';
    END IF;
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_camps_before_insert`;
DELIMITER $$
CREATE TRIGGER `trg_camps_before_insert` BEFORE INSERT ON `camps`
FOR EACH ROW
BEGIN
    IF (NEW.name IS NULL OR NEW.name = '') AND (NEW.camp_name IS NOT NULL AND NEW.camp_name != '') THEN
        SET NEW.name = SUBSTRING(NEW.camp_name, 1, 150);
    END IF;
    IF (NEW.camp_name IS NULL OR NEW.camp_name = '') AND (NEW.name IS NOT NULL AND NEW.name != '') THEN
        SET NEW.camp_name = NEW.name;
    END IF;
    IF NEW.status = 'Active' THEN
        SET NEW.status = 'Operational';
    END IF;
END$$
DELIMITER ;

-- 2. Add / Sync River Basins in `rivers` table
INSERT INTO `rivers` (`id`, `name`, `basin_name`, `states_covered`, `warning_level_meters`, `danger_mark_meters`, `current_level_meters`, `status`)
VALUES 
(9, 'Kosi River', 'Kosi Basin', 'Bihar', 48.00, 50.50, 51.20, 'Danger'),
(10, 'Panchganga River', 'Krishna-Panchganga Basin', 'Maharashtra', 39.00, 43.00, 37.50, 'Normal')
ON DUPLICATE KEY UPDATE 
  `warning_level_meters` = VALUES(`warning_level_meters`),
  `danger_mark_meters` = VALUES(`danger_mark_meters`),
  `current_level_meters` = VALUES(`current_level_meters`),
  `status` = VALUES(`status`);

UPDATE `rivers` SET `current_level_meters` = 50.20, `status` = 'Warning' WHERE `id` = 6; -- Brahmaputra
UPDATE `rivers` SET `current_level_meters` = 29.10, `status` = 'Danger' WHERE `id` = 7;  -- Mahanadi

-- 3. High-Risk River Basins & Zones
-- Clean existing matching zones if already present to prevent duplicates
DELETE FROM `zones` WHERE `zone_name` IN (
  'Supaul Kosi Flood Basin',
  'Guwahati Brahmaputra Sector',
  'Sangli Krishna Floodplain',
  'Kolhapur Panchganga Basin',
  'Cuttack Mahanadi Delta'
) OR `name` IN (
  'Supaul Kosi Flood Basin',
  'Guwahati Brahmaputra Sector',
  'Sangli Krishna Floodplain',
  'Kolhapur Panchganga Basin',
  'Cuttack Mahanadi Delta'
);

INSERT INTO `zones` (`name`, `zone_name`, `district`, `state`, `latitude`, `longitude`, `river_basin`, `river_id`, `primary_dam_id`, `warning_level_m`, `danger_level_m`, `current_water_level_m`, `rainfall_24h_mm`, `risk_level`, `status`) 
VALUES
-- Bihar (Kosi River - Notorious annual flash floods)
('Supaul Kosi Flood Basin', 'Supaul Kosi Flood Basin', 'Supaul', 'Bihar', 26.1260000, 86.6050000, 'Kosi River', 9, NULL, 48.00, 50.50, 51.20, 165.00, 'Critical', 'active'),

-- Assam (Brahmaputra Valley - Severe annual riverine flooding)
('Guwahati Brahmaputra Sector', 'Guwahati Brahmaputra Sector', 'Kamrup', 'Assam', 26.1850000, 91.7450000, 'Brahmaputra', 6, NULL, 49.50, 51.00, 50.20, 110.00, 'High', 'active'),

-- Western Maharashtra (Krishna & Panchganga Basins - Regular dam discharge inundation)
('Sangli Krishna Floodplain', 'Sangli Krishna Floodplain', 'Sangli', 'Maharashtra', 16.8524000, 74.5815000, 'Krishna River', 2, 2, 14.50, 16.00, 13.80, 75.00, 'Moderate', 'active'),
('Kolhapur Panchganga Basin', 'Kolhapur Panchganga Basin', 'Kolhapur', 'Maharashtra', 16.7050000, 74.2433000, 'Panchganga', 10, NULL, 39.00, 43.00, 37.50, 45.00, 'Low', 'active'),

-- Odisha (Mahanadi Delta - Cyclone & heavy monsoon runoff)
('Cuttack Mahanadi Delta', 'Cuttack Mahanadi Delta', 'Cuttack', 'Odisha', 20.4625000, 85.8830000, 'Mahanadi', 7, 6, 26.00, 28.50, 29.10, 185.00, 'Critical', 'active');

-- 4. Hydrological Risk Levels Observations
INSERT INTO `risk_levels` (`zone_id`, `rainfall_value`, `river_level_value`, `score`, `category`, `source`, `observed_at`)
SELECT z.id, z.rainfall_24h_mm, z.current_water_level_m,
       CASE z.risk_level
           WHEN 'Critical' THEN 94.50
           WHEN 'High' THEN 72.80
           WHEN 'Moderate' THEN 48.50
           ELSE 22.00
       END AS score,
       z.risk_level AS category,
       'IMD & CWC Hydrology Ingestion (Seed v3)',
       NOW()
FROM `zones` z
WHERE z.name IN (
  'Supaul Kosi Flood Basin',
  'Guwahati Brahmaputra Sector',
  'Sangli Krishna Floodplain',
  'Kolhapur Panchganga Basin',
  'Cuttack Mahanadi Delta'
);

-- 5. Designated Operational Relief Camps
-- Clean existing matching camps if already present
DELETE FROM `camps` WHERE `camp_name` IN (
  'Supaul District High School Shelter',
  'Birpur Flood Evacuation Center',
  'Guwahati Sarusajai Indoor Camp',
  'Kaziranga Wildlife Corridor Shelter',
  'Sangli Miraj Municipal Hall Shelter',
  'Kolhapur Gandhi Maidan Relief Camp',
  'Cuttack Ravenshaw Cyclone Center'
) OR `name` IN (
  'Supaul District High School Shelter',
  'Birpur Flood Evacuation Center',
  'Guwahati Sarusajai Indoor Camp',
  'Kaziranga Wildlife Corridor Shelter',
  'Sangli Miraj Municipal Hall Shelter',
  'Kolhapur Gandhi Maidan Relief Camp',
  'Cuttack Ravenshaw Cyclone Center'
);

-- Insert with precise zone_id foreign key linkage
-- Bihar Shelters (Linked to Supaul Kosi Flood Basin)
INSERT INTO `camps` (`zone_id`, `name`, `camp_name`, `address`, `district`, `latitude`, `longitude`, `total_capacity`, `current_occupancy`, `contact_person`, `contact_phone`, `status`, `active`)
SELECT z.id, 
       'Supaul District High School Shelter', 
       'Supaul District High School Shelter', 
       'Near Gandhi Maidan, Supaul, Bihar', 
       'Supaul', 
       26.1290000, 86.6090000, 800, 720, 
       'Rameshwar Jha (Circle Officer)', '9835012345', 'Operational', 1
FROM `zones` z WHERE z.name = 'Supaul Kosi Flood Basin' LIMIT 1;

INSERT INTO `camps` (`zone_id`, `name`, `camp_name`, `address`, `district`, `latitude`, `longitude`, `total_capacity`, `current_occupancy`, `contact_person`, `contact_phone`, `status`, `active`)
SELECT z.id, 
       'Birpur Flood Evacuation Center', 
       'Birpur Flood Evacuation Center', 
       'Birpur Sub-division, Supaul', 
       'Supaul', 
       26.5180000, 87.0180000, 500, 500, 
       'Sanjay Kumar (Disaster Incharge)', '9835098765', 'Full', 1
FROM `zones` z WHERE z.name = 'Supaul Kosi Flood Basin' LIMIT 1;

-- Assam Shelters (Linked to Guwahati Brahmaputra Sector)
INSERT INTO `camps` (`zone_id`, `name`, `camp_name`, `address`, `district`, `latitude`, `longitude`, `total_capacity`, `current_occupancy`, `contact_person`, `contact_phone`, `status`, `active`)
SELECT z.id, 
       'Guwahati Sarusajai Indoor Camp', 
       'Guwahati Sarusajai Indoor Camp', 
       'National Highway 37, Guwahati', 
       'Kamrup', 
       26.1890000, 91.7510000, 1200, 850, 
       'Hemanta Sarma (Camp Manager)', '9435011223', 'Operational', 1
FROM `zones` z WHERE z.name = 'Guwahati Brahmaputra Sector' LIMIT 1;

INSERT INTO `camps` (`zone_id`, `name`, `camp_name`, `address`, `district`, `latitude`, `longitude`, `total_capacity`, `current_occupancy`, `contact_person`, `contact_phone`, `status`, `active`)
SELECT z.id, 
       'Kaziranga Wildlife Corridor Shelter', 
       'Kaziranga Wildlife Corridor Shelter', 
       'Kohora Range, Assam', 
       'Kamrup', 
       26.5850000, 93.4110000, 400, 180, 
       'Bipul Gogoi', '9435044556', 'Operational', 1
FROM `zones` z WHERE z.name = 'Guwahati Brahmaputra Sector' LIMIT 1;

-- Maharashtra Shelters
INSERT INTO `camps` (`zone_id`, `name`, `camp_name`, `address`, `district`, `latitude`, `longitude`, `total_capacity`, `current_occupancy`, `contact_person`, `contact_phone`, `status`, `active`)
SELECT z.id, 
       'Sangli Miraj Municipal Hall Shelter', 
       'Sangli Miraj Municipal Hall Shelter', 
       'Near Civil Hospital, Sangli', 
       'Sangli', 
       16.8560000, 74.5880000, 600, 320, 
       'Santosh Patil (Tahsildar)', '9822011223', 'Operational', 1
FROM `zones` z WHERE z.name = 'Sangli Krishna Floodplain' LIMIT 1;

INSERT INTO `camps` (`zone_id`, `name`, `camp_name`, `address`, `district`, `latitude`, `longitude`, `total_capacity`, `current_occupancy`, `contact_person`, `contact_phone`, `status`, `active`)
SELECT z.id, 
       'Kolhapur Gandhi Maidan Relief Camp', 
       'Kolhapur Gandhi Maidan Relief Camp', 
       'Bavda Road, Kolhapur', 
       'Kolhapur', 
       16.7110000, 74.2480000, 750, 200, 
       'Sachin Desai', '9822055667', 'Operational', 1
FROM `zones` z WHERE z.name = 'Kolhapur Panchganga Basin' LIMIT 1;

-- Odisha Shelters (Linked to Cuttack Mahanadi Delta)
INSERT INTO `camps` (`zone_id`, `name`, `camp_name`, `address`, `district`, `latitude`, `longitude`, `total_capacity`, `current_occupancy`, `contact_person`, `contact_phone`, `status`, `active`)
SELECT z.id, 
       'Cuttack Ravenshaw Cyclone Center', 
       'Cuttack Ravenshaw Cyclone Center', 
       'College Square, Cuttack', 
       'Cuttack', 
       20.4650000, 85.8880000, 1000, 980, 
       'Debasish Mohanty', '9437012345', 'Operational', 1
FROM `zones` z WHERE z.name = 'Cuttack Mahanadi Delta' LIMIT 1;
