# Sawari — Public Transit Navigator for Nepal

> Navigate Nepal's cities using public transportation with confidence.

Sawari is a web-based application that helps users find public bus routes between any two points in Kathmandu Valley. Instead of relying on expensive ride-hailing apps or asking strangers for directions, Sawari provides step-by-step public transit guidance — including which bus to take, where to board, where to get off, and how much to pay.

---

## The Problem

Google Maps doesn't show public transportation routes in most Nepali cities. Commuters are left guessing which bus goes where, how much it costs, and where to transfer — especially newcomers and tourists.

## The Solution

Enter a starting point and a destination. Sawari will:

1. Find the nearest bus stops to both locations.
2. Determine the best bus route (direct or with transfers).
3. Show walking directions to and from bus stops.
4. Display the bus name and photo for easy identification.
5. Estimate the fare and suggest what to tell the conductor.

If no direct route exists, Sawari calculates an optimal transfer point using pathfinding algorithms (Dijkstra / A\*).

---

## Key Features

| Feature                | Description                                                                |
| ---------------------- | -------------------------------------------------------------------------- |
| **Route Finder**       | Point-to-point public transit directions with transfers                    |
| **Fare Estimation**    | Government base rate + real-world adjustments, student/elderly discounts   |
| **Tourist Mode**       | Boarding/alighting phrases, safety tips, cultural notes                    |
| **Smart Alerts**       | Admin-issued warnings for strikes, accidents, or route disruptions         |
| **Wait Time Estimate** | Approximate bus frequency based on route length and fleet size             |
| **Community Driven**   | Users suggest missing stops or corrections; agents earn leaderboard points |
| **Carbon Footprint**   | Compare emissions of public transit vs. ride-sharing                       |
| **Feedback System**    | Ratings, reviews, complaints, and suggestions per route/vehicle            |

---

## Tech Stack

| Layer       | Technology                   |
| ----------- | ---------------------------- |
| Frontend    | HTML, CSS, JavaScript        |
| Backend     | PHP                          |
| Database    | MySQL                        |
| Maps        | Leaflet, OpenStreetMap, OSRM |
| Geolocation | Browser Geolocation API      |
| Algorithms  | Dijkstra, A\* (Pathfinding)  |

---

## Architecture Overview

The system is powered by community-sourced data collected by volunteer **Agents** who map bus stops, register vehicles, and define routes through a dedicated dashboard.

**User Roles:**

- **User** — Searches routes, views directions, submits feedback.
- **Agent** — Maps locations, registers vehicles, creates routes.
- **Admin** — Approves/rejects contributions, manages alerts, oversees users.

---

## Database Schema

The application uses **9 tables**:

| Table           | Purpose                                               |
| --------------- | ----------------------------------------------------- |
| `locations`     | Bus stops and landmarks with GPS coordinates          |
| `vehicles`      | Registered buses, micro-buses, and tempos             |
| `routes`        | Ordered sequences of locations forming transit routes |
| `contributions` | Agent data proposals (pending/accepted/rejected)      |
| `agents`        | Volunteer data collectors                             |
| `admins`        | System administrators                                 |
| `alerts`        | Emergency route disruption notices                    |
| `suggestions`   | User feedback, complaints, and ratings                |
| `trips`         | Logged route queries for analytics                    |

> See [vision.md](vision.md) for the full database schema and detailed feature specifications.

---

## Pages

| Page            | Description                                                |
| --------------- | ---------------------------------------------------------- |
| Landing Page    | Project introduction and Agents Leaderboard                |
| Main Page       | Full-screen map with search bar and floating route details |
| Agent Dashboard | Manage vehicles, places, and routes                        |
| Admin Dashboard | CRUD operations, report handling, user management          |

---

## Getting Started

### Prerequisites

- [XAMPP](https://www.apachefriends.org/) (Apache + MySQL + PHP)
- A modern web browser

### Setup

```bash
# Clone the repository into your XAMPP htdocs folder
git clone <repo-url> C:/xampp/htdocs/CCRC

# Start Apache and MySQL from the XAMPP Control Panel

# Import the database schema into MySQL

# Open in browser
http://localhost/CCRC
```

---

## Contributing

Contributions are welcome! Whether you're fixing bugs, adding features, or mapping new bus routes as an agent — every bit helps.

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/my-feature`)
3. Commit your changes (`git commit -m "Add my feature"`)
4. Push to the branch (`git push origin feature/my-feature`)
5. Open a Pull Request

---

## License

This project is open source. See the LICENSE file for details.

---

<p align="center">
  <i>Built to make public transit accessible for everyone in Nepal.</i>
</p>
