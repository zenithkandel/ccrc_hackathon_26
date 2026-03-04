# 🚌 Sawari — Navigate Nepal's Public Transport

Sawari is a web-based public transport navigation system for Kathmandu Valley, Nepal. It helps commuters find bus routes, estimate fares, get walking directions, and navigate the city's public transportation network.

## Features

- **Route Finding** — Dijkstra-based pathfinding with transfer optimization
- **Fare Estimation** — Per-km fare calculation with student/elderly discounts
- **Walking Directions** — OSRM-powered walking guidance to/from bus stops
- **Multi-Bus Transfers** — Smart routing when no direct bus is available 
- **Tourist Help Mode** — Nepali phrases and tips for visitors
- **Emergency Alerts** — Real-time alerts for route disruptions
- **Carbon Comparison** — CO₂ savings from choosing public transport
- **Community Driven** — Volunteer agents contribute route data
- **Interactive Map** — Leaflet.js map with route visualization
- **Live Bus Tracking** — Real-time GPS positions of tracked vehicles on the map (auto-refresh every 10s)

## Tech Stack

- **Backend:** PHP 8+ (vanilla, no framework)
- **Database:** MySQL 8+ (PDO)
- **Frontend:** HTML5, CSS3, vanilla JavaScript
- **Maps:** Leaflet.js + OpenStreetMap tiles
- **GPS Tracking:** Vehicle GPS modules reporting via HTTP API
- **Walking API:** OSRM (public instance)
- **Server:** Apache (XAMPP)

## Prerequisites

- [XAMPP](https://www.apachefriends.org/) with PHP 8.0+ and MySQL 8.0+
- Apache with `mod_rewrite` enabled
- PHP extensions: `pdo_mysql`, `json`, `mbstring`

## Installation

### 1. Clone / Copy to XAMPP

```
Copy the project folder to: c:\xampp\htdocs\CCRC\
```

### 2. Start XAMPP Services

Start **Apache** and **MySQL** from the XAMPP Control Panel.

### 3. Create Database & Tables

Open phpMyAdmin (`http://localhost/phpmyadmin`) or use MySQL CLI:

```sql
-- Run schema.sql to create the database and tables
SOURCE c:/xampp/htdocs/CCRC/schema.sql;

-- Run indexes.sql for performance indexes
SOURCE c:/xampp/htdocs/CCRC/indexes.sql;
```

### 4. Seed Sample Data

```bash
php seed.php
```

Or visit: `http://localhost/CCRC/seed.php`

### 5. Access the Application

- **Landing Page:** http://localhost/CCRC/
- **Map / Route Finder:** http://localhost/CCRC/pages/map.php
- **Admin Login:** http://localhost/CCRC/pages/auth/login.php
- **Agent Registration:** http://localhost/CCRC/pages/auth/register.php

## Default Accounts

| Role  | Email             | Password  |
| ----- | ----------------- | --------- |
| Admin | admin@sawari.com  | Admin@123 |
| Agent | agent1@sawari.com | Agent@123 |
| Agent | agent2@sawari.com | Agent@123 |
| Agent | agent3@sawari.com | Agent@123 |
| Agent | agent4@sawari.com | Agent@123 |
| Agent | agent5@sawari.com | Agent@123 |

## Project Structure

```
CCRC/
├── algorithms/          # Route-finding engine
│   ├── dijkstra.php     # Modified Dijkstra with transfer penalties
│   ├── graph.php        # Transit graph construction
│   ├── helpers.php      # Haversine, fare calc, OSRM walking
│   └── pathfinder.php   # Main orchestrator
├── api/                 # REST API endpoints
│   ├── agents/          # Agent CRUD
│   ├── alerts/          # Alert CRUD
│   ├── auth/            # Login, register, logout
│   ├── contributions/   # Contribution review
│   ├── locations/       # Location CRUD
│   ├── routes/          # Route CRUD
│   ├── search/          # Route search & autocomplete
│   ├── suggestions/     # User feedback
│   ├── trips/           # Trip logging
│   └── vehicles/        # Vehicle CRUD
├── assets/
│   ├── css/             # Stylesheets (global, admin, agent, auth, landing, map)
│   ├── images/          # Static images & uploads
│   └── js/              # JavaScript (utils, admin, agent, map, search)
├── config/
│   ├── constants.php    # App-wide constants
│   ├── database.php     # PDO singleton
│   └── session.php      # Session management
├── includes/
│   ├── auth.php         # Authentication helpers
│   ├── functions.php    # Utility functions
│   ├── header.php       # HTML template header
│   ├── footer.php       # HTML template footer
│   ├── validation.php   # Input validation
│   ├── admin-sidebar.php
│   └── agent-sidebar.php
├── pages/
│   ├── admin/           # Admin dashboard pages
│   ├── agent/           # Agent dashboard pages
│   ├── auth/            # Login, register, logout pages
│   └── map.php          # Interactive map page
├── index.php            # Landing page
├── schema.sql           # Database schema
├── indexes.sql          # Performance indexes
├── seed.php             # Data seeding script
└── README.md
```

## How It Works

### Route-Finding Algorithm

1. **Graph Construction** — Builds an adjacency list from approved routes, with Haversine distances as edge weights
2. **Modified Dijkstra** — Uses state-space expansion `(location_id, route_id)` to correctly handle transfer penalties
3. **Path Parsing** — Groups consecutive edges into riding segments, inserts transfer instructions at route changes
4. **Walking Integration** — OSRM API provides walking directions for first/last mile
5. **Fare Calculation** — Base rate (NPR 15) + per-km (NPR 1.8/km), rounded to nearest NPR 5

### User Roles

- **Public Users** — Search routes, view results, submit feedback (no login)
- **Agents** — Register, contribute locations/routes/vehicles, track contributions
- **Admins** — Review contributions, manage alerts, view suggestions, manage system

### Contribution Workflow

1. Agent proposes a new location/route/vehicle → status: `pending`
2. Admin reviews and approves or rejects → status: `approved`/`rejected`
3. Approved data becomes available for route searching

## Configuration

Key settings in `config/constants.php`:

| Constant               | Default | Description                  |
| ---------------------- | ------- | ---------------------------- |
| FARE_BASE_RATE         | 15      | Base fare in NPR             |
| FARE_PER_KM            | 1.8     | Per-kilometer rate in NPR    |
| STUDENT_DISCOUNT       | 0.50    | 50% discount for students    |
| ELDERLY_DISCOUNT       | 0.50    | 50% discount for elderly     |
| TRANSFER_PENALTY_KM    | 2.0     | Transfer penalty (km equiv)  |
| NEAREST_STOP_RADIUS_KM | 2.0     | Max walking distance to stop |
| AVG_BUS_SPEED_KMH      | 15      | Average bus speed for ETA    |

## API Endpoints

| Method | Endpoint                        | Description        | Auth   |
| ------ | ------------------------------- | ------------------ | ------ |
| POST   | `/api/auth/login.php`           | Login              | No     |
| POST   | `/api/auth/register.php`        | Register agent     | No     |
| GET    | `/api/search/locations.php?q=X` | Autocomplete       | Public |
| POST   | `/api/search/find-route.php`    | Find route A→B     | Public |
| GET    | `/api/locations/read.php`       | List locations     | Auth   |
| POST   | `/api/locations/create.php`     | Add location       | Auth   |
| GET    | `/api/routes/read.php`          | List routes        | Auth   |
| POST   | `/api/routes/create.php`        | Add route          | Auth   |
| GET    | `/api/vehicles/read.php`        | List vehicles      | Auth   |
| POST   | `/api/vehicles/create.php`      | Add vehicle        | Auth   |
| GET    | `/api/alerts/read.php`          | List alerts        | Public |
| POST   | `/api/suggestions/create.php`   | Submit feedback    | Public |
| GET    | `/api/vehicles/tracking.php`    | Live GPS positions | Public |
| POST   | `/api/vehicles/gps-update.php`  | Push GPS position  | Device |

## License

This project is developed for educational purposes.

---

Built with ❤️ for Nepal's commuters.

