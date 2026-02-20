# Sawari — Public Transportation Navigator for Nepal

Sawari is a web-based public transportation navigation app built for the crowded cities of Nepal. It tells users exactly which bus to take, where to board, what to say to the conductor, and how much to pay — something Google Maps cannot do for Nepal's informal transit system.

The app uses **its own stored route database** (not arbitrary driving directions) and supports **live GPS tracking** of buses via hardware GPS devices.

---

## Quick Start

### Prerequisites

- [XAMPP](https://www.apachefriends.org/) (PHP 7.4+ with Apache & MySQL)
- A modern web browser

### Installation

1. **Clone or copy** the project into your XAMPP htdocs folder:

   ```
   C:\xampp\htdocs\CCRC\
   ```

2. **Start Apache and MySQL** from the XAMPP Control Panel.

3. **Run the setup wizard** — open in your browser:

   ```
   http://localhost/CCRC/setup.php
   ```

   This will automatically:
   - Check PHP version and extensions
   - Create the `sawari` database with all 9 tables
   - Seed demo data (stops, routes, vehicles, agents, alerts)
   - Create upload directories

4. **Open the app:**
   ```
   http://localhost/CCRC/
   ```

### Default Credentials

| Role  | Email             | Password |
| ----- | ----------------- | -------- |
| Admin | admin@sawari.com  | admin123 |
| Agent | ram@sawari.com    | agent123 |
| Agent | sita@sawari.com   | agent123 |
| Agent | bikash@sawari.com | agent123 |

> **Delete `setup.php` after installation in production.**

---

## How It Works

1. **User enters Point A** (starting location) and **Point B** (destination).
2. Sawari finds bus stops near both points from its database.
3. It checks which stored bus routes connect those stops.
4. If a direct route exists, it shows the vehicle, fare, boarding/dropoff stops, and what to say to the conductor.
5. If no direct route exists, it finds a transfer point and suggests two buses.
6. The route is drawn on the map using OSRM for realistic road-following paths between stops.
7. If the bus has a GPS device, the user sees it moving on the map in real time.

### Nepal Fare Rules

- Minimum fare: **NPR 20** (NPR 15 for students/elderly)
- All fares are rounded to the **nearest multiple of 5**
- Base fare + per-km rate from ride data

---

## Project Structure

```
CCRC/
├── index.php                   ← Landing page
├── setup.php                   ← One-click database installer
├── schema.sql                  ← Database schema (9 tables)
├── test-data.sql               ← Additional test data
├── vision.md                   ← Product vision & architecture
├── workflow.md                 ← Development phases & progress
├── README.md                   ← This file
│
├── api/                        ← Backend REST API (PHP)
│   ├── config.php              ← DB connection, constants, helpers
│   ├── locations.php           ← CRUD + search + nearby for locations
│   ├── routes.php              ← CRUD for routes
│   ├── vehicles.php            ← CRUD + GPS update + live tracking
│   ├── trips.php               ← Trip logging + ratings/feedback
│   ├── contributions.php       ← Contribution management
│   ├── alerts.php              ← Route alerts CRUD
│   ├── suggestions.php         ← Community suggestion submissions
│   ├── agents.php              ← Agent auth + profile
│   ├── admins.php              ← Admin auth + management
│   ├── routing-engine.php      ← Route finding algorithm (direct + transfer)
│   └── gps-device.php          ← GPS hardware device receiver endpoint
│
├── assets/
│   ├── css/
│   │   ├── global.css          ← Design tokens, reset, typography, utilities
│   │   ├── components.css      ← Buttons, cards, forms, modals, badges, toasts
│   │   ├── map.css             ← User map page styles + responsive
│   │   ├── agent.css           ← Agent dashboard styles
│   │   └── admin.css           ← Admin dashboard styles
│   └── js/
│       ├── map.js              ← Leaflet map init, markers, route rendering
│       ├── search.js           ← Point A/B autocomplete, alert markers
│       ├── routing.js          ← Route display, fare, carbon, trip logging
│       ├── tracking.js         ← Live vehicle tracking (8s polling, smooth animation)
│       ├── agent.js            ← Agent dashboard logic (Sawari object)
│       └── admin.js            ← Admin dashboard logic (Sawari object)
│
├── includes/                   ← Shared PHP templates
│   ├── admin-header.php        ← Admin layout header + sidebar
│   ├── admin-footer.php        ← Admin layout footer + bottom nav
│   ├── agent-header.php        ← Agent layout header + sidebar
│   ├── agent-footer.php        ← Agent layout footer + bottom nav
│   ├── auth-admin.php          ← Admin session guard
│   └── auth-agent.php          ← Agent session guard
│
├── pages/
│   ├── map.php                 ← Main user page (full-screen map)
│   ├── agent/
│   │   ├── login.php           ← Agent login
│   │   ├── dashboard.php       ← Agent stats, leaderboard
│   │   ├── add-location.php    ← Pin bus stops on map (with existing stops toggle)
│   │   ├── add-vehicle.php     ← Register vehicles with image upload
│   │   ├── add-route.php       ← Build routes by selecting ordered stops
│   │   └── my-contributions.php← View contribution history
│   └── admin/
│       ├── login.php           ← Admin login
│       ├── dashboard.php       ← Admin stats overview
│       ├── manage-locations.php← Approve/reject locations
│       ├── manage-vehicles.php ← Approve/reject vehicles
│       ├── manage-routes.php   ← Approve/reject routes, visualize on map
│       ├── manage-agents.php   ← Manage agent accounts
│       ├── manage-alerts.php   ← Create/resolve route alerts
│       ├── contributions.php   ← Unified contribution review queue
│       └── suggestions.php     ← Community suggestion inbox
│
├── tools/
│   └── gps-simulator.php       ← CLI/browser tool to simulate vehicle GPS movement
│
├── uploads/
│   └── vehicles/               ← Vehicle image uploads
│
└── logs/
    └── gps-device.json         ← Rolling log of GPS device data (last 500 entries)
```

---

## Tech Stack

| Layer    | Technology                                             |
| -------- | ------------------------------------------------------ |
| Frontend | HTML5, CSS3 (custom design system), Vanilla JavaScript |
| Backend  | PHP 7.4+                                               |
| Database | MySQL 5.7+ / MariaDB 10.3+                             |
| Maps     | Leaflet 1.9.4 + OpenStreetMap tiles                    |
| Routing  | OSRM (public demo — road path rendering between stops) |
| Icons    | Feather Icons                                          |
| Font     | Inter (via CDN)                                        |
| Server   | Apache (XAMPP)                                         |

---

## Database Schema (9 Tables)

| Table           | Purpose                                                     |
| --------------- | ----------------------------------------------------------- |
| `admins`        | System administrators                                       |
| `agents`        | Volunteer data collectors                                   |
| `contributions` | Tracks all agent submissions with approval status           |
| `locations`     | Bus stops and landmarks with GPS coordinates                |
| `routes`        | Named routes with ordered `location_list` (JSON)            |
| `vehicles`      | Buses/microbuses with images, GPS fields, route assignments |
| `trips`         | Logged user trips with ratings and feedback                 |
| `alerts`        | Route-specific warnings (strikes, road blocks, etc.)        |
| `suggestions`   | Community-submitted improvement ideas                       |

---

## API Endpoints

### Public

| Method | Endpoint                                   | Description                       |
| ------ | ------------------------------------------ | --------------------------------- |
| GET    | `api/locations.php?action=approved`        | All approved bus stops            |
| GET    | `api/locations.php?action=nearby`          | Stops near a coordinate           |
| GET    | `api/locations.php?action=search`          | Search stops by name              |
| GET    | `api/routing-engine.php?action=find-route` | Find bus route between two points |
| GET    | `api/vehicles.php?action=live`             | All GPS-active vehicles           |
| GET    | `api/alerts.php?action=active`             | Active route alerts               |
| POST   | `api/trips.php?action=log`                 | Log a trip                        |
| POST   | `api/trips.php?action=feedback`            | Submit trip rating/review         |
| POST   | `api/suggestions.php?action=submit`        | Submit a suggestion               |

### GPS Device

| Method | Endpoint             | Description                            |
| ------ | -------------------- | -------------------------------------- |
| POST   | `api/gps-device.php` | Receive GPS data from hardware devices |

### Agent (requires agent session)

| Method | Endpoint                          | Description           |
| ------ | --------------------------------- | --------------------- |
| POST   | `api/agents.php?action=login`     | Agent login           |
| POST   | `api/locations.php?action=submit` | Submit a new location |
| POST   | `api/vehicles.php?action=submit`  | Submit a new vehicle  |
| POST   | `api/routes.php?action=submit`    | Submit a new route    |

### Admin (requires admin session)

| Method | Endpoint                           | Description        |
| ------ | ---------------------------------- | ------------------ |
| POST   | `api/admins.php?action=login`      | Admin login        |
| POST   | `api/locations.php?action=approve` | Approve a location |
| POST   | `api/vehicles.php?action=approve`  | Approve a vehicle  |
| POST   | `api/routes.php?action=approve`    | Approve a route    |
| POST   | `api/alerts.php?action=create`     | Create an alert    |

---

## GPS Device Integration

Sawari accepts live GPS data from hardware devices via `POST /CCRC/api/gps-device.php`.

**Payload format:**

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

**Field mapping:** `bus_id` → `vehicle_id`, `speed` → `velocity`. The endpoint validates coordinates are within Nepal (26-31°N, 80-89°E), checks GPS quality via HDOP, and maintains a debug log at `logs/gps-device.json`.

---

## GPS Simulator (for testing)

```bash
# CLI — simulate vehicle 1 moving along its route at 25 km/h
php tools/gps-simulator.php --vehicle=1 --speed=25 --interval=3

# Browser
http://localhost/CCRC/tools/gps-simulator.php?vehicle_id=1&speed=25&action=info
```

---

## Key Features

- **Route Finding** — Direct routes and multi-bus transfers using database-stored route data
- **Live Bus Tracking** — 8-second polling with smooth CSS-animated marker movement
- **Nepal Fare Rules** — Base fare + per-km rate, rounded to nearest NPR 5, minimum NPR 20
- **Student/Elderly Discount** — 75% of standard fare displayed alongside regular price
- **Carbon Calculator** — CO₂ comparison between bus (0.089 kg/km) and car (0.21 kg/km)
- **Tourist Help Mode** — Boarding tips, conductor phrases, safety precautions
- **Smart Alerts** — Admin-managed route warnings shown on map
- **Community Suggestions** — Users can report missing stops or route corrections
- **Agent System** — Volunteers collect field data (stops, vehicles, routes) with leaderboard
- **Trip Logging & Feedback** — Star ratings, accuracy feedback, reviews
- **Responsive Design** — Mobile-first with safe-area-inset support, landscape mode, touch-friendly targets
- **Unique Map Markers** — SVG-based pins: blue (origin), orange (destination), green (boarding), amber (transfer), red (alerts), bus icon (vehicles)

---

## Design System

The app uses a custom CSS design system with CSS variables:

- **Primary color:** `#1A56DB` (blue)
- **Accent color:** `#E8590C` (orange)
- **Font:** Inter
- **Icons:** Feather Icons (`feather.replace({ 'stroke-width': 1.75 })`)
- **Button variants:** `btn-primary`, `btn-secondary`, `btn-ghost`, `btn-accent`, `btn-danger`, `btn-success`
- **Toast types:** `toast-success`, `toast-danger`, `toast-warning`, `toast-info`

---

## License

This project is developed for educational purposes as part of a college project (CCRC).
