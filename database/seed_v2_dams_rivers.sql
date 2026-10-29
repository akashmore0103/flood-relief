-- ============================================================================
-- Flood Risk Prediction & Relief Camp Locator
-- Telemetry Seed Data v2.0 (Major Indian Rivers & Dams)
-- ============================================================================

USE `flood_relief_db`;

SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 1. SEED MAJOR INDIAN RIVERS
-- ----------------------------------------------------------------------------
TRUNCATE TABLE `rivers`;
INSERT INTO `rivers` (`id`, `name`, `basin_name`, `states_covered`, `warning_level_meters`, `danger_mark_meters`, `current_level_meters`, `status`) VALUES
(1, 'Godavari River', 'Godavari Basin', 'Maharashtra, Telangana, Andhra Pradesh, Chhattisgarh', 14.50, 16.20, 15.35, 'Warning'),
(2, 'Krishna River', 'Krishna Basin', 'Maharashtra, Karnataka, Telangana, Andhra Pradesh', 52.00, 54.50, 53.60, 'Warning'),
(3, 'Ganga River', 'Ganga Basin', 'Uttarakhand, Uttar Pradesh, Bihar, West Bengal', 70.20, 71.30, 69.80, 'Normal'),
(4, 'Yamuna River', 'Yamuna Basin', 'Haryana, Delhi, Uttar Pradesh', 204.50, 205.30, 205.10, 'Warning'),
(5, 'Narmada River', 'Narmada Basin', 'Madhya Pradesh, Maharashtra, Gujarat', 28.00, 31.00, 27.40, 'Normal'),
(6, 'Brahmaputra River', 'Brahmaputra Basin', 'Arunachal Pradesh, Assam', 49.60, 50.50, 50.25, 'Warning'),
(7, 'Mahanadi River', 'Mahanadi Basin', 'Chhattisgarh, Odisha', 26.50, 27.40, 25.90, 'Normal'),
(8, 'Periyar River', 'Periyar Basin', 'Kerala, Tamil Nadu', 12.00, 13.50, 13.15, 'Warning');

-- ----------------------------------------------------------------------------
-- 2. SEED MAJOR RESERVOIRS & DAMS
-- ----------------------------------------------------------------------------
TRUNCATE TABLE `dams`;
INSERT INTO `dams` (`id`, `river_id`, `name`, `state`, `district`, `gross_capacity_tmc`, `current_storage_tmc`, `live_storage_pct`, `water_level_meters`, `fsl_meters`, `inflow_cusecs`, `outflow_cusecs`, `gate_status`, `latitude`, `longitude`, `last_updated`) VALUES
(1, 1, 'Jayakwadi Dam (Nath Sagar)', 'Maharashtra', 'Chhatrapati Sambhajinagar', 102.70, 92.40, 89.97, 463.50, 463.90, 38500, 42000, '18 Gates Opened at 1.5 ft', 19.4975000, 75.3888000, NOW()),
(2, 2, 'Koyna Dam (Shivsagar Lake)', 'Maharashtra', 'Satara', 105.20, 98.60, 93.72, 657.80, 659.43, 45000, 62500, '6 Radial Gates Raised 3.0 ft', 17.4000000, 73.7500000, NOW()),
(3, 2, 'Almatti Dam (Lal Bahadur Shastri Sagar)', 'Karnataka', 'Bagalkot', 123.08, 108.50, 88.15, 519.20, 519.60, 95000, 120000, '26 Crest Gates Discharging', 16.3312000, 75.8890000, NOW()),
(4, 3, 'Tehri Dam Reservoir', 'Uttarakhand', 'Tehri Garhwal', 148.00, 112.50, 76.01, 820.50, 830.00, 22000, 18500, 'Spillway Controlled Flow', 30.3789000, 78.4800000, NOW()),
(5, 5, 'Sardar Sarovar Dam', 'Gujarat', 'Narmada', 334.00, 295.20, 88.38, 136.80, 138.68, 85000, 115000, '23 Radial Gates Open at 1.8m', 21.8315000, 73.7483000, NOW()),
(6, 7, 'Hirakud Dam', 'Odisha', 'Sambalpur', 203.00, 168.40, 82.95, 191.50, 192.02, 72000, 95000, '12 Sluice Gates Open', 21.5700000, 83.8700000, NOW()),
(7, 2, 'Prakasam Barrage', 'Andhra Pradesh', 'Krishna (Vijayawada)', 3.07, 2.95, 96.09, 17.50, 17.39, 175000, 185000, '70 Gates Fully Raised', 16.5074000, 80.6053000, NOW()),
(8, 8, 'Idukki & Cheruthoni Reservoir', 'Kerala', 'Idukki', 70.50, 64.20, 91.06, 732.10, 732.40, 28000, 35000, 'Cheruthoni 3 Shutters Raised 60cm', 9.8510000, 76.9730000, NOW());

-- ----------------------------------------------------------------------------
-- 3. LINK ZONES TO RIVERS & UPSTREAM DAMS
-- ----------------------------------------------------------------------------
UPDATE `zones` SET `river_id` = 8, `primary_dam_id` = 8 WHERE `id` = 1; -- Aluva / Periyar / Idukki
UPDATE `zones` SET `river_id` = 2, `primary_dam_id` = 2 WHERE `id` = 2; -- Kuttanad / Krishna basin representation
UPDATE `zones` SET `river_id` = 8, `primary_dam_id` = 8 WHERE `id` = 3; -- Chalakudy / Periyar
UPDATE `zones` SET `river_id` = 8, `primary_dam_id` = 8 WHERE `id` = 4; -- Ranni / Pamba
UPDATE `zones` SET `river_id` = 1, `primary_dam_id` = 1 WHERE `id` = 5; -- Wayanad / Hilly zone

-- Insert additional national basin zones for demo
INSERT INTO `zones` (`id`, `name`, `district`, `state`, `latitude`, `longitude`, `status`, `river_id`, `primary_dam_id`) VALUES
(6, 'Sangli - Krishna River Floodplain', 'Sangli', 'Maharashtra', 16.8524000, 74.5815000, 'active', 2, 2),
(7, 'Nanded - Lower Godavari Inundation Zone', 'Nanded', 'Maharashtra', 19.1383000, 77.3210000, 'active', 1, 1),
(8, 'Vijayawada - Krishna Delta Basin', 'Krishna', 'Andhra Pradesh', 16.5062000, 80.6480000, 'active', 2, 7)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `river_id`=VALUES(`river_id`), `primary_dam_id`=VALUES(`primary_dam_id`);

-- ----------------------------------------------------------------------------
-- 4. SEED CITIZEN PROFILE BOOKMARKS (For default citizen user ID = 3)
-- ----------------------------------------------------------------------------
INSERT INTO `citizen_profiles` (`user_id`, `saved_village_city`, `saved_river_id`, `saved_dam_id`, `alert_notifications_enabled`) VALUES
(3, 'Aluva Town / Riverview Enclave', 8, 8, 1)
ON DUPLICATE KEY UPDATE `saved_village_city` = VALUES(`saved_village_city`), `saved_dam_id` = VALUES(`saved_dam_id`);

-- ----------------------------------------------------------------------------
-- 5. SEED MISSING & FOUND PERSONS REGISTRY
-- ----------------------------------------------------------------------------
TRUNCATE TABLE `missing_persons`;
INSERT INTO `missing_persons` (`id`, `report_type`, `person_name`, `gender`, `age`, `last_seen_or_found_location`, `last_seen_or_found_date`, `photo_url`, `contact_name`, `contact_phone`, `contact_relation`, `camp_id`, `status`, `notes`) VALUES
(1, 'Missing', 'Aarav Sharma', 'Child', 9, 'Near Sangli Old Bridge during flood evacuation', CURDATE() - INTERVAL 1 DAY, NULL, 'Ramesh Sharma', '+91 98220 12345', 'Father', NULL, 'Reported', 'Wearing blue t-shirt and grey shorts. Separated while boarding rescue boat.'),
(2, 'Missing', 'Sunita Deshmukh', 'Female', 62, 'Godavari Riverbank Colony, Nanded', CURDATE() - INTERVAL 2 DAY, NULL, 'Vikas Deshmukh', '+91 94221 54321', 'Son', NULL, 'Verified', 'Diabetic patient, wearing maroon cotton saree and gold spectacles.'),
(3, 'Found', 'Unidentified Boy (approx 7 yrs)', 'Child', 7, 'Rescued from rooftop at Bank Junction, Aluva', CURDATE(), NULL, 'Suresh Varma (Camp Incharge)', '+91 94470 12345', 'Relief Officer', 1, 'Reported', 'Currently sheltered safely at Aluva Govt Higher Secondary School Camp. Responds to the name "Appu".');

SET FOREIGN_KEY_CHECKS = 1;
