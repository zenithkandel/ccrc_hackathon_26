# SAWARI

I have imagined Sawari as a user-guiding app which guides people around the crowded cities of Nepal by telling them about the bus routes on their way. Let’s explain using an example. You are at your home, and you need to go to a job appointment, a friend's home, or anywhere else. Let's take your home as Point A and your friend's home as Point B. Currently, you have options like taking a Pathao ride (a ride-booking app for bikes and taxis). Another option is to ask hundreds of people about the way. Yes, there is Google Maps, but it doesn't show the routes public transportation actually follows. It just shows general driving routes.

This is where Sawari comes in handy.

Unlike traditional navigation apps, Sawari uses **stored public transport route data from its own database** and now supports **live bus tracking** using real-time latitude, longitude, and velocity data sent by registered vehicles.

---

## How it works:

1. You enter your starting point (Point A).
2. You enter your destination (Point B).

3. **Sawari Processing:**
   1. Analyzes the distance and location.
   2. Searches for nearby approved bus stops from the `locations` table around Point A.
   3. Searches for nearby approved bus stops around Point B.
   4. Checks the `routes` table to determine if any stored route connects the two areas using its `location_list`.

---

## ROUTE RESOLUTION USING STORED DATA

Instead of calculating arbitrary driving paths, Sawari:

- Uses the ordered `location_list` JSON stored inside each route.
- Determines whether Point A and Point B lie on the same route (forward direction).
- Identifies:
  - Boarding Stop Index
  - Destination Stop Index
- Confirms valid travel direction based on index order.

If both points lie on the same route in correct sequence:

1. Selects that route.
2. Shows walking direction from Point A to the nearest stored stop (using simple distance calculation).
3. Displays:
   - Vehicle name
   - Vehicle image
   - Fare
   - What to say to the conductor
4. Displays all intermediate stops in sequence from `location_list`.

---

## Path Between Stops (Node-to-Node Calculation)

The main routing is determined strictly using stored route data.

For visual navigation between two consecutive stops (for example, Bagbazar → Putalisadak):

- OSRM is used **only to calculate the road path between those two stored coordinates**.
- This ensures:
  - Realistic path rendering
  - Accurate turn-by-turn guidance between route nodes

Example:

If a route says:
Kalanki → RNAC → Bagbazar → Putalisadak → Naxal → Nagpokhari → Buspark

Sawari:

- Uses the stored stop sequence for logical routing.
- Uses OSRM only between:
  - RNAC ↔ Bagbazar
  - Bagbazar ↔ Putalisadak
  - Putalisadak ↔ Naxal
  - etc.

---

## Live Bus Tracking Feature

If a vehicle has GPS enabled:

- It continuously updates:
  - `latitude`
  - `longitude`
  - `velocity`

Sawari:

1. Retrieves the vehicle’s real-time coordinates from the `vehicles` table.
2. Matches the vehicle to its active route using the `used_routes` field.
3. Compares the live coordinates with the ordered `location_list` of that route.
4. Determines:
   - The nearest upcoming stop.
   - Whether the vehicle has passed or is approaching a stop.
5. Estimates arrival time using:
   - Distance to next stop.
   - Current velocity.
6. Displays:
   - Current moving bus marker on map.
   - "Approaching [Stop Name]".
   - Estimated arrival time.

The vehicle marker is logically constrained between two route nodes to prevent map deviation from the stored route.

---

## If Direct Route Not Available:

1. Searches for routes near Point A.
2. Searches for routes near Point B.
3. Finds an intersection stop where two routes connect.
4. Uses Dijkstra algorithm on stored route graph:
   - Nodes = locations
   - Edges = route connections
5. Determines optimal transfer stop.
6. Shows:
   - First bus details
   - Transfer location
   - Second bus details
7. Live tracking works independently for each vehicle if GPS data is available.

---

## If Calculation Fails:

The app politely apologizes and suggests alternative transportation options.

---

## After the Ride:

- User gives:
  - Rating
  - Review
  - Accuracy feedback

---

## Technical Routing Philosophy (Updated)

- Route logic comes strictly from database (`routes.location_list`).
- No arbitrary road routing is used for deciding bus flow.
- OSRM is used only for:
  - Rendering realistic paths between consecutive stored stops.
  - Walking navigation to/from stops.
- Live tracking relies entirely on vehicle-provided latitude, longitude, and velocity.
