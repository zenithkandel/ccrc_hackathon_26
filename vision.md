# SAWARI — Product Vision & Architecture

Sawari is a user-guiding web app that helps people navigate the crowded cities of Nepal using public transportation. It tells you exactly which bus to take, where to board, what to say to the conductor, how much to pay, and tracks the bus live on a map.

---

## The Problem

You need to get from Point A (your home) to Point B (your friend's house, a job interview, the hospital). Your current options:

- **Pathao** — Expensive ride-booking (bikes/taxis)
- **Ask strangers** — Unreliable and time-consuming
- **Google Maps** — Shows driving paths, not actual public bus routes. It doesn't know which bus goes where in Nepal's informal transit system.

Sawari solves this by using **its own stored public bus route database** combined with **live GPS tracking from hardware devices on buses**.

---

## How It Works

### Route Finding

1. User enters **Point A** (starting location) and **Point B** (destination).
2. Sawari finds the nearest approved bus stops to both points from the `locations` table.
3. It checks the `routes` table to determine if any stored route connects the two areas using the ordered `location_list` JSON.
4. If a **direct route** exists:
   - Shows the vehicle name and image
   - Displays the fare (NPR, rounded to nearest 5, minimum 20)
   - Shows student/elderly discounted fare (75% of regular)
   - Lists boarding stop, intermediate stops, and drop-off stop
   - Provides a conductor tip: _"Tell the conductor: '[boarding stop]' to '[drop-off stop]'"_
   - Draws the route on the map using OSRM for realistic road-following paths between consecutive stops
   - Shows walking directions from Point A to boarding stop, and from drop-off to Point B
5. If **no direct route** exists:
   - Finds an intersection stop shared between two routes
   - Suggests a two-bus journey: first bus → transfer stop → second bus
   - Each leg shows vehicle, fare, stops, and conductor tip independently

### Route Resolution — Technical Details

Sawari does **not** calculate arbitrary road routes for buses. Instead:

- It uses the ordered `location_list` JSON stored in each route record.
- It checks whether both the origin stop and destination stop exist on the same route.
- It validates direction by checking index order (boarding index must be before destination index).
- OSRM is used **only for visual rendering** — to draw realistic road paths between two consecutive stored stops on the map. All routing logic comes strictly from the database.

**Example route:** Kalanki → RNAC → Bagbazar → Putalisadak → Naxal → Nagpokhari → Buspark

OSRM renders road paths between:

- Kalanki ↔ RNAC
- RNAC ↔ Bagbazar
- Bagbazar ↔ Putalisadak
- etc.

The routing algorithm itself never touches OSRM. It's pure database lookup + index comparison.

### Transfer Route Algorithm

When no direct route connects A and B:

1. Collects all routes passing near Point A.
2. Collects all routes passing near Point B.
3. Finds intersection stops (locations that appear on both an A-route and a B-route).
4. Evaluates each transfer option by total distance.
5. Returns the best transfer result with full leg details.

---

## Live Bus Tracking

Vehicles with GPS hardware send real-time position data to Sawari via `POST /api/gps-device.php`.

**GPS data payload from hardware device:**

```json
{
  "data": {
    "bus_id": 1,
    "latitude": 27.673159,
    "longitude": 85.343842,
    "speed": 1.8,
    "direction": 0,
    "altitude": 1208.1,
    "satellites": 7,
    "hdop": 2,
    "timestamp": "2026-02-19T09:06:53Z"
  }
}
```

**How tracking works:**

1. The GPS device on a bus sends position data to `api/gps-device.php`.
2. The endpoint validates the data, maps `bus_id` → `vehicle_id`, and writes `latitude`, `longitude`, `velocity` to the `vehicles` table.
3. GPS quality is assessed via HDOP (good < 5, moderate 5-10, poor > 10).
4. On the user's map, `tracking.js` polls `api/vehicles.php?action=live` every **8 seconds**.
5. The API returns all vehicles where `gps_active = 1` and `last_gps_update` is within the last 2 minutes.
6. Vehicle markers **slide smoothly** to new positions using `requestAnimationFrame` with cubic ease-out interpolation (2-second animation).
7. For each vehicle, the system compares its position against the route's ordered stop list to determine:
   - Which stop it's approaching
   - Distance to that stop
   - ETA based on current velocity

**Marker behavior:**

- Normal vehicles: orange circle with bus SVG icon (32px)
- Vehicles approaching a stop (< 500m): pulsing animation ring
- Stale vehicles (no update in 2 minutes): automatically hidden

---

## Nepal Fare Calculation

The fare system follows Nepal's public transportation pricing rules:

- **Base fare** per route (stored in `routes` table as `fare_base`)
- **Per-kilometer rate** (stored as `fare_per_km`)
- **Final fare** = `fare_base + fare_per_km × distance_km`
- **Rounding**: Always rounded to the nearest **multiple of 5 NPR**
- **Minimum fare**: **NPR 20** (NPR 15 for student/elderly)
- **Student/Elderly discount**: **75%** of standard fare, also rounded to multiples of 5

Both the server (`routing-engine.php`) and client (`routing.js`) apply the same rounding logic to maintain consistency.

---

## Carbon Emission Calculator

Every route result shows a carbon comparison:

| Mode         | CO₂ per km |
| ------------ | ---------- |
| Regular bus  | 0.089 kg   |
| Electric bus | 0.020 kg   |
| Car/taxi     | 0.210 kg   |

The card shows how much CO₂ the user saves by taking the bus instead of a car.

---

## User Experience Features

### Tourist Help Mode

- What to say to the conductor when boarding
- How to signal your stop ("Roknu!" = Stop!)
- Safety precautions for crowded buses
- Peak hour warnings (8-10 AM, 4-6 PM)

### Smart Emergency Alerts

- Admin creates alerts for specific routes (strikes, road blocks, diversions)
- Alerts appear as warning markers on the map with severity levels (critical/high/medium/low)
- Alert banner shown in search panel when relevant routes are affected

### Community Suggestions

- Users can suggest missing stops, route corrections, new routes
- Suggestions reviewed by admin

### Trip Logging & Feedback

- Users can log trips and receive a feedback prompt after 5 seconds
- Star rating (1-5), accuracy feedback (accurate/slightly off/inaccurate), text review
- Trip data used for analytics

### Estimated Wait Time

- Based on route length: shorter routes run more frequently
- Displayed as a range (e.g., "~10-15 min")

---

## Data Collection — Agent System

Volunteers (agents) collect real-world bus data through the agent dashboard:

### Agent Capabilities

- **Add Location**: Pin bus stops on a Leaflet map with GPS support. Toggle to show existing approved stops to avoid duplicates. Automatic nearby-duplicate warning (< 300m).
- **Add Vehicle**: Register buses with name, description, image upload, route assignment, electric flag, service hours.
- **Add Route**: Build routes by selecting an ordered sequence of approved stops on the map. Outputs `location_list` JSON.
- **My Contributions**: Track all submitted locations, vehicles, and routes with approval status.
- **Leaderboard**: Agents ranked by contribution count.

### Approval Workflow

1. Agent submits data → status = `pending`
2. Admin reviews on dashboard → `approved` or `rejected` (with reason)
3. Only approved data appears in the public user map

---

## Application Pages

| Page             | URL                                      | Purpose                                       |
| ---------------- | ---------------------------------------- | --------------------------------------------- |
| Landing          | `/CCRC/`                                 | Intro page, navigation to map/agent/admin     |
| User Map         | `/CCRC/pages/map.php`                    | Full-screen map with route finding & tracking |
| Agent Login      | `/CCRC/pages/agent/login.php`            | Agent authentication                          |
| Agent Dashboard  | `/CCRC/pages/agent/dashboard.php`        | Stats, leaderboard                            |
| Add Location     | `/CCRC/pages/agent/add-location.php`     | Pin stops on map                              |
| Add Vehicle      | `/CCRC/pages/agent/add-vehicle.php`      | Register vehicles                             |
| Add Route        | `/CCRC/pages/agent/add-route.php`        | Build ordered routes                          |
| My Contributions | `/CCRC/pages/agent/my-contributions.php` | Contribution history                          |
| Admin Login      | `/CCRC/pages/admin/login.php`            | Admin authentication                          |
| Admin Dashboard  | `/CCRC/pages/admin/dashboard.php`        | System overview stats                         |
| Manage Locations | `/CCRC/pages/admin/manage-locations.php` | Approve/reject stops                          |
| Manage Vehicles  | `/CCRC/pages/admin/manage-vehicles.php`  | Approve/reject vehicles                       |
| Manage Routes    | `/CCRC/pages/admin/manage-routes.php`    | Approve/reject routes                         |
| Manage Agents    | `/CCRC/pages/admin/manage-agents.php`    | Agent account management                      |
| Manage Alerts    | `/CCRC/pages/admin/manage-alerts.php`    | Create/resolve alerts                         |
| Contributions    | `/CCRC/pages/admin/contributions.php`    | Unified review queue                          |
| Suggestions      | `/CCRC/pages/admin/suggestions.php`      | Community suggestion inbox                    |

---

## Tech Stack

| Layer      | Technology                                             |
| ---------- | ------------------------------------------------------ |
| Frontend   | HTML5, CSS3 (custom design system), Vanilla JavaScript |
| Backend    | PHP 7.4+                                               |
| Database   | MySQL 5.7+ / MariaDB 10.3+                             |
| Maps       | Leaflet 1.9.4 + OpenStreetMap tiles                    |
| Road Paths | OSRM (public demo: `router.project-osrm.org`)          |
| Icons      | Feather Icons                                          |
| Font       | Inter (Google Fonts CDN)                               |
| Server     | Apache via XAMPP                                       |

---

## Database Schema (9 Tables)

### 1. `admins`

System administrators who approve/reject contributions.

| Column   | Type                           | Description      |
| -------- | ------------------------------ | ---------------- |
| admin_id | INT AUTO_INCREMENT PK          | Unique ID        |
| name     | VARCHAR(255)                   | Full name        |
| email    | VARCHAR(255) UNIQUE            | Login email      |
| password | VARCHAR(255)                   | bcrypt hash      |
| role     | ENUM('superadmin','moderator') | Permission level |
| status   | ENUM('active','inactive')      | Account status   |

### 2. `agents`

Volunteers who collect and submit field data.

| Column              | Type                            | Description        |
| ------------------- | ------------------------------- | ------------------ |
| agent_id            | INT PK                          | Unique ID          |
| name                | VARCHAR(255)                    | Full name          |
| email               | VARCHAR(255)                    | Login email        |
| password            | VARCHAR(255)                    | bcrypt hash        |
| contributions_count | INT DEFAULT 0                   | Total submissions  |
| accuracy_score      | DECIMAL(5,2)                    | Data quality score |
| status              | ENUM(active/suspended/inactive) | Account status     |

### 3. `contributions`

Tracks every agent submission with approval workflow.

| Column           | Type                                  | Description            |
| ---------------- | ------------------------------------- | ---------------------- |
| contribution_id  | INT PK                                | Unique ID              |
| agent_id         | INT FK                                | Submitting agent       |
| type             | ENUM('location','vehicle','route')    | What was submitted     |
| status           | ENUM('pending','approved','rejected') | Review status          |
| notes            | TEXT                                  | Agent notes            |
| rejection_reason | TEXT                                  | Admin rejection reason |

### 4. `locations`

Bus stops and landmarks with GPS coordinates.

| Column            | Type                            | Description                |
| ----------------- | ------------------------------- | -------------------------- |
| location_id       | INT PK                          | Unique ID                  |
| name              | VARCHAR(255)                    | Stop name (e.g. "Kalanki") |
| latitude          | DECIMAL(10,8)                   | GPS latitude               |
| longitude         | DECIMAL(11,8)                   | GPS longitude              |
| type              | ENUM('stop','landmark')         | Location category          |
| status            | ENUM(pending/approved/rejected) | Review status              |
| departure_count   | INT DEFAULT 0                   | Times used as trip start   |
| destination_count | INT DEFAULT 0                   | Times used as trip end     |

### 5. `routes`

Named bus routes with ordered stop sequences.

| Column        | Type                            | Description                         |
| ------------- | ------------------------------- | ----------------------------------- |
| route_id      | INT PK                          | Unique ID                           |
| name          | VARCHAR(255)                    | Route name (e.g. "Kalanki-Buspark") |
| location_list | JSON                            | Ordered array of stop objects       |
| fare_base     | DECIMAL(8,2)                    | Base fare in NPR                    |
| fare_per_km   | DECIMAL(8,2)                    | Per-kilometer rate in NPR           |
| status        | ENUM(pending/approved/rejected) | Review status                       |

### 6. `vehicles`

Buses, microbuses, and tempos with images and live GPS.

| Column          | Type                            | Description                 |
| --------------- | ------------------------------- | --------------------------- |
| vehicle_id      | INT PK                          | Unique ID                   |
| name            | VARCHAR(255)                    | Vehicle/company name        |
| image_path      | VARCHAR(255)                    | Uploaded photo filename     |
| electric        | TINYINT(1)                      | Is electric vehicle         |
| used_routes     | JSON                            | Array of assigned route IDs |
| latitude        | TEXT                            | Live GPS latitude           |
| longitude       | TEXT                            | Live GPS longitude          |
| velocity        | TEXT                            | Current speed (km/h)        |
| gps_active      | TINYINT(1)                      | Currently sending GPS data  |
| last_gps_update | DATETIME                        | Last GPS ping timestamp     |
| starts_at       | TIME                            | Service start time          |
| stops_at        | TIME                            | Service end time            |
| status          | ENUM(pending/approved/rejected) | Review status               |

### 7. `trips`

Logged user journeys with feedback.

| Column              | Type                                   | Description            |
| ------------------- | -------------------------------------- | ---------------------- |
| trip_id             | INT PK                                 | Unique ID              |
| session_id          | VARCHAR(64)                            | Anonymous user session |
| route_id            | INT FK                                 | Route used             |
| vehicle_id          | INT FK                                 | Vehicle used           |
| boarding_stop_id    | INT FK                                 | Where user boarded     |
| destination_stop_id | INT FK                                 | Where user got off     |
| fare_paid           | DECIMAL(8,2)                           | Fare amount            |
| carbon_saved        | DECIMAL(8,4)                           | CO₂ savings (kg)       |
| rating              | TINYINT                                | 1-5 stars              |
| accuracy_feedback   | ENUM(accurate/slightly_off/inaccurate) | Route accuracy         |
| review              | TEXT                                   | User comments          |

### 8. `alerts`

Route-specific warnings and emergencies.

| Column   | Type                                   | Description    |
| -------- | -------------------------------------- | -------------- |
| alert_id | INT PK                                 | Unique ID      |
| route_id | INT FK                                 | Affected route |
| title    | VARCHAR(255)                           | Alert headline |
| severity | ENUM('low','medium','high','critical') | Severity level |
| status   | ENUM('active','resolved','expired')    | Current state  |

### 9. `suggestions`

Community-submitted improvement ideas.

| Column        | Type                                                          | Description   |
| ------------- | ------------------------------------------------------------- | ------------- |
| suggestion_id | INT PK                                                        | Unique ID     |
| type          | ENUM('missing_stop','route_correction','new_route','general') | Category      |
| title         | VARCHAR(255)                                                  | Brief title   |
| description   | TEXT                                                          | Details       |
| status        | ENUM('pending','reviewed','implemented','dismissed')          | Review status |

---

## Technical Routing Philosophy

1. **Route logic is database-driven** — routing decisions come from stored `location_list` sequences, not computed road paths.
2. **OSRM is visual only** — used to render realistic road-following polylines between two consecutive stops.
3. **Live tracking is GPS-dependent** — only vehicles actively sending GPS data appear on the map.
4. **Fare follows Nepal rules** — multiples of 5, minimum 20 NPR, student/elderly discounts.
5. **Everything requires approval** — no user-submitted data appears publicly until admin approves it.
