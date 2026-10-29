-- ============================================================================
-- Flood Risk Prediction & Relief Camp Locator
-- Database Schema (PostgreSQL 15+, 16+, 17+ & Render.com Standard)
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1. ROLES TABLE
-- Defines user authorization tiers: Admin (1), Coordinator (2), Citizen (3)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS roles CASCADE;
CREATE TABLE roles (
    id SERIAL PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

-- ----------------------------------------------------------------------------
-- 2. RIVERS TABLE
-- Key flood-prone Indian river basins and water level monitoring marks
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS rivers CASCADE;
CREATE TABLE rivers (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    basin_name VARCHAR(100) NOT NULL,
    states_covered VARCHAR(255) NOT NULL,
    warning_level_meters NUMERIC(6, 2) NOT NULL,
    danger_mark_meters NUMERIC(6, 2) NOT NULL,
    current_level_meters NUMERIC(6, 2) NOT NULL,
    status VARCHAR(20) DEFAULT 'Normal' CHECK (status IN ('Normal', 'Warning', 'Danger')),
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_river_status ON rivers(status);

-- ----------------------------------------------------------------------------
-- 3. RIVER BASINS TABLE
-- Drainage basins and waterways linked to dams and regional flood zones
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS river_basins CASCADE;
CREATE TABLE river_basins (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    river_id INT NULL REFERENCES rivers(id) ON DELETE SET NULL,
    description TEXT NULL,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_basin_name ON river_basins(name);

-- ----------------------------------------------------------------------------
-- 4. DAMS TABLE
-- Major reservoirs, gross storage capacity (TMC), live storage %, and discharge
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS dams CASCADE;
CREATE TABLE dams (
    id SERIAL PRIMARY KEY,
    river_id INT NOT NULL REFERENCES rivers(id) ON DELETE CASCADE,
    name VARCHAR(150) NOT NULL,
    state VARCHAR(100) NOT NULL,
    district VARCHAR(100) NOT NULL,
    gross_capacity_tmc NUMERIC(8, 2) NOT NULL,
    current_storage_tmc NUMERIC(8, 2) NOT NULL,
    live_storage_pct NUMERIC(5, 2) NOT NULL,
    water_level_meters NUMERIC(6, 2) NOT NULL,
    fsl_meters NUMERIC(6, 2) NOT NULL,
    inflow_cusecs INT NOT NULL DEFAULT 0,
    outflow_cusecs INT NOT NULL DEFAULT 0,
    gate_status VARCHAR(150) DEFAULT 'All Gates Closed',
    latitude NUMERIC(10, 7) NOT NULL,
    longitude NUMERIC(10, 7) NOT NULL,
    last_updated TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_dam_river ON dams(river_id);
CREATE INDEX idx_dam_state ON dams(state);

-- ----------------------------------------------------------------------------
-- 5. ZONES TABLE
-- Disaster monitoring geographical areas, center coordinates, and boundaries
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS zones CASCADE;
CREATE TABLE zones (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL DEFAULT '',
    zone_name VARCHAR(150) NULL,
    district VARCHAR(100) NOT NULL,
    state VARCHAR(100) DEFAULT 'Kerala',
    latitude NUMERIC(10, 7) NOT NULL,
    longitude NUMERIC(10, 7) NOT NULL,
    boundary_geojson TEXT NULL,
    river_basin VARCHAR(100) NULL,
    river_id INT NULL REFERENCES rivers(id) ON DELETE SET NULL,
    primary_dam_id INT NULL REFERENCES dams(id) ON DELETE SET NULL,
    warning_level_m NUMERIC(6, 2) NULL,
    danger_level_m NUMERIC(6, 2) NULL,
    current_water_level_m NUMERIC(6, 2) NULL,
    rainfall_24h_mm NUMERIC(6, 2) NULL,
    risk_level VARCHAR(50) NULL,
    status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active', 'inactive')),
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_zones_coords ON zones(latitude, longitude);
CREATE INDEX idx_zones_district ON zones(district);

-- ----------------------------------------------------------------------------
-- 6. USERS TABLE
-- System accounts with role assignment, contact info, and bcrypt password hash
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS users CASCADE;
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    role_id INT NOT NULL REFERENCES roles(id) ON UPDATE CASCADE,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(20) NULL,
    district VARCHAR(100) NULL,
    river_basin_id INT NULL REFERENCES river_basins(id) ON DELETE SET NULL,
    monitored_dam_id INT NULL REFERENCES dams(id) ON DELETE SET NULL,
    address TEXT NULL,
    password_hash VARCHAR(255) NOT NULL,
    status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active', 'inactive', 'suspended')),
    last_login TIMESTAMPTZ NULL,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_users_role ON users(role_id);
CREATE INDEX idx_users_email ON users(email);

-- ----------------------------------------------------------------------------
-- 7. RISK RULES TABLE
-- Configurable formula weights, normalization ceilings, and classification bands
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS risk_rules CASCADE;
CREATE TABLE risk_rules (
    id SERIAL PRIMARY KEY,
    rule_name VARCHAR(100) NOT NULL,
    rainfall_weight NUMERIC(4, 2) NOT NULL DEFAULT 0.50,
    river_weight NUMERIC(4, 2) NOT NULL DEFAULT 0.50,
    max_rainfall_baseline NUMERIC(6, 2) NOT NULL DEFAULT 250.00,
    max_river_baseline NUMERIC(5, 2) NOT NULL DEFAULT 15.00,
    low_max_score NUMERIC(5, 2) NOT NULL DEFAULT 25.00,
    mod_max_score NUMERIC(5, 2) NOT NULL DEFAULT 50.00,
    high_max_score NUMERIC(5, 2) NOT NULL DEFAULT 75.00,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    updated_by INT NULL REFERENCES users(id) ON DELETE SET NULL,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

-- ----------------------------------------------------------------------------
-- 8. RISK LEVELS TABLE
-- Recorded hydrological observations per zone, computed risk scores, and categories
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS risk_levels CASCADE;
CREATE TABLE risk_levels (
    id SERIAL PRIMARY KEY,
    zone_id INT NOT NULL REFERENCES zones(id) ON DELETE CASCADE,
    rainfall_value NUMERIC(6, 2) NOT NULL DEFAULT 0.00,
    river_level_value NUMERIC(5, 2) NOT NULL DEFAULT 0.00,
    score NUMERIC(5, 2) NOT NULL,
    category VARCHAR(20) NOT NULL CHECK (category IN ('Low', 'Moderate', 'High', 'Critical')),
    source VARCHAR(100) DEFAULT 'Manual Entry',
    observed_at TIMESTAMPTZ NOT NULL,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_risk_zone_date ON risk_levels(zone_id, observed_at);
CREATE INDEX idx_risk_category ON risk_levels(category);

-- ----------------------------------------------------------------------------
-- 9. CAMPS TABLE
-- Relief camp directory with GPS coordinates, capacity, status, dam link & contact
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS camps CASCADE;
CREATE TABLE camps (
    id SERIAL PRIMARY KEY,
    zone_id INT NULL REFERENCES zones(id) ON DELETE SET NULL,
    dam_id INT NULL REFERENCES dams(id) ON DELETE SET NULL,
    river_basin_id INT NULL REFERENCES river_basins(id) ON DELETE SET NULL,
    name VARCHAR(150) NOT NULL UNIQUE,
    camp_name VARCHAR(150) NULL,
    address TEXT NOT NULL,
    district VARCHAR(100) NOT NULL,
    latitude NUMERIC(10, 7) NOT NULL,
    longitude NUMERIC(10, 7) NOT NULL,
    contact_person VARCHAR(100) NOT NULL,
    contact_phone VARCHAR(20) NOT NULL,
    total_capacity INT NOT NULL DEFAULT 100,
    current_occupancy INT NOT NULL DEFAULT 0,
    status VARCHAR(20) DEFAULT 'Operational' CHECK (status IN ('Operational', 'Full', 'Standby', 'Closed', 'Active')),
    supply_status VARCHAR(30) DEFAULT 'Sufficient' CHECK (supply_status IN ('Abundant', 'Sufficient', 'Critical', 'Replenishment Needed')),
    active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_camps_coords ON camps(latitude, longitude);
CREATE INDEX idx_camps_status ON camps(status, active);
CREATE INDEX idx_camps_dam ON camps(dam_id);
CREATE INDEX idx_camps_basin ON camps(river_basin_id);

-- ----------------------------------------------------------------------------
-- 9.1 RELIEF CAMPS COMPATIBILITY VIEW
-- ----------------------------------------------------------------------------
CREATE OR REPLACE VIEW relief_camps AS 
SELECT c.*, 
       c.id AS camp_id,
       COALESCE(c.camp_name, c.name) AS display_name,
       (c.total_capacity - c.current_occupancy) AS available_beds
FROM camps c;

-- ----------------------------------------------------------------------------
-- 10. CAMP UPDATES TABLE
-- Audit log of changes to camp occupancy, capacity, and operational status
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS camp_updates CASCADE;
CREATE TABLE camp_updates (
    id SERIAL PRIMARY KEY,
    camp_id INT NOT NULL REFERENCES camps(id) ON DELETE CASCADE,
    previous_occupancy INT NOT NULL,
    new_occupancy INT NOT NULL,
    previous_status VARCHAR(50) NOT NULL,
    new_status VARCHAR(50) NOT NULL,
    updated_by INT NULL REFERENCES users(id) ON DELETE SET NULL,
    remarks VARCHAR(255) NULL,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

-- ----------------------------------------------------------------------------
-- 11. HELP REQUESTS TABLE
-- Citizen emergency assistance requests with unique reference code and GPS pin
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS help_requests CASCADE;
CREATE TABLE help_requests (
    id SERIAL PRIMARY KEY,
    reference_code VARCHAR(30) NOT NULL UNIQUE,
    citizen_id INT NULL REFERENCES users(id) ON DELETE SET NULL,
    guest_name VARCHAR(100) NOT NULL,
    guest_phone VARCHAR(20) NOT NULL,
    guest_email VARCHAR(120) NULL,
    request_type VARCHAR(30) NOT NULL DEFAULT 'Evacuation' CHECK (request_type IN ('Evacuation', 'Medical Support', 'Food & Water', 'Shelter', 'Missing Person', 'Other')),
    urgency VARCHAR(20) NOT NULL DEFAULT 'High' CHECK (urgency IN ('Low', 'Medium', 'High', 'Critical')),
    water_severity VARCHAR(30) NOT NULL DEFAULT 'Waist-Level' CHECK (water_severity IN ('Ankle-Level', 'Waist-Level', 'Roof-Level', 'Dry/Threatened')),
    mobile_verified BOOLEAN DEFAULT TRUE,
    people_count INT NOT NULL DEFAULT 1,
    description TEXT NOT NULL,
    address TEXT NOT NULL,
    latitude NUMERIC(10, 7) NOT NULL,
    longitude NUMERIC(10, 7) NOT NULL,
    status VARCHAR(20) DEFAULT 'New' CHECK (status IN ('New', 'Acknowledged', 'In Progress', 'Resolved', 'Closed')),
    assigned_to INT NULL REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_help_status ON help_requests(status);
CREATE INDEX idx_help_urgency ON help_requests(urgency);
CREATE INDEX idx_help_reference ON help_requests(reference_code);

-- ----------------------------------------------------------------------------
-- 12. HELP REQUEST LOGS TABLE
-- Audit trail of status transitions and responder notes for help requests
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS help_request_logs CASCADE;
CREATE TABLE help_request_logs (
    id SERIAL PRIMARY KEY,
    request_id INT NOT NULL REFERENCES help_requests(id) ON DELETE CASCADE,
    action VARCHAR(100) NOT NULL,
    previous_status VARCHAR(50) NULL,
    new_status VARCHAR(50) NOT NULL,
    action_by INT NULL REFERENCES users(id) ON DELETE SET NULL,
    notes TEXT NULL,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

-- ----------------------------------------------------------------------------
-- 13. ALERTS TABLE
-- Official evacuation advisories and emergency alerts broadcast by administrators
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS alerts CASCADE;
CREATE TABLE alerts (
    id SERIAL PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    severity VARCHAR(30) NOT NULL DEFAULT 'Warning' CHECK (severity IN ('Advisory', 'Warning', 'Evacuation', 'Critical Danger')),
    target_zone_id INT NULL REFERENCES zones(id) ON DELETE SET NULL,
    channel VARCHAR(20) DEFAULT 'In-App' CHECK (channel IN ('In-App', 'SMS', 'Email', 'All')),
    status VARCHAR(20) DEFAULT 'Published' CHECK (status IN ('Draft', 'Published', 'Archived')),
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_by INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    expires_at TIMESTAMPTZ NULL,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_alerts_status_severity ON alerts(status, severity);

-- ----------------------------------------------------------------------------
-- 14. ALERT DELIVERY LOGS TABLE
-- Records simulation/dispatch of emergency notifications
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS alert_delivery_logs CASCADE;
CREATE TABLE alert_delivery_logs (
    id SERIAL PRIMARY KEY,
    alert_id INT NOT NULL REFERENCES alerts(id) ON DELETE CASCADE,
    channel VARCHAR(50) NOT NULL,
    recipient_target VARCHAR(150) NOT NULL,
    delivery_status VARCHAR(20) DEFAULT 'Sent' CHECK (delivery_status IN ('Sent', 'Delivered', 'Failed')),
    details TEXT NULL,
    sent_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

-- ----------------------------------------------------------------------------
-- 15. AUDIT LOGS TABLE
-- System administrative audit trail (data imports, threshold changes, logins)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS audit_logs CASCADE;
CREATE TABLE audit_logs (
    id SERIAL PRIMARY KEY,
    user_id INT NULL REFERENCES users(id) ON DELETE SET NULL,
    action_type VARCHAR(100) NOT NULL,
    table_affected VARCHAR(50) NULL,
    record_id INT NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_audit_action ON audit_logs(action_type);
CREATE INDEX idx_audit_created ON audit_logs(created_at);

-- ----------------------------------------------------------------------------
-- 16. CITIZEN PROFILES TABLE
-- Bookmarked village, nearby river, and upstream dam for personalized watch
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS citizen_profiles CASCADE;
CREATE TABLE citizen_profiles (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    saved_village_city VARCHAR(150) NULL,
    saved_river_id INT NULL REFERENCES rivers(id) ON DELETE SET NULL,
    saved_dam_id INT NULL REFERENCES dams(id) ON DELETE SET NULL,
    alert_notifications_enabled BOOLEAN DEFAULT TRUE,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

-- ----------------------------------------------------------------------------
-- 17. MISSING & FOUND PERSONS REGISTRY TABLE
-- Public community directory for flood rescue and missing family tracing
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS missing_persons CASCADE;
CREATE TABLE missing_persons (
    id SERIAL PRIMARY KEY,
    report_type VARCHAR(20) NOT NULL DEFAULT 'Missing' CHECK (report_type IN ('Missing', 'Found')),
    person_name VARCHAR(120) NOT NULL,
    gender VARCHAR(20) NOT NULL CHECK (gender IN ('Male', 'Female', 'Child', 'Other')),
    age INT NULL,
    last_seen_or_found_location VARCHAR(255) NOT NULL,
    last_seen_or_found_date DATE NOT NULL,
    photo_url VARCHAR(255) NULL,
    contact_name VARCHAR(120) NOT NULL,
    contact_phone VARCHAR(20) NOT NULL,
    contact_relation VARCHAR(100) NULL,
    camp_id INT NULL REFERENCES camps(id) ON DELETE SET NULL,
    status VARCHAR(20) DEFAULT 'Reported' CHECK (status IN ('Reported', 'Verified', 'Reunited', 'Closed')),
    reported_by_user_id INT NULL REFERENCES users(id) ON DELETE SET NULL,
    notes TEXT NULL,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_missing_status ON missing_persons(status, report_type);
CREATE INDEX idx_missing_name ON missing_persons(person_name);

-- ----------------------------------------------------------------------------
-- 18. SIMULATION LOGS TABLE
-- Records administrator viva simulation runs and parameter triggers
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS simulation_logs CASCADE;
CREATE TABLE simulation_logs (
    id SERIAL PRIMARY KEY,
    triggered_by INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    dam_id INT NOT NULL REFERENCES dams(id) ON DELETE CASCADE,
    simulated_rainfall_mm NUMERIC(6, 2) NOT NULL,
    simulated_discharge_cusecs INT NOT NULL,
    action VARCHAR(20) NOT NULL CHECK (action IN ('Triggered', 'Reset')),
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);
