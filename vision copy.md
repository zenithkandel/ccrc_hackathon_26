# SAWARI

I have imagined Sawari as a user-guiding app which guides people around the crowded cities of Nepal by telling them about the bus routes on their way. Let's explain using an example. You are at your home, and you need to go to a job appointment, a friend's home, or anywhere else. Let's take your home as Point A and your friend's home as Point B. Currently, you have options like taking a Pathao ride (a ride-booking app for bikes and taxis). Another option is to ask hundreds of people about the way. Yes, there is Google Maps, but it doesn't show the routes public transportation uses. It just shows the driving route where there might or might not be public transportation services. This is where Sawari comes in handy.

## How it works:

1.  You enter your starting point (Point A).
2.  You enter your destination (Point B).
3.  **Sawari Processing:**
    1.  Analyzes the distance and the location.
    2.  Searches for bus stops and stations around Point A.
    3.  Searches for bus stops and stations around Point B.
    4.  Checks if there is any public transportation going from the nearest bus station at Point A to the nearest bus station at Point B.
    5.  **If yes:**
        1.  It decides the bus stations and the route.
        2.  It gives the walking direction from Point A to Bus Station A.
        3.  It shows which bus to ride (Mahanagar, Sajha, Nepal Yatayat, Araniko Yatayat, etc.) along with their pictures for identification.
        4.  It shows the price to pay and what to say to the conductor to get off at the designated station.
        5.  After reaching Station B, it shows the walking direction from Station B to Point B.
    6.  **If not:**
        1.  It searches for the nearest bus station at Point A from where buses towards Point B are available.
        2.  It also searches for the nearest bus station at Point B from where buses towards Point A are available.
        3.  It retrieves both routes and calculates where they meet, deciding where the user should drop off and change buses using advanced math algorithms like _Dijkstra_.
        4.  In exchanging buses, when the user says they have gotten off the first bus, it shows the picture and name of the bus to take next.
        5.  It shows which bus to ride along with pictures for identification.
        6.  It shows the price to pay and what to say to the conductor.
        7.  After reaching Station B, it shows the walking direction to Point B.
    7.  If calculation fails, the app apologizes to the user.
4.  After the ride, the app asks for a review, accuracy rating, and suggestions.
5.  That’s all 😊

## EXTRA FEATURES AND UX ENHANCEMENTS

1.  **Fare Calculation:**
    1.  Government assigned rate index + normal rate increment.
    2.  (e.g., if government rate is 13rs, bus takes 15rs. We account for this).
    3.  User selects if they are a student or elderly for discounts.
2.  **Tourist Help Mode:**
    1.  What to say while getting on/off the bus.
    2.  Extra precautions (pickpockets, haphazard driving, etc.).
3.  **Seamless Bus Switching Mechanism:**
    1.  Accounts for buses, micro-buses, and tempos (tuk-tuks).
4.  **Estimated Wait Time:**
    1.  Calculates route length and number of buses to estimate frequency (e.g., "a bus should come every 3 minutes").
5.  **Rating, Review, & Complaints:**
    1.  Robust feedback system.
6.  **Smart Emergency Alerts:**
    1.  Admin can label routes as "disturbed" (strikes, accidents) to trigger rerouting.
7.  **Community Driven:**
    1.  Users suggest missing stops or wrong routes.
    2.  Agents Leaderboard to motivate contributors.
8.  **Carbon Emission Calculation:**
    1.  Compares carbon footprint of public transport vs. ride-sharing to encourage green travel.

## Data Collection Methodology

- **Agents:** The main source of data. Volunteers (Agents) travel around the valley feeding data to servers.
- **Agent App Features:**
  - **Map Places:**
    - Log GPS coordinates of stops.
    - Label places (e.g., "Charkhal 1", "Charkhal 2" for directional stops).
    - Add descriptions (e.g., "Near US Embassy").
  - **Vehicle Registration:**
    - Photo, name, and description of the vehicle.
    - Precautions (e.g., "Overspeeding common").
    - Service start/end times.
    - Duplicate check to prevent redundancy.
  - **Route Mapping:**
    - Create routes connecting mapped places.
    - Drag-and-drop UI for ordering stops.
    - Assign routes to vehicles.

## Page Structure

1.  **Landing Page:** Project intro, Agents Leaderboard.
2.  **Main Page (User):** Full-screen map, search bar (Start/End), floating details bar (route info, fare, images).
3.  **Agent Dashboard:** Manage Vehicles, Places, Routes, Profile.
4.  **Admin Dashboard:** CRUD for Vehicles, Places, Routes; Report/Proposal handling; User management.

## Tech Stack

| Layer           | Technologies                  |
| --------------- | ----------------------------- |
| **Frontend**    | HTML, CSS, JavaScript         |
| **Backend**     | PHP                           |
| **Database**    | MySQL                         |
| **Maps**        | Leaflet, OpenStreetMaps, OSRM |
| **Geolocation** | Browser Geolocation API       |
| **Algorithms**  | Dijkstra, A\* (Pathfinding)   |

## DATABASE STRUCTURE

### 1. `locations`

Stores all the location data of the places registered to the system.

| TITLE               | DESCRIPTION                                                                                       | DATA-TYPE                                 |
| ------------------- | ------------------------------------------------------------------------------------------------- | ----------------------------------------- |
| `location_id`       | Stores the unique identifier of the location entry.                                               | `INT AUTO_INCREMENT`                      |
| `name`              | The official name of the place or bus stop.                                                       | `VARCHAR(255)`                            |
| `description`       | A short description of the place, usually between 5 to 10 words.                                  | `TEXT`                                    |
| `latitude`          | The pin-point latitude coordinate of the location.                                                | `DECIMAL(10, 8)`                          |
| `longitude`         | The pin-point longitude coordinate of the location.                                               | `DECIMAL(11, 8)`                          |
| `type`              | The type of the location, such as a designated bus stop or a general landmark.                    | `ENUM('stop', 'landmark')`                |
| `status`            | The status of the entry’s visibility and authenticity (pending, approved, or rejected).           | `ENUM('pending', 'approved', 'rejected')` |
| `contribution_id`   | The index to the contribution entry associated with this location from the `contributions` table. | `INT`                                     |
| `updated_by`        | The index to the agent’s unique identifier from the `agents` table who last modified the entry.   | `INT`                                     |
| `approved_by`       | The index to the admin’s unique identifier from the `admins` table who approved the entry.        | `INT`                                     |
| `updated_at`        | The timestamp indicating the date and time of the approval from the admin.                        | `DATETIME`                                |
| `departure_count`   | The number of times this location has been used as a departure point to find routes.              | `INT DEFAULT 0`                           |
| `destination_count` | The number of times this location has been used as a destination point to find routes.            | `INT DEFAULT 0`                           |

---

### 2. `vehicles`

Stores all the data related to the vehicles or yatayats registered in our system.

| TITLE             | DESCRIPTION                                                                                                                                                                   | DATA-TYPE                                 |
| ----------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------- |
| `vehicle_id`      | Stores the unique identifier of the vehicle entry.                                                                                                                            | `INT AUTO_INCREMENT`                      |
| `name`            | The name of the vehicle or the yatayat company.                                                                                                                               | `VARCHAR(255)`                            |
| `description`     | A short description of the yatayat or vehicle (approx. 5 to 10 words).                                                                                                        | `TEXT`                                    |
| `image_path`      | The file path to the image showing the vehicle or the yatayat for user identification.                                                                                        | `VARCHAR(255)`                            |
| `status`          | The status of the entry’s visibility and authenticity (pending, approved, or rejected).                                                                                       | `ENUM('pending', 'approved', 'rejected')` |
| `contribution_id` | The index to the contribution entry associated with this vehicle from the `contributions` table.                                                                              | `INT`                                     |
| `updated_by`      | The index to the agent’s unique identifier from the `agents` table who last updated the entry.                                                                                | `INT`                                     |
| `approved_by`     | The index to the admin’s unique identifier from the `admins` table who approved the entry.                                                                                    | `INT`                                     |
| `updated_at`      | The timestamp indicating the date and time of the approval from the admin.                                                                                                    | `DATETIME`                                |
| `used_routes`     | A JSON array of the routes that this vehicle moves in along with the number of vehicles in that route. Example: `[{"route_id": 1, "count": 6}, {"route_id": 3, "count": 4}]`. | `JSON`                                    |
| `starts_at`       | The time at which this vehicle service starts operating (e.g., 6:00 AM).                                                                                                      | `TIME`                                    |
| `stops_at`        | The time at which this vehicle service stops operating (e.g., 9:00 PM).                                                                                                       | `TIME`                                    |

---

### 3. `routes`

Stores all the data regarding the bus routes, which are sequences of mapped locations.

| TITLE             | DESCRIPTION                                                                                                                                                              | DATA-TYPE                                 |
| ----------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ----------------------------------------- |
| `route_id`        | Stores the unique identifier of the route entry.                                                                                                                         | `INT AUTO_INCREMENT`                      |
| `name`            | The name of the route in a format indicating start and end points (e.g., Greenland - Sundhara).                                                                          | `VARCHAR(255)`                            |
| `description`     | A short description of the specific route, usually between 5 to 10 words.                                                                                                | `TEXT`                                    |
| `image_path`      | The file path to the image representing the route map or the visual layout of the route.                                                                                 | `VARCHAR(255)`                            |
| `status`          | The status of the entry’s visibility and authenticity (pending, approved, or rejected).                                                                                  | `ENUM('pending', 'approved', 'rejected')` |
| `contribution_id` | The index to the contribution entry associated with this route from the `contributions` table.                                                                           | `INT`                                     |
| `updated_by`      | The index to the agent’s unique identifier from the `agents` table who created or updated the route.                                                                     | `INT`                                     |
| `approved_by`     | The index to the admin’s unique identifier from the `admins` table who verified the route.                                                                               | `INT`                                     |
| `updated_at`      | The timestamp indicating the date and time of the approval from the admin.                                                                                               | `DATETIME`                                |
| `location_list`   | A JSON array of the locations that this route moves along with the index of the location. Example: `[{"index": 1, "location_id": 10}, {"index": 2, "location_id": 15}]`. | `JSON`                                    |

---

### 4. `contributions`

Stores the history and status of all data proposals made by agents.

| TITLE                 | DESCRIPTION                                                                                                       | DATA-TYPE                                 |
| --------------------- | ----------------------------------------------------------------------------------------------------------------- | ----------------------------------------- |
| `contribution_id`     | Stores the unique identifier of the contribution request.                                                         | `INT AUTO_INCREMENT`                      |
| `type`                | The type of contribution, which can be a vehicle, a route, or a location.                                         | `ENUM('vehicle', 'route', 'location')`    |
| `associated_entry_id` | The index of the entry in the related table (vehicle, route, or location) which this contribution corresponds to. | `INT`                                     |
| `proposed_by`         | The index to the agent’s unique identifier from the `agents` table who made the proposal.                         | `INT`                                     |
| `accepted_by`         | The index to the admin’s unique identifier from the `admins` table who responded to the proposal.                 | `INT`                                     |
| `status`              | The status of the contribution request (accepted, rejected, or pending).                                          | `ENUM('pending', 'accepted', 'rejected')` |
| `proposed_at`         | The timestamp marking the creation of the contribution request.                                                   | `DATETIME`                                |
| `responded_at`        | The timestamp marking the rejection or approval of the request by an admin.                                       | `DATETIME`                                |
| `rejection_reason`    | The detailed reason for rejection if the admin decided to reject the request.                                     | `TEXT`                                    |

---

### 5. `agents`

Stores all the data related to the volunteers (agents) who collect and feed data into the system.

| TITLE                   | DESCRIPTION                                                                                                                                                       | DATA-TYPE            |
| ----------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------- |
| `agent_id`              | Stores the unique identifier of the agent.                                                                                                                        | `INT AUTO_INCREMENT` |
| `name`                  | The full name of the registered agent.                                                                                                                            | `VARCHAR(255)`       |
| `email`                 | The official email address of the agent for communication and login.                                                                                              | `VARCHAR(255)`       |
| `phone_number`          | The primary phone number of the agent.                                                                                                                            | `VARCHAR(20)`        |
| `image_path`            | The file path to the agent's profile picture image.                                                                                                               | `VARCHAR(255)`       |
| `joined_at`             | The timestamp indicating when the agent account was created.                                                                                                      | `DATETIME`           |
| `password_hash`         | The secure hash of the agent's login password.                                                                                                                    | `VARCHAR(255)`       |
| `contributions_summary` | A JSON array containing the count of contributions made to the system for vehicles, locations, and routes. Example: `{"vehicle": 5, "location": 10, "route": 3}`. | `JSON`               |
| `last_login`            | The timestamp indicating the date and time of the agent's last login.                                                                                             | `DATETIME`           |

---

### 6. `admins`

Stores all the data related to the administrators who manage the system and verify data.

| TITLE           | DESCRIPTION                                                           | DATA-TYPE            |
| --------------- | --------------------------------------------------------------------- | -------------------- |
| `admin_id`      | Stores the unique identifier of the admin.                            | `INT AUTO_INCREMENT` |
| `name`          | The full name of the administrator.                                   | `VARCHAR(255)`       |
| `email`         | The email address of the admin for system access.                     | `VARCHAR(255)`       |
| `phone_number`  | The contact phone number of the administrator.                        | `VARCHAR(20)`        |
| `image_path`    | The file path to the admin's profile picture image.                   | `VARCHAR(255)`       |
| `joined_at`     | The timestamp indicating when the admin account was created.          | `DATETIME`           |
| `password_hash` | The secure hash of the admin's login password.                        | `VARCHAR(255)`       |
| `last_login`    | The timestamp indicating the date and time of the admin's last login. | `DATETIME`           |

---

### 7. `alerts`

Stores data about smart emergency alerts issued by the administration due to route disturbances.

| TITLE             | DESCRIPTION                                                                                              | DATA-TYPE            |
| ----------------- | -------------------------------------------------------------------------------------------------------- | -------------------- |
| `alert_id`        | Stores the unique identifier of the issued emergency alert.                                              | `INT AUTO_INCREMENT` |
| `name`            | The title or name of the alert (e.g., Strike in Sundhara).                                               | `VARCHAR(255)`       |
| `description`     | A short description providing details about the alert and the reason for the disturbance.                | `TEXT`               |
| `issued_by`       | The index to the admin’s unique identifier from the `admins` table who created the alert.                | `INT`                |
| `routes_affected` | A JSON array containing the IDs of the routes affected by this specific issue. Example: `[3, 5, 9, 12]`. | `JSON`               |
| `reported_at`     | The timestamp indicating when the alert was first created.                                               | `DATETIME`           |
| `expires_at`      | The timestamp indicating the expected time that the issue will be resolved and the alert will expire.    | `DATETIME`           |

---

### 8. `suggestions`

Stores user feedback, complaints, and suggestions regarding rides and app accuracy.

| TITLE                | DESCRIPTION                                                                                   | DATA-TYPE                                                       |
| -------------------- | --------------------------------------------------------------------------------------------- | --------------------------------------------------------------- |
| `suggestion_id`      | Stores the unique identifier of the suggestion or feedback entry.                             | `INT AUTO_INCREMENT`                                            |
| `type`               | The type of feedback provided (complaint, suggestion, correction, or appreciation).           | `ENUM('complaint', 'suggestion', 'correction', 'appreciation')` |
| `message`            | The detailed message or feedback provided by the end user.                                    | `TEXT`                                                          |
| `rating`             | The numerical user rating for the trip or the route accuracy (ranging from 1 to 5).           | `INT`                                                           |
| `related_route_id`   | The index to the specific route related to this feedback if one was provided.                 | `INT`                                                           |
| `related_vehicle_id` | The index to the specific vehicle related to this feedback if one was provided.               | `INT`                                                           |
| `ip_address`         | The IP address of the client device that submitted the feedback.                              | `VARCHAR(45)`                                                   |
| `status`             | The current status of the feedback entry (pending, reviewed, or resolved).                    | `ENUM('pending', 'reviewed', 'resolved')`                       |
| `reviewed_by`        | The index to the admin’s unique identifier from the `admins` table who reviewed the feedback. | `INT`                                                           |
| `submitted_at`       | The timestamp marking when the feedback was submitted.                                        | `DATETIME`                                                      |
| `reviewed_at`        | The timestamp marking when the admin completed the review of the feedback.                    | `DATETIME`                                                      |

---

### 9. `trips`

Logs trip searches and route queries for system analysis and improvement.

| TITLE                     | DESCRIPTION                                                                       | DATA-TYPE            |
| ------------------------- | --------------------------------------------------------------------------------- | -------------------- |
| `trip_id`                 | Stores the unique identifier of the recorded trip query.                          | `INT AUTO_INCREMENT` |
| `ip_address`              | The IP address of the client device that performed the search.                    | `VARCHAR(45)`        |
| `start_location_id`       | The index of the starting point location from the `locations` table.              | `INT`                |
| `destination_location_id` | The index of the destination point location from the `locations` table.           | `INT`                |
| `routes_used`             | A JSON array of the routes suggested or used for the trip. Example: `[3, 2, 12]`. | `JSON`               |
| `queried_at`              | The timestamp indicating the time that the user searched for that specific route. | `DATETIME`           |
