-- Create Database
CREATE DATABASE IF NOT EXISTS test_sawari_db;
USE test_sawari_db;

-- 1. Agents Table
CREATE TABLE agents (
    agent_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone_number VARCHAR(20),
    image_path VARCHAR(255),
    joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    password_hash VARCHAR(255) NOT NULL,
    contributions_summary JSON, -- Stores count: {"vehicle": 5, "location": 10}
    last_login DATETIME
);

-- 2. Admins Table
CREATE TABLE admins (
    admin_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone_number VARCHAR(20),
    image_path VARCHAR(255),
    joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    password_hash VARCHAR(255) NOT NULL,
    last_login DATETIME
);

-- 3. Contributions Table
CREATE TABLE contributions (
    contribution_id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('vehicle', 'route', 'location') NOT NULL,
    associated_entry_id INT NOT NULL,
    proposed_by INT,
    accepted_by INT,
    status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
    proposed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    responded_at DATETIME,
    rejection_reason TEXT,
    FOREIGN KEY (proposed_by) REFERENCES agents(agent_id) ON DELETE SET NULL,
    FOREIGN KEY (accepted_by) REFERENCES admins(admin_id) ON DELETE SET NULL
);

-- 4. Locations Table
CREATE TABLE locations (
    location_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    type ENUM('stop', 'landmark') DEFAULT 'stop',
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    contribution_id INT,
    updated_by INT,
    approved_by INT,
    updated_at DATETIME,
    departure_count INT DEFAULT 0,
    destination_count INT DEFAULT 0,
    FOREIGN KEY (contribution_id) REFERENCES contributions(contribution_id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES agents(agent_id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES admins(admin_id) ON DELETE SET NULL
);

-- 5. Routes Table
CREATE TABLE routes (
    route_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL, -- Format: Start - End
    description TEXT,
    image_path VARCHAR(255),
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    contribution_id INT,
    updated_by INT,
    approved_by INT,
    updated_at DATETIME,
    location_list JSON, -- Stores order: [{"index": 1, "location_id": 55}, ...]
    FOREIGN KEY (contribution_id) REFERENCES contributions(contribution_id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES agents(agent_id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES admins(admin_id) ON DELETE SET NULL
);

-- 6. Vehicles Table
CREATE TABLE vehicles (
    vehicle_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    image_path VARCHAR(255),
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    contribution_id INT,
    updated_by INT,
    approved_by INT,
    updated_at DATETIME,
    used_routes JSON, -- Stores routes: [{"route_id": 1, "count": 6}, ...]
    starts_at TIME,
    stops_at TIME,
    current_lat DECIMAL(10, 8) DEFAULT NULL,   -- GPS: real-time latitude
    current_lng DECIMAL(11, 8) DEFAULT NULL,   -- GPS: real-time longitude
    current_speed DECIMAL(5, 1) DEFAULT NULL,  -- GPS: speed in km/h
    gps_updated_at DATETIME DEFAULT NULL,      -- GPS: last position update timestamp
    FOREIGN KEY (contribution_id) REFERENCES contributions(contribution_id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES agents(agent_id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES admins(admin_id) ON DELETE SET NULL
);

-- 7. Alerts Table
CREATE TABLE alerts (
    alert_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    issued_by INT,
    routes_affected JSON, -- Array of route_ids: [3, 5, 9]
    reported_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME,
    FOREIGN KEY (issued_by) REFERENCES admins(admin_id) ON DELETE SET NULL
);

-- 8. Suggestions Table
CREATE TABLE suggestions (
    suggestion_id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('complaint', 'suggestion', 'correction', 'appreciation') NOT NULL,
    message TEXT NOT NULL,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    related_route_id INT,
    related_vehicle_id INT,
    ip_address VARCHAR(45),
    status ENUM('pending', 'reviewed', 'resolved') DEFAULT 'pending',
    reviewed_by INT,
    submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    reviewed_at DATETIME,
    FOREIGN KEY (related_route_id) REFERENCES routes(route_id) ON DELETE SET NULL,
    FOREIGN KEY (related_vehicle_id) REFERENCES vehicles(vehicle_id) ON DELETE SET NULL,
    FOREIGN KEY (reviewed_by) REFERENCES admins(admin_id) ON DELETE SET NULL
);

-- 9. Trips Table (Analytics)
CREATE TABLE trips (
    trip_id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45),
    start_location_id INT,
    destination_location_id INT,
    routes_used JSON, -- Array of route_ids used
    queried_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (start_location_id) REFERENCES locations(location_id) ON DELETE CASCADE,
    FOREIGN KEY (destination_location_id) REFERENCES locations(location_id) ON DELETE CASCADE
);