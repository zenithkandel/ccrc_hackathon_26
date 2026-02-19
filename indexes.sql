-- ═══════════════════════════════════════════════════════════
-- Sawari — Performance Indexes
-- ═══════════════════════════════════════════════════════════
-- Run after schema.sql to add indexes for frequently queried columns.

USE test_sawari_db;

-- Locations
CREATE INDEX idx_locations_status ON locations(status);
CREATE INDEX idx_locations_name ON locations(name);
CREATE INDEX idx_locations_type ON locations(type);

-- Routes
CREATE INDEX idx_routes_status ON routes(status);

-- Vehicles
CREATE INDEX idx_vehicles_status ON vehicles(status);

-- Contributions
CREATE INDEX idx_contributions_status ON contributions(status);
CREATE INDEX idx_contributions_type ON contributions(type);
CREATE INDEX idx_contributions_proposed_by ON contributions(proposed_by);

-- Suggestions
CREATE INDEX idx_suggestions_status ON suggestions(status);

-- Alerts
CREATE INDEX idx_alerts_expires_at ON alerts(expires_at);

-- Trips
CREATE INDEX idx_trips_queried_at ON trips(queried_at);
CREATE INDEX idx_trips_start ON trips(start_location_id);
CREATE INDEX idx_trips_dest ON trips(destination_location_id);
