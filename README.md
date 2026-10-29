# Flood Risk Prediction & Relief Camp Locator
> A web-based disaster decision-support platform providing area-level flood risk assessment, live relief camp discovery, emergency rescue ticket triage, and authorized alert broadcasts.

Built strictly in accordance with the project Product Requirements Document (PRD v1.0) for an **MCA Academic Web Technology Project**.

---

## 🛠️ Technology Stack
- **Frontend**: HTML5, CSS3, Bootstrap 5.3, JavaScript (ES6)
- **Backend**: Native Object-Oriented PHP 8.x with PDO (Clean Service-Oriented Architecture)
- **Database**: MySQL 5.7+ / MariaDB 10.4+
- **Data Formats**: JSON for APIs; dedicated XML import engine using `SimpleXML` and `DOMDocument`
- **Maps**: Dual-mode Map Adapter (Google Maps JavaScript API + automatic zero-failure fallback to Leaflet.js / OpenStreetMap)
- **Server Environment**: Apache on XAMPP

---

## 📋 Pre-configured Demo Accounts
| Role | Email | Password | Access Rights |
| :--- | :--- | :--- | :--- |
| **System Administrator** | `admin@floodrelief.org` | `Admin@1234` | Full administrative control, risk rules, data import, zones, camps, and alert broadcasting |
| **Relief Coordinator** | `coordinator@floodrelief.org` | `Coord@1234` | Request queue triage, assignment, operational notes, live camp bed updates |
| **Public Citizen** | `citizen@example.com` | `Citizen@1234` | Public dashboard, camp locator, emergency ticket submission & tracking |

*(Note: The login page at `views/auth/login.php` features quick one-click demo account credential buttons for easy viva presentations!)*

---

## 🚀 Step-by-Step XAMPP Setup & Installation

### Step 1: Start XAMPP Services
1. Open the **XAMPP Control Panel**.
2. Start both **Apache** and **MySQL** services.

### Step 2: Place the Project in XAMPP `htdocs`
Copy or link this project folder into your XAMPP web root directory:
```text
C:\xampp\htdocs\flood-relief
```

### Step 3: Initialize the Database
1. Open your browser and navigate to phpMyAdmin: [http://localhost/phpmyadmin/](http://localhost/phpmyadmin/)
2. Click **New** and create a database named `flood_relief_db` with Collation `utf8mb4_unicode_ci`.
3. Select `flood_relief_db`, click the **Import** tab, and import:
   - `database/schema.sql` (Creates all 12 tables, relationships, and constraints)
4. Next, import:
   - `database/seed_data.sql` (Populates sample zones, camps, rules, users, and telemetry)

### Step 4: Verify System Diagnostics
Open your browser and visit the built-in diagnostic health check:
```text
http://localhost/flood-relief/database/test_connection.php
```
Verify that all PHP extensions (`pdo_mysql`, `simplexml`, `dom`, `json`, `mbstring`) and database tables show **PASS**.

### Step 5: Launch the Application
Access the public citizen dashboard:
```text
http://localhost/flood-relief/
```

---

## 📁 Project Architecture & Folder Organization

```text
flood-relief/
├── config/
│   ├── database.php          # MySQL PDO database credentials & options
│   ├── app.php               # System constants, timezones, session setup
│   └── maps_config.php       # Google Maps API key & Leaflet fallback
├── core/
│   ├── Database.php          # Singleton PDO wrapper with prepared statements
│   ├── Auth.php              # Session handling & role-based access control (RBAC)
│   ├── CSRF.php              # Anti-CSRF token generator & validator
│   ├── Helper.php            # XSS sanitization, reference codes, badges
│   ├── Validator.php         # Server-side validation engine
│   └── Response.php          # JSON & XML response helper
├── services/
│   ├── RiskRuleEngine.php    # Mathematical weighted scoring & freshness checks
│   ├── CampLocatorService.php# Haversine distance calculations & capacity filters
│   ├── HelpRequestService.php# Emergency ticket lifecycle & audit trail
│   ├── AlertService.php      # Evacuation broadcaster & delivery simulator
│   └── DataImportService.php # XML, JSON, and CSV hydrology parser
├── api/                      # RESTful JSON endpoints
│   ├── auth.php              # Login, logout, session query
│   ├── zones.php             # Zones listing & admin CRUD
│   ├── zone_risk.php         # Zone risk telemetry & calculation detail
│   ├── camps_nearby.php      # Haversine proximity query (lat, lng, radius)
│   ├── help_requests.php     # Public ticket submission & reference tracker
│   ├── update_request.php    # Coordinator triage & responder assignment
│   ├── update_camp.php       # Coordinator live bed capacity updater
│   ├── alerts.php            # Active alerts query & admin broadcasting
│   ├── import_risk_data.php  # XML/JSON observation ingestion endpoint
│   └── manage_rules.php      # Scoring weights & threshold configurator
├── views/
│   ├── layouts/              # Reusable layouts (header, navbar, alert banner, footer)
│   ├── citizen/              # Public pages (Risk Dashboard, Camps Map, Help Form, Tracker, Alerts)
│   ├── coordinator/          # Operational hub, triage queue, camp status updater
│   ├── admin/                # Command center, zones, rules, XML import, camps, alerts, users, audit logs
│   └── auth/                 # Portal login, registration, 403 unauthorized
├── assets/
│   ├── css/                  # Custom disaster UI styling, badges, emergency pulse
│   └── js/                   # Map adapter, risk dashboard, camp locator controllers
├── database/
│   ├── schema.sql            # Complete DDL table script (12 tables)
│   ├── seed_data.sql         # Seed records for testing
│   ├── sample_observations.xml # Sample XML feed for import testing
│   └── test_connection.php   # Diagnostic health-check script
└── index.php                 # Root router directing to citizen dashboard
```

---

## 🧮 Flood Risk Scoring Formulation
The rule engine computes a transparent, normalized composite score ($0 - 100$ scale):
- **Rainfall Normalized**: $S_{\text{rain}} = \min(100, (R / R_{\max}) \times 100)$
- **River Level Normalized**: $S_{\text{river}} = \min(100, (H / H_{\max}) \times 100)$
- **Composite Score**: $\text{Risk Score} = (S_{\text{rain}} \times W_{\text{rain}}) + (S_{\text{river}} \times W_{\text{river}})$

### Category Thresholds:
- `0.0 – 25.0`: **Low** (Normal conditions, routine monitoring)
- `25.1 – 50.0`: **Moderate** (Elevated water levels, prepare emergency kits)
- `50.1 – 75.0`: **High** (Danger level, prepare for possible evacuation)
- `75.1 – 100.0`: **Critical** (Imminent inundation, immediate evacuation to shelters)

### Data Freshness Rule:
If observation timestamp is older than 24 hours, the system displays a visible **Historical Data Warning Notice**.
