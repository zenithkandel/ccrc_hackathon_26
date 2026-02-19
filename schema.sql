-- =============================================
-- SAWARI - Database Schema
-- Public Transportation Navigation App (Nepal)
-- =============================================

CREATE DATABASE IF NOT EXISTS sawari;
USE sawari;

-- =============================================
-- 1. ADMINS
-- System administrators who approve/reject contributions
-- =============================================
CREATE TABLE admins (
    admin_id        INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(255) NOT NULL,
    email           VARCHAR(255) NOT NULL UNIQUE,
    password        VARCHAR(255) NOT NULL,          -- bcrypt hashed
    role            ENUM('superadmin', 'moderator') NOT NULL DEFAULT 'moderator',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login      DATETIME DEFAULT NULL,
    status          ENUM('active', 'inactive') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- 2. AGENTS
-- Volunteers who collect and submit field data
-- =============================================
CREATE TABLE agents (
    agent_id        INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(255) NOT NULL,
    email           VARCHAR(255) NOT NULL UNIQUE,
    password        VARCHAR(255) NOT NULL,          -- bcrypt hashed
    phone           VARCHAR(20) DEFAULT NULL,
    points          INT NOT NULL DEFAULT 0,         -- leaderboard score
    contributions_count INT NOT NULL DEFAULT 0,     -- total contributions made
    approved_count  INT NOT NULL DEFAULT 0,         -- total approved contributions
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login      DATETIME DEFAULT NULL,
    status          ENUM('active', 'suspended', 'inactive') NOT NULL DEFAULT 'active',
    approved_by     INT DEFAULT NULL,               -- admin who approved agent registration
    FOREIGN KEY (approved_by) REFERENCES admins(admin_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- 3. CONTRIBUTIONS
-- Tracks every data submission by agents (locations, vehicles, routes)
-- =============================================
CREATE TABLE contributions (
    contribution_id INT AUTO_INCREMENT PRIMARY KEY,
    agent_id        INT NOT NULL,
    type            ENUM('location', 'vehicle', 'route') NOT NULL,
    status          ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    notes           TEXT DEFAULT NULL,               -- agent notes about the contribution
    rejection_reason TEXT DEFAULT NULL,               -- admin reason for rejection
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_by     INT DEFAULT NULL,                -- admin who reviewed
    reviewed_at     DATETIME DEFAULT NULL,
    FOREIGN KEY (agent_id) REFERENCES agents(agent_id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES admins(admin_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- 4. LOCATIONS
-- Bus stops and landmarks used for route mapping
-- =============================================
CREATE TABLE locations (
    location_id       INT AUTO_INCREMENT PRIMARY KEY,
    name              VARCHAR(255) NOT NULL,
    description       TEXT DEFAULT NULL,
    latitude          DECIMAL(10,8) NOT NULL,
    longitude         DECIMAL(11,8) NOT NULL,
    type              ENUM('stop', 'landmark') NOT NULL DEFAULT 'stop',
    status            ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    contribution_id   INT DEFAULT NULL,
    updated_by        INT DEFAULT NULL,              -- agent who submitted/updated
    approved_by       INT DEFAULT NULL,              -- admin who approved
    updated_at        DATETIME DEFAULT NULL,
    departure_count   INT NOT NULL DEFAULT 0,        -- times used as starting point
    destination_count INT NOT NULL DEFAULT 0,        -- times used as destination
    FOREIGN KEY (contribution_id) REFERENCES contributions(contribution_id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES agents(agent_id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES admins(admin_id) ON DELETE SET NULL,
    INDEX idx_location_coords (latitude, longitude),
    INDEX idx_location_status (status),
    INDEX idx_location_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- 5. ROUTES
-- Ordered sequences of locations forming a bus route
-- location_list JSON format:
-- [
--   { "location_id": 1, "name": "Kalanki", "latitude": 27.6933, "longitude": 85.2814 },
--   { "location_id": 2, "name": "RNAC", "latitude": 27.7000, "longitude": 85.3100 },
--   ...
-- ]
-- =============================================
CREATE TABLE routes (
    route_id        INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(255) NOT NULL,
    description     TEXT DEFAULT NULL,
    status          ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    contribution_id INT DEFAULT NULL,
    updated_by      INT DEFAULT NULL,
    approved_by     INT DEFAULT NULL,
    updated_at      DATETIME DEFAULT NULL,
    location_list   JSON NOT NULL,                   -- ordered array of stops with coords
    fare_base       DECIMAL(6,2) DEFAULT NULL,       -- base fare in NPR
    fare_per_km     DECIMAL(6,2) DEFAULT NULL,       -- fare rate per km
    FOREIGN KEY (contribution_id) REFERENCES contributions(contribution_id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES agents(agent_id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES admins(admin_id) ON DELETE SET NULL,
    INDEX idx_route_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- 6. VEHICLES
-- Registered public transport vehicles with optional live tracking
-- used_routes JSON format:
-- [1, 5, 12]  (array of route_ids the vehicle operates on)
-- =============================================
CREATE TABLE vehicles (
    vehicle_id      INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(255) NOT NULL,           -- vehicle/yatayat name
    description     TEXT DEFAULT NULL,
    image_path      VARCHAR(255) DEFAULT NULL,       -- path to vehicle image
    status          ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    contribution_id INT DEFAULT NULL,
    latitude        TEXT DEFAULT NULL,                -- live GPS latitude
    longitude       TEXT DEFAULT NULL,                -- live GPS longitude
    velocity        TEXT DEFAULT NULL,                -- live speed (km/h)
    electric        TINYINT(1) NOT NULL DEFAULT 0,   -- 1 = electric vehicle
    updated_by      INT DEFAULT NULL,
    approved_by     INT DEFAULT NULL,
    updated_at      DATETIME DEFAULT NULL,
    used_routes     JSON DEFAULT NULL,               -- array of route_ids
    starts_at       TIME DEFAULT NULL,               -- daily service start time
    stops_at        TIME DEFAULT NULL,               -- daily service end time
    gps_active      TINYINT(1) NOT NULL DEFAULT 0,   -- whether GPS is currently sending
    last_gps_update DATETIME DEFAULT NULL,           -- timestamp of last GPS ping
    FOREIGN KEY (contribution_id) REFERENCES contributions(contribution_id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES agents(agent_id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES admins(admin_id) ON DELETE SET NULL,
    INDEX idx_vehicle_status (status),
    INDEX idx_vehicle_gps (gps_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- 7. TRIPS
-- Records of user journeys for ratings, reviews, and analytics
-- =============================================
CREATE TABLE trips (
    trip_id             INT AUTO_INCREMENT PRIMARY KEY,
    session_id          VARCHAR(64) NOT NULL,         -- anonymous user session identifier
    route_id            INT DEFAULT NULL,
    vehicle_id          INT DEFAULT NULL,
    boarding_stop_id    INT DEFAULT NULL,             -- location_id of boarding stop
    destination_stop_id INT DEFAULT NULL,             -- location_id of destination stop
    transfer_stop_id    INT DEFAULT NULL,             -- location_id if transfer was needed
    second_route_id     INT DEFAULT NULL,             -- route_id of second leg (if transfer)
    started_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at            DATETIME DEFAULT NULL,
    rating              TINYINT DEFAULT NULL,         -- 1-5 star rating
    review              TEXT DEFAULT NULL,            -- user review text
    accuracy_feedback   ENUM('accurate', 'slightly_off', 'inaccurate') DEFAULT NULL,
    fare_paid           DECIMAL(6,2) DEFAULT NULL,    -- actual fare paid by user
    carbon_saved        DECIMAL(8,4) DEFAULT NULL,    -- kg CO2 saved vs ride-sharing
    FOREIGN KEY (route_id) REFERENCES routes(route_id) ON DELETE SET NULL,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(vehicle_id) ON DELETE SET NULL,
    FOREIGN KEY (boarding_stop_id) REFERENCES locations(location_id) ON DELETE SET NULL,
    FOREIGN KEY (destination_stop_id) REFERENCES locations(location_id) ON DELETE SET NULL,
    FOREIGN KEY (transfer_stop_id) REFERENCES locations(location_id) ON DELETE SET NULL,
    FOREIGN KEY (second_route_id) REFERENCES routes(route_id) ON DELETE SET NULL,
    INDEX idx_trip_session (session_id),
    INDEX idx_trip_route (route_id),
    INDEX idx_trip_date (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- 8. ALERTS
-- Emergency/disruption alerts set by admins on routes
-- =============================================
CREATE TABLE alerts (
    alert_id        INT AUTO_INCREMENT PRIMARY KEY,
    route_id        INT DEFAULT NULL,                -- NULL = system-wide alert
    title           VARCHAR(255) NOT NULL,
    description     TEXT NOT NULL,
    severity        ENUM('low', 'medium', 'high', 'critical') NOT NULL DEFAULT 'medium',
    created_by      INT NOT NULL,                    -- admin who created
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at      DATETIME DEFAULT NULL,           -- NULL = no expiry
    status          ENUM('active', 'resolved', 'expired') NOT NULL DEFAULT 'active',
    resolved_at     DATETIME DEFAULT NULL,
    FOREIGN KEY (route_id) REFERENCES routes(route_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES admins(admin_id) ON DELETE CASCADE,
    INDEX idx_alert_status (status),
    INDEX idx_alert_route (route_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- 9. SUGGESTIONS
-- Community-driven suggestions (missing stops, corrections, etc.)
-- =============================================
CREATE TABLE suggestions (
    suggestion_id   INT AUTO_INCREMENT PRIMARY KEY,
    user_type       ENUM('agent', 'user') NOT NULL DEFAULT 'user',
    user_identifier VARCHAR(255) DEFAULT NULL,       -- agent_id or session_id
    type            ENUM('missing_stop', 'route_correction', 'new_route', 'general') NOT NULL,
    title           VARCHAR(255) NOT NULL,
    description     TEXT NOT NULL,
    latitude        DECIMAL(10,8) DEFAULT NULL,      -- optional coords for location-based suggestions
    longitude       DECIMAL(11,8) DEFAULT NULL,
    related_route_id INT DEFAULT NULL,
    status          ENUM('pending', 'reviewed', 'implemented', 'dismissed') NOT NULL DEFAULT 'pending',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_by     INT DEFAULT NULL,
    reviewed_at     DATETIME DEFAULT NULL,
    review_notes    TEXT DEFAULT NULL,
    FOREIGN KEY (related_route_id) REFERENCES routes(route_id) ON DELETE SET NULL,
    FOREIGN KEY (reviewed_by) REFERENCES admins(admin_id) ON DELETE SET NULL,
    INDEX idx_suggestion_status (status),
    INDEX idx_suggestion_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- DEFAULT ADMIN SEED (password: admin123)
-- Change this password immediately after first login!
-- Hash generated with: password_hash('admin123', PASSWORD_BCRYPT)
-- =============================================
INSERT INTO admins (name, email, password, role) VALUES
('Super Admin', 'admin@sawari.com', '$2y$12$Ao.m4poLfru1/LQa2c0BhOYVYlbnB4Lk8GbCPxsJgpSXAPgxUQaHW', 'superadmin');
