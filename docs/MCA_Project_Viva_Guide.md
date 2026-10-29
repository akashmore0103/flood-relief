# MCA Project Demonstration & Viva Examination Guide
## Project: Flood Risk Prediction & Relief Camp Locator

---

## 1. Project Abstract & Overview
**Flood Risk Prediction & Relief Camp Locator** is a web-based disaster management platform developed using **PHP, MySQL, HTML5, CSS3, Bootstrap 5, and JavaScript (ES6)**. It provides a multi-stakeholder decision-support ecosystem for three distinct user roles:

1. **Citizens**: Can assess localized flood threat levels via an interactive risk dashboard, locate nearby operating relief shelters using geospatial distance filtering, submit emergency rescue tickets with precise GPS pins, track their ticket progress via unique reference codes, and view official evacuation advisories.
2. **Relief Coordinators**: Monitor incoming emergency tickets in real-time, triage priority requests (Critical first), dispatch rescue units, log action notes, and update live camp bed vacancies to prevent overcrowding.
3. **System Administrators**: Configure rule-engine weights and thresholds, ingest external meteorological and hydrological telemetry feeds (via XML and JSON), manage disaster monitoring zones, register relief shelters, broadcast emergency alerts, and audit security events.

---

## 2. Step-by-Step Examiner Demonstration Script

Follow this sequence during your academic project demonstration to showcase all User Stories from the PRD:

### Step 1: Public Flood Risk Assessment (US-01 & US-04)
1. Navigate to the homepage: `http://localhost/flood-relief/`
2. Select **"Aluva - Lower Periyar Basin"** from the Zone dropdown.
3. Show the examiner:
   - The **Calculated Flood Threat Level** (e.g. `83.0 / 100`) and the **CRITICAL** danger badge.
   - The visual progress meter bar.
   - The contributing hydrological indicators: **24-Hour Cumulative Rainfall** ($195\text{ mm}$) and **River Water Level** ($13.2\text{ m}$).
   - The **Data Freshness Badge** showing observation recency.
   - The **Actionable Safety Guidance** displaying specific recommendations for citizens.
4. Select **"Ranni - Upper Pamba Basin"** to show how the indicators dynamically change to **LOW** risk with a green badge without a full-page reload.

### Step 2: Relief Camp Locator & Proximity Routing (US-02 & US-07)
1. Click **"Relief Camps Map"** in the top navigation bar.
2. Click the **"Use My Current Location"** button. The browser detects coordinates and centers the map.
3. Show the examiner:
   - The color-coded markers (Green = Available beds, Orange = &gt;85% full, Red = Full).
   - The sidebar list sorted by proximity with distance in kilometers (e.g. `2.4 km away`).
   - The live bed availability badge (e.g. `185 / 350 beds vacant`).
4. Click on any camp card or map marker to view the popup window.
5. Click **"Get Directions"** to demonstrate driving/walking route generation.

### Step 3: Emergency Assistance Request Submission (US-03)
1. Click **"Request Help"** in the navigation bar.
2. Fill out a sample rescue request:
   - Name: `George Thomas`
   - Phone: `98470 55667`
   - Assistance Category: `Evacuation / Boat Rescue`
   - Urgency: `Critical`
   - People: `4`
   - Description: `Water 4 feet high in courtyard. Elderly person needs boat rescue.`
   - Click **"Detect My GPS"** or click on the mini-map to drop a pinpoint marker.
3. Click **"Submit Emergency Assistance Ticket"**.
4. Show the examiner the confirmation popup containing the generated **Unique Reference Code** (e.g., `REQ-20260905-XXXX`). Click **"Copy Code"**.

### Step 4: Public Ticket Tracking
1. Click **"Track Ticket Status"** in the modal or visit the **"Track Ticket"** page.
2. Paste the copied reference code and click **"Track Now"**.
3. Point out:
   - Current status: `New`.
   - The timeline log showing when the ticket was registered.

### Step 5: Coordinator Operations & Triage (US-05)
1. Click **"Staff / Coordinator Login"** on the right side of the navbar.
2. Click the quick demo button: **"Relief Coordinator"** (`coordinator@floodrelief.org` / `Coord@1234`) and sign in.
3. Show the **Coordinator Operations Hub** with status counters.
4. Click **"Full Request Queue"**. Notice the new request is highlighted in red because its urgency is `Critical`.
5. Click **"Triage / Update"** on the ticket:
   - Change Status to `In Progress`.
   - Assign responder to `Rajesh Kumar`.
   - Add note: `Rescue boat unit #2 dispatched from Aluva Depot.`
   - Click **"Save & Log Action"**.
6. Switch back to the public citizen tracker tab, refresh, and show how the citizen can now see that their rescue team has been dispatched!

### Step 6: Camp Capacity Live Management (US-07)
1. In the Coordinator menu, click **"Update Camp Capacity"**.
2. Change the current occupancy of a shelter (e.g. increase from 140 to 180).
3. Select status `Operational` and click **"Save"**.
4. Show how the vacant bed count and change log immediately record the transition.

### Step 7: Admin Ingestion of XML Hydrology Feeds (US-08)
1. Sign out and sign in as **Administrator** (`admin@floodrelief.org` / `Admin@1234`).
2. Go to **Admin Console &rarr; Import Hydrology (XML/JSON)**.
3. Click **"Paste Sample XML"** to populate realistic hydrological telemetry data.
4. Click **"Process & Ingest Hydrology Data"**.
5. Show the examiner the instant confirmation: `Successfully imported 5 observation(s)`.
6. Return to the public dashboard to show how the risk scores have been recalculated from the XML feed!

### Step 8: Emergency Alert Broadcasting (US-06)
1. Go to **Admin Console &rarr; Broadcast Emergency Alerts**.
2. Enter:
   - Title: `FLASH FLOOD WARNING: Periyar Water Surging`
   - Severity: `Critical Danger`
   - Target Zone: `Aluva - Lower Periyar Basin`
   - Message: `Reservoir gates opened. All low-lying houses must evacuate immediately.`
3. Click **"Authorize & Broadcast Alert"**.
4. Visit the public dashboard or any public page: show the prominent **Emergency Alert Ticker** that appears across the top of the screen!

---

## 3. Technical Architecture Highlights to Explain

### A. Why Native Object-Oriented PHP instead of a heavy framework?
- **Academic Clarity**: MVC frameworks hide database interactions and routing behind magic methods. Using native PHP with clean OOP (`Database::getInstance()`, `RiskRuleEngine`, `CampLocatorService`) proves deep understanding of core design patterns, PDO, and standard web security without third-party dependencies.

### B. Mathematical Proximity: The Haversine Formula
- Explain that rather than making expensive external Google Distance Matrix API calls for every shelter search, the backend computes distances on the server in MySQL using the **Spherical Haversine Law of Cosines**:
  $$\text{Distance} = 2R \cdot \arcsin\left(\sqrt{\sin^2\left(\frac{\Delta\phi}{2}\right) + \cos(\phi_1)\cos(\phi_2)\sin^2\left(\frac{\Delta\lambda}{2}\right)}\right)$$
  where $R = 6371\text{ km}$. This enables instantaneous radius filtering even offline!

### C. The Resilient Map Adapter Pattern
- Point out `assets/js/map_adapter.js`. Explain that it defines a pluggable abstraction layer: if a valid Google Maps API key is configured, it initializes Google Maps with Directions; if the key is absent or Google Cloud billing expires (common during student vivas), it automatically falls back to **Leaflet.js + OpenStreetMap**. The application never crashes.

### D. Multi-Format Data Ingestion (XML and JSON)
- The PRD explicitly mandates XML support. Explain how `DataImportService.php` uses PHP's `SimpleXML` and `DOMDocument` extensions to parse hierarchical XML nodes (`<hydrology_feed>`, `<observation>`, `<rainfall_mm>`, `<river_level_m>`), validates the data, runs it through `RiskRuleEngine`, and saves it to the relational database.

### E. Web Security Protections
- **SQL Injection**: 100% prevented by using PDO prepared statements with parameterized inputs throughout all database queries.
- **Cross-Site Scripting (XSS)**: Neutralized using `Helper::e()` (`htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`) on all dynamic outputs.
- **Cross-Site Request Forgery (CSRF)**: Enforced via `CSRF.php` with cryptographically secure tokens (`hash_equals()`).
- **Authentication**: Passwords stored using industry-standard `PASSWORD_BCRYPT`.

---

## 4. Top 10 Expected Viva Questions & Answers

**Q1: What is the primary problem solved by this system?**
> *Answer:* Traditional disaster websites either show raw numerical meteorological data that ordinary citizens cannot interpret, or they list relief camps without live occupancy and distance. This system translates raw rainfall and river measurements into human-readable risk levels (`Low`, `Moderate`, `High`, `Critical`), identifies the nearest available shelters, enables emergency ticket tracking, and gives coordinators a centralized triage queue.

**Q2: How does the Risk Rule Engine compute the score?**
> *Answer:* It normalizes observed rainfall ($0\text{ to }250\text{ mm}$) and river level ($0\text{ to }15\text{ m}$) into $0-100$ scores, then applies administrator-configurable weights ($W_{\text{rain}} \times 0.5 + W_{\text{river}} \times 0.5$). The resulting score ($0-100$) maps to four categories: Low ($0-25$), Moderate ($25.1-50$), High ($50.1-75$), and Critical ($75.1-100$).

**Q3: How does the system handle stale or missing hydrological data?**
> *Answer:* Per PRD Requirement FR-08, if an observation's timestamp is older than 24 hours (`DATA_STALE_HOURS`), the system flags it as stale (`is_stale = true`) and displays a prominent warning informing citizens that they are viewing historical data.

**Q4: Why did you implement both XML and JSON import?**
> *Answer:* Government hydrological departments and river basin authorities frequently publish observation bulletins in standardized XML feeds, while modern IoT water level sensors transmit JSON. The system provides a unified `DataImportService` supporting both formats.

**Q5: What database design normalization was applied?**
> *Answer:* The database is normalized to Third Normal Form (3NF). Entities are separated into distinct tables (`zones`, `camps`, `risk_levels`, `help_requests`, `users`, `alerts`, `audit_logs`). Historical transitions are decoupled into dedicated audit tables (`help_request_logs`, `camp_updates`, `alert_delivery_logs`) to preserve referential integrity without data duplication.

**Q6: How are emergency help requests tracked without forcing citizens to register?**
> *Answer:* During an emergency, forcing citizens to create an account creates friction that could cost lives. Therefore, guests can submit requests immediately with their name, phone, and GPS coordinates. The system generates a secure, unique alphanumeric reference code (e.g. `REQ-20260905-XXXX`) that allows them to track triage progress publicly.

**Q7: How is Role-Based Access Control (RBAC) enforced?**
> *Answer:* Sessions track the authenticated user's `role_name`. Before loading protected views or processing API requests, `Auth::requireRole(['Administrator'])` or `Auth::requireRole(['Relief Coordinator', 'Administrator'])` executes. Unauthorized access triggers an HTTP 403 response or redirects to `views/auth/unauthorized.php`.

**Q8: What happens if the internet goes down or Google Maps API quota is exceeded?**
> *Answer:* The frontend Map Adapter automatically falls back to OpenStreetMap tiles via Leaflet.js, and nearest camps are calculated server-side using the SQL Haversine formula, so camp discovery and distance sorting continue working smoothly.

**Q9: How are emergency alerts communicated to citizens?**
> *Answer:* When an Administrator broadcasts an alert, it is stored in the `alerts` table and targeted to either specific zones or all zones. The global `alert_banner.php` component automatically queries active critical alerts and renders a persistent emergency notification at the top of every page.

**Q10: What future enhancements can be added to this project?**
> *Answer:* As outlined in PRD Section 13, future scope includes integrating Machine Learning flood progression models (LSTM / Random Forest), automated telemetry ingestion from IoT river sensors, SMS gateway integration with telecom providers, and multi-language regional translation.
