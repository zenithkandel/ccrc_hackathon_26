# SAWARI

I have imagined Sawari as a user-guiding app which guides people around the crowded cities of Nepal by telling them about the bus routes on their way. Let's explain using an example. You are at your home, and you need to go to a job appointment, a friend's home, or anywhere else. Let's take your home as Point A and your friend's home as Point B.

Currently, you have options like taking a Pathao ride (a ride-booking app for bikes and taxis). Another option is to ask hundreds of people about the way. Yes, there is Google Maps, but it doesn't show the routes public transportation actually follows. It only shows general driving paths which may not align with real bus routes.

This is where Sawari comes in handy.

Unlike traditional navigation apps, Sawari uses **stored public transportation route data from its own database** and supports **live bus tracking** using real-time latitude, longitude, and velocity data sent by registered vehicles.

---

## How it works:

1. You enter your starting point (Point A).
2. You enter your destination (Point B).

3. **Sawari Processing:**
   1. Analyzes the distance and the location.
   2. Searches for bus stops and stations around Point A from the `locations` table.
   3. Searches for bus stops and stations around Point B.
   4. Checks the `routes` table to determine whether any stored route connects the two areas using the ordered `location_list`.

---

## ROUTE RESOLUTION USING STORED DATA

Sawari does not calculate arbitrary road routes for buses.

Instead, it:

- Uses the ordered `location_list` JSON stored in each route.
- Determines whether both stops lie on the same route.
- Identifies:
  - Boarding Stop Index
  - Destination Stop Index
- Validates direction using index order.

### If Direct Route Exists:

1. Selects the route.
2. Shows walking direction from Point A to nearest stored stop.
3. Displays:
   - Vehicle name
   - Vehicle image
   - Fare
   - What to say to the conductor
4. Displays intermediate stops in order from `location_list`.
5. Shows walking direction from final stop to Point B.

---

## Path Between Stops (Node-to-Node Calculation)

Routing logic comes strictly from stored database route data.

For visual map rendering between two consecutive stored stops:

- OSRM is used **only to calculate road path between those two stop coordinates**.

Example route:
Kalanki → RNAC → Bagbazar → Putalisadak → Naxal → Nagpokhari → Buspark

Sawari:

- Uses stored sequence for routing logic.
- Uses OSRM only between:
  - RNAC ↔ Bagbazar
  - Bagbazar ↔ Putalisadak
  - Putalisadak ↔ Naxal
  - etc.

---

## Live Bus Tracking Feature

If a vehicle has GPS enabled:

It continuously updates:

- `latitude`
- `longitude`
- `velocity`

Sawari:

1. Retrieves real-time coordinates from the `vehicles` table.
2. Matches vehicle to its active route using `used_routes`.
3. Compares live coordinates against ordered `location_list`.
4. Determines:
   - Current segment (between which two stops).
   - Nearest upcoming stop.
5. Estimates arrival time using:
   - Distance to next stop.
   - Current velocity.
6. Displays:
   - Moving vehicle marker snapped between route nodes.
   - "Approaching [Stop Name]".
   - Estimated arrival time.

Live tracking is dependent only on vehicles actively sending GPS data.

---

## If Direct Route Not Available:

1. Searches routes near Point A.
2. Searches routes near Point B.
3. Finds intersection stop.
4. Uses Dijkstra algorithm:
   - Nodes = locations
   - Edges = route connections
5. Determines optimal transfer.
6. Displays first bus + transfer + second bus.
7. Live tracking applies independently per vehicle if available.

---

## If Calculation Fails:

The app apologizes and suggests alternatives.

---

## After the Ride:

User provides:

- Rating
- Review
- Accuracy feedback

---

# EXTRA FEATURES AND UX ENHANCEMENTS

1. **Fare Calculation**
   - Government assigned rate index + practical increment.
   - Student/Elderly discount support.

2. **Tourist Help Mode**
   - What to say while boarding.
   - Safety precautions.

3. **Seamless Bus Switching**
   - Supports buses, micro-buses, tempos.

4. **Estimated Wait Time**
   - Based on route length and vehicle frequency.

5. **Rating, Review & Complaints**

6. **Smart Emergency Alerts**
   - Admin labels disturbed routes.

7. **Community Driven**
   - Missing stop suggestions.
   - Agents leaderboard.

8. **Carbon Emission Calculation**
   - Public vs ride-sharing comparison.

---

# Data Collection Methodology

## Agents

Volunteers collect data.

### Agent App Features

- Map Places (GPS logging)
- Vehicle Registration
- Route Mapping
- Duplicate check
- Service timing input

---

# Page Structure

1. Landing Page
2. Main Page (User Map)
3. Agent Dashboard
4. Admin Dashboard

---

# Tech Stack

| Layer       | Technologies                                     |
| ----------- | ------------------------------------------------ |
| Frontend    | HTML, CSS, JavaScript                            |
| Backend     | PHP                                              |
| Database    | MySQL                                            |
| Maps        | Leaflet, OpenStreetMap, OSRM (node-to-node only) |
| Geolocation | Browser Geolocation API                          |
| Algorithms  | Dijkstra, A\*                                    |

---

# DATABASE STRUCTURE

## 1. `locations`

| TITLE             | DESCRIPTION               | DATA-TYPE                             |
| ----------------- | ------------------------- | ------------------------------------- |
| location_id       | Unique identifier         | INT AUTO_INCREMENT                    |
| name              | Official stop name        | VARCHAR(255)                          |
| description       | Short description         | TEXT                                  |
| latitude          | Latitude coordinate       | DECIMAL(10,8)                         |
| longitude         | Longitude coordinate      | DECIMAL(11,8)                         |
| type              | stop or landmark          | ENUM('stop','landmark')               |
| status            | pending/approved/rejected | ENUM('pending','approved','rejected') |
| contribution_id   | Related contribution      | INT                                   |
| updated_by        | Agent ID                  | INT                                   |
| approved_by       | Admin ID                  | INT                                   |
| updated_at        | Approval timestamp        | DATETIME                              |
| departure_count   | Times used as start       | INT DEFAULT 0                         |
| destination_count | Times used as end         | INT DEFAULT 0                         |

---

## 2. `vehicles`

| TITLE           | DESCRIPTION               | DATA-TYPE                             |
| --------------- | ------------------------- | ------------------------------------- |
| vehicle_id      | Unique ID                 | INT AUTO_INCREMENT                    |
| name            | Vehicle/yatayat name      | VARCHAR(255)                          |
| description     | Short description         | TEXT                                  |
| image_path      | Vehicle image             | VARCHAR(255)                          |
| status          | pending/approved/rejected | ENUM('pending','approved','rejected') |
| contribution_id | Related contribution      | INT                                   |
| latitude        | Live GPS latitude         | TEXT                                  |
| longitude       | Live GPS longitude        | TEXT                                  |
| velocity        | Live speed data           | TEXT                                  |
| electric        | Is electric               | TINYINT(1) DEFAULT 0                  |
| updated_by      | Agent ID                  | INT                                   |
| approved_by     | Admin ID                  | INT                                   |
| updated_at      | Approval time             | DATETIME                              |
| used_routes     | JSON route usage          | JSON                                  |
| starts_at       | Service start             | TIME                                  |
| stops_at        | Service stop              | TIME                                  |

---

## 3. `routes`

| TITLE           | DESCRIPTION               | DATA-TYPE                             |
| --------------- | ------------------------- | ------------------------------------- |
| route_id        | Unique ID                 | INT AUTO_INCREMENT                    |
| name            | Route name                | VARCHAR(255)                          |
| description     | Short description         | TEXT                                  |
| status          | pending/approved/rejected | ENUM('pending','approved','rejected') |
| contribution_id | Related contribution      | INT                                   |
| updated_by      | Agent ID                  | INT                                   |
| approved_by     | Admin ID                  | INT                                   |
| updated_at      | Approval time             | DATETIME                              |
| location_list   | Ordered stop JSON         | JSON                                  |

---

## 4. `contributions`

(unchanged — included exactly as defined)

## 5. `agents`

(unchanged)

## 6. `admins`

(unchanged)

## 7. `alerts`

(unchanged)

## 8. `suggestions`

(unchanged)

## 9. `trips`

(unchanged)

---

# Technical Routing Philosophy

- Route flow is determined strictly from database.
- OSRM is used only between stored route nodes.
- Live tracking relies only on GPS-enabled vehicles.
- No arbitrary road routing determines bus movement.
