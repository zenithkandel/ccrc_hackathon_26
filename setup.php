<?php
/**
 * SAWARI — One-Click Setup & Database Installer
 * 
 * This script will:
 * 1. Check PHP version & required extensions
 * 2. Test MySQL connectivity
 * 3. Create the 'sawari' database
 * 4. Create all tables & indexes
 * 5. Seed demo data (admin, agent, stops, routes, vehicles, alerts, suggestions, trips)
 * 6. Create required upload directories
 * 
 * Default credentials after setup:
 *   Admin  →  admin@sawari.com / admin123
 *   Agent  →  ram@sawari.com   / agent123
 * 
 * ⚠ DELETE THIS FILE AFTER SETUP IN PRODUCTION
 */

// ───────────────────────────────────────────
// Configuration — match api/config.php
// ───────────────────────────────────────────
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'sawari';
$DB_CHARSET = 'utf8mb4';

// ───────────────────────────────────────────
// Helpers
// ───────────────────────────────────────────
$steps = [];
$errors = [];
$success = true;

function step(string $label, string $status, string $detail = ''): void
{
    global $steps;
    $steps[] = ['label' => $label, 'status' => $status, 'detail' => $detail];
}

function fail(string $label, string $msg): void
{
    global $steps, $errors, $success;
    $steps[] = ['label' => $label, 'status' => 'fail', 'detail' => $msg];
    $errors[] = $msg;
    $success = false;
}

$ran = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_setup'])) {
    $ran = true;

    // ───────────────────────────────────────
    // STEP 1: PHP Version & Extensions
    // ───────────────────────────────────────
    if (version_compare(PHP_VERSION, '7.4.0', '>=')) {
        step('PHP Version', 'ok', 'PHP ' . PHP_VERSION);
    } else {
        fail('PHP Version', 'PHP 7.4+ required. You have: ' . PHP_VERSION);
    }

    $requiredExts = ['pdo', 'pdo_mysql', 'json', 'session', 'mbstring'];
    $missingExts = [];
    foreach ($requiredExts as $ext) {
        if (!extension_loaded($ext)) {
            $missingExts[] = $ext;
        }
    }
    if (empty($missingExts)) {
        step('PHP Extensions', 'ok', implode(', ', $requiredExts));
    } else {
        fail('PHP Extensions', 'Missing: ' . implode(', ', $missingExts));
    }

    // ───────────────────────────────────────
    // STEP 2: MySQL Connection (without DB)
    // ───────────────────────────────────────
    $pdo = null;
    try {
        $pdo = new PDO(
            "mysql:host={$DB_HOST};charset={$DB_CHARSET}",
            $DB_USER,
            $DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        $ver = $pdo->query('SELECT VERSION()')->fetchColumn();
        step('MySQL Connection', 'ok', "Connected — Server v{$ver}");
    } catch (PDOException $e) {
        fail('MySQL Connection', $e->getMessage());
    }

    if ($pdo) {
        // ───────────────────────────────────
        // STEP 3: Create Database
        // ───────────────────────────────────
        try {
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$DB_NAME}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$DB_NAME}`");
            step('Create Database', 'ok', "`{$DB_NAME}` ready");
        } catch (PDOException $e) {
            fail('Create Database', $e->getMessage());
        }

        // ───────────────────────────────────
        // STEP 4: Create Tables
        // ───────────────────────────────────
        $tables = [

            // 1. Admins
            "CREATE TABLE IF NOT EXISTS admins (
                admin_id        INT AUTO_INCREMENT PRIMARY KEY,
                name            VARCHAR(255) NOT NULL,
                email           VARCHAR(255) NOT NULL UNIQUE,
                password        VARCHAR(255) NOT NULL,
                role            ENUM('superadmin', 'moderator') NOT NULL DEFAULT 'moderator',
                created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_login      DATETIME DEFAULT NULL,
                status          ENUM('active', 'inactive') NOT NULL DEFAULT 'active'
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            // 2. Agents
            "CREATE TABLE IF NOT EXISTS agents (
                agent_id        INT AUTO_INCREMENT PRIMARY KEY,
                name            VARCHAR(255) NOT NULL,
                email           VARCHAR(255) NOT NULL UNIQUE,
                password        VARCHAR(255) NOT NULL,
                phone           VARCHAR(20) DEFAULT NULL,
                points          INT NOT NULL DEFAULT 0,
                contributions_count INT NOT NULL DEFAULT 0,
                approved_count  INT NOT NULL DEFAULT 0,
                created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_login      DATETIME DEFAULT NULL,
                status          ENUM('active', 'suspended', 'inactive') NOT NULL DEFAULT 'active',
                approved_by     INT DEFAULT NULL,
                FOREIGN KEY (approved_by) REFERENCES admins(admin_id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            // 3. Contributions
            "CREATE TABLE IF NOT EXISTS contributions (
                contribution_id INT AUTO_INCREMENT PRIMARY KEY,
                agent_id        INT NOT NULL,
                type            ENUM('location', 'vehicle', 'route') NOT NULL,
                status          ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
                notes           TEXT DEFAULT NULL,
                rejection_reason TEXT DEFAULT NULL,
                created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                reviewed_by     INT DEFAULT NULL,
                reviewed_at     DATETIME DEFAULT NULL,
                FOREIGN KEY (agent_id) REFERENCES agents(agent_id) ON DELETE CASCADE,
                FOREIGN KEY (reviewed_by) REFERENCES admins(admin_id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            // 4. Locations
            "CREATE TABLE IF NOT EXISTS locations (
                location_id       INT AUTO_INCREMENT PRIMARY KEY,
                name              VARCHAR(255) NOT NULL,
                description       TEXT DEFAULT NULL,
                latitude          DECIMAL(10,8) NOT NULL,
                longitude         DECIMAL(11,8) NOT NULL,
                type              ENUM('stop', 'landmark') NOT NULL DEFAULT 'stop',
                status            ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
                contribution_id   INT DEFAULT NULL,
                updated_by        INT DEFAULT NULL,
                approved_by       INT DEFAULT NULL,
                updated_at        DATETIME DEFAULT NULL,
                departure_count   INT NOT NULL DEFAULT 0,
                destination_count INT NOT NULL DEFAULT 0,
                FOREIGN KEY (contribution_id) REFERENCES contributions(contribution_id) ON DELETE SET NULL,
                FOREIGN KEY (updated_by) REFERENCES agents(agent_id) ON DELETE SET NULL,
                FOREIGN KEY (approved_by) REFERENCES admins(admin_id) ON DELETE SET NULL,
                INDEX idx_location_coords (latitude, longitude),
                INDEX idx_location_status (status),
                INDEX idx_location_type (type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            // 5. Routes
            "CREATE TABLE IF NOT EXISTS routes (
                route_id        INT AUTO_INCREMENT PRIMARY KEY,
                name            VARCHAR(255) NOT NULL,
                description     TEXT DEFAULT NULL,
                status          ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
                contribution_id INT DEFAULT NULL,
                updated_by      INT DEFAULT NULL,
                approved_by     INT DEFAULT NULL,
                updated_at      DATETIME DEFAULT NULL,
                location_list   JSON NOT NULL,
                fare_base       DECIMAL(6,2) DEFAULT NULL,
                fare_per_km     DECIMAL(6,2) DEFAULT NULL,
                FOREIGN KEY (contribution_id) REFERENCES contributions(contribution_id) ON DELETE SET NULL,
                FOREIGN KEY (updated_by) REFERENCES agents(agent_id) ON DELETE SET NULL,
                FOREIGN KEY (approved_by) REFERENCES admins(admin_id) ON DELETE SET NULL,
                INDEX idx_route_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            // 6. Vehicles
            "CREATE TABLE IF NOT EXISTS vehicles (
                vehicle_id      INT AUTO_INCREMENT PRIMARY KEY,
                name            VARCHAR(255) NOT NULL,
                description     TEXT DEFAULT NULL,
                image_path      VARCHAR(255) DEFAULT NULL,
                status          ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
                contribution_id INT DEFAULT NULL,
                latitude        TEXT DEFAULT NULL,
                longitude       TEXT DEFAULT NULL,
                velocity        TEXT DEFAULT NULL,
                electric        TINYINT(1) NOT NULL DEFAULT 0,
                updated_by      INT DEFAULT NULL,
                approved_by     INT DEFAULT NULL,
                updated_at      DATETIME DEFAULT NULL,
                used_routes     JSON DEFAULT NULL,
                starts_at       TIME DEFAULT NULL,
                stops_at        TIME DEFAULT NULL,
                gps_active      TINYINT(1) NOT NULL DEFAULT 0,
                last_gps_update DATETIME DEFAULT NULL,
                FOREIGN KEY (contribution_id) REFERENCES contributions(contribution_id) ON DELETE SET NULL,
                FOREIGN KEY (updated_by) REFERENCES agents(agent_id) ON DELETE SET NULL,
                FOREIGN KEY (approved_by) REFERENCES admins(admin_id) ON DELETE SET NULL,
                INDEX idx_vehicle_status (status),
                INDEX idx_vehicle_gps (gps_active),
                INDEX idx_vehicle_live (status, gps_active, last_gps_update)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            // 7. Trips
            "CREATE TABLE IF NOT EXISTS trips (
                trip_id             INT AUTO_INCREMENT PRIMARY KEY,
                session_id          VARCHAR(64) NOT NULL,
                route_id            INT DEFAULT NULL,
                vehicle_id          INT DEFAULT NULL,
                boarding_stop_id    INT DEFAULT NULL,
                destination_stop_id INT DEFAULT NULL,
                transfer_stop_id    INT DEFAULT NULL,
                second_route_id     INT DEFAULT NULL,
                started_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                ended_at            DATETIME DEFAULT NULL,
                rating              TINYINT DEFAULT NULL,
                review              TEXT DEFAULT NULL,
                accuracy_feedback   ENUM('accurate', 'slightly_off', 'inaccurate') DEFAULT NULL,
                fare_paid           DECIMAL(6,2) DEFAULT NULL,
                carbon_saved        DECIMAL(8,4) DEFAULT NULL,
                FOREIGN KEY (route_id) REFERENCES routes(route_id) ON DELETE SET NULL,
                FOREIGN KEY (vehicle_id) REFERENCES vehicles(vehicle_id) ON DELETE SET NULL,
                FOREIGN KEY (boarding_stop_id) REFERENCES locations(location_id) ON DELETE SET NULL,
                FOREIGN KEY (destination_stop_id) REFERENCES locations(location_id) ON DELETE SET NULL,
                FOREIGN KEY (transfer_stop_id) REFERENCES locations(location_id) ON DELETE SET NULL,
                FOREIGN KEY (second_route_id) REFERENCES routes(route_id) ON DELETE SET NULL,
                INDEX idx_trip_session (session_id),
                INDEX idx_trip_route (route_id),
                INDEX idx_trip_date (started_at),
                INDEX idx_trip_rating (rating)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            // 8. Alerts
            "CREATE TABLE IF NOT EXISTS alerts (
                alert_id        INT AUTO_INCREMENT PRIMARY KEY,
                route_id        INT DEFAULT NULL,
                title           VARCHAR(255) NOT NULL,
                description     TEXT NOT NULL,
                severity        ENUM('low', 'medium', 'high', 'critical') NOT NULL DEFAULT 'medium',
                created_by      INT NOT NULL,
                created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                expires_at      DATETIME DEFAULT NULL,
                status          ENUM('active', 'resolved', 'expired') NOT NULL DEFAULT 'active',
                resolved_at     DATETIME DEFAULT NULL,
                FOREIGN KEY (route_id) REFERENCES routes(route_id) ON DELETE CASCADE,
                FOREIGN KEY (created_by) REFERENCES admins(admin_id) ON DELETE CASCADE,
                INDEX idx_alert_status (status),
                INDEX idx_alert_route (route_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            // 9. Suggestions
            "CREATE TABLE IF NOT EXISTS suggestions (
                suggestion_id   INT AUTO_INCREMENT PRIMARY KEY,
                user_type       ENUM('agent', 'user') NOT NULL DEFAULT 'user',
                user_identifier VARCHAR(255) DEFAULT NULL,
                type            ENUM('missing_stop', 'route_correction', 'new_route', 'general') NOT NULL,
                title           VARCHAR(255) NOT NULL,
                description     TEXT NOT NULL,
                latitude        DECIMAL(10,8) DEFAULT NULL,
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ];

        // Extra indexes (safe with IF NOT EXISTS on tables, but indexes
        // will silently fail if they already exist — we wrap in try/catch)
        $indexes = [
            "CREATE INDEX idx_contribution_agent ON contributions(agent_id, status)",
            "CREATE INDEX idx_contribution_type  ON contributions(type, status)",
        ];

        $tableCount = 0;
        foreach ($tables as $sql) {
            try {
                $pdo->exec($sql);
                $tableCount++;
            } catch (PDOException $e) {
                fail('Create Tables', $e->getMessage());
                break;
            }
        }
        if ($tableCount === count($tables)) {
            step('Create Tables', 'ok', "{$tableCount} tables created / verified");
        }

        // Indexes (ignore duplicate-key errors)
        foreach ($indexes as $idx) {
            try {
                $pdo->exec($idx);
            } catch (PDOException $e) { /* already exists */
            }
        }
        step('Create Indexes', 'ok', 'Performance indexes applied');

        // ───────────────────────────────────
        // STEP 5: Seed Demo Data
        // ───────────────────────────────────
        $seeded = [];

        // Check if data already exists to avoid duplicates
        $adminCount = (int) $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
        $agentCount = (int) $pdo->query("SELECT COUNT(*) FROM agents")->fetchColumn();

        // ----- Admin -----
        if ($adminCount === 0) {
            $adminHash = password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $pdo->prepare("INSERT INTO admins (name, email, password, role) VALUES (?, ?, ?, 'superadmin')");
            $stmt->execute(['Super Admin', 'admin@sawari.com', $adminHash]);
            $seeded[] = 'admin';
        }

        // ----- Agent -----
        if ($agentCount === 0) {
            $agentHash = password_hash('agent123', PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $pdo->prepare("INSERT INTO agents (name, email, password, phone, points, contributions_count, approved_count, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')");
            $stmt->execute(['Ram Shrestha', 'ram@sawari.com', $agentHash, '+977 9801234567', 150, 15, 15]);

            // Second agent for leaderboard variety
            $agent2Hash = password_hash('agent123', PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt->execute(['Sita Tamang', 'sita@sawari.com', $agent2Hash, '+977 9807654321', 95, 10, 8]);

            // Third agent
            $agent3Hash = password_hash('agent123', PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt->execute(['Bikash Gurung', 'bikash@sawari.com', $agent3Hash, '+977 9812345678', 60, 7, 5]);

            $seeded[] = '3 agents';
        }

        // ----- Locations (15 Kathmandu bus stops) -----
        $locationCount = (int) $pdo->query("SELECT COUNT(*) FROM locations")->fetchColumn();
        if ($locationCount === 0) {
            $locStmt = $pdo->prepare("INSERT INTO locations (name, description, latitude, longitude, type, status, approved_by, updated_at) VALUES (?, ?, ?, ?, 'stop', 'approved', 1, NOW())");

            $stops = [
                ['Kalanki Chowk', 'Major intersection and bus terminal in western Kathmandu', 27.69350000, 85.28140000],
                ['Balkhu', 'Bus stop near Balkhu bridge and Tribhuvan University', 27.68420000, 85.29860000],
                ['Kalimati', 'Near Kalimati vegetable market area', 27.69500000, 85.30500000],
                ['Tripureshwor', 'Major bus stop near Tripureshwor tower', 27.69600000, 85.31700000],
                ['Thapathali', 'Bus stop near Thapathali bridge', 27.69200000, 85.32400000],
                ['Maitighar Mandala', 'Central roundabout near Singha Durbar', 27.69400000, 85.32200000],
                ['Ratnapark', 'Central Kathmandu bus hub near Dharahara', 27.70400000, 85.31400000],
                ['Jamal', 'Bus stop near Jamal area', 27.71000000, 85.31700000],
                ['Durbarmarg', 'Near Royal Palace Museum and Narayanhiti', 27.71400000, 85.31900000],
                ['Putalisadak', 'Major commercial area bus stop', 27.70300000, 85.32400000],
                ['Koteshwor', 'Eastern Kathmandu junction near ring road', 27.67750000, 85.34900000],
                ['Chabahil', 'Bus stop near Chabahil Ganesthan', 27.71800000, 85.34200000],
                ['Gongabu Bus Park', 'Main long-distance bus terminal (New Bus Park)', 27.73000000, 85.31200000],
                ['Balaju', 'Northern Kathmandu near Balaju Industrial District', 27.72800000, 85.30400000],
                ['Maharajgunj', 'Bus stop near Teaching Hospital and UNDP', 27.73400000, 85.32700000],
            ];

            foreach ($stops as $s) {
                $locStmt->execute($s);
            }
            $seeded[] = '15 bus stops';
        }

        // ----- Vehicles (5 Kathmandu public transport) -----
        $vehicleCount = (int) $pdo->query("SELECT COUNT(*) FROM vehicles")->fetchColumn();
        if ($vehicleCount === 0) {
            $vehStmt = $pdo->prepare("INSERT INTO vehicles (name, description, electric, starts_at, stops_at, status, approved_by, updated_at) VALUES (?, ?, ?, ?, ?, 'approved', 1, NOW())");

            $vehicles = [
                ['Sajha Yatayat', 'Modern public bus service operated by Sajha Yatayat cooperative. Air-conditioned, clean buses on major routes.', 0, '06:00', '21:00'],
                ['Kathmandu Micro Bus', 'Blue and green micro buses running Ring Road and city routes. Seats 12-15 passengers.', 0, '05:30', '21:00'],
                ['Safa Tempo', 'Three-wheeled electric tempo running fixed routes within Kathmandu. Eco-friendly option.', 1, '06:00', '20:00'],
                ['Mayur Yatayat', 'Public bus service operating on longer urban routes. Standard city bus.', 0, '05:30', '20:30'],
                ['Valley Electric Bus', 'New electric bus service on pilot routes in Kathmandu valley.', 1, '06:30', '19:30'],
            ];

            foreach ($vehicles as $v) {
                $vehStmt->execute($v);
            }
            $seeded[] = '5 vehicles';
        }

        // ----- Routes (3 real Kathmandu routes) -----
        $routeCount = (int) $pdo->query("SELECT COUNT(*) FROM routes")->fetchColumn();
        if ($routeCount === 0) {
            $rtStmt = $pdo->prepare("INSERT INTO routes (name, description, location_list, fare_base, fare_per_km, status, approved_by, updated_at) VALUES (?, ?, ?, ?, ?, 'approved', 1, NOW())");

            // Route 1: Kalanki → Ratnapark → Gongabu
            $rtStmt->execute([
                'Kalanki – Ratnapark – Gongabu',
                'Major east-west route connecting Kalanki junction through city center to New Bus Park',
                json_encode([
                    ['location_id' => 1, 'name' => 'Kalanki Chowk', 'latitude' => 27.6935, 'longitude' => 85.2814],
                    ['location_id' => 2, 'name' => 'Balkhu', 'latitude' => 27.6842, 'longitude' => 85.2986],
                    ['location_id' => 3, 'name' => 'Kalimati', 'latitude' => 27.695, 'longitude' => 85.305],
                    ['location_id' => 4, 'name' => 'Tripureshwor', 'latitude' => 27.696, 'longitude' => 85.317],
                    ['location_id' => 7, 'name' => 'Ratnapark', 'latitude' => 27.704, 'longitude' => 85.314],
                    ['location_id' => 8, 'name' => 'Jamal', 'latitude' => 27.71, 'longitude' => 85.317],
                    ['location_id' => 14, 'name' => 'Balaju', 'latitude' => 27.728, 'longitude' => 85.304],
                    ['location_id' => 13, 'name' => 'Gongabu Bus Park', 'latitude' => 27.73, 'longitude' => 85.312],
                ]),
                20.00,
                2.50,
            ]);

            // Route 2: Ratnapark → Koteshwor
            $rtStmt->execute([
                'Ratnapark – Koteshwor',
                'Route connecting central Kathmandu to eastern ring road junction via Putalisadak',
                json_encode([
                    ['location_id' => 7, 'name' => 'Ratnapark', 'latitude' => 27.704, 'longitude' => 85.314],
                    ['location_id' => 10, 'name' => 'Putalisadak', 'latitude' => 27.703, 'longitude' => 85.324],
                    ['location_id' => 6, 'name' => 'Maitighar Mandala', 'latitude' => 27.694, 'longitude' => 85.322],
                    ['location_id' => 5, 'name' => 'Thapathali', 'latitude' => 27.692, 'longitude' => 85.324],
                    ['location_id' => 11, 'name' => 'Koteshwor', 'latitude' => 27.6775, 'longitude' => 85.349],
                ]),
                15.00,
                2.50,
            ]);

            // Route 3: Gongabu → Ring Road → Koteshwor
            $rtStmt->execute([
                'Gongabu – Ring Road – Koteshwor',
                'Partial ring road route running the northern and eastern sections',
                json_encode([
                    ['location_id' => 13, 'name' => 'Gongabu Bus Park', 'latitude' => 27.73, 'longitude' => 85.312],
                    ['location_id' => 15, 'name' => 'Maharajgunj', 'latitude' => 27.734, 'longitude' => 85.327],
                    ['location_id' => 12, 'name' => 'Chabahil', 'latitude' => 27.718, 'longitude' => 85.342],
                    ['location_id' => 11, 'name' => 'Koteshwor', 'latitude' => 27.6775, 'longitude' => 85.349],
                ]),
                18.00,
                2.00,
            ]);
            $seeded[] = '3 routes';

            // Assign routes to vehicles
            $pdo->exec("UPDATE vehicles SET used_routes = '[1, 2]'   WHERE name = 'Sajha Yatayat'");
            $pdo->exec("UPDATE vehicles SET used_routes = '[1, 2, 3]' WHERE name = 'Kathmandu Micro Bus'");
            $pdo->exec("UPDATE vehicles SET used_routes = '[2]'       WHERE name = 'Safa Tempo'");
            $pdo->exec("UPDATE vehicles SET used_routes = '[1, 3]'   WHERE name = 'Mayur Yatayat'");
            $pdo->exec("UPDATE vehicles SET used_routes = '[2]'       WHERE name = 'Valley Electric Bus'");
            $seeded[] = 'vehicle-route assignments';
        }

        // ----- Alerts (demo emergency alerts) -----
        $alertCount = (int) $pdo->query("SELECT COUNT(*) FROM alerts")->fetchColumn();
        if ($alertCount === 0) {
            $alStmt = $pdo->prepare("INSERT INTO alerts (route_id, title, description, severity, created_by, expires_at, status) VALUES (?, ?, ?, ?, 1, ?, 'active')");

            $alStmt->execute([
                1,
                'Road Construction at Kalimati',
                'Ongoing road widening project near Kalimati vegetable market. Expect delays of 15-20 minutes during peak hours. Buses may skip the Kalimati stop temporarily.',
                'medium',
                date('Y-m-d H:i:s', strtotime('+7 days')),
            ]);

            $alStmt->execute([
                2,
                'Waterlogging at Thapathali',
                'Heavy monsoon rain has caused waterlogging near Thapathali bridge. Some micro buses are diverting via Maitighar. Please plan accordingly.',
                'high',
                date('Y-m-d H:i:s', strtotime('+3 days')),
            ]);

            $alStmt->execute([
                null,
                'Dashain Festival Schedule',
                'During Dashain festival (Oct 1-15), bus services will run on reduced schedules. Last buses depart by 7 PM. Plan your travel early.',
                'low',
                date('Y-m-d H:i:s', strtotime('+30 days')),
            ]);

            $seeded[] = '3 alerts';
        }

        // ----- Suggestions (demo community suggestions) -----
        $sugCount = (int) $pdo->query("SELECT COUNT(*) FROM suggestions")->fetchColumn();
        if ($sugCount === 0) {
            $sugStmt = $pdo->prepare("INSERT INTO suggestions (user_type, user_identifier, type, title, description, latitude, longitude, related_route_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $sugStmt->execute([
                'user',
                'demo-session-001',
                'missing_stop',
                'New stop at Bagbazar needed',
                'There is a very busy pickup area near Bagbazar chowk but no official stop is listed. Many passengers wait here for Route 2 buses.',
                27.7050,
                85.3190,
                2,
                'pending',
            ]);

            $sugStmt->execute([
                'agent',
                '1',
                'route_correction',
                'Route 1 should include Sorhakhutte',
                'The Kalanki-Gongabu route passes through Sorhakhutte which is a popular boarding point but is not listed as a stop.',
                27.7200,
                85.3050,
                1,
                'pending',
            ]);

            $sugStmt->execute([
                'user',
                'demo-session-002',
                'new_route',
                'Direct Kalanki to Koteshwor route',
                'Currently there is no direct bus from Kalanki to Koteshwor. Passengers have to transfer at Ratnapark. A direct route would be very helpful.',
                null,
                null,
                null,
                'pending',
            ]);

            $seeded[] = '3 suggestions';
        }

        // ----- Trips (demo trip data with ratings) -----
        $tripCount = (int) $pdo->query("SELECT COUNT(*) FROM trips")->fetchColumn();
        if ($tripCount === 0) {
            $trStmt = $pdo->prepare("INSERT INTO trips (session_id, route_id, vehicle_id, boarding_stop_id, destination_stop_id, transfer_stop_id, second_route_id, started_at, ended_at, rating, review, accuracy_feedback, fare_paid, carbon_saved) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            // Trip 1: Kalanki to Ratnapark on Sajha Yatayat (rated 5 stars)
            $trStmt->execute([
                'demo-session-001',
                1,
                1,
                1,
                7,
                null,
                null,
                date('Y-m-d H:i:s', strtotime('-3 days')),
                date('Y-m-d H:i:s', strtotime('-3 days +45 minutes')),
                5,
                'Excellent service! Clean bus and on time.',
                'accurate',
                30.00,
                1.2500,
            ]);

            // Trip 2: Ratnapark to Koteshwor on Micro Bus (rated 4 stars)
            $trStmt->execute([
                'demo-session-002',
                2,
                2,
                7,
                11,
                null,
                null,
                date('Y-m-d H:i:s', strtotime('-2 days')),
                date('Y-m-d H:i:s', strtotime('-2 days +35 minutes')),
                4,
                'Good route, slightly crowded during rush hour.',
                'accurate',
                25.00,
                0.8750,
            ]);

            // Trip 3: Transfer trip Kalanki → Ratnapark → Koteshwor (rated 3 stars)
            $trStmt->execute([
                'demo-session-003',
                1,
                1,
                1,
                11,
                7,
                2,
                date('Y-m-d H:i:s', strtotime('-1 day')),
                date('Y-m-d H:i:s', strtotime('-1 day +1 hour 10 minutes')),
                3,
                'Transfer was confusing, better signage needed at Ratnapark.',
                'slightly_off',
                50.00,
                2.1000,
            ]);

            // Trip 4: Gongabu to Chabahil on Electric Bus (rated 5 stars — no review)
            $trStmt->execute([
                'demo-session-004',
                3,
                5,
                13,
                12,
                null,
                null,
                date('Y-m-d H:i:s', strtotime('-12 hours')),
                date('Y-m-d H:i:s', strtotime('-12 hours +25 minutes')),
                5,
                null,
                'accurate',
                20.00,
                0.4200,
            ]);

            // Trip 5: Recent unfinished trip (no rating yet)
            $trStmt->execute([
                'demo-session-005',
                2,
                3,
                7,
                11,
                null,
                null,
                date('Y-m-d H:i:s', strtotime('-1 hour')),
                null,
                null,
                null,
                null,
                15.00,
                0.6300,
            ]);

            // Update departure/destination counts on locations
            $pdo->exec("UPDATE locations SET departure_count = 3 WHERE location_id = 1");   // Kalanki
            $pdo->exec("UPDATE locations SET destination_count = 2 WHERE location_id = 7"); // Ratnapark
            $pdo->exec("UPDATE locations SET departure_count = 3 WHERE location_id = 7");   // Ratnapark (as departure)
            $pdo->exec("UPDATE locations SET destination_count = 3 WHERE location_id = 11"); // Koteshwor
            $pdo->exec("UPDATE locations SET departure_count = 1 WHERE location_id = 13");  // Gongabu
            $pdo->exec("UPDATE locations SET destination_count = 1 WHERE location_id = 12"); // Chabahil

            $seeded[] = '5 trips with ratings';
        }

        // ----- Contributions (demo agent contributions — matches the agent's counts) -----
        $contribCount = (int) $pdo->query("SELECT COUNT(*) FROM contributions")->fetchColumn();
        if ($contribCount === 0) {
            $cStmt = $pdo->prepare("INSERT INTO contributions (agent_id, type, status, notes, reviewed_by, reviewed_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");

            // Agent 1 (Ram) — 15 contributions, all approved
            $types = [
                'location',
                'location',
                'location',
                'location',
                'location',
                'vehicle',
                'vehicle',
                'vehicle',
                'route',
                'route',
                'route',
                'location',
                'location',
                'location',
                'location'
            ];
            for ($i = 0; $i < 15; $i++) {
                $cStmt->execute([
                    1,
                    $types[$i],
                    'approved',
                    'Field data collected from Kathmandu valley survey #' . ($i + 1),
                    1,
                    date('Y-m-d H:i:s', strtotime("-" . (30 - $i) . " days")),
                    date('Y-m-d H:i:s', strtotime("-" . (31 - $i) . " days")),
                ]);
            }

            // Agent 2 (Sita) — 10 contributions, 8 approved, 2 pending
            for ($i = 0; $i < 10; $i++) {
                $status = $i < 8 ? 'approved' : 'pending';
                $revBy = $i < 8 ? 1 : null;
                $revAt = $i < 8 ? date('Y-m-d H:i:s', strtotime("-" . (20 - $i) . " days")) : null;
                $cStmt->execute([
                    2,
                    $types[$i % count($types)],
                    $status,
                    'Data collection from Lalitpur area #' . ($i + 1),
                    $revBy,
                    $revAt,
                    date('Y-m-d H:i:s', strtotime("-" . (21 - $i) . " days")),
                ]);
            }

            // Agent 3 (Bikash) — 7 contributions, 5 approved, 2 pending
            for ($i = 0; $i < 7; $i++) {
                $status = $i < 5 ? 'approved' : 'pending';
                $revBy = $i < 5 ? 1 : null;
                $revAt = $i < 5 ? date('Y-m-d H:i:s', strtotime("-" . (15 - $i) . " days")) : null;
                $cStmt->execute([
                    3,
                    $types[$i % count($types)],
                    $status,
                    'Eastern Kathmandu field survey #' . ($i + 1),
                    $revBy,
                    $revAt,
                    date('Y-m-d H:i:s', strtotime("-" . (16 - $i) . " days")),
                ]);
            }

            $seeded[] = '32 contributions';
        }

        if (!empty($seeded)) {
            step('Seed Demo Data', 'ok', 'Seeded: ' . implode(', ', $seeded));
        } else {
            step('Seed Demo Data', 'skip', 'Data already exists — skipped to avoid duplicates');
        }

        // ───────────────────────────────────
        // STEP 6: Upload Directories
        // ───────────────────────────────────
        $uploadDir = __DIR__ . '/uploads';
        $vehicleDir = $uploadDir . '/vehicles';

        $dirsCreated = [];
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
            $dirsCreated[] = 'uploads/';
        }
        if (!is_dir($vehicleDir)) {
            mkdir($vehicleDir, 0755, true);
            $dirsCreated[] = 'uploads/vehicles/';
        }

        if (!empty($dirsCreated)) {
            step('Upload Directories', 'ok', 'Created: ' . implode(', ', $dirsCreated));
        } else {
            step('Upload Directories', 'ok', 'Already exist');
        }

        // ───────────────────────────────────
        // STEP 7: Verify Everything
        // ───────────────────────────────────
        $verify = [];
        $tableList = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $verify['tables'] = count($tableList);
        $verify['admins'] = (int) $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
        $verify['agents'] = (int) $pdo->query("SELECT COUNT(*) FROM agents")->fetchColumn();
        $verify['stops'] = (int) $pdo->query("SELECT COUNT(*) FROM locations")->fetchColumn();
        $verify['routes'] = (int) $pdo->query("SELECT COUNT(*) FROM routes")->fetchColumn();
        $verify['vehicles'] = (int) $pdo->query("SELECT COUNT(*) FROM vehicles")->fetchColumn();
        $verify['trips'] = (int) $pdo->query("SELECT COUNT(*) FROM trips")->fetchColumn();
        $verify['alerts'] = (int) $pdo->query("SELECT COUNT(*) FROM alerts")->fetchColumn();
        $verify['suggestions'] = (int) $pdo->query("SELECT COUNT(*) FROM suggestions")->fetchColumn();
        $verify['contributions'] = (int) $pdo->query("SELECT COUNT(*) FROM contributions")->fetchColumn();

        $summary = "{$verify['tables']} tables, {$verify['admins']} admin(s), {$verify['agents']} agent(s), "
            . "{$verify['stops']} stops, {$verify['routes']} routes, {$verify['vehicles']} vehicles, "
            . "{$verify['trips']} trips, {$verify['alerts']} alerts, {$verify['suggestions']} suggestions, "
            . "{$verify['contributions']} contributions";

        step('Verification', 'ok', $summary);
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sawari — Setup</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: #f0f4f8;
            color: #1e293b;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
            max-width: 640px;
            width: 100%;
            overflow: hidden;
        }

        .card-header {
            background: #1A56DB;
            color: #fff;
            padding: 1.5rem 2rem;
        }

        .card-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
        }

        .card-header p {
            opacity: 0.85;
            margin-top: 0.25rem;
            font-size: 0.9rem;
        }

        .card-body {
            padding: 2rem;
        }

        .step {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .step:last-child {
            border-bottom: none;
        }

        .step-icon {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            font-weight: 700;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .step-icon.ok {
            background: #dcfce7;
            color: #16a34a;
        }

        .step-icon.fail {
            background: #fecaca;
            color: #dc2626;
        }

        .step-icon.skip {
            background: #fef3c7;
            color: #d97706;
        }

        .step-label {
            font-weight: 600;
            font-size: 0.95rem;
        }

        .step-detail {
            font-size: 0.85rem;
            color: #64748b;
            margin-top: 2px;
            word-break: break-word;
        }

        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.15s;
        }

        .btn-primary {
            background: #1A56DB;
            color: #fff;
        }

        .btn-primary:hover {
            background: #1e40af;
        }

        .btn-success {
            background: #16a34a;
            color: #fff;
        }

        .btn-success:hover {
            background: #15803d;
        }

        .btn-danger {
            background: #dc2626;
            color: #fff;
        }

        .btn-danger:hover {
            background: #b91c1c;
        }

        .actions {
            padding: 1.5rem 2rem;
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            border-top: 1px solid #e2e8f0;
        }

        .creds {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1rem 1.25rem;
            margin-top: 1rem;
            font-size: 0.9rem;
        }

        .creds h3 {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            margin-bottom: 0.5rem;
        }

        .creds code {
            background: #e2e8f0;
            padding: 0.15rem 0.4rem;
            border-radius: 4px;
            font-size: 0.85rem;
        }

        .banner {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        .banner-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .banner-error {
            background: #fecaca;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        .banner-warn {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        .pre-info {
            margin-bottom: 1.5rem;
        }

        .pre-info ul {
            list-style: none;
            padding: 0;
        }

        .pre-info li {
            padding: 0.35rem 0;
            font-size: 0.9rem;
        }

        .pre-info li::before {
            content: '→ ';
            color: #1A56DB;
            font-weight: 700;
        }
    </style>
</head>

<body>
    <div class="card">
        <div class="card-header">
            <h1>&#x1F68C; Sawari — Setup Wizard</h1>
            <p>One-click database installation &amp; demo data seeding</p>
        </div>

        <div class="card-body">
            <?php if (!$ran): ?>
                <div class="banner banner-warn">
                    <strong>Warning:</strong> This will create the <code>sawari</code> database and seed demo data. Existing
                    data will NOT be overwritten.
                </div>

                <div class="pre-info">
                    <p><strong>This setup will:</strong></p>
                    <ul>
                        <li>Check PHP version (7.4+) and required extensions</li>
                        <li>Connect to MySQL at <code><?= htmlspecialchars($DB_HOST) ?></code> as
                            <code><?= htmlspecialchars($DB_USER) ?></code></li>
                        <li>Create database <code><?= htmlspecialchars($DB_NAME) ?></code></li>
                        <li>Create 9 tables (admins, agents, contributions, locations, routes, vehicles, trips, alerts,
                            suggestions)</li>
                        <li>Seed demo data: 1 admin, 3 agents, 15 bus stops, 3 routes, 5 vehicles, 5 trips, 3 alerts, 3
                            suggestions, 32 contributions</li>
                        <li>Create upload directories</li>
                    </ul>
                </div>

                <form method="POST">
                    <button type="submit" name="run_setup" value="1" class="btn btn-primary">
                        Run Setup
                    </button>
                </form>

            <?php else: ?>

                <?php if ($success): ?>
                    <div class="banner banner-success">
                        <strong>Setup Complete!</strong> The Sawari database is ready with demo data.
                    </div>
                <?php else: ?>
                    <div class="banner banner-error">
                        <strong>Setup encountered errors.</strong> See details below.
                    </div>
                <?php endif; ?>

                <?php foreach ($steps as $s): ?>
                    <div class="step">
                        <div class="step-icon <?= $s['status'] ?>">
                            <?php if ($s['status'] === 'ok'): ?>&#10003;<?php elseif ($s['status'] === 'fail'): ?>&#10007;<?php else: ?>&#8212;<?php endif; ?>
                        </div>
                        <div>
                            <div class="step-label"><?= htmlspecialchars($s['label']) ?></div>
                            <?php if ($s['detail']): ?>
                                <div class="step-detail"><?= htmlspecialchars($s['detail']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if ($success): ?>
                    <div class="creds">
                        <h3>Default Login Credentials</h3>
                        <p style="margin-bottom: 0.5rem;">
                            <strong>Admin:</strong> <code>admin@sawari.com</code> / <code>admin123</code>
                        </p>
                        <p style="margin-bottom: 0.5rem;">
                            <strong>Agent 1:</strong> <code>ram@sawari.com</code> / <code>agent123</code> (150 pts)
                        </p>
                        <p style="margin-bottom: 0.5rem;">
                            <strong>Agent 2:</strong> <code>sita@sawari.com</code> / <code>agent123</code> (95 pts)
                        </p>
                        <p>
                            <strong>Agent 3:</strong> <code>bikash@sawari.com</code> / <code>agent123</code> (60 pts)
                        </p>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>

        <div class="actions">
            <?php if ($ran && $success): ?>
                <a href="index.php" class="btn btn-success">Open Sawari</a>
                <a href="pages/map.php" class="btn btn-primary">Open Map</a>
                <a href="pages/admin/login.php" class="btn btn-primary">Admin Login</a>
                <a href="pages/agent/login.php" class="btn btn-primary">Agent Login</a>
            <?php elseif ($ran): ?>
                <form method="POST" style="display:inline;">
                    <button type="submit" name="run_setup" value="1" class="btn btn-danger">Retry Setup</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>