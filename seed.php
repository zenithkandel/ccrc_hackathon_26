<?php
/**
 * Seed Script — seed.php
 * 
 * Inserts comprehensive realistic data for Kathmandu Valley public transport.
 * Run from CLI:  php seed.php
 * Or via browser: http://localhost/test_sawari/seed.php
 * 
 * Default Accounts:
 *   Admin:  admin@sawari.com  / Admin@123
 *   Agents: agent1@sawari.com / Agent@123  (and agent2-agent5)
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$isCli = php_sapi_name() === 'cli';
$nl = $isCli ? "\n" : "<br>";

echo "=== Sawari Comprehensive Seed Script ===$nl$nl";

$pdo = getDBConnection();

// ─── Clear existing data (fresh start) ────────────────
echo "0. Clearing existing data for fresh seed...{$nl}";

$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
$pdo->exec('TRUNCATE TABLE trips');
$pdo->exec('TRUNCATE TABLE suggestions');
$pdo->exec('TRUNCATE TABLE alerts');
$pdo->exec('TRUNCATE TABLE vehicles');
$pdo->exec('TRUNCATE TABLE routes');
$pdo->exec('TRUNCATE TABLE locations');
$pdo->exec('TRUNCATE TABLE contributions');
$pdo->exec('TRUNCATE TABLE agents');
$pdo->exec('TRUNCATE TABLE admins');
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

echo "   ✓ All tables cleared{$nl}";

// ─── 1. Admin Account ─────────────────────────────────
echo "{$nl}1. Seeding admin account...{$nl}";

$pdo->prepare('
    INSERT INTO admins (name, email, password_hash, phone_number, joined_at)
    VALUES (:name, :email, :hash, :phone, NOW())
')->execute([
            'name' => 'System Admin',
            'email' => 'admin@sawari.com',
            'hash' => hashPassword('Admin@123'),
            'phone' => '9801000001',
        ]);
$adminId = (int) $pdo->lastInsertId();
echo "   ✓ Admin: admin@sawari.com / Admin@123{$nl}";

// ─── 2. Agent Accounts ────────────────────────────────
echo "{$nl}2. Seeding agent accounts...{$nl}";

$agentsData = [
    ['name' => 'Ramesh Shrestha', 'email' => 'agent1@sawari.com', 'phone' => '9841234567'],
    ['name' => 'Sita Tamang', 'email' => 'agent2@sawari.com', 'phone' => '9812345678'],
    ['name' => 'Bikash Maharjan', 'email' => 'agent3@sawari.com', 'phone' => '9803456789'],
    ['name' => 'Anita Gurung', 'email' => 'agent4@sawari.com', 'phone' => '9845678901'],
    ['name' => 'Sunil Karki', 'email' => 'agent5@sawari.com', 'phone' => '9867890123'],
];

$agentIds = [];
$insertAgent = $pdo->prepare('
    INSERT INTO agents (name, email, password_hash, phone_number, contributions_summary, joined_at)
    VALUES (:name, :email, :hash, :phone, :summary, :joined)
');
foreach ($agentsData as $i => $a) {
    $joinedDate = date('Y-m-d H:i:s', strtotime("-" . ($i * 45 + 30) . " days"));
    $insertAgent->execute([
        'name' => $a['name'],
        'email' => $a['email'],
        'hash' => hashPassword('Agent@123'),
        'phone' => $a['phone'],
        'summary' => json_encode(['vehicle' => 0, 'location' => 0, 'route' => 0]),
        'joined' => $joinedDate,
    ]);
    $agentIds[] = (int) $pdo->lastInsertId();
    echo "   ✓ {$a['name']}: {$a['email']} / Agent@123{$nl}";
}

// ─── 3. Locations (49 real Kathmandu Valley stops + landmarks) ──
echo "{$nl}3. Seeding locations...{$nl}";

$locations = [
    // Major bus stops
    ['name' => 'Ratnapark', 'lat' => 27.7050, 'lng' => 85.3140, 'type' => 'stop', 'desc' => 'Central hub near Tudikhel, major bus interchange point.'],
    ['name' => 'Kalanki', 'lat' => 27.6933, 'lng' => 85.2814, 'type' => 'stop', 'desc' => 'Western gateway of Kathmandu, connection to Prithvi Highway.'],
    ['name' => 'Koteshwor', 'lat' => 27.6778, 'lng' => 85.3492, 'type' => 'stop', 'desc' => 'Eastern junction connecting Bhaktapur and Ring Road.'],
    ['name' => 'Lagankhel', 'lat' => 27.6710, 'lng' => 85.3206, 'type' => 'stop', 'desc' => 'Main bus park of Lalitpur / Patan area.'],
    ['name' => 'Chabahil', 'lat' => 27.7178, 'lng' => 85.3428, 'type' => 'stop', 'desc' => 'Busy stop near Chabahil Ganesthan and Bouddha road.'],
    ['name' => 'Balaju', 'lat' => 27.7295, 'lng' => 85.3030, 'type' => 'stop', 'desc' => 'Northern Kathmandu near Balaju Industrial District.'],
    ['name' => 'New Bus Park', 'lat' => 27.7110, 'lng' => 85.3175, 'type' => 'stop', 'desc' => 'Gongabu New Bus Park — long-distance buses depart here.'],
    ['name' => 'Satdobato', 'lat' => 27.6574, 'lng' => 85.3244, 'type' => 'stop', 'desc' => 'Southern Lalitpur junction near ICIMOD and Godawari road.'],
    ['name' => 'Jawalakhel', 'lat' => 27.6714, 'lng' => 85.3131, 'type' => 'stop', 'desc' => 'Near Jawalakhel Zoo, popular shopping area in Patan.'],
    ['name' => 'Maharajgunj', 'lat' => 27.7350, 'lng' => 85.3310, 'type' => 'stop', 'desc' => 'Near Teaching Hospital and UN agencies.'],
    ['name' => 'Thapathali', 'lat' => 27.6940, 'lng' => 85.3210, 'type' => 'stop', 'desc' => 'Near Bagmati Bridge, connects Kathmandu and Patan.'],
    ['name' => 'Sundhara', 'lat' => 27.7020, 'lng' => 85.3130, 'type' => 'stop', 'desc' => 'Historic area near Dharahara tower and GPO.'],
    ['name' => 'Tripureshwor', 'lat' => 27.6965, 'lng' => 85.3135, 'type' => 'stop', 'desc' => 'On the main east-west road near National Museum.'],
    ['name' => 'Baneshwor', 'lat' => 27.6890, 'lng' => 85.3380, 'type' => 'stop', 'desc' => 'Major commercial area with government offices.'],
    ['name' => 'Gausala', 'lat' => 27.7090, 'lng' => 85.3485, 'type' => 'stop', 'desc' => 'Near Pashupatinath, connecting eastern routes.'],
    ['name' => 'Teku', 'lat' => 27.6945, 'lng' => 85.3050, 'type' => 'stop', 'desc' => 'Near Teku Hospital and Bagmati river.'],
    ['name' => 'Samakhusi', 'lat' => 27.7240, 'lng' => 85.3145, 'type' => 'stop', 'desc' => 'Busy area on Samakhusi road, north Kathmandu.'],
    ['name' => 'Basundhara', 'lat' => 27.7380, 'lng' => 85.3215, 'type' => 'stop', 'desc' => 'Near Basundhara Park and residential areas.'],
    ['name' => 'Ekantakuna', 'lat' => 27.6668, 'lng' => 85.3122, 'type' => 'stop', 'desc' => 'Lalitpur junction connecting Ring Road south.'],
    ['name' => 'Balkhu', 'lat' => 27.6880, 'lng' => 85.2970, 'type' => 'stop', 'desc' => 'Near Balkhu bridge connecting Kalanki and Patan.'],

    // Additional bus stops — central & inner city
    ['name' => 'Lainchaur', 'lat' => 27.7150, 'lng' => 85.3160, 'type' => 'stop', 'desc' => 'Residential area connecting Thamel and Lazimpat.'],
    ['name' => 'Lazimpat', 'lat' => 27.7195, 'lng' => 85.3200, 'type' => 'stop', 'desc' => 'Embassy quarter north of Thamel, government offices.'],
    ['name' => 'Panipokhari', 'lat' => 27.7260, 'lng' => 85.3250, 'type' => 'stop', 'desc' => 'Junction near historic water tank, connects to Maharajgunj.'],
    ['name' => 'Teaching Hospital', 'lat' => 27.7360, 'lng' => 85.3300, 'type' => 'stop', 'desc' => 'Tribhuvan University Teaching Hospital (TUTH) at Maharajgunj.'],
    ['name' => 'Jamal', 'lat' => 27.7095, 'lng' => 85.3155, 'type' => 'stop', 'desc' => 'Just north of Ratnapark near Nepal Academy Hall.'],
    ['name' => 'RNAC', 'lat' => 27.7005, 'lng' => 85.3175, 'type' => 'stop', 'desc' => 'Near old Royal Nepal Airlines building, New Road area.'],
    ['name' => 'Maitighar', 'lat' => 27.6955, 'lng' => 85.3235, 'type' => 'stop', 'desc' => 'Near Singha Durbar and Maitighar Mandala, government hub.'],
    ['name' => 'Dhapasi', 'lat' => 27.7430, 'lng' => 85.3180, 'type' => 'stop', 'desc' => 'Northern residential area beyond Basundhara.'],
    ['name' => 'Greenland', 'lat' => 27.7400, 'lng' => 85.3140, 'type' => 'stop', 'desc' => 'Dhapasi Greenland area, popular residential colony.'],
    ['name' => 'Putalisadak', 'lat' => 27.7070, 'lng' => 85.3225, 'type' => 'stop', 'desc' => 'Busy road connecting Ratnapark and Dillibazar with bookshops.'],
    ['name' => 'Bagbazar', 'lat' => 27.7055, 'lng' => 85.3190, 'type' => 'stop', 'desc' => 'Commercial area east of Ratnapark, near Bagbazar campus.'],
    ['name' => 'New Road', 'lat' => 27.7025, 'lng' => 85.3105, 'type' => 'stop', 'desc' => 'Main shopping street, connects Ratnapark to Basantapur.'],
    ['name' => 'Kalimati', 'lat' => 27.6965, 'lng' => 85.2990, 'type' => 'stop', 'desc' => 'Largest vegetable market in Kathmandu, between Kalanki and Tripureshwor.'],
    ['name' => 'Kupondole', 'lat' => 27.6830, 'lng' => 85.3150, 'type' => 'stop', 'desc' => 'Lalitpur residential area between Thapathali and Jawalakhel.'],
    ['name' => 'Pulchowk', 'lat' => 27.6790, 'lng' => 85.3180, 'type' => 'stop', 'desc' => 'Home to IOE Pulchowk Campus (engineering), central Lalitpur.'],
    ['name' => 'Naxal', 'lat' => 27.7170, 'lng' => 85.3275, 'type' => 'stop', 'desc' => 'Between Lazimpat and Dillibazar, near Nag Pokhari.'],
    ['name' => 'Dillibazar', 'lat' => 27.7105, 'lng' => 85.3310, 'type' => 'stop', 'desc' => 'Busy road east of Putalisadak leading to Battisputali.'],
    ['name' => 'Battisputali', 'lat' => 27.7130, 'lng' => 85.3380, 'type' => 'stop', 'desc' => 'Junction connecting to Gausala and eastern Kathmandu.'],
    ['name' => 'Sinamangal', 'lat' => 27.6940, 'lng' => 85.3450, 'type' => 'stop', 'desc' => 'Near Tribhuvan International Airport, eastern Ring Road.'],
    ['name' => 'Tinkune', 'lat' => 27.6860, 'lng' => 85.3470, 'type' => 'stop', 'desc' => 'Airport junction on Ring Road connecting to Koteshwor.'],
    ['name' => 'Jorpati', 'lat' => 27.7270, 'lng' => 85.3650, 'type' => 'stop', 'desc' => 'Northeast of Bouddha, gateway to Sankhu and Sundarijal.'],
    ['name' => 'Patan Dhoka', 'lat' => 27.6775, 'lng' => 85.3230, 'type' => 'stop', 'desc' => 'Historic gate entrance to Patan, near Patan Durbar Square.'],
    ['name' => 'Sitapaila', 'lat' => 27.7110, 'lng' => 85.2730, 'type' => 'stop', 'desc' => 'Western suburb of Kathmandu beyond Swayambhunath.'],
    ['name' => 'Sukedhara', 'lat' => 27.7320, 'lng' => 85.3100, 'type' => 'stop', 'desc' => 'Between Balaju and Tokha road, northern residential area.'],

    // Landmarks
    ['name' => 'Bouddha Stupa', 'lat' => 27.7215, 'lng' => 85.3620, 'type' => 'landmark', 'desc' => 'UNESCO World Heritage Site — one of the largest spherical stupas in Nepal.'],
    ['name' => 'Swayambhunath', 'lat' => 27.7149, 'lng' => 85.2905, 'type' => 'landmark', 'desc' => 'The Monkey Temple — ancient hilltop complex west of Kathmandu.'],
    ['name' => 'Pashupatinath', 'lat' => 27.7107, 'lng' => 85.3488, 'type' => 'landmark', 'desc' => 'UNESCO World Heritage Hindu temple on the banks of Bagmati River.'],
    ['name' => 'Durbar Square', 'lat' => 27.7045, 'lng' => 85.3067, 'type' => 'landmark', 'desc' => 'Historic royal palace square in the heart of old Kathmandu.'],
    ['name' => 'Thamel', 'lat' => 27.7153, 'lng' => 85.3110, 'type' => 'landmark', 'desc' => 'Tourist district with hotels, restaurants, and trekking agencies.'],
];

$insertLoc = $pdo->prepare('
    INSERT INTO locations (name, description, latitude, longitude, type, status, approved_by, updated_at, departure_count, destination_count)
    VALUES (:name, :desc, :lat, :lng, :type, "approved", :admin, NOW(), :dep, :dest)
');

$locationIds = [];
foreach ($locations as $loc) {
    $dep = rand(5, 150);
    $dest = rand(5, 150);
    $insertLoc->execute([
        'name' => $loc['name'],
        'desc' => $loc['desc'],
        'lat' => $loc['lat'],
        'lng' => $loc['lng'],
        'type' => $loc['type'],
        'admin' => $adminId,
        'dep' => $dep,
        'dest' => $dest,
    ]);
    $locationIds[$loc['name']] = (int) $pdo->lastInsertId();
    echo "   ✓ {$loc['name']} (ID: {$locationIds[$loc['name']]}){$nl}";
}

// ─── Add some pending locations (agent proposals) ─────
echo "{$nl}   Adding pending locations...{$nl}";

$pendingLocs = [
    ['name' => 'Kirtipur', 'lat' => 27.6793, 'lng' => 85.2785, 'type' => 'stop', 'desc' => 'University area south-west of Kathmandu.', 'agent_idx' => 2],
    ['name' => 'Bhaktapur', 'lat' => 27.6710, 'lng' => 85.4298, 'type' => 'landmark', 'desc' => 'Ancient Newar city, UNESCO heritage site.', 'agent_idx' => 3],
];

$insertPendingLoc = $pdo->prepare('
    INSERT INTO locations (name, description, latitude, longitude, type, status, updated_by, updated_at)
    VALUES (:name, :desc, :lat, :lng, :type, "pending", :agent, NOW())
');
$insertContrib = $pdo->prepare('
    INSERT INTO contributions (type, associated_entry_id, proposed_by, status, proposed_at)
    VALUES (:type, :entry_id, :agent, :status, :proposed_at)
');

foreach ($pendingLocs as $pl) {
    $insertPendingLoc->execute([
        'name' => $pl['name'],
        'desc' => $pl['desc'],
        'lat' => $pl['lat'],
        'lng' => $pl['lng'],
        'type' => $pl['type'],
        'agent' => $agentIds[$pl['agent_idx']],
    ]);
    $plId = (int) $pdo->lastInsertId();
    $insertContrib->execute([
        'type' => 'location',
        'entry_id' => $plId,
        'agent' => $agentIds[$pl['agent_idx']],
        'status' => 'pending',
        'proposed_at' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 5) . ' days')),
    ]);
    $contribId = (int) $pdo->lastInsertId();
    $pdo->prepare('UPDATE locations SET contribution_id = :cid WHERE location_id = :lid')
        ->execute(['cid' => $contribId, 'lid' => $plId]);
    echo "   ✓ {$pl['name']} (pending, by {$agentsData[$pl['agent_idx']]['name']}){$nl}";
}

// ─── 4. Routes (10 realistic routes) ─────────────────
echo "{$nl}4. Seeding routes...{$nl}";

$routes = [
    [
        'name' => 'Kalanki - Ratnapark - Chabahil',
        'desc' => 'Major east-west corridor through city center via Kalimati and Sundhara.',
        'stops' => ['Kalanki', 'Balkhu', 'Teku', 'Tripureshwor', 'Sundhara', 'Ratnapark', 'Chabahil'],
    ],
    [
        'name' => 'Lagankhel - Ratnapark - Balaju',
        'desc' => 'North-south route from Patan through central Kathmandu to Balaju.',
        'stops' => ['Lagankhel', 'Thapathali', 'Sundhara', 'Ratnapark', 'Samakhusi', 'Balaju'],
    ],
    [
        'name' => 'Satdobato - Lagankhel - Jawalakhel',
        'desc' => 'Southern Lalitpur ring connecting Satdobato to Jawalakhel via Ekantakuna.',
        'stops' => ['Satdobato', 'Ekantakuna', 'Jawalakhel', 'Lagankhel'],
    ],
    [
        'name' => 'Koteshwor - Baneshwor - Chabahil - Bouddha Stupa',
        'desc' => 'Eastern route connecting Koteshwor to Bouddha via Gausala.',
        'stops' => ['Koteshwor', 'Baneshwor', 'Gausala', 'Chabahil', 'Bouddha Stupa'],
    ],
    [
        'name' => 'Balaju - Basundhara - Maharajgunj - Chabahil',
        'desc' => 'Northern ring connecting Balaju through Basundhara to Chabahil.',
        'stops' => ['Balaju', 'Basundhara', 'Maharajgunj', 'Chabahil'],
    ],
    [
        'name' => 'Ratnapark - Baneshwor - Koteshwor',
        'desc' => 'City center to eastern Kathmandu via Baneshwor.',
        'stops' => ['Ratnapark', 'Baneshwor', 'Koteshwor'],
    ],
    [
        'name' => 'Kalanki - Balkhu - Ekantakuna - Satdobato',
        'desc' => 'Ring Road south route from Kalanki to Satdobato.',
        'stops' => ['Kalanki', 'Balkhu', 'Ekantakuna', 'Satdobato'],
    ],
    [
        'name' => 'New Bus Park - Samakhusi - Balaju',
        'desc' => 'Short local route from Gongabu Bus Park to Balaju.',
        'stops' => ['New Bus Park', 'Samakhusi', 'Balaju'],
    ],
    [
        'name' => 'Ratnapark - New Bus Park - Basundhara',
        'desc' => 'City center northbound to Basundhara via Gongabu.',
        'stops' => ['Ratnapark', 'New Bus Park', 'Samakhusi', 'Basundhara'],
    ],
    [
        'name' => 'Jawalakhel - Thapathali - Ratnapark - Thamel',
        'desc' => 'Patan to Thamel tourist corridor via Thapathali and Ratnapark.',
        'stops' => ['Jawalakhel', 'Lagankhel', 'Thapathali', 'Tripureshwor', 'Sundhara', 'Ratnapark', 'Thamel'],
    ],
    // ─── New Routes using additional stops ─────
    [
        'name' => 'Lainchaur - Lazimpat - Teaching Hospital',
        'desc' => 'Northern corridor via embassy quarter to Teaching Hospital.',
        'stops' => ['Lainchaur', 'Lazimpat', 'Panipokhari', 'Teaching Hospital', 'Maharajgunj'],
    ],
    [
        'name' => 'Ratnapark - Jamal - Lainchaur - Lazimpat',
        'desc' => 'City center northbound through Jamal to Lazimpat.',
        'stops' => ['Ratnapark', 'Jamal', 'Lainchaur', 'Lazimpat'],
    ],
    [
        'name' => 'Sundhara - RNAC - Maitighar - Baneshwor',
        'desc' => 'East-bound corridor from Sundhara via RNAC and Maitighar.',
        'stops' => ['Sundhara', 'RNAC', 'Maitighar', 'Baneshwor'],
    ],
    [
        'name' => 'Dhapasi - Greenland - Basundhara - New Bus Park',
        'desc' => 'Northern access route from Dhapasi to Gongabu Bus Park.',
        'stops' => ['Dhapasi', 'Greenland', 'Basundhara', 'New Bus Park'],
    ],
    [
        'name' => 'Ratnapark - Putalisadak - Dillibazar - Battisputali',
        'desc' => 'Eastern midtown route via Putalisadak and Dillibazar.',
        'stops' => ['Ratnapark', 'Bagbazar', 'Putalisadak', 'Dillibazar', 'Battisputali', 'Gausala'],
    ],
    [
        'name' => 'Kalanki - Kalimati - New Road - Ratnapark',
        'desc' => 'Western approach through Kalimati market and New Road.',
        'stops' => ['Kalanki', 'Kalimati', 'Teku', 'Tripureshwor', 'New Road', 'Ratnapark'],
    ],
    [
        'name' => 'Lagankhel - Patan Dhoka - Pulchowk - Kupondole',
        'desc' => 'Patan internal route through historic Patan Dhoka.',
        'stops' => ['Lagankhel', 'Patan Dhoka', 'Pulchowk', 'Kupondole', 'Thapathali'],
    ],
    [
        'name' => 'Koteshwor - Tinkune - Sinamangal - Gausala',
        'desc' => 'Airport corridor along eastern Ring Road.',
        'stops' => ['Koteshwor', 'Tinkune', 'Sinamangal', 'Gausala'],
    ],
    [
        'name' => 'Chabahil - Bouddha Stupa - Jorpati',
        'desc' => 'Northeast extension from Chabahil to Jorpati via Bouddha.',
        'stops' => ['Chabahil', 'Bouddha Stupa', 'Jorpati'],
    ],
    [
        'name' => 'Sitapaila - Kalanki - Kalimati - Sundhara',
        'desc' => 'Western suburb inbound route via Kalanki and Kalimati.',
        'stops' => ['Sitapaila', 'Kalanki', 'Kalimati', 'Tripureshwor', 'Sundhara'],
    ],
];

$insertRoute = $pdo->prepare('
    INSERT INTO routes (name, description, status, approved_by, updated_at, location_list)
    VALUES (:name, :desc, "approved", :admin, NOW(), :loc_list)
');

$routeIds = [];
foreach ($routes as $route) {
    $locList = [];
    foreach ($route['stops'] as $i => $stopName) {
        if (isset($locationIds[$stopName])) {
            $locList[] = ['index' => $i + 1, 'location_id' => $locationIds[$stopName]];
        }
    }

    $insertRoute->execute([
        'name' => $route['name'],
        'desc' => $route['desc'],
        'admin' => $adminId,
        'loc_list' => json_encode($locList),
    ]);
    $routeIds[$route['name']] = (int) $pdo->lastInsertId();
    echo "   ✓ {$route['name']} (" . count($locList) . " stops){$nl}";
}

// ─── Add a pending route ──────────────────────────────
echo "{$nl}   Adding pending route...{$nl}";

$pendingRouteStops = [
    ['index' => 1, 'location_id' => $locationIds['Gausala']],
    ['index' => 2, 'location_id' => $locationIds['Pashupatinath']],
];
$pdo->prepare('
    INSERT INTO routes (name, description, status, updated_by, updated_at, location_list)
    VALUES (:name, :desc, "pending", :agent, NOW(), :loc_list)
')->execute([
            'name' => 'Gausala - Pashupatinath',
            'desc' => 'Short route to Pashupatinath temple.',
            'agent' => $agentIds[0],
            'loc_list' => json_encode($pendingRouteStops),
        ]);
$pendingRouteId = (int) $pdo->lastInsertId();
$insertContrib->execute([
    'type' => 'route',
    'entry_id' => $pendingRouteId,
    'agent' => $agentIds[0],
    'status' => 'pending',
    'proposed_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
]);
$cId = (int) $pdo->lastInsertId();
$pdo->prepare('UPDATE routes SET contribution_id = :cid WHERE route_id = :rid')
    ->execute(['cid' => $cId, 'rid' => $pendingRouteId]);
echo "   ✓ Gausala - Pashupatinath (pending, by Ramesh Shrestha){$nl}";


// ─── 5. Vehicles (8 vehicles covering all routes) ─────
echo "{$nl}5. Seeding vehicles...{$nl}";

$vehicles = [
    [
        'name' => 'Sajha Yatayat (Route 1)',
        'desc' => 'Large air-conditioned bus, most comfortable public transport in Kathmandu.',
        'start' => '06:00',
        'stop' => '21:00',
        'routes' => [['name' => 'Kalanki - Ratnapark - Chabahil', 'count' => 8]],
    ],
    [
        'name' => 'Micro Bus 21',
        'desc' => 'White micro-bus running the Lagankhel-Balaju route frequently.',
        'start' => '05:30',
        'stop' => '21:30',
        'routes' => [['name' => 'Lagankhel - Ratnapark - Balaju', 'count' => 12]],
    ],
    [
        'name' => 'Tempo (Satdobato Line)',
        'desc' => 'Three-wheeled tempo covering Satdobato-Lagankhel corridor.',
        'start' => '06:00',
        'stop' => '19:00',
        'routes' => [['name' => 'Satdobato - Lagankhel - Jawalakhel', 'count' => 10]],
    ],
    [
        'name' => 'Mahanagar Yatayat (Blue Bus)',
        'desc' => 'Blue city bus running eastern routes through Baneshwor.',
        'start' => '05:45',
        'stop' => '20:30',
        'routes' => [
            ['name' => 'Koteshwor - Baneshwor - Chabahil - Bouddha Stupa', 'count' => 6],
            ['name' => 'Ratnapark - Baneshwor - Koteshwor', 'count' => 8],
        ],
    ],
    [
        'name' => 'Micro Bus (Balaju-Chabahil)',
        'desc' => 'Micro-bus connecting northern ring road stops.',
        'start' => '06:00',
        'stop' => '20:00',
        'routes' => [['name' => 'Balaju - Basundhara - Maharajgunj - Chabahil', 'count' => 7]],
    ],
    [
        'name' => 'Mini Bus (Ring Road South)',
        'desc' => 'Mini-bus covering the southern Ring Road section.',
        'start' => '05:30',
        'stop' => '20:00',
        'routes' => [['name' => 'Kalanki - Balkhu - Ekantakuna - Satdobato', 'count' => 9]],
    ],
    [
        'name' => 'Local Bus (Gongabu-Balaju)',
        'desc' => 'Local bus connecting New Bus Park area to Balaju.',
        'start' => '06:30',
        'stop' => '19:30',
        'routes' => [
            ['name' => 'New Bus Park - Samakhusi - Balaju', 'count' => 5],
            ['name' => 'Ratnapark - New Bus Park - Basundhara', 'count' => 4],
        ],
    ],
    [
        'name' => 'Sajha Yatayat (Patan-Thamel)',
        'desc' => 'Comfortable bus on the popular Jawalakhel to Thamel tourist route.',
        'start' => '06:30',
        'stop' => '21:00',
        'routes' => [['name' => 'Jawalakhel - Thapathali - Ratnapark - Thamel', 'count' => 6]],
    ],
    // ─── New vehicles for added routes ─────
    [
        'name' => 'Micro Bus (Lazimpat Line)',
        'desc' => 'Micro-bus running Lainchaur-Lazimpat-Teaching Hospital corridor.',
        'start' => '06:00',
        'stop' => '20:30',
        'routes' => [
            ['name' => 'Lainchaur - Lazimpat - Teaching Hospital', 'count' => 8],
            ['name' => 'Ratnapark - Jamal - Lainchaur - Lazimpat', 'count' => 6],
        ],
    ],
    [
        'name' => 'Tempo (RNAC-Maitighar)',
        'desc' => 'Three-wheeled tempo on the Sundhara-RNAC-Maitighar-Baneshwor route.',
        'start' => '06:00',
        'stop' => '19:00',
        'routes' => [['name' => 'Sundhara - RNAC - Maitighar - Baneshwor', 'count' => 10]],
    ],
    [
        'name' => 'Micro Bus (Dhapasi-Gongabu)',
        'desc' => 'Local micro-bus connecting Dhapasi colony to New Bus Park.',
        'start' => '06:30',
        'stop' => '19:30',
        'routes' => [['name' => 'Dhapasi - Greenland - Basundhara - New Bus Park', 'count' => 6]],
    ],
    [
        'name' => 'Mahanagar Yatayat (Putalisadak Line)',
        'desc' => 'Blue city bus on the eastern midtown route via Dillibazar.',
        'start' => '05:45',
        'stop' => '20:30',
        'routes' => [
            ['name' => 'Ratnapark - Putalisadak - Dillibazar - Battisputali', 'count' => 7],
            ['name' => 'Kalanki - Kalimati - New Road - Ratnapark', 'count' => 8],
        ],
    ],
    [
        'name' => 'Sajha Yatayat (Patan Internal)',
        'desc' => 'Comfortable bus covering Patan internal route through Pulchowk.',
        'start' => '06:30',
        'stop' => '20:00',
        'routes' => [['name' => 'Lagankhel - Patan Dhoka - Pulchowk - Kupondole', 'count' => 6]],
    ],
    [
        'name' => 'Micro Bus (Airport Line)',
        'desc' => 'Micro-bus along eastern Ring Road near Tribhuvan Airport.',
        'start' => '05:30',
        'stop' => '21:00',
        'routes' => [
            ['name' => 'Koteshwor - Tinkune - Sinamangal - Gausala', 'count' => 9],
            ['name' => 'Chabahil - Bouddha Stupa - Jorpati', 'count' => 5],
        ],
    ],
    [
        'name' => 'Mini Bus (Sitapaila Express)',
        'desc' => 'Mini-bus from Sitapaila western suburb to Sundhara via Kalanki.',
        'start' => '06:00',
        'stop' => '19:00',
        'routes' => [['name' => 'Sitapaila - Kalanki - Kalimati - Sundhara', 'count' => 5]],
    ],
];

$insertVehicle = $pdo->prepare('
    INSERT INTO vehicles (name, description, status, approved_by, updated_at, starts_at, stops_at, used_routes)
    VALUES (:name, :desc, "approved", :admin, NOW(), :starts, :stops, :routes)
');

$vehicleIds = [];
foreach ($vehicles as $v) {
    $usedRoutes = [];
    foreach ($v['routes'] as $r) {
        if (isset($routeIds[$r['name']])) {
            $usedRoutes[] = ['route_id' => $routeIds[$r['name']], 'count' => $r['count']];
        }
    }

    $insertVehicle->execute([
        'name' => $v['name'],
        'desc' => $v['desc'],
        'admin' => $adminId,
        'starts' => $v['start'],
        'stops' => $v['stop'],
        'routes' => json_encode($usedRoutes),
    ]);
    $vehicleIds[$v['name']] = (int) $pdo->lastInsertId();
    echo "   ✓ {$v['name']}{$nl}";
}

// ─── Add a pending vehicle ────────────────────────────
echo "{$nl}   Adding pending vehicle...{$nl}";

$pdo->prepare('
    INSERT INTO vehicles (name, description, status, updated_by, updated_at, starts_at, stops_at, used_routes)
    VALUES (:name, :desc, "pending", :agent, NOW(), :starts, :stops, :routes)
')->execute([
            'name' => 'Electric Safa Tempo',
            'desc' => 'Green electric three-wheeler for Lagankhel area.',
            'agent' => $agentIds[1],
            'starts' => '07:00',
            'stops' => '18:00',
            'routes' => json_encode([['route_id' => $routeIds['Satdobato - Lagankhel - Jawalakhel'], 'count' => 4]]),
        ]);
$pendingVehicleId = (int) $pdo->lastInsertId();
$insertContrib->execute([
    'type' => 'vehicle',
    'entry_id' => $pendingVehicleId,
    'agent' => $agentIds[1],
    'status' => 'pending',
    'proposed_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
]);
$cId2 = (int) $pdo->lastInsertId();
$pdo->prepare('UPDATE vehicles SET contribution_id = :cid WHERE vehicle_id = :vid')
    ->execute(['cid' => $cId2, 'vid' => $pendingVehicleId]);
echo "   ✓ Electric Safa Tempo (pending, by Sita Tamang){$nl}";


// ─── 6. Accepted Contributions (for leaderboard) ──────
echo "{$nl}6. Seeding accepted contributions for leaderboard...{$nl}";

$contributions = [
    // Ramesh — 8 accepted
    ['type' => 'location', 'agent' => 0, 'days_ago' => 120],
    ['type' => 'location', 'agent' => 0, 'days_ago' => 110],
    ['type' => 'location', 'agent' => 0, 'days_ago' => 95],
    ['type' => 'location', 'agent' => 0, 'days_ago' => 80],
    ['type' => 'route', 'agent' => 0, 'days_ago' => 75],
    ['type' => 'route', 'agent' => 0, 'days_ago' => 60],
    ['type' => 'route', 'agent' => 0, 'days_ago' => 50],
    ['type' => 'vehicle', 'agent' => 0, 'days_ago' => 40],
    // Sita — 6 accepted
    ['type' => 'location', 'agent' => 1, 'days_ago' => 100],
    ['type' => 'location', 'agent' => 1, 'days_ago' => 85],
    ['type' => 'location', 'agent' => 1, 'days_ago' => 70],
    ['type' => 'route', 'agent' => 1, 'days_ago' => 55],
    ['type' => 'route', 'agent' => 1, 'days_ago' => 45],
    ['type' => 'vehicle', 'agent' => 1, 'days_ago' => 35],
    // Bikash — 5 accepted
    ['type' => 'location', 'agent' => 2, 'days_ago' => 90],
    ['type' => 'location', 'agent' => 2, 'days_ago' => 75],
    ['type' => 'route', 'agent' => 2, 'days_ago' => 60],
    ['type' => 'route', 'agent' => 2, 'days_ago' => 45],
    ['type' => 'vehicle', 'agent' => 2, 'days_ago' => 30],
    // Anita — 3 accepted
    ['type' => 'location', 'agent' => 3, 'days_ago' => 80],
    ['type' => 'location', 'agent' => 3, 'days_ago' => 65],
    ['type' => 'route', 'agent' => 3, 'days_ago' => 50],
    // Sunil — 2 accepted
    ['type' => 'location', 'agent' => 4, 'days_ago' => 70],
    ['type' => 'route', 'agent' => 4, 'days_ago' => 40],
    // Rejected one for variety
    ['type' => 'location', 'agent' => 4, 'days_ago' => 35, 'status' => 'rejected', 'reason' => 'Duplicate of existing Basundhara stop.'],
];

$approvedLocIds = array_values($locationIds);
$approvedRouteIds = array_values($routeIds);

$insertContribFull = $pdo->prepare('
    INSERT INTO contributions (type, associated_entry_id, proposed_by, accepted_by, status, proposed_at, responded_at, rejection_reason)
    VALUES (:type, :entry, :agent, :admin, :status, :proposed, :responded, :reason)
');

$contribCounts = [
    0 => ['location' => 0, 'route' => 0, 'vehicle' => 0],
    1 => ['location' => 0, 'route' => 0, 'vehicle' => 0],
    2 => ['location' => 0, 'route' => 0, 'vehicle' => 0],
    3 => ['location' => 0, 'route' => 0, 'vehicle' => 0],
    4 => ['location' => 0, 'route' => 0, 'vehicle' => 0],
];

foreach ($contributions as $c) {
    $status = $c['status'] ?? 'accepted';
    $proposedAt = date('Y-m-d H:i:s', strtotime("-{$c['days_ago']} days"));
    $respondedAt = date('Y-m-d H:i:s', strtotime("-" . ($c['days_ago'] - rand(1, 3)) . " days"));

    $entryId = 1;
    if ($c['type'] === 'location' && !empty($approvedLocIds)) {
        $entryId = $approvedLocIds[array_rand($approvedLocIds)];
    } elseif ($c['type'] === 'route' && !empty($approvedRouteIds)) {
        $entryId = $approvedRouteIds[array_rand($approvedRouteIds)];
    } elseif ($c['type'] === 'vehicle' && !empty($vehicleIds)) {
        $vKeys = array_keys($vehicleIds);
        $entryId = $vehicleIds[$vKeys[array_rand($vKeys)]];
    }

    $insertContribFull->execute([
        'type' => $c['type'],
        'entry' => $entryId,
        'agent' => $agentIds[$c['agent']],
        'admin' => $status === 'accepted' ? $adminId : null,
        'status' => $status,
        'proposed' => $proposedAt,
        'responded' => $respondedAt,
        'reason' => $c['reason'] ?? null,
    ]);

    if ($status === 'accepted') {
        $contribCounts[$c['agent']][$c['type']]++;
    }
}

// Update agents' contributions_summary
foreach ($contribCounts as $idx => $counts) {
    $pdo->prepare('UPDATE agents SET contributions_summary = :summary WHERE agent_id = :id')
        ->execute([
            'summary' => json_encode($counts),
            'id' => $agentIds[$idx],
        ]);
}
echo "   ✓ " . count($contributions) . " contributions seeded{$nl}";


// ─── 7. Alerts ────────────────────────────────────────
echo "{$nl}7. Seeding alerts...{$nl}";

$alerts = [
    [
        'name' => 'Road Widening at Tripureshwor',
        'desc' => 'Ongoing road widening project at Tripureshwor junction causing delays and detours for buses on routes through Sundhara.',
        'routes' => ['Kalanki - Ratnapark - Chabahil', 'Jawalakhel - Thapathali - Ratnapark - Thamel'],
        'expires' => '+30 days',
    ],
    [
        'name' => 'Strike Alert — Balkhu Area',
        'desc' => 'Transportation strike expected in Balkhu area. Ring Road south services may be affected.',
        'routes' => ['Kalanki - Balkhu - Ekantakuna - Satdobato'],
        'expires' => '+2 days',
    ],
    [
        'name' => 'Bouddha Festival Traffic',
        'desc' => 'Heavy foot traffic around Bouddha Stupa due to festival. Expect delays on eastern routes.',
        'routes' => ['Koteshwor - Baneshwor - Chabahil - Bouddha Stupa'],
        'expires' => '+5 days',
    ],
];

$insertAlert = $pdo->prepare('
    INSERT INTO alerts (name, description, issued_by, routes_affected, reported_at, expires_at)
    VALUES (:name, :desc, :admin, :routes, NOW(), :expires)
');

foreach ($alerts as $a) {
    $routeAffected = [];
    foreach ($a['routes'] as $rName) {
        if (isset($routeIds[$rName])) {
            $routeAffected[] = $routeIds[$rName];
        }
    }
    $insertAlert->execute([
        'name' => $a['name'],
        'desc' => $a['desc'],
        'admin' => $adminId,
        'routes' => json_encode($routeAffected),
        'expires' => date('Y-m-d H:i:s', strtotime($a['expires'])),
    ]);
    echo "   ✓ {$a['name']}{$nl}";
}


// ─── 8. Suggestions / Feedback ────────────────────────
echo "{$nl}8. Seeding suggestions...{$nl}";

$suggestions = [
    [
        'type' => 'appreciation',
        'message' => 'Sawari helped me find the bus from Kalanki to Bouddha with one transfer. Saved me so much time!',
        'rating' => 5,
        'route' => 'Kalanki - Ratnapark - Chabahil',
        'status' => 'reviewed',
        'ip' => '192.168.1.101',
    ],
    [
        'type' => 'suggestion',
        'message' => 'Please add night bus routes. Many people travel after 9 PM and the app shows no service.',
        'rating' => 4,
        'route' => null,
        'status' => 'pending',
        'ip' => '192.168.1.102',
    ],
    [
        'type' => 'complaint',
        'message' => 'The fare shown for Micro Bus 21 was NPR 25 but the conductor charged NPR 35. Please update fares.',
        'rating' => 2,
        'route' => 'Lagankhel - Ratnapark - Balaju',
        'status' => 'pending',
        'ip' => '192.168.1.103',
    ],
    [
        'type' => 'correction',
        'message' => 'Sajha Yatayat on Route 1 now starts at 6:30 AM not 6:00 AM. Their winter schedule changed.',
        'rating' => 3,
        'route' => 'Kalanki - Ratnapark - Chabahil',
        'status' => 'pending',
        'ip' => '192.168.1.104',
    ],
    [
        'type' => 'appreciation',
        'message' => 'Tourist from Japan here. The walking directions from Swayambhunath to Balaju bus stop were perfect!',
        'rating' => 5,
        'route' => 'Lagankhel - Ratnapark - Balaju',
        'status' => 'reviewed',
        'ip' => '10.0.0.55',
    ],
    [
        'type' => 'suggestion',
        'message' => 'It would be great to show real-time bus locations. Maybe agents can update bus positions?',
        'rating' => 4,
        'route' => null,
        'status' => 'pending',
        'ip' => '192.168.1.105',
    ],
    [
        'type' => 'complaint',
        'message' => 'Route from Satdobato to Bouddha suggested 3 transfers. That seems too many. Is there a more direct way?',
        'rating' => 2,
        'route' => 'Satdobato - Lagankhel - Jawalakhel',
        'status' => 'resolved',
        'ip' => '192.168.1.106',
    ],
    [
        'type' => 'appreciation',
        'message' => 'Student discount feature is awesome! Auto-calculated my fare at 50% off. Dhanyabad Sawari!',
        'rating' => 5,
        'route' => 'Ratnapark - Baneshwor - Koteshwor',
        'status' => 'reviewed',
        'ip' => '192.168.1.107',
    ],
];

$insertSuggestion = $pdo->prepare('
    INSERT INTO suggestions (type, message, rating, related_route_id, ip_address, status, reviewed_by, submitted_at, reviewed_at)
    VALUES (:type, :msg, :rating, :route_id, :ip, :status, :reviewed_by, :submitted, :reviewed_at)
');

foreach ($suggestions as $i => $s) {
    $routeId = null;
    if ($s['route'] && isset($routeIds[$s['route']])) {
        $routeId = $routeIds[$s['route']];
    }
    $submittedAt = date('Y-m-d H:i:s', strtotime('-' . (count($suggestions) - $i) * 3 . ' days'));
    $reviewedAt = in_array($s['status'], ['reviewed', 'resolved']) ? date('Y-m-d H:i:s', strtotime($submittedAt . ' +1 day')) : null;
    $reviewedBy = in_array($s['status'], ['reviewed', 'resolved']) ? $adminId : null;

    $insertSuggestion->execute([
        'type' => $s['type'],
        'msg' => $s['message'],
        'rating' => $s['rating'],
        'route_id' => $routeId,
        'ip' => $s['ip'],
        'status' => $s['status'],
        'reviewed_by' => $reviewedBy,
        'submitted' => $submittedAt,
        'reviewed_at' => $reviewedAt,
    ]);
    echo "   ✓ {$s['type']}: " . substr($s['message'], 0, 50) . "...{$nl}";
}


// ─── 9. Sample Trips (search history) ─────────────────
echo "{$nl}9. Seeding sample trips...{$nl}";

$trips = [
    ['from' => 'Kalanki', 'to' => 'Chabahil', 'routes' => ['Kalanki - Ratnapark - Chabahil']],
    ['from' => 'Lagankhel', 'to' => 'Balaju', 'routes' => ['Lagankhel - Ratnapark - Balaju']],
    ['from' => 'Kalanki', 'to' => 'Bouddha Stupa', 'routes' => ['Kalanki - Ratnapark - Chabahil', 'Koteshwor - Baneshwor - Chabahil - Bouddha Stupa']],
    ['from' => 'Satdobato', 'to' => 'Balaju', 'routes' => ['Satdobato - Lagankhel - Jawalakhel', 'Lagankhel - Ratnapark - Balaju']],
    ['from' => 'Ratnapark', 'to' => 'Koteshwor', 'routes' => ['Ratnapark - Baneshwor - Koteshwor']],
    ['from' => 'Jawalakhel', 'to' => 'Thamel', 'routes' => ['Jawalakhel - Thapathali - Ratnapark - Thamel']],
    ['from' => 'Koteshwor', 'to' => 'Bouddha Stupa', 'routes' => ['Koteshwor - Baneshwor - Chabahil - Bouddha Stupa']],
    ['from' => 'Balaju', 'to' => 'Chabahil', 'routes' => ['Balaju - Basundhara - Maharajgunj - Chabahil']],
    ['from' => 'Kalanki', 'to' => 'Satdobato', 'routes' => ['Kalanki - Balkhu - Ekantakuna - Satdobato']],
    ['from' => 'Ratnapark', 'to' => 'Basundhara', 'routes' => ['Ratnapark - New Bus Park - Basundhara']],
    ['from' => 'Lagankhel', 'to' => 'Chabahil', 'routes' => ['Lagankhel - Ratnapark - Balaju', 'Kalanki - Ratnapark - Chabahil']],
    ['from' => 'Sundhara', 'to' => 'Maharajgunj', 'routes' => ['Lagankhel - Ratnapark - Balaju', 'Balaju - Basundhara - Maharajgunj - Chabahil']],
    ['from' => 'New Bus Park', 'to' => 'Balaju', 'routes' => ['New Bus Park - Samakhusi - Balaju']],
    ['from' => 'Thapathali', 'to' => 'Thamel', 'routes' => ['Jawalakhel - Thapathali - Ratnapark - Thamel']],
    ['from' => 'Ekantakuna', 'to' => 'Lagankhel', 'routes' => ['Satdobato - Lagankhel - Jawalakhel']],
    // ─── New trips using added locations ─────
    ['from' => 'Lainchaur', 'to' => 'Teaching Hospital', 'routes' => ['Lainchaur - Lazimpat - Teaching Hospital']],
    ['from' => 'Ratnapark', 'to' => 'Lazimpat', 'routes' => ['Ratnapark - Jamal - Lainchaur - Lazimpat']],
    ['from' => 'Sundhara', 'to' => 'Baneshwor', 'routes' => ['Sundhara - RNAC - Maitighar - Baneshwor']],
    ['from' => 'Dhapasi', 'to' => 'New Bus Park', 'routes' => ['Dhapasi - Greenland - Basundhara - New Bus Park']],
    ['from' => 'Ratnapark', 'to' => 'Gausala', 'routes' => ['Ratnapark - Putalisadak - Dillibazar - Battisputali']],
    ['from' => 'Kalanki', 'to' => 'Ratnapark', 'routes' => ['Kalanki - Kalimati - New Road - Ratnapark']],
    ['from' => 'Lagankhel', 'to' => 'Thapathali', 'routes' => ['Lagankhel - Patan Dhoka - Pulchowk - Kupondole']],
    ['from' => 'Koteshwor', 'to' => 'Gausala', 'routes' => ['Koteshwor - Tinkune - Sinamangal - Gausala']],
    ['from' => 'Chabahil', 'to' => 'Jorpati', 'routes' => ['Chabahil - Bouddha Stupa - Jorpati']],
    ['from' => 'Sitapaila', 'to' => 'Sundhara', 'routes' => ['Sitapaila - Kalanki - Kalimati - Sundhara']],
    ['from' => 'RNAC', 'to' => 'Maitighar', 'routes' => ['Sundhara - RNAC - Maitighar - Baneshwor']],
    ['from' => 'Greenland', 'to' => 'Basundhara', 'routes' => ['Dhapasi - Greenland - Basundhara - New Bus Park']],
    ['from' => 'Pulchowk', 'to' => 'Kupondole', 'routes' => ['Lagankhel - Patan Dhoka - Pulchowk - Kupondole']],
    ['from' => 'Dillibazar', 'to' => 'Battisputali', 'routes' => ['Ratnapark - Putalisadak - Dillibazar - Battisputali']],
    ['from' => 'Jamal', 'to' => 'Lainchaur', 'routes' => ['Ratnapark - Jamal - Lainchaur - Lazimpat']],
];

$insertTrip = $pdo->prepare('
    INSERT INTO trips (ip_address, start_location_id, destination_location_id, routes_used, queried_at)
    VALUES (:ip, :start, :dest, :routes, :queried)
');

$ips = ['192.168.1.10', '10.0.0.5', '192.168.1.22', '172.16.0.8', '192.168.2.15', '10.0.1.3'];

foreach ($trips as $i => $t) {
    $usedRoutes = [];
    foreach ($t['routes'] as $rName) {
        if (isset($routeIds[$rName])) {
            $usedRoutes[] = $routeIds[$rName];
        }
    }
    $insertTrip->execute([
        'ip' => $ips[$i % count($ips)],
        'start' => $locationIds[$t['from']],
        'dest' => $locationIds[$t['to']],
        'routes' => json_encode($usedRoutes),
        'queried' => date('Y-m-d H:i:s', strtotime('-' . rand(0, 30) . ' days -' . rand(0, 23) . ' hours')),
    ]);
}
echo "   ✓ " . count($trips) . " sample trips seeded{$nl}";


// ─── Summary ──────────────────────────────────────────
echo "{$nl}=== Seeding Complete ==={$nl}{$nl}";
echo "Accounts:{$nl}";
echo "  Admin:  admin@sawari.com  / Admin@123{$nl}";
echo "  Agent1: agent1@sawari.com / Agent@123 (Ramesh Shrestha){$nl}";
echo "  Agent2: agent2@sawari.com / Agent@123 (Sita Tamang){$nl}";
echo "  Agent3: agent3@sawari.com / Agent@123 (Bikash Maharjan){$nl}";
echo "  Agent4: agent4@sawari.com / Agent@123 (Anita Gurung){$nl}";
echo "  Agent5: agent5@sawari.com / Agent@123 (Sunil Karki){$nl}{$nl}";

echo "Data:{$nl}";
echo "  " . count($locations) . " approved locations (+ 2 pending){$nl}";
echo "  " . count($routes) . " approved routes (+ 1 pending){$nl}";
echo "  " . count($vehicles) . " approved vehicles (+ 1 pending){$nl}";
echo "  " . count($contributions) . " contributions (accepted + rejected + pending){$nl}";
echo "  " . count($alerts) . " active alerts{$nl}";
echo "  " . count($suggestions) . " suggestions/feedback{$nl}";
echo "  " . count($trips) . " sample trip searches{$nl}";
