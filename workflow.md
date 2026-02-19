# SAWARI — Development Workflow

> This document is the **master blueprint** for building the Sawari web application from scratch.  
> Every phase, task, file, and decision is documented here. When the developer is told to "follow the workflow", they execute the next incomplete phase in order.

---

## Table of Contents

1. [Project Overview & Architecture](#1-project-overview--architecture)
2. [Directory Structure](#2-directory-structure)
3. [Phase 0 — Project Setup & Foundation](#phase-0--project-setup--foundation)
4. [Phase 1 — Database Initialization & Core Backend](#phase-1--database-initialization--core-backend)
5. [Phase 2 — Authentication System](#phase-2--authentication-system)
6. [Phase 3 — Admin Dashboard](#phase-3--admin-dashboard)
7. [Phase 4 — Agent Dashboard](#phase-4--agent-dashboard)
8. [Phase 5 — Public Pages (Landing + Main Map)](#phase-5--public-pages-landing--main-map)
9. [Phase 6 — Route-Finding Engine (Core Algorithm)](#phase-6--route-finding-engine-core-algorithm)
10. [Phase 7 — Extra Features & Enhancements](#phase-7--extra-features--enhancements)
11. [Phase 8 — Testing, Polish & Deployment](#phase-8--testing-polish--deployment)
12. [API Endpoint Reference](#api-endpoint-reference)
13. [Key Design Decisions](#key-design-decisions)

---

## 1. Project Overview & Architecture

### What Sawari Does

Sawari is a **public transportation route-finding web app** for cities in Nepal. A user enters Point A (starting location) and Point B (destination), and the system:

1. Finds nearby bus stops/stations around both points.
2. Determines which buses/vehicles travel between those stops.
3. If a direct route exists → shows the single bus to take.
4. If no direct route exists → calculates multi-bus transfers using Dijkstra/A\* algorithms, finding where to switch buses.
5. Shows walking directions (Point A → Station A, Station B → Point B).
6. Displays vehicle images, fare estimates, conductor instructions, wait times, and alerts.

### Three User Roles

| Role            | Access                                                                                            | Auth Required          |
| --------------- | ------------------------------------------------------------------------------------------------- | ---------------------- |
| **Public User** | Search routes, view map, submit feedback                                                          | No                     |
| **Agent**       | Contribute locations, routes, vehicles; manage profile                                            | Yes (email + password) |
| **Admin**       | Full CRUD on all data; approve/reject contributions; manage agents; issue alerts; review feedback | Yes (email + password) |

### Tech Stack

| Layer              | Technology                                                    |
| ------------------ | ------------------------------------------------------------- |
| Frontend           | HTML5, CSS3 (custom + responsive), Vanilla JavaScript         |
| Backend            | PHP 8+ (procedural with organized includes, no framework)     |
| Database           | MySQL 8+ (via XAMPP)                                          |
| Maps               | Leaflet.js + OpenStreetMap tiles                              |
| Routing/Directions | OSRM (Open Source Routing Machine) API for walking directions |
| Geolocation        | Browser Geolocation API                                       |
| Pathfinding        | Dijkstra / A\* (custom PHP implementation)                    |
| Server             | Apache (XAMPP)                                                |

### Core Data Flow

```
[Agent] --proposes--> [Contribution] --reviewed by--> [Admin]
                            |
                    creates entry in
                            |
                   [Location / Route / Vehicle]
                            |
                    (status: pending → approved)
                            |
              [Public User searches routes]
                            |
                   [Pathfinding Algorithm]
                            |
              [Results displayed on map]
                            |
              [Trip logged for analytics]
```

---

## 2. Directory Structure

```
test_sawari/
│
├── index.php                          # Landing page (entry point)
├── schema.sql                         # Database schema
├── vision.md                          # Project vision document
├── workflow.md                        # THIS FILE — development workflow
│
├── config/
│   ├── database.php                   # DB connection (PDO)
│   ├── constants.php                  # App-wide constants (paths, keys, settings)
│   └── session.php                    # Session initialization & helpers
│
├── includes/
│   ├── header.php                     # Common HTML head + nav (public)
│   ├── footer.php                     # Common footer + scripts
│   ├── functions.php                  # Shared utility functions
│   ├── auth.php                       # Authentication helper functions
│   └── validation.php                 # Input validation & sanitization helpers
│
├── assets/
│   ├── css/
│   │   ├── global.css                 # Reset, typography, variables, common
│   │   ├── landing.css                # Landing page styles
│   │   ├── map.css                    # Main map page styles
│   │   ├── dashboard.css              # Shared agent/admin dashboard styles
│   │   ├── auth.css                   # Login/register form styles
│   │   └── components.css             # Reusable component styles (cards, modals, tables)
│   ├── js/
│   │   ├── map.js                     # Leaflet map initialization & interaction
│   │   ├── search.js                  # Search bar logic (autocomplete, geocoding)
│   │   ├── pathfinder.js              # Client-side route display & animation
│   │   ├── dashboard.js               # Dashboard common interactions (modals, tabs)
│   │   ├── agent/
│   │   │   ├── locations.js           # Agent location CRUD UI logic
│   │   │   ├── routes.js              # Agent route CRUD UI logic
│   │   │   └── vehicles.js            # Agent vehicle CRUD UI logic
│   │   ├── admin/
│   │   │   ├── contributions.js       # Admin contribution review UI
│   │   │   ├── alerts.js              # Admin alert management UI
│   │   │   ├── suggestions.js         # Admin suggestion review UI
│   │   │   └── users.js               # Admin agent management UI
│   │   └── utils.js                   # Shared JS helpers (fetch wrapper, formatters)
│   └── images/
│       ├── uploads/                   # User-uploaded images (vehicles, routes, profiles)
│       ├── logo.png
│       └── icons/                     # UI icons
│
├── pages/
│   ├── map.php                        # Main map page (public route finder)
│   ├── auth/
│   │   ├── login.php                  # Login page (agents & admins)
│   │   ├── register.php               # Agent registration page
│   │   └── logout.php                 # Logout handler
│   ├── agent/
│   │   ├── dashboard.php              # Agent main dashboard
│   │   ├── locations.php              # Agent: manage locations
│   │   ├── routes.php                 # Agent: manage routes
│   │   ├── vehicles.php               # Agent: manage vehicles
│   │   └── profile.php                # Agent: edit profile
│   └── admin/
│       ├── dashboard.php              # Admin main dashboard
│       ├── locations.php              # Admin: manage all locations
│       ├── routes.php                 # Admin: manage all routes
│       ├── vehicles.php               # Admin: manage all vehicles
│       ├── contributions.php          # Admin: review contributions
│       ├── alerts.php                 # Admin: manage alerts
│       ├── suggestions.php            # Admin: review suggestions
│       └── agents.php                 # Admin: manage agent accounts
│
├── api/
│   ├── auth/
│   │   ├── login.php                  # POST: authenticate user
│   │   ├── register.php               # POST: register new agent
│   │   └── logout.php                 # POST: destroy session
│   ├── locations/
│   │   ├── create.php                 # POST: add new location
│   │   ├── read.php                   # GET: fetch locations (with filters)
│   │   ├── update.php                 # PUT/POST: update location
│   │   └── delete.php                 # DELETE/POST: remove location
│   ├── routes/
│   │   ├── create.php                 # POST: add new route
│   │   ├── read.php                   # GET: fetch routes (with filters)
│   │   ├── update.php                 # PUT/POST: update route
│   │   └── delete.php                 # DELETE/POST: remove route
│   ├── vehicles/
│   │   ├── create.php                 # POST: add new vehicle
│   │   ├── read.php                   # GET: fetch vehicles (with filters)
│   │   ├── update.php                 # PUT/POST: update vehicle
│   │   └── delete.php                 # DELETE/POST: remove vehicle
│   ├── contributions/
│   │   ├── read.php                   # GET: fetch contributions
│   │   └── respond.php               # POST: accept/reject contribution
│   ├── alerts/
│   │   ├── create.php                 # POST: create new alert
│   │   ├── read.php                   # GET: fetch active alerts
│   │   ├── update.php                 # PUT/POST: update alert
│   │   └── delete.php                 # DELETE/POST: remove alert
│   ├── suggestions/
│   │   ├── create.php                 # POST: submit feedback (public)
│   │   ├── read.php                   # GET: fetch suggestions (admin)
│   │   └── respond.php               # POST: mark reviewed/resolved
│   ├── trips/
│   │   └── log.php                    # POST: log a trip query
│   ├── search/
│   │   ├── locations.php              # GET: autocomplete location search
│   │   └── find-route.php             # POST: the core pathfinding endpoint
│   └── agents/
│       ├── read.php                   # GET: leaderboard / agent list
│       └── update.php                 # PUT/POST: update agent profile
│
└── algorithms/
    ├── graph.php                      # Graph data structure (adjacency list from routes)
    ├── dijkstra.php                   # Dijkstra's shortest path implementation
    ├── pathfinder.php                 # Main pathfinding orchestrator
    └── helpers.php                    # Haversine distance, nearest-stop finder, etc.
```

---

## Phase 0 — Project Setup & Foundation

**Goal:** Set up the project skeleton, configuration, database connection, and shared utilities so all subsequent phases can build on a solid foundation.

### Task 0.1 — Create directory structure

Create every folder listed in the directory structure above (empty folders are fine). This gives us the scaffold.

**Files to create:**

- All directories under `config/`, `includes/`, `assets/`, `pages/`, `api/`, `algorithms/`
- `assets/images/uploads/` directory (with a `.gitkeep` or `.htaccess` denying direct listing)

### Task 0.2 — Database configuration (`config/database.php`)

Create the database connection file using **PDO** (not mysqli — PDO is more secure and flexible).

**Requirements:**

- Define DB constants: `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`
- Use `test_sawari_db` as the database name
- Create a function `getDBConnection()` that returns a PDO instance
- Set PDO error mode to `ERRMODE_EXCEPTION`
- Set default fetch mode to `FETCH_ASSOC`
- Set `ATTR_EMULATE_PREPARES` to `false` for real prepared statements
- Handle connection failure gracefully with a user-friendly error

### Task 0.3 — App constants (`config/constants.php`)

**Define:**

- `BASE_URL` — auto-detected from `DOCUMENT_ROOT` (e.g., `/CCRC`)
- `BASE_PATH` — absolute file system path to project root
- `UPLOAD_DIR` — path to `assets/images/uploads/`
- `MAX_UPLOAD_SIZE` — 5MB
- `ALLOWED_IMAGE_TYPES` — `['image/jpeg', 'image/png', 'image/webp']`
- `ITEMS_PER_PAGE` — 15 (for pagination)
- `OSRM_API_URL` — `https://router.project-osrm.org` (public OSRM instance for walking directions)
- `FARE_BASE_RATE` — base fare in NPR (e.g., 15)
- `FARE_PER_KM` — per-km rate (e.g., 1.8)
- `STUDENT_DISCOUNT` — 0.50 (50% discount)
- `ELDERLY_DISCOUNT` — 0.50 (50% discount)

### Task 0.4 — Session configuration (`config/session.php`)

**Requirements:**

- Start session with secure settings (`httponly`, `samesite`)
- Create helper functions:
  - `isLoggedIn()` → bool
  - `isAdmin()` → bool
  - `isAgent()` → bool
  - `getCurrentUserId()` → int|null
  - `getCurrentUserRole()` → string|null ('admin'|'agent'|null)
  - `requireAuth($role)` → redirects to login if not authenticated with the right role
  - `setFlashMessage($type, $message)` → stores a one-time message in session
  - `getFlashMessage()` → retrieves and clears the flash message

### Task 0.5 — Shared functions (`includes/functions.php`)

**Utility functions to create:**

- `sanitize($input)` → htmlspecialchars with ENT_QUOTES
- `redirect($path)` → header redirect + exit
- `formatDateTime($datetime)` → human-friendly date
- `timeAgo($datetime)` → "2 hours ago" style
- `generateSlug($string)` → URL-friendly string
- `uploadImage($file, $subfolder)` → handle file upload, return saved path or false
- `deleteImage($path)` → remove uploaded image file
- `jsonResponse($data, $statusCode)` → set header + echo json + exit
- `getClientIP()` → get user's IP address
- `paginate($totalItems, $currentPage, $perPage)` → returns offset, totalPages, etc.

### Task 0.6 — Validation helpers (`includes/validation.php`)

**Functions:**

- `validateEmail($email)` → bool
- `validatePassword($password)` → bool (min 8 chars, at least 1 number, 1 letter)
- `validatePhone($phone)` → bool (Nepali format validation)
- `validateLatitude($lat)` → bool (range -90 to 90)
- `validateLongitude($lng)` → bool (range -180 to 180)
- `validateRating($rating)` → bool (1-5 integer)
- `validateRequired($fields, $data)` → returns array of missing field names
- `validateImageUpload($file)` → returns error message or true

### Task 0.7 — Auth helpers (`includes/auth.php`)

**Functions:**

- `hashPassword($password)` → bcrypt hash
- `verifyPassword($password, $hash)` → bool
- `loginAgent($email, $password)` → sets session, returns true/false
- `loginAdmin($email, $password)` → sets session, returns true/false
- `registerAgent($data)` → creates agent account, returns agent_id or error
- `updateLastLogin($userId, $role)` → updates last_login timestamp

### Task 0.8 — Common HTML templates

**`includes/header.php`:**

- HTML5 doctype, meta tags (viewport, charset, description)
- Link to `global.css` + page-specific CSS (passed as variable)
- Navigation bar:
  - Logo + "Sawari" brand
  - Links: Home, Find Route, (Login/Dashboard based on auth state)
  - Mobile hamburger menu

**`includes/footer.php`:**

- Footer content (copyright, links)
- Common JS includes (utils.js)
- Page-specific JS includes (passed as variable)

### Task 0.9 — Run database schema

Execute `schema.sql` against MySQL to create the `test_sawari_db` database and all 9 tables. Fix the schema bug (file says `CREATE DATABASE IF NOT EXISTS test_sawari_db` but then `USE sawari_db` — should be `USE test_sawari_db`).

### Task 0.10 — Create `.htaccess` for clean URLs (optional but recommended)

- Deny direct access to `config/`, `includes/`, `algorithms/`
- Set up basic rewrite rules if needed

---

## Phase 1 — Database Initialization & Core Backend

**Goal:** Build the data access layer — PHP functions that perform CRUD operations on every table. These are the backbone all API endpoints will use.

### Task 1.1 — Locations data access

**Create `api/locations/` handlers with these DB operations:**

| Operation                                  | Description                                                                  |
| ------------------------------------------ | ---------------------------------------------------------------------------- |
| `createLocation($data)`                    | Insert new location + create contribution record. Status = 'pending'         |
| `getLocations($filters)`                   | Fetch locations with optional filters: status, type, search term, pagination |
| `getLocationById($id)`                     | Fetch single location by ID                                                  |
| `updateLocation($id, $data)`               | Update location fields + create new contribution                             |
| `deleteLocation($id)`                      | Soft-delete or hard-delete location                                          |
| `approveLocation($id, $adminId)`           | Set status='approved', set approved_by, update contribution                  |
| `rejectLocation($id, $adminId, $reason)`   | Set status='rejected', update contribution with reason                       |
| `searchLocations($query)`                  | Search by name (for autocomplete), return only approved locations            |
| `getNearestLocations($lat, $lng, $radius)` | Find locations within radius using Haversine formula                         |
| `incrementDepartureCount($id)`             | Increment departure_count                                                    |
| `incrementDestinationCount($id)`           | Increment destination_count                                                  |

### Task 1.2 — Routes data access

| Operation                               | Description                                                              |
| --------------------------------------- | ------------------------------------------------------------------------ |
| `createRoute($data)`                    | Insert new route + contribution. `location_list` is JSON                 |
| `getRoutes($filters)`                   | Fetch routes with filters: status, search, pagination                    |
| `getRouteById($id)`                     | Fetch single route with its location list expanded (join location names) |
| `updateRoute($id, $data)`               | Update route + new contribution                                          |
| `deleteRoute($id)`                      | Remove route                                                             |
| `approveRoute($id, $adminId)`           | Approve route, update contribution                                       |
| `rejectRoute($id, $adminId, $reason)`   | Reject route                                                             |
| `getRoutesByLocationId($locationId)`    | Find all approved routes that pass through a location                    |
| `getRoutesConnecting($locId1, $locId2)` | Find routes containing both locations (direct routes)                    |

### Task 1.3 — Vehicles data access

| Operation                               | Description                                        |
| --------------------------------------- | -------------------------------------------------- |
| `createVehicle($data)`                  | Insert vehicle + contribution. Handle image upload |
| `getVehicles($filters)`                 | Fetch with filters                                 |
| `getVehicleById($id)`                   | Fetch single vehicle with route details expanded   |
| `updateVehicle($id, $data)`             | Update + new contribution                          |
| `deleteVehicle($id)`                    | Remove vehicle                                     |
| `approveVehicle($id, $adminId)`         | Approve                                            |
| `rejectVehicle($id, $adminId, $reason)` | Reject                                             |
| `getVehiclesByRouteId($routeId)`        | Find all approved vehicles operating on a route    |

### Task 1.4 — Contributions data access

| Operation                                                | Description                                            |
| -------------------------------------------------------- | ------------------------------------------------------ |
| `createContribution($type, $entryId, $agentId)`          | Create new pending contribution                        |
| `getContributions($filters)`                             | Fetch with filters: status, type, agent_id, pagination |
| `respondToContribution($id, $adminId, $status, $reason)` | Accept/reject, set responded_at                        |
| `getContributionsByAgent($agentId)`                      | All contributions by an agent                          |
| `getContributionStats()`                                 | Aggregate counts for dashboard                         |

### Task 1.5 — Agents data access

| Operation                              | Description                              |
| -------------------------------------- | ---------------------------------------- |
| `createAgent($data)`                   | Register new agent with hashed password  |
| `getAgentById($id)`                    | Fetch agent profile                      |
| `updateAgent($id, $data)`              | Update profile (name, phone, image)      |
| `getAgentByEmail($email)`              | For login lookup                         |
| `getLeaderboard($limit)`               | Top agents sorted by total contributions |
| `updateContributionsSummary($agentId)` | Recalculate and update the JSON summary  |
| `getAllAgents($filters)`               | Admin: list all agents with pagination   |

### Task 1.6 — Admins data access

| Operation                 | Description                     |
| ------------------------- | ------------------------------- |
| `getAdminByEmail($email)` | For login                       |
| `getAdminById($id)`       | Fetch admin profile             |
| `createAdmin($data)`      | Create admin (used for seeding) |

### Task 1.7 — Alerts data access

| Operation                            | Description                                                    |
| ------------------------------------ | -------------------------------------------------------------- |
| `createAlert($data)`                 | Create new alert with routes_affected JSON                     |
| `getAlerts($filters)`                | Fetch alerts (active only by default — where expires_at > NOW) |
| `getAlertById($id)`                  | Single alert                                                   |
| `updateAlert($id, $data)`            | Modify alert                                                   |
| `deleteAlert($id)`                   | Remove alert                                                   |
| `getActiveAlertsByRouteId($routeId)` | Check if a route is currently affected                         |

### Task 1.8 — Suggestions data access

| Operation                                     | Description                                          |
| --------------------------------------------- | ---------------------------------------------------- |
| `createSuggestion($data)`                     | Public: submit feedback with IP logging              |
| `getSuggestions($filters)`                    | Admin: fetch with filters (status, type, pagination) |
| `respondToSuggestion($id, $adminId, $status)` | Mark as reviewed/resolved                            |

### Task 1.9 — Trips data access

| Operation        | Description                                               |
| ---------------- | --------------------------------------------------------- |
| `logTrip($data)` | Log a route search query                                  |
| `getTripStats()` | Aggregate analytics: most popular routes, locations, etc. |

### Task 1.10 — Seed admin account

Create a PHP script (`seed.php`) that inserts a default admin account:

- Email: `admin@sawari.com`
- Password: `Admin@123` (bcrypt hashed)
- Name: `System Admin`

---

## Phase 2 — Authentication System

**Goal:** Build login, registration, logout, and session-based access control for Agents and Admins.

### Task 2.1 — Login page (`pages/auth/login.php`)

**UI Requirements:**

- Clean, centered card layout
- Email + password fields
- Role selector (Agent / Admin) — can be tabs or radio buttons
- "Remember me" checkbox (optional, stretch)
- Link to registration page (for agents)
- Display flash messages (error/success)

**Logic:**

- POST to `api/auth/login.php`
- Validate inputs server-side
- Check credentials against `agents` or `admins` table based on selected role
- On success: set session variables (`user_id`, `role`, `name`, `email`), update `last_login`, redirect to dashboard
- On failure: flash error message, redirect back

### Task 2.2 — Registration page (`pages/auth/register.php`)

**UI Requirements:**

- Name, email, phone, password, confirm password fields
- Profile image upload (optional)
- Terms acceptance checkbox

**Logic:**

- POST to `api/auth/register.php`
- Validate all fields (email uniqueness, password strength, phone format)
- Hash password with bcrypt
- Insert into `agents` table
- Auto-login after registration
- Redirect to agent dashboard

### Task 2.3 — Login API (`api/auth/login.php`)

- Accept POST: `email`, `password`, `role`
- Validate inputs
- Query appropriate table (agents or admins)
- Verify password hash
- Set session
- Return JSON response (for AJAX) or redirect (for form submit)

### Task 2.4 — Registration API (`api/auth/register.php`)

- Accept POST: `name`, `email`, `phone_number`, `password`, `image` (optional file)
- Validate all fields
- Check email uniqueness
- Hash password
- Handle image upload
- Insert agent record
- Initialize `contributions_summary` as `{"vehicle": 0, "location": 0, "route": 0}`
- Return success/error

### Task 2.5 — Logout (`pages/auth/logout.php`)

- Destroy session
- Redirect to landing page

### Task 2.6 — Auth middleware integration

- In every agent page: call `requireAuth('agent')` at top
- In every admin page: call `requireAuth('admin')` at top
- In API endpoints: check auth before processing

---

## Phase 3 — Admin Dashboard

**Goal:** Build the complete admin panel where admins can manage all data, review contributions, issue alerts, and review feedback.

### Task 3.1 — Admin dashboard layout (`pages/admin/dashboard.php`)

**UI: Sidebar + Content Area layout**

**Sidebar navigation:**

- Dashboard (overview)
- Locations
- Routes
- Vehicles
- Contributions
- Alerts
- Suggestions / Feedback
- Agents Management

**Dashboard overview content:**

- Stats cards: Total locations, routes, vehicles, pending contributions
- Recent contributions (last 5)
- Active alerts count
- Unreviewed suggestions count
- Quick actions (Add Location, Add Route, etc.)

### Task 3.2 — Admin: Locations management (`pages/admin/locations.php`)

**Features:**

- Table listing all locations (paginated) with columns: Name, Type, Status, Coordinates, Updated By, Actions
- Filter bar: by status (all/pending/approved/rejected), by type (stop/landmark), search by name
- **Add Location** button → opens modal with:
  - Name, Description, Type (stop/landmark)
  - Interactive Leaflet map for pin-drop (click to set lat/lng)
  - Manual lat/lng input as fallback
- **Edit** action → same modal pre-filled
- **Approve/Reject** actions (for pending entries) — reject requires a reason
- **Delete** action with confirmation modal
- Status badges with color coding (green=approved, yellow=pending, red=rejected)

### Task 3.3 — Admin: Routes management (`pages/admin/routes.php`)

**Features:**

- Table listing all routes with columns: Name, Status, # Stops, Updated By, Actions
- Filter bar: by status, search by name
- **Add/Edit Route** modal:
  - Name, Description, Image upload
  - Location selector: searchable dropdown of approved locations
  - Drag-and-drop sortable list to order stops
  - Preview on Leaflet map (polyline connecting stops)
- **Approve/Reject/Delete** actions
- Click route row to expand and see full stop list on a mini-map

### Task 3.4 — Admin: Vehicles management (`pages/admin/vehicles.php`)

**Features:**

- Table listing all vehicles: Name, Status, Operating Hours, # Routes, Actions
- Filter and search
- **Add/Edit Vehicle** modal:
  - Name, Description, Image upload
  - Service start time, stop time
  - Route assignment: multi-select from approved routes with count per route
  - Preview of routes assigned
- **Approve/Reject/Delete** actions

### Task 3.5 — Admin: Contributions review (`pages/admin/contributions.php`)

**Features:**

- Table of all contributions: ID, Type, Proposed By (agent name), Status, Proposed At, Actions
- Filter by: status (pending/accepted/rejected), type (vehicle/route/location)
- **Review** action:
  - Shows the full entry details (the associated location/route/vehicle)
  - Side-by-side comparison if it's an update
  - Accept or Reject buttons
  - Reject requires a reason textarea
- On accept: update contribution status + update the associated entry's status to 'approved'
- On reject: update contribution status + set rejection_reason + set entry status to 'rejected'
- Update agent's `contributions_summary` JSON after accept

### Task 3.6 — Admin: Alerts management (`pages/admin/alerts.php`)

**Features:**

- Table of alerts: Name, Description, Routes Affected, Reported At, Expires At, Actions
- **Active** vs **Expired** tabs
- **Create Alert** modal:
  - Name, Description
  - Routes affected: multi-select checklist of approved routes
  - Expiry date/time picker
- **Edit/Delete** actions
- Visual indicator for currently active alerts

### Task 3.7 — Admin: Suggestions management (`pages/admin/suggestions.php`)

**Features:**

- Table: Type, Message (truncated), Rating, Related Route, Related Vehicle, Status, Submitted At, Actions
- Filter by: status (pending/reviewed/resolved), type (complaint/suggestion/correction/appreciation)
- **View** action → full message in modal, linked route/vehicle info
- Mark as **Reviewed** or **Resolved**
- Aggregate stats: average rating, type breakdown chart

### Task 3.8 — Admin: Agent management (`pages/admin/agents.php`)

**Features:**

- Table of agents: Name, Email, Phone, Joined At, Contributions Count, Last Login, Actions
- Search by name or email
- View agent profile details
- View contribution history for specific agent
- Ability to deactivate/remove agent (if needed — can add a `status` field later)

---

## Phase 4 — Agent Dashboard

**Goal:** Build the agent panel where volunteer agents can contribute locations, routes, and vehicles to the system.

### Task 4.1 — Agent dashboard layout (`pages/agent/dashboard.php`)

**Sidebar navigation:**

- Dashboard (overview)
- Locations
- Routes
- Vehicles
- My Contributions
- Profile

**Dashboard overview:**

- Welcome message with agent name
- Stats cards: My Locations, My Routes, My Vehicles, Pending Proposals
- Recent contribution statuses (accepted/rejected/pending)
- Quick actions

### Task 4.2 — Agent: Locations management (`pages/agent/locations.php`)

**Features:**

- Table of locations submitted by this agent: Name, Type, Status, Actions
- **Add Location** button → modal:
  - Name, Description
  - Type: stop / landmark
  - Interactive Leaflet map with pin drop (agent clicks on map to place marker)
  - "Use My Location" button (Browser Geolocation API)
  - Manual lat/lng input
- **Edit** action → only for their own pending entries
- Status badge display (pending = waiting for admin review)
- Cannot delete approved entries

**Backend flow:**

1. Agent submits location → insert into `locations` table with status='pending'
2. Create `contributions` record with type='location'
3. Link contribution_id to the location entry
4. Admin reviews in their dashboard

### Task 4.3 — Agent: Routes management (`pages/agent/routes.php`)

**Features:**

- Table of routes submitted by this agent
- **Create Route** flow:
  1. Enter route name (format: "Start - End") and description
  2. Upload route image (optional)
  3. Select locations to include: searchable dropdown of **approved** locations
  4. Order locations by drag-and-drop or index input
  5. Preview on Leaflet map (polyline connecting selected stops in order)
  6. Submit → creates route + contribution record
- **Edit** own pending routes
- View approved routes (read-only)

### Task 4.4 — Agent: Vehicles management (`pages/agent/vehicles.php`)

**Features:**

- Table of vehicles submitted by this agent
- **Add Vehicle** flow:
  1. Name, Description
  2. Upload vehicle image (for user identification)
  3. Set operating hours: starts_at, stops_at (time pickers)
  4. Assign routes: select from **approved** routes + set count per route
  5. Submit → creates vehicle + contribution record
- **Edit** own pending vehicles

### Task 4.5 — Agent: My contributions view

- Combined view of all contributions made by this agent
- Table: Type, Entry Name, Status, Proposed At, Responded At, Rejection Reason
- Filter by type and status
- This helps agents track what's been accepted/rejected and why

### Task 4.6 — Agent: Profile management (`pages/agent/profile.php`)

**Features:**

- View/edit: Name, Email (read-only), Phone, Profile Image
- Change password (current password + new password + confirm)
- View stats: total contributions, breakdown by type
- Account creation date

---

## Phase 5 — Public Pages (Landing + Main Map)

**Goal:** Build the public-facing pages — the landing page and the interactive map page where users find routes.

### Task 5.1 — Landing page (`index.php`)

**Sections:**

1. **Hero Section:**
   - Large headline: "Navigate Nepal's Public Transport with Ease"
   - Subtitle explaining what Sawari does
   - CTA button: "Find Your Route →" (links to map page)
   - Background: subtle map pattern or illustration

2. **How It Works:**
   - 3-step visual: Enter Start → Enter Destination → Get Directions
   - Simple icons + short descriptions

3. **Features Highlight:**
   - Grid of feature cards: Real Routes, Fare Estimates, Bus Switching, Walking Directions, Emergency Alerts, Community Driven
   - Each with an icon and 1-line description

4. **Agents Leaderboard:**
   - Top 10 agents by contribution count
   - Table/cards showing: Rank, Agent Name, Total Contributions
   - Fetched from DB (approved contributions only)

5. **Call to Action:**
   - "Want to help? Become an Agent!" → link to register page

6. **Footer:**
   - About, Contact, Links, Copyright

### Task 5.2 — Main Map page (`pages/map.php`)

**Layout (full-screen map experience):**

```
┌─────────────────────────────────────────────────┐
│  [Logo]  [Search Bar: From ___  To ___]  [≡]   │ ← Top bar
├─────────────────────────────────────────────────┤
│                                                 │
│                                                 │
│              FULL SCREEN LEAFLET MAP            │
│                                                 │
│                                                 │
│                                                 │
│   ┌───────────────────────────────┐             │
│   │      FLOATING RESULTS PANEL   │             │ ← Sliding panel
│   │  Route info, fare, vehicle    │             │
│   │  images, walking directions   │             │
│   └───────────────────────────────┘             │
└─────────────────────────────────────────────────┘
```

**Components:**

1. **Top Search Bar:**
   - Two input fields: "Starting Point" and "Destination"
   - Each with autocomplete dropdown (searches approved locations by name)
   - "Use My Location" icon button for starting point (GPS)
   - Swap button (↕) to reverse start/destination
   - "Search" button to trigger pathfinding
   - Optional: passenger type selector (regular/student/elderly) for fare

2. **Leaflet Map:**
   - OpenStreetMap tiles
   - Initial view: centered on Kathmandu Valley (27.7172, 85.3240), zoom 13
   - All approved stops displayed as small markers (toggleable layer)
   - On search:
     - Marker for Point A (green)
     - Marker for Point B (red)
     - Markers for relevant bus stops (blue)
     - Polyline for walking directions (dashed line)
     - Polyline for bus route segments (colored solid lines, different color per route)
     - Visual distinction between walking and riding segments

3. **Floating Results Panel (bottom sheet / sidebar):**
   - Appears after successful route search
   - Shows step-by-step directions:
     - 🚶 "Walk 350m to Bus Stop X" (with walking direction summary)
     - 🚌 "Take [Vehicle Name] towards [Direction]" + vehicle image
     - 💰 "Fare: NPR 25"
     - 🗣️ "Tell conductor: 'I'll get off at [Stop Y]'"
     - 🚶 "Walk 200m to your destination"
   - If multi-bus: shows transfer point clearly
   - Total estimated fare
   - Estimated wait time (if calculable)
   - Active alerts affecting this route (warning banner)
   - "Rate this route" button → opens suggestion/feedback form

4. **Alert Banner:**
   - If any active alerts exist for displayed routes, show a yellow/red banner
   - "⚠️ Alert: [Alert Name] — some routes may be affected"

5. **Feedback Modal:**
   - After viewing results, user can submit feedback
   - Type selector (complaint/suggestion/correction/appreciation)
   - Message textarea
   - Rating (1-5 stars)
   - Auto-attach the related route/vehicle IDs
   - Submit → `api/suggestions/create.php`

### Task 5.3 — Location autocomplete search

**`api/search/locations.php`:**

- GET with query parameter `?q=sundhara`
- Search `locations` table WHERE status='approved' AND name LIKE '%query%'
- Return JSON array: `[{location_id, name, latitude, longitude, type}, ...]`
- Limit to 10 results
- Used by the search bar's autocomplete dropdown

### Task 5.4 — Map initialization (`assets/js/map.js`)

**Features:**

- Initialize Leaflet map with OSM tiles
- Center on Kathmandu Valley
- Add approved bus stops as a marker layer (fetched via API)
- Custom marker icons for stops vs landmarks
- Map click handler (optional: click to set start/end point)
- Fit map bounds to show search results
- Draw walking directions using OSRM API responses
- Draw bus route polylines from route location lists

### Task 5.5 — Search interaction (`assets/js/search.js`)

**Features:**

- Debounced autocomplete on both input fields
- Fetch suggestions from `api/search/locations.php`
- Display dropdown with location names
- On selection: store location_id, show marker on map
- Geolocation: request browser GPS, reverse geocode to nearest stop
- Trigger search: send start_location_id + destination_location_id to `api/search/find-route.php`
- Display results in floating panel

---

## Phase 6 — Route-Finding Engine (Core Algorithm)

**Goal:** Implement the pathfinding algorithm that determines how to get from Point A to Point B using public transit. This is the heart of Sawari.

### Task 6.1 — Graph construction (`algorithms/graph.php`)

**Purpose:** Build a graph data structure from the database's routes and locations.

**How it works:**

1. Fetch all approved routes with their `location_list` JSON
2. For each route, the locations in order represent edges in the graph
3. Build an **adjacency list** where:
   - Each node = a location_id
   - Each edge = connection between consecutive locations in a route
   - Edge weight = Haversine distance between the two locations
   - Edge metadata = route_id (which route this connection belongs to)

**Data structure:**

```php
$graph = [
    location_id_1 => [
        ['to' => location_id_2, 'weight' => 1.2, 'route_id' => 5],
        ['to' => location_id_3, 'weight' => 0.8, 'route_id' => 8],
    ],
    location_id_2 => [
        ['to' => location_id_1, 'weight' => 1.2, 'route_id' => 5],
        ['to' => location_id_4, 'weight' => 2.1, 'route_id' => 5],
    ],
    // ...
];
```

**Important:** Routes are bidirectional (bus routes generally go both ways), so add edges in both directions.

### Task 6.2 — Dijkstra's algorithm (`algorithms/dijkstra.php`)

**Implementation:**

- Standard Dijkstra's shortest path using a min-priority queue
- Input: graph adjacency list, start_location_id, end_location_id
- Output: shortest path as array of `{location_id, route_id}` pairs + total distance
- Track route changes (where the user needs to switch buses)
- Consider adding a **transfer penalty** to the weight when switching routes (to prefer fewer transfers)

**Transfer penalty logic:**

- When moving from one edge to the next, if the `route_id` changes, add a penalty weight (e.g., +2.0 km equivalent)
- This discourages unnecessary transfers

### Task 6.3 — Nearest stop finder (`algorithms/helpers.php`)

**Functions:**

1. `haversineDistance($lat1, $lng1, $lat2, $lng2)` → distance in km
2. `findNearestStops($lat, $lng, $radiusKm, $limit)`:
   - Query approved locations within radius using Haversine formula in SQL
   - Return ordered by distance
   - Used when user provides GPS coordinates instead of selecting a stop

### Task 6.4 — Main pathfinder orchestrator (`algorithms/pathfinder.php`)

**This is the core function: `findRoute($startLocationId, $endLocationId)`**

**Algorithm flow:**

```
1. Validate both locations exist and are approved
2. Check for active alerts on any routes
3. Build the graph (Task 6.1)
4. Run Dijkstra from start to end (Task 6.2)
5. If path found:
   a. Parse the path into SEGMENTS:
      - Group consecutive locations by route_id
      - Each group = one bus ride segment
   b. For each segment:
      - Get route details (name)
      - Get vehicle details operating on that route
      - Calculate segment distance
      - Calculate fare for that segment
   c. Calculate walking directions:
      - From user's actual Point A to first bus stop (OSRM API)
      - From last bus stop to user's actual Point B (OSRM API)
   d. Check for alerts affecting any route in the path
   e. Compile full response
6. If no path found:
   - Return apology message
   - Suggest nearby locations that DO have routes
```

**Response structure:**

```json
{
  "success": true,
  "summary": {
    "total_distance_km": 8.5,
    "total_fare": 35,
    "estimated_duration_min": 25,
    "transfers": 1,
    "alerts": []
  },
  "segments": [
    {
      "type": "walking",
      "from": {"name": "Your Location", "lat": 27.1234, "lng": 85.5678},
      "to": {"name": "Bus Stop A", "lat": 27.1240, "lng": 85.5680},
      "distance_m": 350,
      "duration_min": 4,
      "directions": "Walk north on the main road for 350m"
    },
    {
      "type": "riding",
      "route_id": 5,
      "route_name": "Greenland - Sundhara",
      "vehicle": {
        "name": "Sajha Yatayat",
        "image": "/assets/images/uploads/sajha.jpg",
        "starts_at": "06:00",
        "stops_at": "21:00"
      },
      "from": {"name": "Bus Stop A", "location_id": 10, "lat": 27.1240, "lng": 85.5680},
      "to": {"name": "Bus Stop C", "location_id": 22, "lat": 27.6800, "lng": 85.3200},
      "stops_in_between": ["Stop B1", "Stop B2"],
      "fare": 25,
      "conductor_instruction": "Tell the conductor: 'Bus Stop C ma rokdinuhos'"
    },
    {
      "type": "transfer",
      "instruction": "Get off at Bus Stop C. Walk to the opposite side of the road. Look for a blue Mahanagar bus.",
      "wait_time_estimate": "~5 minutes"
    },
    {
      "type": "riding",
      "route_id": 8,
      "route_name": "Sundhara - Ratnapark",
      "vehicle": { ... },
      "from": { ... },
      "to": { ... },
      "fare": 15,
      "conductor_instruction": "Tell the conductor: 'Ratnapark ma rokdinuhos'"
    },
    {
      "type": "walking",
      "from": {"name": "Bus Stop D", ...},
      "to": {"name": "Your Destination", ...},
      "distance_m": 200,
      "duration_min": 3,
      "directions": "Walk east for 200m, your destination is on the left"
    }
  ]
}
```

### Task 6.5 — Route search API endpoint (`api/search/find-route.php`)

- POST: `start_location_id`, `destination_location_id`, `passenger_type` (optional: regular/student/elderly)
- Call `findRoute()` from pathfinder
- Log trip in `trips` table
- Increment departure/destination counts on locations
- Return the full response JSON
- Handle errors gracefully

### Task 6.6 — Walking directions via OSRM

**Function: `getWalkingDirections($fromLat, $fromLng, $toLat, $toLng)`**

- Call OSRM public API: `https://router.project-osrm.org/route/v1/foot/{lng1},{lat1};{lng2},{lat2}?overview=full&geometries=geojson`
- Parse response for distance, duration, and geometry
- Return formatted walking segment
- Fallback: if OSRM fails, calculate straight-line distance and estimate walk time at 5 km/h

### Task 6.7 — Fare calculation

**Function: `calculateFare($distanceKm, $passengerType)`**

```
Base fare: FARE_BASE_RATE (e.g., 15 NPR)
Per km: FARE_PER_KM (e.g., 1.8 NPR/km)
Fare = FARE_BASE_RATE + (distanceKm * FARE_PER_KM)
Round up to nearest 5 NPR

If passengerType == 'student': fare *= (1 - STUDENT_DISCOUNT)
If passengerType == 'elderly': fare *= (1 - ELDERLY_DISCOUNT)
Round to nearest integer
```

---

## Phase 7 — Extra Features & Enhancements

**Goal:** Add all the polish features from the vision document.

### Task 7.1 — Tourist Help Mode

**Implementation:**

- Toggle button on the map page: "🌍 Tourist Mode"
- When active, results panel includes extra helpful text:
  - What to say when boarding: "Nepali phrase: '[Destination] jāne bus ho?'"
  - What to say when getting off: "'Rokdinuhos' means 'please stop here'"
  - Precautions: "Keep belongings close", "Hold on tight during the ride"
  - Payment tip: "Have exact change ready, fares are typically paid in cash"
- These are static/semi-static strings stored in a config or JSON file

### Task 7.2 — Estimated Wait Time

**Function: `estimateWaitTime($routeId)`**

**Logic:**

1. Get the vehicle(s) operating on this route
2. Get the vehicle count for this route from `used_routes` JSON
3. Get the route total distance (sum of Haversine distances between consecutive stops)
4. Estimate round-trip time: `totalDistance * 2 / avgSpeed` (assume avg 15 km/h for city buses)
5. Frequency = roundTripTime / vehicleCount
6. Display: "A bus should arrive every ~X minutes"

### Task 7.3 — Carbon Emission Comparison

**After showing route results, add a "Green Impact" card:**

```
🌱 Carbon Footprint Comparison
Public Transport: ~0.089 kg CO₂/km × 8.5 km = 0.76 kg CO₂
Ride-sharing (bike): ~0.103 kg CO₂/km × 8.5 km = 0.88 kg CO₂
Private car/taxi: ~0.192 kg CO₂/km × 8.5 km = 1.63 kg CO₂

You're saving 0.87 kg CO₂ by choosing public transport! 🎉
```

Use standard emission factors for Nepal's context.

### Task 7.4 — Smart Emergency Alerts integration

- Already built the alerts CRUD in Phase 3
- In the pathfinder: when building the graph, check for active alerts on each route
- If a route is affected by an alert:
  - Option A: Increase its edge weights dramatically (soft-avoid)
  - Option B: Remove its edges entirely (hard-block)
  - Make this configurable
- In the response, include alert warnings for any affected routes
- On the map page, show a persistent banner if any systemwide alerts exist

### Task 7.5 — Rating & feedback after route search

- After user views a route result, show a "How was this suggestion?" prompt
- Quick star rating (1-5)
- Optional message
- Type auto-set to 'suggestion' or user can choose
- Auto-attach the route_id(s) used
- Submit to suggestions API
- No login required (tracked by IP)

### Task 7.6 — Agents Leaderboard

- Already included in landing page (Task 5.1)
- Enhancement: detailed leaderboard page with:
  - Rank, Agent Name (or display name), Profile Image
  - Contributions breakdown: X locations, Y routes, Z vehicles
  - Total accepted contributions
  - Member since date
  - Pagination for full list
- API endpoint: `api/agents/read.php?leaderboard=true&limit=50`

### Task 7.7 — Responsive design pass

- Ensure all pages work on mobile screens (360px+)
- Map page: full-screen map, bottom sheet for results (touch-friendly)
- Dashboard: collapsible sidebar → hamburger menu on mobile
- Forms: stacked layout on small screens
- Tables: horizontal scroll or card-based layout on mobile

---

## Phase 8 — Testing, Polish & Deployment

**Goal:** Ensure everything works correctly, handles edge cases, looks polished, and is ready for deployment.

### Task 8.1 — Data seeding & manual testing

- Create a comprehensive seed script (`seed.php`) that populates:
  - 1 admin account
  - 3-5 test agent accounts
  - 15-20 locations across Kathmandu Valley (real coordinates)
  - 5-8 routes connecting those locations
  - 3-5 vehicles assigned to those routes
  - A few test alerts
  - A few test suggestions
- Manually test every user flow:
  - Public: search route, view results, submit feedback
  - Agent: register, login, add location/route/vehicle, view contributions
  - Admin: login, review contributions, approve/reject, manage alerts, view suggestions

### Task 8.2 — Edge case handling

- No route found between two points → friendly message + suggestions
- Same start and destination → show error
- Start/end not near any bus stop → "No bus stops found within X km"
- All routes affected by alerts → warning message
- Empty database (no routes/locations yet) → informative state
- Invalid API inputs → proper validation errors
- Large file uploads → size/type validation
- SQL injection prevention → all queries use prepared statements
- XSS prevention → all output sanitized

### Task 8.3 — Performance optimization

- Add database indexes on frequently queried columns:
  - `locations.status`, `locations.name`
  - `routes.status`
  - `vehicles.status`
  - `contributions.status`, `contributions.type`
  - `suggestions.status`
  - `alerts.expires_at`
- Cache the route graph in session or file (rebuild on route data change)
- Lazy-load location markers on map (fetch within viewport bounds)

### Task 8.4 — Security review

- All passwords: bcrypt hashed (PHP `password_hash()` with `PASSWORD_DEFAULT`)
- All DB queries: prepared statements (PDO)
- All user input: sanitized and validated
- File uploads: type + size checks, rename to hash, store outside web root or with access control
- CSRF protection: token in forms
- Rate limiting on public APIs (suggestion submission, route search) — simple IP-based check
- Session security: regenerate ID on login, httponly cookies, samesite

### Task 8.5 — UI polish

- Consistent color scheme (define CSS custom properties / variables)
- Loading states: skeleton screens or spinners during API calls
- Error states: user-friendly error messages with retry options
- Empty states: helpful messages when no data exists
- Transitions: smooth panel sliding, modal animations
- Favicon and meta tags for SEO
- Print-friendly route results (optional)

### Task 8.6 — Final deployment checklist

- [ ] Database schema executed on production MySQL
- [ ] Admin account seeded
- [ ] `.htaccess` configured for security
- [ ] Upload directory permissions set correctly
- [ ] Error reporting disabled in production (`display_errors = off`)
- [ ] All hardcoded localhost URLs replaced with production domain
- [ ] Tested on multiple browsers (Chrome, Firefox, Safari, Edge)
- [ ] Tested on mobile devices
- [ ] README.md created with setup instructions

---

## API Endpoint Reference

### Authentication

| Method | Endpoint                 | Description            | Auth |
| ------ | ------------------------ | ---------------------- | ---- |
| POST   | `/api/auth/login.php`    | Login (agent or admin) | No   |
| POST   | `/api/auth/register.php` | Register new agent     | No   |
| POST   | `/api/auth/logout.php`   | Logout                 | Yes  |

### Locations

| Method | Endpoint                       | Description                 | Auth        |
| ------ | ------------------------------ | --------------------------- | ----------- |
| GET    | `/api/locations/read.php`      | List locations (filterable) | Agent/Admin |
| GET    | `/api/locations/read.php?id=X` | Single location             | Agent/Admin |
| POST   | `/api/locations/create.php`    | Add new location            | Agent/Admin |
| POST   | `/api/locations/update.php`    | Update location             | Agent/Admin |
| POST   | `/api/locations/delete.php`    | Delete location             | Admin       |

### Routes

| Method | Endpoint                    | Description              | Auth        |
| ------ | --------------------------- | ------------------------ | ----------- |
| GET    | `/api/routes/read.php`      | List routes (filterable) | Agent/Admin |
| GET    | `/api/routes/read.php?id=X` | Single route             | Agent/Admin |
| POST   | `/api/routes/create.php`    | Add new route            | Agent/Admin |
| POST   | `/api/routes/update.php`    | Update route             | Agent/Admin |
| POST   | `/api/routes/delete.php`    | Delete route             | Admin       |

### Vehicles

| Method | Endpoint                   | Description                | Auth        |
| ------ | -------------------------- | -------------------------- | ----------- |
| GET    | `/api/vehicles/read.php`   | List vehicles (filterable) | Agent/Admin |
| POST   | `/api/vehicles/create.php` | Add new vehicle            | Agent/Admin |
| POST   | `/api/vehicles/update.php` | Update vehicle             | Agent/Admin |
| POST   | `/api/vehicles/delete.php` | Delete vehicle             | Admin       |

### Contributions

| Method | Endpoint                         | Description        | Auth        |
| ------ | -------------------------------- | ------------------ | ----------- |
| GET    | `/api/contributions/read.php`    | List contributions | Agent/Admin |
| POST   | `/api/contributions/respond.php` | Accept/reject      | Admin       |

### Alerts

| Method | Endpoint                 | Description  | Auth   |
| ------ | ------------------------ | ------------ | ------ |
| GET    | `/api/alerts/read.php`   | List alerts  | Public |
| POST   | `/api/alerts/create.php` | Create alert | Admin  |
| POST   | `/api/alerts/update.php` | Update alert | Admin  |
| POST   | `/api/alerts/delete.php` | Delete alert | Admin  |

### Suggestions

| Method | Endpoint                       | Description       | Auth   |
| ------ | ------------------------------ | ----------------- | ------ |
| POST   | `/api/suggestions/create.php`  | Submit feedback   | Public |
| GET    | `/api/suggestions/read.php`    | List suggestions  | Admin  |
| POST   | `/api/suggestions/respond.php` | Review suggestion | Admin  |

### Search & Pathfinding

| Method | Endpoint                        | Description            | Auth   |
| ------ | ------------------------------- | ---------------------- | ------ |
| GET    | `/api/search/locations.php?q=X` | Autocomplete locations | Public |
| POST   | `/api/search/find-route.php`    | Find route A→B         | Public |

### Agents

| Method | Endpoint                 | Description              | Auth                         |
| ------ | ------------------------ | ------------------------ | ---------------------------- |
| GET    | `/api/agents/read.php`   | Agent list / leaderboard | Public (leaderboard) / Admin |
| POST   | `/api/agents/update.php` | Update profile           | Agent                        |

### Trips

| Method | Endpoint             | Description       | Auth     |
| ------ | -------------------- | ----------------- | -------- |
| POST   | `/api/trips/log.php` | Log a trip search | Internal |

---

## Key Design Decisions

### 1. No PHP Framework — Intentional

The tech stack specifies vanilla PHP. We use organized includes and a clean directory structure instead of a framework. This keeps things simple and educational.

### 2. PDO over MySQLi

PDO supports prepared statements natively, works with multiple databases, and has a cleaner API. All queries MUST use prepared statements.

### 3. JSON Columns for Flexibility

`location_list` (routes), `used_routes` (vehicles), `routes_affected` (alerts), and `contributions_summary` (agents) are JSON columns. This avoids junction tables for data that's always read as a whole unit.

### 4. Contribution Workflow

Every data change by an agent goes through a contribution → pending → admin review → approved/rejected pipeline. This ensures data quality.

### 5. OSRM for Walking Directions

We use the public OSRM instance for walking directions between user location ↔ bus stop. This is free and doesn't require API keys (unlike Google Maps).

### 6. Graph Rebuilt Per Search (initially)

For simplicity, the route graph is built fresh on each search request. In Phase 8, we optimize with caching. The graph only changes when routes are approved/modified.

### 7. Bidirectional Routes

Bus routes in Nepal generally go both ways on the same path. The graph treats each route edge as bidirectional.

### 8. Transfer Penalty

The Dijkstra implementation adds a configurable "transfer penalty" when switching between routes, so the algorithm prefers fewer bus changes even if the distance is slightly longer.

### 9. IP-based Tracking for Public Users

Since public users don't login, suggestions and trip queries are tracked by IP address for analytics and rate limiting.

### 10. Mobile-First Responsive Design

The map page is designed mobile-first (bottom sheet results panel) since most users in Nepal access the web via smartphones.

---

## Progress Tracker

| Phase                             | Status      | Notes                                                                                                                             |
| --------------------------------- | ----------- | --------------------------------------------------------------------------------------------------------------------------------- |
| Phase 0 — Setup & Foundation      | ✅ Complete | All configs, includes, templates, CSS, JS, .htaccess, DB schema executed                                                          |
| Phase 1 — Database & Backend Core | ✅ Complete | All CRUD endpoints (locations, routes, vehicles, contributions, agents, alerts, suggestions, trips), seed script with sample data |
| Phase 2 — Authentication System   | ✅ Complete | Login, register, logout, session management                                                                                       |
| Phase 3 — Admin Dashboard         | ✅ Complete | Full admin panel with all management pages                                                                                        |
| Phase 4 — Agent Dashboard         | ✅ Complete | Agent panel with contributions, profile, CRUD                                                                                     |
| Phase 5 — Public Pages            | ✅ Complete | Landing page, map page, autocomplete, search UI                                                                                   |
| Phase 6 — Route-Finding Engine    | ✅ Complete | Modified Dijkstra with state-space expansion, OSRM walking, fare calc                                                             |
| Phase 7 — Extra Features          | ✅ Complete | Tourist mode, carbon comparison, alerts integration, feedback, leaderboard, responsive design                                     |
| Phase 8 — Testing & Polish        | ✅ Complete | Comprehensive seed data, DB indexes, README, final testing                                                                        |

---

_This workflow document is the single source of truth. When instructed to proceed, execute the next incomplete phase from top to bottom._
