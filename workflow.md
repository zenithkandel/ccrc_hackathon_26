# SAWARI — Development Workflow

> All phases (0–7) are **complete**. This document serves as a record of the development process and a reference for future enhancements.

---

## Project Structure

```
CCRC/
├── index.php                   ← Landing page
├── setup.php                   ← One-click database installer & seed data
├── schema.sql                  ← Database schema (9 tables)
├── test-data.sql               ← Sample data for testing
├── README.md                   ← Project documentation
├── vision.md                   ← Product vision & architecture
├── workflow.md                 ← This file
│
├── assets/
│   ├── css/
│   │   ├── global.css          ← Design tokens, reset, typography, layout utilities
│   │   ├── components.css      ← Reusable UI components (buttons, cards, forms, modals, toasts)
│   │   ├── map.css             ← Main user page + responsive + marker styles
│   │   ├── agent.css           ← Agent dashboard styles
│   │   └── admin.css           ← Admin dashboard styles
│   ├── js/
│   │   ├── map.js              ← Leaflet map init, SVG markers, route polyline rendering
│   │   ├── search.js           ← Point A/B input, autocomplete, geocoding, trip logging
│   │   ├── routing.js          ← Route resolution display, fare cards, conductor tips
│   │   ├── tracking.js         ← Live vehicle tracking (8s polling, smooth animation)
│   │   ├── agent.js            ← Agent dashboard logic
│   │   └── admin.js            ← Admin dashboard logic
│   └── images/
│       └── vehicles/           ← Uploaded vehicle images
│
├── api/
│   ├── config.php              ← DB connection, constants, helpers
│   ├── locations.php           ← CRUD + search for locations
│   ├── routes.php              ← CRUD + route resolution
│   ├── vehicles.php            ← CRUD + live GPS updates + simulator endpoint
│   ├── trips.php               ← Trip logging, ratings, reviews
│   ├── contributions.php       ← Contribution management
│   ├── alerts.php              ← Alert CRUD
│   ├── suggestions.php         ← Suggestion submissions
│   ├── agents.php              ← Agent auth + profile
│   ├── admins.php              ← Admin auth + management
│   ├── routing-engine.php      ← Route resolution + fare calculation
│   └── gps-device.php          ← Hardware GPS device receiver
│
├── includes/
│   ├── header.php              ← HTML head, navigation
│   └── footer.php              ← Footer, scripts
│
├── pages/
│   ├── map.php                 ← Main user page (full-screen map)
│   ├── agent/
│   │   ├── login.php
│   │   ├── dashboard.php
│   │   ├── add-location.php    ← Map pinning + existing stops toggle
│   │   ├── add-vehicle.php
│   │   ├── add-route.php
│   │   └── my-contributions.php
│   └── admin/
│       ├── login.php
│       ├── dashboard.php
│       ├── manage-locations.php
│       ├── manage-vehicles.php
│       ├── manage-routes.php
│       ├── manage-agents.php
│       ├── manage-alerts.php
│       ├── contributions.php
│       └── suggestions.php
│
├── tools/
│   └── gps-simulator.php       ← GPS testing tool (simulates vehicle movement)
│
├── logs/
│   └── gps-device.json         ← Rolling GPS hardware log (500 entries)
│
└── uploads/
    └── vehicles/               ← Vehicle image uploads
```

---

## Phase 0 — Foundation Setup ✅

**Goal:** Get the development environment and database ready.

| #   | Task                                              | Status |
| --- | ------------------------------------------------- | ------ |
| 0.1 | Set up XAMPP (Apache + MySQL running)             | ✅     |
| 0.2 | Create the `sawari` database using `schema.sql`   | ✅     |
| 0.3 | Create `api/config.php` (DB connection + helpers) | ✅     |
| 0.4 | Create `assets/css/global.css` (reset, variables) | ✅     |
| 0.5 | Create folder structure                           | ✅     |
| 0.6 | Insert seed admin account                         | ✅     |
| 0.7 | Test DB connection                                | ✅     |

**Deliverable:** ✅ Working dev environment, database created, folder structure ready. `setup.php` handles one-click installation.

---

## Phase 1 — Admin Dashboard ✅

### 1A — Admin Auth

| #    | Task                                       | Status |
| ---- | ------------------------------------------ | ------ |
| 1A.1 | Admin login page (`pages/admin/login.php`) | ✅     |
| 1A.2 | `api/admins.php` — login, session, logout  | ✅     |
| 1A.3 | Session-based auth guard for admin pages   | ✅     |

### 1B — Core Admin CRUD

| #    | Task                                                                   | Status |
| ---- | ---------------------------------------------------------------------- | ------ |
| 1B.1 | Admin dashboard landing (`pages/admin/dashboard.php`) — stats overview | ✅     |
| 1B.2 | Manage Locations — list, view on map, approve/reject pending           | ✅     |
| 1B.3 | Manage Vehicles — list, view image, approve/reject pending             | ✅     |
| 1B.4 | Manage Routes — list, visualize on map, approve/reject                 | ✅     |
| 1B.5 | Manage Agents — list, view contributions, suspend/activate             | ✅     |

### 1C — Admin Extra Features

| #    | Task                                              | Status |
| ---- | ------------------------------------------------- | ------ |
| 1C.1 | Contributions review page (unified pending queue) | ✅     |
| 1C.2 | Alerts management (create, resolve, expire)       | ✅     |
| 1C.3 | Suggestions inbox (review community suggestions)  | ✅     |

**Deliverable:** ✅ Fully functional admin panel that can approve/reject all data.

---

## Phase 2 — Agent Dashboard ✅

### 2A — Agent Auth

| #    | Task                                        | Status |
| ---- | ------------------------------------------- | ------ |
| 2A.1 | Agent registration page                     | ✅     |
| 2A.2 | Agent login page (`pages/agent/login.php`)  | ✅     |
| 2A.3 | `api/agents.php` — register, login, session | ✅     |
| 2A.4 | Session-based auth guard for agent pages    | ✅     |

### 2B — Data Collection Features

| #    | Task                                                              | Status |
| ---- | ----------------------------------------------------------------- | ------ |
| 2B.1 | Agent dashboard landing — stats, points, rank                     | ✅     |
| 2B.2 | Add Location — map click to pin, GPS logging, name input          | ✅     |
| 2B.3 | Add Vehicle — form with image upload, route assignment            | ✅     |
| 2B.4 | Add Route — select ordered stops on map, build `location_list`    | ✅     |
| 2B.5 | My Contributions — list all with status                           | ✅     |
| 2B.6 | Duplicate check — warn if location already exists nearby (< 300m) | ✅     |

### 2C — Populate Test Data

| #    | Task                                            | Status |
| ---- | ----------------------------------------------- | ------ |
| 2C.1 | Add 10–15 real Kathmandu bus stops as locations | ✅     |
| 2C.2 | Add 3–5 real vehicles                           | ✅     |
| 2C.3 | Add 2–3 real routes (e.g., Kalanki–Buspark)     | ✅     |
| 2C.4 | Approve all via admin panel                     | ✅     |

**Deliverable:** ✅ Agent can submit locations, vehicles, routes. Test data exists and is approved via `setup.php`.

---

## Phase 3 — Main User Page ✅

### 3A — Map Foundation

| #    | Task                                                           | Status |
| ---- | -------------------------------------------------------------- | ------ |
| 3A.1 | `pages/map.php` — full-screen Leaflet map, OpenStreetMap tiles | ✅     |
| 3A.2 | User geolocation (Browser API) — center map on user            | ✅     |
| 3A.3 | Display approved stops as markers on the map                   | ✅     |
| 3A.4 | Point A / Point B input UI (search bar + map click)            | ✅     |
| 3A.5 | Location search autocomplete from `locations` table            | ✅     |

### 3B — Route Resolution Engine

| #    | Task                                                                                      | Status |
| ---- | ----------------------------------------------------------------------------------------- | ------ |
| 3B.1 | `api/routing-engine.php` — find nearest stops to A and B                                  | ✅     |
| 3B.2 | Direct route detection — check if A-stop and B-stop exist on same route's `location_list` | ✅     |
| 3B.3 | Direction validation — verify index order (A before B)                                    | ✅     |
| 3B.4 | Multi-route with transfer — find shared intersection stops                                | ✅     |
| 3B.5 | Return route result as JSON (stops, vehicle, fare, instructions)                          | ✅     |

### 3C — Route Display & Visualization

| #    | Task                                                          | Status |
| ---- | ------------------------------------------------------------- | ------ |
| 3C.1 | OSRM integration — fetch road path between consecutive stops  | ✅     |
| 3C.2 | Draw polyline route on map (stop-to-stop via OSRM)            | ✅     |
| 3C.3 | Show walking directions (A → boarding stop, final stop → B)   | ✅     |
| 3C.4 | Result panel — vehicle name, hero image, fare, conductor tips | ✅     |
| 3C.5 | Intermediate stops list display                               | ✅     |
| 3C.6 | Transfer display (if multi-route)                             | ✅     |

### 3D — Fare Calculation

| #    | Task                                                          | Status |
| ---- | ------------------------------------------------------------- | ------ |
| 3D.1 | Base fare + per-km calculation, rounded to multiples of 5 NPR | ✅     |
| 3D.2 | Student/elderly discount toggle (75% fare, min 15 NPR)        | ✅     |
| 3D.3 | Display estimated fare in result panel                        | ✅     |

**Deliverable:** ✅ User can enter A and B, see the bus route on the map, get fare/vehicle info, and follow directions.

---

## Phase 4 — Live Bus Tracking ✅

| #   | Task                                                                                     | Status |
| --- | ---------------------------------------------------------------------------------------- | ------ |
| 4.1 | GPS update endpoint (`api/vehicles.php?action=gps_update`) — receives lat/lng/velocity   | ✅     |
| 4.2 | Polling mechanism — `tracking.js` fetches vehicle positions every 8 seconds              | ✅     |
| 4.3 | Moving vehicle markers with smooth animation (cubic ease-out, 2s)                        | ✅     |
| 4.4 | "Approaching [Stop Name]" logic — compare position to `location_list`                    | ✅     |
| 4.5 | ETA calculation — distance to next stop ÷ velocity                                       | ✅     |
| 4.6 | Display ETA and approaching info in UI                                                   | ✅     |
| 4.7 | GPS simulator script for testing (`tools/gps-simulator.php`)                             | ✅     |
| 4.8 | Hardware GPS device receiver (`api/gps-device.php`) — accepts real GPS hardware payloads | ✅     |

**Deliverable:** ✅ Live vehicles on map with smooth movement, ETA, and support for real GPS hardware devices.

---

## Phase 5 — Trip Logging & Feedback ✅

| #   | Task                                                           | Status |
| --- | -------------------------------------------------------------- | ------ |
| 5.1 | Generate session_id for anonymous users                        | ✅     |
| 5.2 | Log trip when user selects a route                             | ✅     |
| 5.3 | Post-ride prompt — rating, review, accuracy (5s delay)         | ✅     |
| 5.4 | `api/trips.php` — save trip + feedback                         | ✅     |
| 5.5 | Increment `departure_count` / `destination_count` on locations | ✅     |

**Deliverable:** ✅ Trips are recorded, users can rate and review.

---

## Phase 6 — Extra Features ✅

| #   | Task                                                                | Status |
| --- | ------------------------------------------------------------------- | ------ |
| 6.1 | Tourist Help Mode — boarding tips, "Roknu!" stop signal, safety     | ✅     |
| 6.2 | Smart Emergency Alerts — show active alerts on map with SVG markers | ✅     |
| 6.3 | Community Suggestions — user suggestion form                        | ✅     |
| 6.4 | Carbon Emission Calculator (bus 0.089 / car 0.21 kg CO₂/km)         | ✅     |
| 6.5 | Estimated Wait Time (based on route frequency)                      | ✅     |
| 6.6 | Agent Leaderboard on agent dashboard                                | ✅     |

**Deliverable:** ✅ All UX enhancements from the vision are implemented.

---

## Phase 7 — Polish & Integration ✅

| #   | Task                                                                     | Status |
| --- | ------------------------------------------------------------------------ | ------ |
| 7.1 | Connect landing page → main map page (button/link)                       | ✅     |
| 7.2 | Responsive design pass (768px / 640px / 380px + landscape + safe-area)   | ✅     |
| 7.3 | Error handling throughout (empty results, no routes found, API failures) | ✅     |
| 7.4 | Loading states and animations                                            | ✅     |
| 7.5 | Cross-browser testing                                                    | ✅     |
| 7.6 | Security hardening — SQL injection, XSS, CSRF                            | ✅     |
| 7.7 | Performance — DB indexing review, query optimization                     | ✅     |
| 7.8 | Final testing with real Kathmandu route data                             | ✅     |

**Deliverable:** ✅ Ship-ready application.

---

## Post-Phase Enhancements ✅

Additional improvements made after the core phases were completed:

| #   | Enhancement                                                         | Status |
| --- | ------------------------------------------------------------------- | ------ |
| E.1 | Smooth vehicle marker animation (requestAnimationFrame + ease-out)  | ✅     |
| E.2 | Vehicle hero image + professional Feather icons (replaced emojis)   | ✅     |
| E.3 | Nepal fare rules (multiples of 5 NPR, min 20, student/elderly 75%)  | ✅     |
| E.4 | Unique SVG-based map marker icons (teardrop pins, circle stop dots) | ✅     |
| E.5 | Comprehensive mobile-responsive CSS overhaul                        | ✅     |
| E.6 | Hardware GPS device API endpoint (`api/gps-device.php`)             | ✅     |
| E.7 | Agent add-location: toggle to show existing approved stops          | ✅     |
| E.8 | One-click setup installer (`setup.php`)                             | ✅     |

---

## Development Order Summary

```
Phase 0  →  Foundation Setup           ✅
Phase 1  →  Admin Dashboard            ✅
Phase 2  →  Agent Dashboard            ✅
Phase 3  →  Main User Page             ✅
Phase 4  →  Live Bus Tracking          ✅
Phase 5  →  Trip Logging & Feedback    ✅
Phase 6  →  Extra Features             ✅
Phase 7  →  Polish & Integration       ✅
Post     →  Enhancements               ✅
```

> **All phases complete.** The application is fully functional with live GPS tracking, route finding, fare calculation, trip logging, and a complete admin/agent workflow.

---

## Development Notes

- **OSRM Server:** Using public demo at `https://router.project-osrm.org`. For production, self-host OSRM with Nepal OSM data.
- **Map Tiles:** OpenStreetMap tiles via `https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png`.
- **Default Map Center:** Kathmandu — `[27.7172, 85.3240]`, zoom level 13.
- **Session Management:** PHP native sessions for both agent and admin dashboards.
- **Image Uploads:** Stored in `uploads/vehicles/`, path referenced in DB.
- **JSON Fields:** Use `JSON_CONTAINS()` and `JSON_EXTRACT()` MySQL functions for querying `location_list` and `used_routes`.
- **GPS Polling:** 8-second interval, 2-minute stale threshold for hiding inactive vehicles.
- **GPS Hardware:** Accepts JSON payloads at `POST /api/gps-device.php` — maps `bus_id` → `vehicle_id`, validates Nepal bounding box.
- **Design System:** CSS custom properties, Inter font, Feather Icons, primary `#1A56DB`, accent `#E8590C`.
- **Default Credentials (via setup.php):** Admin: `admin@sawari.com` / `admin123`. Agents: `ram@sawari.com`, `sita@sawari.com`, `bikash@sawari.com` / `agent123`.
