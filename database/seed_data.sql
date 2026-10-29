-- ============================================================================
-- Flood Risk Prediction & Relief Camp Locator
-- Telemetry & System Seed Data (PostgreSQL 15+, 16+, 17+ & Render.com Standard)
-- ============================================================================

-- 1. SEED ROLES
TRUNCATE TABLE roles CASCADE;
INSERT INTO roles (id, role_name, description) VALUES
(1, 'Administrator', 'Full system control, rule configuration, zone setup, data import, and emergency alert broadcasting'),
(2, 'Relief Coordinator', 'Operational manager triaging emergency help requests, assigning teams, and updating camp capacities'),
(3, 'Citizen', 'Public user viewing flood risks, discovering nearest relief camps, and submitting emergency help requests');

-- 2. SEED MAJOR INDIAN RIVERS
TRUNCATE TABLE rivers CASCADE;
INSERT INTO rivers (id, name, basin_name, states_covered, warning_level_meters, danger_mark_meters, current_level_meters, status) VALUES
(1, 'Godavari River', 'Godavari Basin', 'Maharashtra, Telangana, Andhra Pradesh, Chhattisgarh', 14.50, 16.20, 15.35, 'Warning'),
(2, 'Krishna River', 'Krishna Basin', 'Maharashtra, Karnataka, Telangana, Andhra Pradesh', 52.00, 54.50, 53.60, 'Warning'),
(3, 'Ganga River', 'Ganga Basin', 'Uttarakhand, Uttar Pradesh, Bihar, West Bengal', 70.20, 71.30, 69.80, 'Normal'),
(4, 'Yamuna River', 'Yamuna Basin', 'Haryana, Delhi, Uttar Pradesh', 204.50, 205.30, 205.10, 'Warning'),
(5, 'Narmada River', 'Narmada Basin', 'Madhya Pradesh, Maharashtra, Gujarat', 28.00, 31.00, 27.40, 'Normal'),
(6, 'Brahmaputra River', 'Brahmaputra Basin', 'Arunachal Pradesh, Assam', 49.60, 50.50, 50.25, 'Warning'),
(7, 'Mahanadi River', 'Mahanadi Basin', 'Chhattisgarh, Odisha', 26.50, 27.40, 29.10, 'Danger'),
(8, 'Periyar River', 'Periyar Basin', 'Kerala, Tamil Nadu', 12.00, 13.50, 13.15, 'Warning'),
(9, 'Kosi River', 'Kosi Basin', 'Bihar', 48.00, 50.50, 51.20, 'Danger'),
(10, 'Panchganga River', 'Krishna-Panchganga Basin', 'Maharashtra', 39.00, 43.00, 37.50, 'Normal');

-- 3. SEED RIVER BASINS
TRUNCATE TABLE river_basins CASCADE;
INSERT INTO river_basins (id, name, river_id, description) VALUES
(1, 'Godavari Basin', 1, 'Godavari River catchment and regional drainage basin (Maharashtra/Telangana)'),
(2, 'Krishna Basin', 2, 'Krishna River catchment and regional drainage basin (Western Maharashtra/Karnataka)'),
(3, 'Ganga Basin', 3, 'Ganga River catchment and northern plains drainage basin'),
(4, 'Yamuna Basin', 4, 'Yamuna River catchment basin'),
(5, 'Narmada Basin', 5, 'Narmada River catchment and rift valley drainage basin'),
(6, 'Brahmaputra Basin', 6, 'Brahmaputra Valley and northeastern flood basin'),
(7, 'Mahanadi Basin', 7, 'Mahanadi River delta and eastern catchment basin'),
(8, 'Periyar Basin', 8, 'Periyar River catchment and western ghats drainage basin (Kerala)'),
(9, 'Kosi Basin', 9, 'Kosi River flood basin (North Bihar)'),
(10, 'Krishna-Panchganga Basin', 10, 'Panchganga and upper Krishna sub-basin (Kolhapur/Sangli)'),
(11, 'Bhima Basin', 2, 'Bhima River sub-basin feeding into Krishna system (Pune/Solapur)'),
(12, 'Tapi Basin', 5, 'Tapi River northern Maharashtra drainage basin (Jalgaon/Khandesh)');

-- 4. SEED DAMS & RESERVOIRS
TRUNCATE TABLE dams CASCADE;
INSERT INTO dams (id, river_id, name, state, district, gross_capacity_tmc, current_storage_tmc, live_storage_pct, water_level_meters, fsl_meters, inflow_cusecs, outflow_cusecs, gate_status, latitude, longitude, last_updated) VALUES
(1, 1, 'Jayakwadi Dam (Nath Sagar)', 'Maharashtra', 'Chhatrapati Sambhajinagar', 102.70, 92.40, 89.97, 463.50, 463.90, 38500, 42000, '18 Gates Opened at 1.5 ft', 19.4975000, 75.3888000, NOW()),
(2, 2, 'Koyna Dam (Shivsagar Lake)', 'Maharashtra', 'Satara', 105.20, 98.60, 93.72, 657.80, 659.43, 45000, 62500, '6 Radial Gates Raised 3.0 ft', 17.4000000, 73.7500000, NOW()),
(3, 2, 'Almatti Dam (Lal Bahadur Shastri Sagar)', 'Karnataka', 'Bagalkot', 123.08, 108.50, 88.15, 519.20, 519.60, 95000, 120000, '26 Crest Gates Discharging', 16.3312000, 75.8890000, NOW()),
(4, 3, 'Tehri Dam Reservoir', 'Uttarakhand', 'Tehri Garhwal', 148.00, 112.50, 76.01, 820.50, 830.00, 22000, 18500, 'Spillway Controlled Flow', 30.3789000, 78.4800000, NOW()),
(5, 5, 'Sardar Sarovar Dam', 'Gujarat', 'Narmada', 334.00, 295.20, 88.38, 136.80, 138.68, 85000, 115000, '23 Radial Gates Open at 1.8m', 21.8315000, 73.7483000, NOW()),
(6, 7, 'Hirakud Dam', 'Odisha', 'Sambalpur', 203.00, 168.40, 82.95, 191.50, 192.02, 72000, 95000, '12 Sluice Gates Open', 21.5700000, 83.8700000, NOW()),
(7, 2, 'Prakasam Barrage', 'Andhra Pradesh', 'Krishna (Vijayawada)', 3.07, 2.95, 96.09, 17.50, 17.39, 175000, 185000, '70 Gates Fully Raised', 16.5074000, 80.6053000, NOW()),
(8, 8, 'Idukki & Cheruthoni Reservoir', 'Kerala', 'Idukki', 70.50, 64.20, 91.06, 732.10, 732.40, 28000, 35000, 'Cheruthoni 3 Shutters Raised 60cm', 9.8510000, 76.9730000, NOW()),
(9, 1, 'Gangapur Dam (Nashik)', 'Maharashtra', 'Nashik', 7.20, 6.85, 95.14, 612.40, 613.00, 14500, 12000, '4 Sluice Gates Raised 2.0 ft', 19.9985000, 73.7250000, NOW()),
(11, 1, 'Bhandardara Dam (Wilson Dam)', 'Maharashtra', 'Ahilyanagar', 11.04, 10.80, 97.82, 745.20, 746.00, 18000, 16500, 'Radial Overflow Spillway Active', 19.5412000, 73.7420000, NOW()),
(14, 2, 'Ujani Dam (Bhima Reservoir)', 'Maharashtra', 'Solapur', 117.24, 106.50, 90.84, 496.50, 496.83, 32000, 28000, '16 Spillway Gates Discharging', 18.0740000, 75.1180000, NOW()),
(18, 5, 'Hatnur Dam (Tapi River)', 'Maharashtra', 'Jalgaon', 11.50, 10.90, 94.78, 213.50, 214.00, 24000, 22500, '10 Gates Open at 1.0m', 21.0820000, 75.8850000, NOW());

-- 5. SEED ZONES
TRUNCATE TABLE zones CASCADE;
INSERT INTO zones (id, name, zone_name, district, state, latitude, longitude, boundary_geojson, river_basin, river_id, primary_dam_id, warning_level_m, danger_level_m, current_water_level_m, rainfall_24h_mm, risk_level, status) VALUES
(1, 'Aluva - Lower Periyar Basin', 'Aluva - Lower Periyar Basin', 'Ernakulam', 'Kerala', 10.1076000, 76.3516000, '{"type":"Polygon","coordinates":[[[76.330,10.090],[76.370,10.090],[76.370,10.130],[76.330,10.130],[76.330,10.090]]]}', 'Periyar Basin', 8, 8, 12.00, 13.50, 13.20, 195.00, 'Critical', 'active'),
(2, 'Kuttanad Delta Lowlands', 'Kuttanad Delta Lowlands', 'Alappuzha', 'Kerala', 9.4230000, 76.5126000, '{"type":"Polygon","coordinates":[[[76.490,9.400],[76.530,9.400],[76.530,9.440],[76.490,9.440],[76.490,9.400]]]}', 'Krishna Basin', 2, 2, 8.50, 10.00, 9.60, 145.00, 'High', 'active'),
(3, 'Chalakudy River Catchment', 'Chalakudy River Catchment', 'Thrissur', 'Kerala', 10.3070000, 76.3330000, '{"type":"Polygon","coordinates":[[[76.310,10.290],[76.350,10.290],[76.350,10.330],[76.310,10.330],[76.310,10.290]]]}', 'Periyar Basin', 8, 8, 5.50, 7.00, 6.20, 85.00, 'Moderate', 'active'),
(4, 'Ranni - Upper Pamba Basin', 'Ranni - Upper Pamba Basin', 'Pathanamthitta', 'Kerala', 9.3812000, 76.7885000, '{"type":"Polygon","coordinates":[[[76.760,9.360],[76.810,9.360],[76.810,9.400],[76.760,9.400],[76.760,9.360]]]}', 'Periyar Basin', 8, 8, 2.50, 4.00, 2.80, 35.00, 'Low', 'active'),
(5, 'Meppadi - Vythiri Hilly Zone', 'Meppadi - Vythiri Hilly Zone', 'Wayanad', 'Kerala', 11.5540000, 76.1260000, '{"type":"Polygon","coordinates":[[[76.100,11.530],[76.150,11.530],[76.150,11.570],[76.100,11.570],[76.100,11.530]]]}', 'Godavari Basin', 1, 1, 12.00, 14.00, 13.80, 215.00, 'Critical', 'active'),
(6, 'Sangli - Krishna River Floodplain', 'Sangli - Krishna River Floodplain', 'Sangli', 'Maharashtra', 16.8524000, 74.5815000, NULL, 'Krishna River', 2, 2, 14.50, 16.00, 13.80, 75.00, 'Moderate', 'active'),
(7, 'Nanded - Lower Godavari Inundation Zone', 'Nanded - Lower Godavari Inundation Zone', 'Nanded', 'Maharashtra', 19.1383000, 77.3210000, NULL, 'Godavari River', 1, 1, 14.00, 16.00, 14.20, 80.00, 'Moderate', 'active'),
(8, 'Vijayawada - Krishna Delta Basin', 'Vijayawada - Krishna Delta Basin', 'Krishna', 'Andhra Pradesh', 16.5062000, 80.6480000, NULL, 'Krishna River', 2, 7, 16.00, 17.50, 17.39, 90.00, 'High', 'active'),
(9, 'Supaul Kosi Flood Basin', 'Supaul Kosi Flood Basin', 'Supaul', 'Bihar', 26.1260000, 86.6050000, NULL, 'Kosi River', 9, NULL, 48.00, 50.50, 51.20, 165.00, 'Critical', 'active'),
(10, 'Guwahati Brahmaputra Sector', 'Guwahati Brahmaputra Sector', 'Kamrup', 'Assam', 26.1850000, 91.7450000, NULL, 'Brahmaputra', 6, NULL, 49.50, 51.00, 50.20, 110.00, 'High', 'active'),
(11, 'Kolhapur Panchganga Basin', 'Kolhapur Panchganga Basin', 'Kolhapur', 'Maharashtra', 16.7050000, 74.2433000, NULL, 'Panchganga', 10, NULL, 39.00, 43.00, 37.50, 45.00, 'Low', 'active'),
(12, 'Cuttack Mahanadi Delta', 'Cuttack Mahanadi Delta', 'Cuttack', 'Odisha', 20.4625000, 85.8830000, NULL, 'Mahanadi', 7, 6, 26.00, 28.50, 29.10, 185.00, 'Critical', 'active');

-- 6. SEED USERS
TRUNCATE TABLE users CASCADE;
INSERT INTO users (id, role_id, full_name, email, phone, district, river_basin_id, monitored_dam_id, address, password_hash, status, created_at) VALUES
(1, 1, 'Disaster Operations Admin', 'admin@floodrelief.org', '+91 98470 11223', 'Ernakulam', 8, 8, 'Disaster Control Room, Civil Station, Ernakulam', '$2y$10$64eF.W7aV5pB0K8RkZ3VReuP/f1w7P9QxOqO8KzY5jW8U2T1S0rFe', 'active', NOW()),
(2, 2, 'Rajesh Kumar (Relief Coordinator)', 'coordinator@floodrelief.org', '+91 94471 55667', 'Ernakulam', 8, 8, 'Tahsildar Office, Aluva North', '$2y$10$64eF.W7aV5pB0K8RkZ3VReuP/f1w7P9QxOqO8KzY5jW8U2T1S0rFe', 'active', NOW()),
(3, 3, 'Ananya Nair (Citizen)', 'citizen@example.com', '+91 98950 33445', 'Ernakulam', 8, 8, 'Riverview Enclave, Bank Road, Aluva', '$2y$10$64eF.W7aV5pB0K8RkZ3VReuP/f1w7P9QxOqO8KzY5jW8U2T1S0rFe', 'active', NOW()),
(101, 3, 'Pooja Kulkarni', 'pooja.kulkarni@example.com', '9822101010', 'Nashik', 1, 9, 'Gangapur Road, Anandwalli, Nashik', '$2y$10$wT0lVl8dF9L4C3gL7.x3gO7b/Vv2A0FpG9X6f5B4d1C0e8a7b6c5d', 'active', NOW()),
(102, 3, 'Amol Deshmukh', 'amol.deshmukh@example.com', '9822202020', 'Ahilyanagar', 1, 11, 'Pravara Colony, Akole, Ahilyanagar', '$2y$10$wT0lVl8dF9L4C3gL7.x3gO7b/Vv2A0FpG9X6f5B4d1C0e8a7b6c5d', 'active', NOW()),
(103, 3, 'Sachin Patil', 'sachin.patil@example.com', '9822303030', 'Chhatrapati Sambhajinagar', 1, 1, 'Near Paithan Gate, Chhatrapati Sambhajinagar', '$2y$10$wT0lVl8dF9L4C3gL7.x3gO7b/Vv2A0FpG9X6f5B4d1C0e8a7b6c5d', 'active', NOW()),
(104, 3, 'Ramesh Jadhav', 'ramesh.jadhav@example.com', '9822404040', 'Satara', 2, 2, 'Malkapur Road, Karad, Satara', '$2y$10$wT0lVl8dF9L4C3gL7.x3gO7b/Vv2A0FpG9X6f5B4d1C0e8a7b6c5d', 'active', NOW()),
(105, 3, 'Nitin Shinde', 'nitin.shinde@example.com', '9822505050', 'Solapur', 11, 14, 'Station Road, Pandharpur, Solapur', '$2y$10$wT0lVl8dF9L4C3gL7.x3gO7b/Vv2A0FpG9X6f5B4d1C0e8a7b6c5d', 'active', NOW()),
(106, 3, 'Ganesh Chaudhari', 'ganesh.chaudhari@example.com', '9822606060', 'Jalgaon', 12, 18, 'Civil Lines, Jalgaon', '$2y$10$wT0lVl8dF9L4C3gL7.x3gO7b/Vv2A0FpG9X6f5B4d1C0e8a7b6c5d', 'active', NOW());

-- 7. SEED RISK RULES
TRUNCATE TABLE risk_rules CASCADE;
INSERT INTO risk_rules (id, rule_name, rainfall_weight, river_weight, max_rainfall_baseline, max_river_baseline, low_max_score, mod_max_score, high_max_score, is_active, updated_by) VALUES
(1, 'Standard Monsoonal Thresholds v1.0', 0.50, 0.50, 250.00, 15.00, 25.00, 50.00, 75.00, TRUE, 1);

-- 8. SEED RISK LEVELS
TRUNCATE TABLE risk_levels CASCADE;
INSERT INTO risk_levels (zone_id, rainfall_value, river_level_value, score, category, source, observed_at) VALUES
(1, 195.00, 13.20, 83.00, 'Critical', 'XML Import', NOW()),
(2, 145.00, 9.60, 61.00, 'High', 'XML Import', NOW()),
(3, 85.00, 6.20, 37.67, 'Moderate', 'Manual Entry', NOW()),
(4, 35.00, 2.80, 16.33, 'Low', 'Manual Entry', NOW()),
(5, 215.00, 13.80, 89.00, 'Critical', 'Sensor Feed', NOW()),
(9, 165.00, 51.20, 94.50, 'Critical', 'IMD & CWC Hydrology Ingestion', NOW()),
(10, 110.00, 50.20, 72.80, 'High', 'IMD & CWC Hydrology Ingestion', NOW()),
(6, 75.00, 13.80, 48.50, 'Moderate', 'IMD & CWC Hydrology Ingestion', NOW()),
(11, 45.00, 37.50, 22.00, 'Low', 'IMD & CWC Hydrology Ingestion', NOW()),
(12, 185.00, 29.10, 94.50, 'Critical', 'IMD & CWC Hydrology Ingestion', NOW());

-- 9. SEED RELIEF CAMPS & PRIMARY SHELTERS
TRUNCATE TABLE camps CASCADE;
INSERT INTO camps (id, zone_id, dam_id, river_basin_id, name, camp_name, address, district, latitude, longitude, contact_person, contact_phone, total_capacity, current_occupancy, status, supply_status, active) VALUES
(1, 1, 8, 8, 'Aluva Govt Higher Secondary School Camp', 'Aluva Govt Higher Secondary School Camp', 'Near KSRTC Bus Stand, Aluva', 'Ernakulam', 10.1115000, 76.3550000, 'Suresh Varma (Tahsildar)', '+91 94470 12345', 350, 165, 'Operational', 'Sufficient', TRUE),
(2, 1, 8, 8, 'St. Marys Community Relief Center', 'St. Marys Community Relief Center', 'UC College Junction, Aluva West', 'Ernakulam', 10.1250000, 76.3380000, 'Fr. George Mathew', '+91 94471 23456', 200, 195, 'Full', 'Replenishment Needed', TRUE),
(3, 2, 2, 2, 'Kuttanad Cooperative Society Relief Camp', 'Kuttanad Cooperative Society Relief Camp', 'Champakkulam Boat Jetty Road', 'Alappuzha', 9.4350000, 76.5200000, 'Deepa Chandran (Revenue Inspector)', '+91 94472 34567', 300, 90, 'Operational', 'Sufficient', TRUE),
(4, 3, 8, 8, 'Chalakudy Town Hall Emergency Shelter', 'Chalakudy Town Hall Emergency Shelter', 'Main Central Road, Chalakudy', 'Thrissur', 10.3095000, 76.3355000, 'K. R. Menon', '+91 94473 45678', 500, 140, 'Operational', 'Sufficient', TRUE),
(5, 4, 8, 8, 'Ranni St. Thomas College Camp', 'Ranni St. Thomas College Camp', 'College Hill, Ranni', 'Pathanamthitta', 9.3850000, 76.7910000, 'Thomas Koshy', '+91 94474 56789', 250, 35, 'Operational', 'Abundant', TRUE),
(6, 5, 1, 1, 'Meppadi High Ground Rescue Shelter', 'Meppadi High Ground Rescue Shelter', 'Tea Estate Community Hall, Meppadi', 'Wayanad', 11.5510000, 76.1285000, 'Mohan Das (Village Officer)', '+91 94475 67890', 400, 310, 'Operational', 'Sufficient', TRUE),
(7, 9, NULL, 9, 'Supaul District High School Shelter', 'Supaul District High School Shelter', 'Near Gandhi Maidan, Supaul, Bihar', 'Supaul', 26.1290000, 86.6090000, 'Rameshwar Jha (Circle Officer)', '9835012345', 800, 720, 'Operational', 'Sufficient', TRUE),
(8, 9, NULL, 9, 'Birpur Flood Evacuation Center', 'Birpur Flood Evacuation Center', 'Birpur Sub-division, Supaul', 'Supaul', 26.5180000, 87.0180000, 'Sanjay Kumar (Disaster Incharge)', '9835098765', 500, 500, 'Full', 'Critical', TRUE),
(9, 10, NULL, 6, 'Guwahati Sarusajai Indoor Camp', 'Guwahati Sarusajai Indoor Camp', 'National Highway 37, Guwahati', 'Kamrup', 26.1890000, 91.7510000, 'Hemanta Sarma (Camp Manager)', '9435011223', 1200, 850, 'Operational', 'Abundant', TRUE),
(10, 10, NULL, 6, 'Kaziranga Wildlife Corridor Shelter', 'Kaziranga Wildlife Corridor Shelter', 'Kohora Range, Assam', 'Kamrup', 26.5850000, 93.4110000, 'Bipul Gogoi', '9435044556', 400, 180, 'Operational', 'Sufficient', TRUE),
(11, 6, 2, 2, 'Sangli Miraj Municipal Hall Shelter', 'Sangli Miraj Municipal Hall Shelter', 'Near Civil Hospital, Sangli', 'Sangli', 16.8560000, 74.5880000, 'Santosh Patil (Tahsildar)', '9822011223', 600, 320, 'Operational', 'Sufficient', TRUE),
(12, 11, NULL, 10, 'Kolhapur Gandhi Maidan Relief Camp', 'Kolhapur Gandhi Maidan Relief Camp', 'Bavda Road, Kolhapur', 'Kolhapur', 16.7110000, 74.2480000, 'Sachin Desai', '9822055667', 750, 200, 'Operational', 'Sufficient', TRUE),
(13, 12, 6, 7, 'Cuttack Ravenshaw Cyclone Center', 'Cuttack Ravenshaw Cyclone Center', 'College Square, Cuttack', 'Cuttack', 20.4650000, 85.8880000, 'Debasish Mohanty', '9437012345', 1000, 980, 'Operational', 'Critical', TRUE),
(14, NULL, 9, 1, 'Nashik Municipal Relief Shelter', 'Nashik Municipal Relief Shelter', 'Near Gangapur Dam Road, Anandwalli, Nashik, Maharashtra', 'Nashik', 19.9985000, 73.7250000, 'Sunil Gaikwad (Tehsildar / Relief Incharge)', '+91 94231 88765', 600, 210, 'Operational', 'Sufficient', TRUE),
(15, NULL, 11, 1, 'Akole / Sangamner Emergency Hall', 'Akole / Sangamner Emergency Hall', 'Pravara Riverbank Road, Akole Sub-Division, Ahilyanagar, Maharashtra', 'Ahilyanagar', 19.5412000, 73.9920000, 'Dr. Rameshwar Tambe (Sub-Divisional Officer)', '+91 98224 44551', 450, 120, 'Operational', 'Abundant', TRUE),
(16, NULL, 1, 1, 'Paithan / Chhatrapati Sambhajinagar Shelter', 'Paithan / Chhatrapati Sambhajinagar Shelter', 'Sant Eknath Rangmandir Compound, Paithan, Chhatrapati Sambhajinagar, Maharashtra', 'Chhatrapati Sambhajinagar', 19.4890000, 75.3810000, 'Anil Deshmukh (Municipal Chief Officer)', '+91 94222 33110', 1000, 410, 'Operational', 'Sufficient', TRUE),
(17, NULL, 2, 2, 'Karad / Satara Relief Camp', 'Karad / Satara Relief Camp', 'Karad Town Hall Evacuation Ground, NH 48, Satara District, Maharashtra', 'Satara', 17.2885000, 74.1840000, 'Vijayrao Patil (Disaster Response Cell)', '+91 98500 77123', 800, 280, 'Operational', 'Abundant', TRUE),
(18, NULL, 14, 11, 'Daund / Pandharpur Community Shelter', 'Daund / Pandharpur Community Shelter', 'Bhima River Flood Relief Pavilion, Pandharpur Road, Solapur District, Maharashtra', 'Solapur', 17.6780000, 75.3250000, 'Sandeep Shinde (Circle Inspector)', '+91 94230 99882', 750, 190, 'Operational', 'Sufficient', TRUE),
(19, NULL, 18, 12, 'Jalgaon Flood Relief Camp', 'Jalgaon Flood Relief Camp', 'Tapi Flood Evacuation Center, Near Civil Hospital, Jalgaon, Maharashtra', 'Jalgaon', 21.0120000, 75.5680000, 'Mahesh Chaudhari (Disaster Management Cell)', '+91 98233 11244', 550, 140, 'Operational', 'Sufficient', TRUE);

-- 10. SEED HELP REQUESTS
TRUNCATE TABLE help_requests CASCADE;
INSERT INTO help_requests (id, reference_code, citizen_id, guest_name, guest_phone, guest_email, request_type, urgency, water_severity, mobile_verified, people_count, description, address, latitude, longitude, status, assigned_to, created_at) VALUES
(1, 'REQ-20260905-9012', 3, 'Ananya Nair', '+91 98950 33445', 'ananya@example.com', 'Evacuation', 'Critical', 'Roof-Level', TRUE, 4, 'Water entered ground floor, elderly parents and infant stranded on rooftop terrace. Power disconnected.', 'House #42, Bank Junction, Aluva River View Road', 10.1090000, 76.3530000, 'Acknowledged', 2, NOW() - INTERVAL '2 hours'),
(2, 'REQ-20260905-8431', NULL, 'Mathew Varghese', '+91 98460 77889', NULL, 'Food & Water', 'High', 'Waist-Level', TRUE, 7, 'Drinking water supply contaminated. Need potable drinking water cans and non-perishable food kits.', 'Champakkulam East Ward, Kuttanad', 9.4280000, 76.5180000, 'In Progress', 2, NOW() - INTERVAL '4 hours'),
(3, 'REQ-20260905-7720', NULL, 'Pradeep Menon', '+91 97450 11990', 'pradeep@mail.com', 'Medical Support', 'Medium', 'Ankle-Level', TRUE, 2, 'Diabetic insulin medication urgently required for senior citizen. Road access partially submerged.', 'Near St. Marys Church, Chalakudy', 10.3060000, 76.3320000, 'New', NULL, NOW() - INTERVAL '30 minutes');

-- 11. SEED HELP REQUEST LOGS
TRUNCATE TABLE help_request_logs CASCADE;
INSERT INTO help_request_logs (id, request_id, action, previous_status, new_status, action_by, notes, created_at) VALUES
(1, 1, 'Request Created', NULL, 'New', NULL, 'Emergency request submitted online by citizen.', NOW() - INTERVAL '2 hours'),
(2, 1, 'Status Update', 'New', 'Acknowledged', 2, 'Rescue team dispatched with boat unit #4 from Aluva depot.', NOW() - INTERVAL '90 minutes'),
(3, 2, 'Request Created', NULL, 'New', NULL, 'Guest request submitted via public form.', NOW() - INTERVAL '4 hours'),
(4, 2, 'Status Update', 'New', 'In Progress', 2, 'Cooperative boat loaded with 50 water cans en route to Champakkulam.', NOW() - INTERVAL '2 hours'),
(5, 3, 'Request Created', NULL, 'New', NULL, 'Medical assistance request recorded in system.', NOW() - INTERVAL '30 minutes');

-- 12. SEED ALERTS
TRUNCATE TABLE alerts CASCADE;
INSERT INTO alerts (id, title, message, severity, target_zone_id, channel, status, is_active, created_by, expires_at, created_at) VALUES
(1, 'RED ALERT: Evacuation Advisory for Lower Periyar Basin', 'Periyar River water level has crossed extreme danger mark (13.2m). All residents within 300 meters of riverbanks must move to designated higher-ground relief camps immediately. NDRF teams stationed at Aluva Town.', 'Critical Danger', 1, 'All', 'Published', TRUE, 1, NOW() + INTERVAL '24 hours', NOW()),
(2, 'ORANGE ALERT: River Water Rising in Kuttanad Lowlands', 'Continuous heavy precipitation upstream. Pamba and Manimala rivers swelling. Residents advised to secure livestock, charge emergency lights, and locate closest functional relief camps.', 'Warning', 2, 'All', 'Published', TRUE, 1, NOW() + INTERVAL '36 hours', NOW());

-- 13. SEED ALERT DELIVERY LOGS
TRUNCATE TABLE alert_delivery_logs CASCADE;
INSERT INTO alert_delivery_logs (id, alert_id, channel, recipient_target, delivery_status, details, sent_at) VALUES
(1, 1, 'In-App Broadcast', 'Residents in Zone #1', 'Delivered', 'Broadcasted to public top alert banner across portal.', NOW()),
(2, 1, 'SMS Gateway Simulation', 'Residents in Zone #1', 'Sent', 'Emergency SMS notification queued for registered zone phone numbers.', NOW()),
(3, 2, 'In-App Broadcast', 'Residents in Zone #2', 'Delivered', 'Broadcasted to public top alert banner across portal.', NOW());

-- 14. SEED CITIZEN PROFILES
TRUNCATE TABLE citizen_profiles CASCADE;
INSERT INTO citizen_profiles (id, user_id, saved_village_city, saved_river_id, saved_dam_id, alert_notifications_enabled) VALUES
(1, 3, 'Aluva Town / Riverview Enclave', 8, 8, TRUE),
(2, 101, 'Gangapur Road, Nashik', 1, 9, TRUE),
(3, 102, 'Pravara Colony, Akole', 1, 11, TRUE),
(4, 103, 'Paithan Gate, Sambhajinagar', 1, 1, TRUE),
(5, 104, 'Malkapur Road, Karad', 2, 2, TRUE),
(6, 105, 'Station Road, Pandharpur', 2, 14, TRUE),
(7, 106, 'Civil Lines, Jalgaon', 5, 18, TRUE);

-- 15. SEED MISSING PERSONS
TRUNCATE TABLE missing_persons CASCADE;
INSERT INTO missing_persons (id, report_type, person_name, gender, age, last_seen_or_found_location, last_seen_or_found_date, photo_url, contact_name, contact_phone, contact_relation, camp_id, status, notes) VALUES
(1, 'Missing', 'Aarav Sharma', 'Child', 9, 'Near Sangli Old Bridge during flood evacuation', CURRENT_DATE - INTERVAL '1 day', NULL, 'Ramesh Sharma', '+91 98220 12345', 'Father', NULL, 'Reported', 'Wearing blue t-shirt and grey shorts. Separated while boarding rescue boat.'),
(2, 'Missing', 'Sunita Deshmukh', 'Female', 62, 'Godavari Riverbank Colony, Nanded', CURRENT_DATE - INTERVAL '2 days', NULL, 'Vikas Deshmukh', '+91 94221 54321', 'Son', NULL, 'Verified', 'Diabetic patient, wearing maroon cotton saree and gold spectacles.'),
(3, 'Found', 'Unidentified Boy (approx 7 yrs)', 'Child', 7, 'Rescued from rooftop at Bank Junction, Aluva', CURRENT_DATE, NULL, 'Suresh Varma (Camp Incharge)', '+91 94470 12345', 'Relief Officer', 1, 'Reported', 'Currently sheltered safely at Aluva Govt Higher Secondary School Camp. Responds to the name "Appu".');

-- 16. SYNCHRONIZE POSTGRESQL SERIAL SEQUENCES
SELECT setval('roles_id_seq', (SELECT COALESCE(MAX(id), 1) FROM roles));
SELECT setval('rivers_id_seq', (SELECT COALESCE(MAX(id), 1) FROM rivers));
SELECT setval('river_basins_id_seq', (SELECT COALESCE(MAX(id), 1) FROM river_basins));
SELECT setval('dams_id_seq', (SELECT COALESCE(MAX(id), 1) FROM dams));
SELECT setval('zones_id_seq', (SELECT COALESCE(MAX(id), 1) FROM zones));
SELECT setval('users_id_seq', (SELECT COALESCE(MAX(id), 1) FROM users));
SELECT setval('risk_rules_id_seq', (SELECT COALESCE(MAX(id), 1) FROM risk_rules));
SELECT setval('risk_levels_id_seq', (SELECT COALESCE(MAX(id), 1) FROM risk_levels));
SELECT setval('camps_id_seq', (SELECT COALESCE(MAX(id), 1) FROM camps));
SELECT setval('camp_updates_id_seq', (SELECT COALESCE(MAX(id), 1) FROM camp_updates));
SELECT setval('help_requests_id_seq', (SELECT COALESCE(MAX(id), 1) FROM help_requests));
SELECT setval('help_request_logs_id_seq', (SELECT COALESCE(MAX(id), 1) FROM help_request_logs));
SELECT setval('alerts_id_seq', (SELECT COALESCE(MAX(id), 1) FROM alerts));
SELECT setval('alert_delivery_logs_id_seq', (SELECT COALESCE(MAX(id), 1) FROM alert_delivery_logs));
SELECT setval('audit_logs_id_seq', (SELECT COALESCE(MAX(id), 1) FROM audit_logs));
SELECT setval('citizen_profiles_id_seq', (SELECT COALESCE(MAX(id), 1) FROM citizen_profiles));
SELECT setval('missing_persons_id_seq', (SELECT COALESCE(MAX(id), 1) FROM missing_persons));
SELECT setval('simulation_logs_id_seq', (SELECT COALESCE(MAX(id), 1) FROM simulation_logs));
