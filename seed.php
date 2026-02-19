<?php
/**
 * Seed Script — seed.php
 * 
 * Seeds comprehensive REAL Kathmandu Valley public transit data.
 * Routes are based on actual Sajha Yatayat, Mahanagar Yatayat,
 * micro-bus, tempo and mini-bus services operating in the valley.
 *
 * Run from CLI:  php seed.php
 * Or via browser: http://localhost/CCRC/seed.php  (auto-detected from project directory)
 * 
 * Default Accounts:
 *   Admin:  admin@sawari.com  / Admin@123
 *   Agents: agent1@sawari.com / Agent@123  (through agent5)
 *
 * Sources:
 *   - Sajha Yatayat official routes (en.wikipedia.org/wiki/Sajha_Yatayat)
 *   - Common micro-bus / tempo routes in Kathmandu Valley
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$isCli = php_sapi_name() === 'cli';
$nl = $isCli ? "\n" : "<br>";

echo "=== Sawari — Real Kathmandu Valley Transit Seed ===$nl$nl";

$pdo = getDBConnection();

/* ────────────────────────────────────────────────────────
   0.  CLEAR ALL TABLES
   ──────────────────────────────────────────────────────── */
echo "0. Clearing existing data...{$nl}";
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (['trips', 'suggestions', 'alerts', 'vehicles', 'routes', 'locations', 'contributions', 'agents', 'admins'] as $t) {
    $pdo->exec("TRUNCATE TABLE {$t}");
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
echo "   ✓ All tables cleared{$nl}";

/* ────────────────────────────────────────────────────────
   1.  ADMIN ACCOUNT
   ──────────────────────────────────────────────────────── */
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
echo "   ✓ admin@sawari.com / Admin@123{$nl}";

/* ────────────────────────────────────────────────────────
   2.  AGENT ACCOUNTS (5 agents with Nepali names)
   ──────────────────────────────────────────────────────── */
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
    $insertAgent->execute([
        'name' => $a['name'],
        'email' => $a['email'],
        'hash' => hashPassword('Agent@123'),
        'phone' => $a['phone'],
        'summary' => json_encode(['vehicle' => 0, 'location' => 0, 'route' => 0]),
        'joined' => date('Y-m-d H:i:s', strtotime('-' . ($i * 45 + 30) . ' days')),
    ]);
    $agentIds[] = (int) $pdo->lastInsertId();
    echo "   ✓ {$a['name']}: {$a['email']} / Agent@123{$nl}";
}

/* ────────────────────────────────────────────────────────
   3.  LOCATIONS — 57 real Kathmandu Valley stops & landmarks
        Coordinates verified against OpenStreetMap / Google Maps.
   ──────────────────────────────────────────────────────── */
echo "{$nl}3. Seeding locations...{$nl}";

$locations = [
    // ─── Core Kathmandu City ──────────────────────────
    ['name' => 'Ratnapark', 'lat' => 27.7050, 'lng' => 85.3140, 'type' => 'stop', 'desc' => 'Central bus hub of Kathmandu near Rani Pokhari, busiest transit interchange in the valley.'],
    ['name' => 'Sundhara', 'lat' => 27.7013, 'lng' => 85.3138, 'type' => 'stop', 'desc' => 'Historic area south of Ratnapark near Dharahara tower and GPO.'],
    ['name' => 'New Road', 'lat' => 27.7031, 'lng' => 85.3117, 'type' => 'stop', 'desc' => 'Major commercial street near Basantapur Durbar Square.'],
    ['name' => 'Jamal', 'lat' => 27.7093, 'lng' => 85.3155, 'type' => 'stop', 'desc' => 'North of Ratnapark near Nepal Academy Hall and Kaiser Library.'],
    ['name' => 'Asan', 'lat' => 27.7075, 'lng' => 85.3115, 'type' => 'stop', 'desc' => 'Historic marketplace in old Kathmandu, local bazaar hub.'],
    ['name' => 'Bagbazar', 'lat' => 27.7055, 'lng' => 85.3190, 'type' => 'stop', 'desc' => 'Commercial area east of Ratnapark near Bagbazar campus.'],
    ['name' => 'Putalisadak', 'lat' => 27.7070, 'lng' => 85.3225, 'type' => 'stop', 'desc' => 'Central intersection with bookshops, connecting east-west routes.'],
    ['name' => 'Durbar Marg', 'lat' => 27.7005, 'lng' => 85.3200, 'type' => 'stop', 'desc' => 'Main avenue leading to Narayanhiti Palace Museum.'],
    ['name' => 'NAC', 'lat' => 27.7005, 'lng' => 85.3175, 'type' => 'stop', 'desc' => 'Near old Nepal Airlines Corporation (NAC) building, key Sajha Yatayat stop.'],
    ['name' => 'Thamel', 'lat' => 27.7153, 'lng' => 85.3110, 'type' => 'stop', 'desc' => 'Famous tourist district with hotels, restaurants and trekking agencies.'],

    // ─── Western Kathmandu ───────────────────────────
    ['name' => 'Kalanki', 'lat' => 27.6933, 'lng' => 85.2814, 'type' => 'stop', 'desc' => 'Western gateway to Kathmandu, Ring Road junction for Prithvi Highway.'],
    ['name' => 'Kalimati', 'lat' => 27.6965, 'lng' => 85.2990, 'type' => 'stop', 'desc' => 'Largest vegetable wholesale market in Kathmandu valley.'],
    ['name' => 'Teku', 'lat' => 27.6945, 'lng' => 85.3050, 'type' => 'stop', 'desc' => 'Near Teku Hospital and Bagmati-Bishnumati confluence.'],
    ['name' => 'Tripureshwor', 'lat' => 27.6965, 'lng' => 85.3135, 'type' => 'stop', 'desc' => 'South-central Kathmandu near Dasharath Stadium and RNAC building.'],
    ['name' => 'Balkhu', 'lat' => 27.6880, 'lng' => 85.2970, 'type' => 'stop', 'desc' => 'Ring Road junction near Balkhu bridge connecting to Patan.'],
    ['name' => 'Kirtipur', 'lat' => 27.6793, 'lng' => 85.2785, 'type' => 'stop', 'desc' => 'Historic Newari town, home of Tribhuvan University main campus.'],

    // ─── Southern / Patan (Lalitpur) ─────────────────
    ['name' => 'Kupondole', 'lat' => 27.6830, 'lng' => 85.3150, 'type' => 'stop', 'desc' => 'Northern Patan near the Kupondole bridge to Kathmandu.'],
    ['name' => 'Pulchowk', 'lat' => 27.6790, 'lng' => 85.3180, 'type' => 'stop', 'desc' => 'Central Patan, home of IOE Pulchowk Engineering Campus. Sajha Yatayat HQ.'],
    ['name' => 'Patan Dhoka', 'lat' => 27.6775, 'lng' => 85.3230, 'type' => 'stop', 'desc' => 'Historic Patan gate area near Patan Durbar Square.'],
    ['name' => 'Jawalakhel', 'lat' => 27.6714, 'lng' => 85.3131, 'type' => 'stop', 'desc' => 'Patan area near the Central Zoo, popular commercial center.'],
    ['name' => 'Lagankhel', 'lat' => 27.6710, 'lng' => 85.3206, 'type' => 'stop', 'desc' => 'Main bus park of Lalitpur/Patan, southern transit hub.'],
    ['name' => 'Satdobato', 'lat' => 27.6574, 'lng' => 85.3244, 'type' => 'stop', 'desc' => 'Southern Ring Road junction near ICIMOD and Godawari road.'],
    ['name' => 'Ekantakuna', 'lat' => 27.6668, 'lng' => 85.3122, 'type' => 'stop', 'desc' => 'Ring Road junction in southwest Lalitpur connecting Bhaisepati.'],
    ['name' => 'Bhaisepati', 'lat' => 27.6580, 'lng' => 85.3040, 'type' => 'stop', 'desc' => 'Residential area southwest of Ekantakuna, starting point of Sajha Line 7.'],
    ['name' => 'Thapathali', 'lat' => 27.6940, 'lng' => 85.3210, 'type' => 'stop', 'desc' => 'Near Bagmati bridge between Patan and Kathmandu, Thapathali Campus area.'],
    ['name' => 'Maitighar', 'lat' => 27.6955, 'lng' => 85.3235, 'type' => 'stop', 'desc' => 'Near Singha Durbar and Maitighar Mandala, government district.'],
    ['name' => 'Babar Mahal', 'lat' => 27.6910, 'lng' => 85.3290, 'type' => 'stop', 'desc' => 'Between Maitighar and Baneshwor, near Nepal Art Council.'],
    ['name' => 'Sankhamul', 'lat' => 27.6860, 'lng' => 85.3328, 'type' => 'stop', 'desc' => 'Between Patan and Baneshwor along the Bagmati river.'],

    // ─── Eastern Kathmandu ───────────────────────────
    ['name' => 'Dillibazar', 'lat' => 27.7105, 'lng' => 85.3310, 'type' => 'stop', 'desc' => 'Busy road east of Putalisadak leading to Battisputali.'],
    ['name' => 'Battisputali', 'lat' => 27.7130, 'lng' => 85.3380, 'type' => 'stop', 'desc' => 'Junction between Dillibazar and Gaushala in eastern Kathmandu.'],
    ['name' => 'Baneshwor', 'lat' => 27.6890, 'lng' => 85.3380, 'type' => 'stop', 'desc' => 'Major commercial district with government offices and banks.'],
    ['name' => 'Koteshwor', 'lat' => 27.6778, 'lng' => 85.3492, 'type' => 'stop', 'desc' => 'Eastern Ring Road junction connecting toward Bhaktapur.'],
    ['name' => 'Tinkune', 'lat' => 27.6860, 'lng' => 85.3470, 'type' => 'stop', 'desc' => 'Tri-junction near Tribhuvan International Airport on Ring Road.'],
    ['name' => 'Sinamangal', 'lat' => 27.6940, 'lng' => 85.3450, 'type' => 'stop', 'desc' => 'Along eastern Ring Road near the airport runway.'],
    ['name' => 'Kalopul', 'lat' => 27.6985, 'lng' => 85.3340, 'type' => 'stop', 'desc' => 'Bridge area connecting Bagbazar to Gaushala and eastern routes.'],
    ['name' => 'Gaushala', 'lat' => 27.7090, 'lng' => 85.3485, 'type' => 'stop', 'desc' => 'Ring Road junction near Pashupatinath temple area.'],
    ['name' => 'Airport', 'lat' => 27.6966, 'lng' => 85.3591, 'type' => 'stop', 'desc' => 'Tribhuvan International Airport (TIA), only international airport in Nepal.'],

    // ─── Northeast Kathmandu ─────────────────────────
    ['name' => 'Chabahil', 'lat' => 27.7178, 'lng' => 85.3428, 'type' => 'stop', 'desc' => 'Ring Road junction in northeast Kathmandu near Ganesh temple.'],
    ['name' => 'Bouddhanath', 'lat' => 27.7215, 'lng' => 85.3620, 'type' => 'landmark', 'desc' => 'UNESCO World Heritage Site, one of the largest Buddhist stupas in the world.'],
    ['name' => 'Jorpati', 'lat' => 27.7270, 'lng' => 85.3650, 'type' => 'stop', 'desc' => 'Northeast settlement beyond Bouddha, gateway to Sankhu.'],

    // ─── Northern Kathmandu ──────────────────────────
    ['name' => 'Lazimpat', 'lat' => 27.7195, 'lng' => 85.3200, 'type' => 'stop', 'desc' => 'Embassy quarter north of Thamel, government and diplomatic area.'],
    ['name' => 'Naxal', 'lat' => 27.7170, 'lng' => 85.3275, 'type' => 'stop', 'desc' => 'Between Lazimpat and Dillibazar near Nag Pokhari.'],
    ['name' => 'Panipokhari', 'lat' => 27.7260, 'lng' => 85.3250, 'type' => 'stop', 'desc' => 'Junction near historic water tank, on road to Maharajgunj.'],
    ['name' => 'Maharajgunj', 'lat' => 27.7350, 'lng' => 85.3310, 'type' => 'stop', 'desc' => 'Northern Kathmandu near UN agencies and US Embassy.'],
    ['name' => 'Teaching Hospital', 'lat' => 27.7360, 'lng' => 85.3300, 'type' => 'stop', 'desc' => 'Tribhuvan University Teaching Hospital (TUTH) at Maharajgunj.'],
    ['name' => 'Narayan Gopal Chowk', 'lat' => 27.7320, 'lng' => 85.3220, 'type' => 'stop', 'desc' => 'Named after legendary singer Narayan Gopal, Ring Road intersection.'],
    ['name' => 'Gongabu', 'lat' => 27.7340, 'lng' => 85.3135, 'type' => 'stop', 'desc' => 'New Bus Park area — main long-distance bus terminal in Kathmandu.'],
    ['name' => 'Balaju', 'lat' => 27.7295, 'lng' => 85.3030, 'type' => 'stop', 'desc' => 'Northwestern Kathmandu near Balaju Industrial District and Water Garden.'],
    ['name' => 'Machha Pokhari', 'lat' => 27.7175, 'lng' => 85.3025, 'type' => 'stop', 'desc' => 'Between Thamel and Balaju, residential area with fish pond.'],
    ['name' => 'Samakhusi', 'lat' => 27.7240, 'lng' => 85.3145, 'type' => 'stop', 'desc' => 'On the road from Balaju to Gongabu, busy commercial area.'],
    ['name' => 'Basundhara', 'lat' => 27.7380, 'lng' => 85.3215, 'type' => 'stop', 'desc' => 'Northern residential colony near Ring Road, Basundhara Park.'],

    // ─── Bhaktapur direction ─────────────────────────
    ['name' => 'Thimi', 'lat' => 27.6720, 'lng' => 85.3870, 'type' => 'stop', 'desc' => 'Historic Newari pottery town between Kathmandu and Bhaktapur.'],
    ['name' => 'Suryabinayak', 'lat' => 27.6650, 'lng' => 85.4445, 'type' => 'stop', 'desc' => 'Eastern Bhaktapur terminus, near Suryabinayak Ganesh temple.'],

    // ─── Landmarks ───────────────────────────────────
    ['name' => 'Swayambhunath', 'lat' => 27.7149, 'lng' => 85.2905, 'type' => 'landmark', 'desc' => 'The Monkey Temple — ancient Buddhist hilltop complex, UNESCO World Heritage Site.'],
    ['name' => 'Pashupatinath', 'lat' => 27.7107, 'lng' => 85.3488, 'type' => 'landmark', 'desc' => 'Sacred Hindu temple on the banks of Bagmati River, UNESCO World Heritage Site.'],
    ['name' => 'Durbar Square', 'lat' => 27.7045, 'lng' => 85.3067, 'type' => 'landmark', 'desc' => 'Historic royal palace square in the heart of old Kathmandu, UNESCO site.'],
    ['name' => 'Patan Durbar', 'lat' => 27.6730, 'lng' => 85.3250, 'type' => 'landmark', 'desc' => 'Stunning palace square in Lalitpur with Newari architecture, UNESCO site.'],
];

$insertLoc = $pdo->prepare('
    INSERT INTO locations (name, description, latitude, longitude, type, status, approved_by, updated_at, departure_count, destination_count)
    VALUES (:name, :desc, :lat, :lng, :type, "approved", :admin, NOW(), :dep, :dest)
');

$locationIds = [];
foreach ($locations as $loc) {
    $insertLoc->execute([
        'name' => $loc['name'],
        'desc' => $loc['desc'],
        'lat' => $loc['lat'],
        'lng' => $loc['lng'],
        'type' => $loc['type'],
        'admin' => $adminId,
        'dep' => rand(10, 200),
        'dest' => rand(10, 200),
    ]);
    $locationIds[$loc['name']] = (int) $pdo->lastInsertId();
}
echo "   ✓ " . count($locations) . " locations seeded{$nl}";

// ─── Two pending locations (agent proposals) ──────────
$pendingLocs = [
    ['name' => 'Budhanilkantha', 'lat' => 27.7790, 'lng' => 85.3615, 'type' => 'stop', 'desc' => 'Far north of the valley, famous sleeping Vishnu temple.', 'agent_idx' => 2],
    ['name' => 'Godawari', 'lat' => 27.5970, 'lng' => 85.3740, 'type' => 'stop', 'desc' => 'Southern end of valley near Royal Botanical Garden.', 'agent_idx' => 3],
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
    $cId = (int) $pdo->lastInsertId();
    $pdo->prepare('UPDATE locations SET contribution_id = :cid WHERE location_id = :lid')
        ->execute(['cid' => $cId, 'lid' => $plId]);
    echo "   ✓ {$pl['name']} (pending){$nl}";
}

/* ────────────────────────────────────────────────────────
   4.  ROUTES — Real Kathmandu Valley bus routes
       Based on official Sajha Yatayat data + common micro/tempo routes.
   ──────────────────────────────────────────────────────── */
echo "{$nl}4. Seeding routes...{$nl}";

$routes = [
    // ═══ SAJHA YATAYAT — Official Routes (Wikipedia verified) ═══
    [
        'name' => 'Sajha Line 1: Lagankhel - Gongabu',
        'desc' => 'Sajha Yatayat flagship route traversing the valley north-south from Patan to New Bus Park via Tripureshwor, Jamal, Lazimpat, and Teaching Hospital.',
        'stops' => ['Lagankhel', 'Jawalakhel', 'Pulchowk', 'Kupondole', 'Thapathali', 'Tripureshwor', 'NAC', 'Jamal', 'Lazimpat', 'Panipokhari', 'Teaching Hospital', 'Narayan Gopal Chowk', 'Samakhusi', 'Gongabu'],
    ],
    [
        'name' => 'Sajha Line 3: Ratnapark - Airport',
        'desc' => 'Sajha Yatayat route from Ratnapark to Tribhuvan International Airport via Thapathali, Maitighar, Baneshwor and Sinamangal.',
        'stops' => ['Ratnapark', 'Tripureshwor', 'Thapathali', 'Maitighar', 'Babar Mahal', 'Baneshwor', 'Tinkune', 'Sinamangal', 'Airport'],
    ],
    [
        'name' => 'Sajha Line 4: Ratnapark - Suryabinayak',
        'desc' => 'Sajha Yatayat long route from central Kathmandu to Bhaktapur district via Baneshwor, Koteshwor and Thimi.',
        'stops' => ['Ratnapark', 'Babar Mahal', 'Baneshwor', 'Tinkune', 'Koteshwor', 'Thimi', 'Suryabinayak'],
    ],
    [
        'name' => 'Sajha Line 5: Swayambhunath - Suryabinayak',
        'desc' => 'Sajha Yatayat cross-valley route from Monkey Temple in the west to Bhaktapur in the east via Kalanki, Kalimati, Ratnapark and Koteshwor.',
        'stops' => ['Swayambhunath', 'Kalanki', 'Kalimati', 'Teku', 'Tripureshwor', 'NAC', 'Ratnapark', 'Babar Mahal', 'Baneshwor', 'Tinkune', 'Koteshwor', 'Thimi', 'Suryabinayak'],
    ],
    [
        'name' => 'Sajha Line 7: Bhaisepati - Ratnapark',
        'desc' => 'Sajha Yatayat route from Bhaisepati in southwest Lalitpur to Ratnapark via Ekantakuna, Jawalakhel, Kupondole and Tripureshwor.',
        'stops' => ['Bhaisepati', 'Ekantakuna', 'Jawalakhel', 'Pulchowk', 'Kupondole', 'Thapathali', 'Tripureshwor', 'NAC', 'Ratnapark'],
    ],

    // ═══ MAHANAGAR YATAYAT — Ring Road ═══
    [
        'name' => 'Ring Road (Full Circuit)',
        'desc' => 'Mahanagar Yatayat blue bus running the full Ring Road circuit connecting all major junctions around the valley.',
        'stops' => ['Kalanki', 'Balkhu', 'Ekantakuna', 'Satdobato', 'Koteshwor', 'Tinkune', 'Sinamangal', 'Gaushala', 'Chabahil', 'Maharajgunj', 'Gongabu', 'Balaju', 'Kalanki'],
    ],

    // ═══ MICRO BUS ROUTES — Common private operators ═══
    [
        'name' => 'Micro: Kalanki - Ratnapark',
        'desc' => 'Frequent micro-bus service from western Kalanki to city center via Kalimati vegetable market.',
        'stops' => ['Kalanki', 'Kalimati', 'Teku', 'Tripureshwor', 'Sundhara', 'Ratnapark'],
    ],
    [
        'name' => 'Micro: Ratnapark - Koteshwor',
        'desc' => 'Micro-bus from city center to eastern Koteshwor via Bagbazar, Putalisadak and Baneshwor.',
        'stops' => ['Ratnapark', 'Bagbazar', 'Putalisadak', 'Dillibazar', 'Battisputali', 'Baneshwor', 'Koteshwor'],
    ],
    [
        'name' => 'Micro: Ratnapark - Bouddhanath - Jorpati',
        'desc' => 'Micro-bus heading northeast from Ratnapark to Jorpati via Bouddhanath stupa area.',
        'stops' => ['Ratnapark', 'Bagbazar', 'Kalopul', 'Gaushala', 'Chabahil', 'Bouddhanath', 'Jorpati'],
    ],
    [
        'name' => 'Micro: Gongabu - Ratnapark',
        'desc' => 'Micro-bus from New Bus Park (Gongabu) to Ratnapark via Balaju, Machha Pokhari and Thamel.',
        'stops' => ['Gongabu', 'Samakhusi', 'Balaju', 'Machha Pokhari', 'Thamel', 'Ratnapark'],
    ],
    [
        'name' => 'Micro: Ratnapark - Maharajgunj',
        'desc' => 'Micro-bus northbound from Ratnapark to Maharajgunj via Jamal, Durbar Marg and Lazimpat.',
        'stops' => ['Ratnapark', 'Jamal', 'Durbar Marg', 'Lazimpat', 'Naxal', 'Maharajgunj'],
    ],
    [
        'name' => 'Micro: Ratnapark - Gaushala',
        'desc' => 'Micro-bus heading east from Ratnapark to Gaushala near Pashupatinath.',
        'stops' => ['Ratnapark', 'Putalisadak', 'Dillibazar', 'Battisputali', 'Gaushala'],
    ],
    [
        'name' => 'Micro: Sundhara - Baneshwor',
        'desc' => 'Micro-bus from Sundhara through government district to Baneshwor via NAC & Maitighar.',
        'stops' => ['Sundhara', 'NAC', 'Maitighar', 'Babar Mahal', 'Baneshwor'],
    ],
    [
        'name' => 'Micro: New Road - Balaju',
        'desc' => 'Inner city micro-bus from New Road through Asan bazaar and Thamel to northern Balaju.',
        'stops' => ['New Road', 'Asan', 'Thamel', 'Machha Pokhari', 'Balaju'],
    ],
    [
        'name' => 'Micro: Lagankhel - Sankhamul - Baneshwor',
        'desc' => 'Micro-bus connecting Lagankhel bus park to Baneshwor area via Sankhamul bridge.',
        'stops' => ['Lagankhel', 'Sankhamul', 'Baneshwor', 'Tinkune'],
    ],

    // ═══ SAFA TEMPO — Electric three-wheelers ═══
    [
        'name' => 'Tempo: Satdobato - Jawalakhel',
        'desc' => 'Electric Safa Tempo covering the Satdobato-Lagankhel-Jawalakhel corridor in Lalitpur.',
        'stops' => ['Satdobato', 'Lagankhel', 'Jawalakhel'],
    ],
    [
        'name' => 'Tempo: Patan Dhoka - Satdobato',
        'desc' => 'Three-wheeled tempo connecting Patan Dhoka through Lagankhel to Satdobato.',
        'stops' => ['Patan Dhoka', 'Lagankhel', 'Satdobato'],
    ],

    // ═══ MINI BUS ROUTES ═══
    [
        'name' => 'Mini Bus: Koteshwor - Suryabinayak',
        'desc' => 'Mini-bus service from Koteshwor to Suryabinayak (Bhaktapur) via historic Thimi.',
        'stops' => ['Koteshwor', 'Thimi', 'Suryabinayak'],
    ],
    [
        'name' => 'Mini Bus: Kalanki - Lagankhel (Ring Road South)',
        'desc' => 'Mini-bus along the southern Ring Road from Kalanki to Lagankhel via Balkhu and Satdobato.',
        'stops' => ['Kalanki', 'Balkhu', 'Ekantakuna', 'Satdobato', 'Lagankhel'],
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

// Pending route proposal
$pendingRouteStops = [
    ['index' => 1, 'location_id' => $locationIds['Gaushala']],
    ['index' => 2, 'location_id' => $locationIds['Pashupatinath']],
    ['index' => 3, 'location_id' => $locationIds['Airport']],
];
$pdo->prepare('
    INSERT INTO routes (name, description, status, updated_by, updated_at, location_list)
    VALUES (:name, :desc, "pending", :agent, NOW(), :loc_list)
')->execute([
            'name' => 'Gaushala - Pashupatinath - Airport',
            'desc' => 'Proposed route connecting Gaushala to the Airport via Pashupatinath.',
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
$pdo->prepare('UPDATE routes SET contribution_id = :cid WHERE route_id = :rid')
    ->execute(['cid' => (int) $pdo->lastInsertId(), 'rid' => $pendingRouteId]);
echo "   ✓ Gaushala - Pashupatinath - Airport (pending){$nl}";

/* ────────────────────────────────────────────────────────
   5.  VEHICLES — 15 real vehicles with downloaded images
   ──────────────────────────────────────────────────────── */
echo "{$nl}5. Seeding vehicles...{$nl}";

$vehicles = [
    // ─── Sajha Yatayat fleet ─────────────────────
    [
        'name' => 'Sajha Yatayat Bus (Line 1 & 7)',
        'desc' => 'Red and blue air-conditioned Sajha Yatayat bus. Most comfortable public transport in Kathmandu. Operates the Lagankhel-Gongabu and Bhaisepati-Ratnapark routes.',
        'image' => 'vehicles/sajha-yatayat.jpg',
        'start' => '06:00',
        'stop' => '21:00',
        'routes' => [
            ['name' => 'Sajha Line 1: Lagankhel - Gongabu', 'count' => 10],
            ['name' => 'Sajha Line 7: Bhaisepati - Ratnapark', 'count' => 6],
        ],
    ],
    [
        'name' => 'Sajha Yatayat Electric Bus (Line 3)',
        'desc' => 'Chinese-made CHTC Kinwin electric bus operated by Sajha Yatayat on the Airport route. Zero emission, quiet ride.',
        'image' => 'vehicles/sajha-electric.jpg',
        'start' => '06:30',
        'stop' => '20:30',
        'routes' => [
            ['name' => 'Sajha Line 3: Ratnapark - Airport', 'count' => 5],
        ],
    ],
    [
        'name' => 'Sajha Yatayat Bus (Line 4 & 5)',
        'desc' => 'Sajha Yatayat diesel bus serving the Bhaktapur corridor and the cross-valley Swayambhunath-Suryabinayak route.',
        'image' => 'vehicles/sajha-yatayat.jpg',
        'start' => '06:00',
        'stop' => '20:00',
        'routes' => [
            ['name' => 'Sajha Line 4: Ratnapark - Suryabinayak', 'count' => 6],
            ['name' => 'Sajha Line 5: Swayambhunath - Suryabinayak', 'count' => 5],
        ],
    ],

    // ─── Mahanagar Yatayat ───────────────────────
    [
        'name' => 'Mahanagar Yatayat (Ring Road Bus)',
        'desc' => 'Blue Mahanagar Yatayat city bus running the full Ring Road circuit. Recognizable blue livery. Frequent service especially during rush hours.',
        'image' => 'vehicles/mahanagar-yatayat.jpg',
        'start' => '05:30',
        'stop' => '21:00',
        'routes' => [
            ['name' => 'Ring Road (Full Circuit)', 'count' => 15],
        ],
    ],

    // ─── Micro Buses ─────────────────────────────
    [
        'name' => 'Micro Bus (Kalanki-Ratnapark)',
        'desc' => 'White Toyota HiAce-style micro-bus on the busy Kalanki to Ratnapark corridor via Kalimati market.',
        'image' => 'vehicles/micro-bus.jpg',
        'start' => '05:30',
        'stop' => '21:30',
        'routes' => [['name' => 'Micro: Kalanki - Ratnapark', 'count' => 15]],
    ],
    [
        'name' => 'Micro Bus (Ratnapark-Koteshwor)',
        'desc' => 'Frequent micro-bus from Ratnapark to eastern Koteshwor via Putalisadak and Baneshwor.',
        'image' => 'vehicles/micro-bus.jpg',
        'start' => '05:30',
        'stop' => '21:00',
        'routes' => [['name' => 'Micro: Ratnapark - Koteshwor', 'count' => 12]],
    ],
    [
        'name' => 'Micro Bus (Ratnapark-Jorpati)',
        'desc' => 'Micro-bus running northeast from Ratnapark past Bouddhanath stupa to Jorpati.',
        'image' => 'vehicles/micro-bus.jpg',
        'start' => '06:00',
        'stop' => '20:30',
        'routes' => [['name' => 'Micro: Ratnapark - Bouddhanath - Jorpati', 'count' => 10]],
    ],
    [
        'name' => 'Micro Bus (Gongabu-Ratnapark)',
        'desc' => 'Micro-bus from New Bus Park to Ratnapark via tourist hub Thamel.',
        'image' => 'vehicles/micro-bus.jpg',
        'start' => '05:30',
        'stop' => '21:00',
        'routes' => [['name' => 'Micro: Gongabu - Ratnapark', 'count' => 12]],
    ],
    [
        'name' => 'Micro Bus (Ratnapark-Maharajgunj)',
        'desc' => 'Micro-bus heading north from Ratnapark through Durbar Marg and Lazimpat embassy quarter.',
        'image' => 'vehicles/micro-bus.jpg',
        'start' => '06:00',
        'stop' => '20:30',
        'routes' => [
            ['name' => 'Micro: Ratnapark - Maharajgunj', 'count' => 8],
            ['name' => 'Micro: Ratnapark - Gaushala', 'count' => 7],
        ],
    ],
    [
        'name' => 'Micro Bus (Sundhara-Baneshwor)',
        'desc' => 'Micro-bus through government corridor from Sundhara via NAC and Maitighar to Baneshwor.',
        'image' => 'vehicles/micro-bus.jpg',
        'start' => '06:00',
        'stop' => '20:00',
        'routes' => [
            ['name' => 'Micro: Sundhara - Baneshwor', 'count' => 10],
            ['name' => 'Micro: Lagankhel - Sankhamul - Baneshwor', 'count' => 8],
        ],
    ],
    [
        'name' => 'Micro Bus (New Road-Balaju)',
        'desc' => 'Inner city micro-bus from New Road shopping street through historic Asan and Thamel.',
        'image' => 'vehicles/micro-bus.jpg',
        'start' => '06:00',
        'stop' => '20:30',
        'routes' => [['name' => 'Micro: New Road - Balaju', 'count' => 8]],
    ],

    // ─── Safa Tempos ─────────────────────────────
    [
        'name' => 'Safa Tempo (Lalitpur)',
        'desc' => 'Green electric three-wheeled Safa Tempo operating in the Lalitpur/Patan area. Zero-emission, affordable transport.',
        'image' => 'vehicles/safa-tempo.jpg',
        'start' => '06:00',
        'stop' => '19:00',
        'routes' => [
            ['name' => 'Tempo: Satdobato - Jawalakhel', 'count' => 12],
            ['name' => 'Tempo: Patan Dhoka - Satdobato', 'count' => 10],
        ],
    ],

    // ─── Mini Buses ──────────────────────────────
    [
        'name' => 'Mini Bus (Koteshwor-Bhaktapur)',
        'desc' => 'Medium-sized mini-bus connecting Koteshwor to Bhaktapur district via historic Thimi.',
        'image' => 'vehicles/mini-bus.jpg',
        'start' => '06:00',
        'stop' => '20:00',
        'routes' => [['name' => 'Mini Bus: Koteshwor - Suryabinayak', 'count' => 9]],
    ],
    [
        'name' => 'Mini Bus (Ring Road South)',
        'desc' => 'Mini-bus covering the southern Ring Road arc from Kalanki to Lagankhel.',
        'image' => 'vehicles/mini-bus.jpg',
        'start' => '05:30',
        'stop' => '20:00',
        'routes' => [['name' => 'Mini Bus: Kalanki - Lagankhel (Ring Road South)', 'count' => 9]],
    ],
];

$insertVehicle = $pdo->prepare('
    INSERT INTO vehicles (name, description, image_path, status, approved_by, updated_at, starts_at, stops_at, used_routes, current_lat, current_lng, current_speed, gps_updated_at)
    VALUES (:name, :desc, :image, "approved", :admin, NOW(), :starts, :stops, :routes, :lat, :lng, :speed, :gps_at)
');

// Some vehicles have live GPS positions for demo purposes
$gpsData = [
    'Sajha Yatayat Bus (Line 1 & 7)' => ['lat' => 27.7140, 'lng' => 85.3190, 'speed' => 18.5],
    'Mahanagar Yatayat (Ring Road Bus)' => ['lat' => 27.6920, 'lng' => 85.3220, 'speed' => 22.0],
    'Micro Bus (Ratnapark-Jorpati)' => ['lat' => 27.7350, 'lng' => 85.3240, 'speed' => 15.0],
    'Safa Tempo (Lalitpur)' => ['lat' => 27.6780, 'lng' => 85.3160, 'speed' => 12.5],
];

$vehicleIds = [];
foreach ($vehicles as $v) {
    $usedRoutes = [];
    foreach ($v['routes'] as $r) {
        if (isset($routeIds[$r['name']])) {
            $usedRoutes[] = ['route_id' => $routeIds[$r['name']], 'count' => $r['count']];
        }
    }
    $gps = $gpsData[$v['name']] ?? null;
    $insertVehicle->execute([
        'name' => $v['name'],
        'desc' => $v['desc'],
        'image' => $v['image'] ?? null,
        'admin' => $adminId,
        'starts' => $v['start'],
        'stops' => $v['stop'],
        'routes' => json_encode($usedRoutes),
        'lat' => $gps ? $gps['lat'] : null,
        'lng' => $gps ? $gps['lng'] : null,
        'speed' => $gps ? $gps['speed'] : null,
        'gps_at' => $gps ? date('Y-m-d H:i:s') : null,
    ]);
    $vehicleIds[$v['name']] = (int) $pdo->lastInsertId();
    echo "   ✓ {$v['name']}{$nl}";
}

// Pending vehicle
$pdo->prepare('
    INSERT INTO vehicles (name, description, image_path, status, updated_by, updated_at, starts_at, stops_at, used_routes)
    VALUES (:name, :desc, :image, "pending", :agent, NOW(), :starts, :stops, :routes)
')->execute([
            'name' => 'Electric Safa Tempo (New)',
            'desc' => 'Proposed new electric three-wheeler for the Satdobato-Jawalakhel corridor.',
            'image' => 'vehicles/safa-tempo.jpg',
            'agent' => $agentIds[1],
            'starts' => '07:00',
            'stops' => '18:00',
            'routes' => json_encode([['route_id' => $routeIds['Tempo: Satdobato - Jawalakhel'], 'count' => 4]]),
        ]);
$pendingVehicleId = (int) $pdo->lastInsertId();
$insertContrib->execute([
    'type' => 'vehicle',
    'entry_id' => $pendingVehicleId,
    'agent' => $agentIds[1],
    'status' => 'pending',
    'proposed_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
]);
$pdo->prepare('UPDATE vehicles SET contribution_id = :cid WHERE vehicle_id = :vid')
    ->execute(['cid' => (int) $pdo->lastInsertId(), 'vid' => $pendingVehicleId]);
echo "   ✓ Electric Safa Tempo (pending){$nl}";

/* ────────────────────────────────────────────────────────
   6.  ACCEPTED CONTRIBUTIONS (for leaderboard)
   ──────────────────────────────────────────────────────── */
echo "{$nl}6. Seeding accepted contributions...{$nl}";

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
    // Sunil — 2 accepted + 1 rejected
    ['type' => 'location', 'agent' => 4, 'days_ago' => 70],
    ['type' => 'route', 'agent' => 4, 'days_ago' => 40],
    ['type' => 'location', 'agent' => 4, 'days_ago' => 35, 'status' => 'rejected', 'reason' => 'Duplicate of existing Basundhara stop.'],
];

$approvedLocIds = array_values($locationIds);
$approvedRouteIds = array_values($routeIds);
$approvedVehIds = array_values($vehicleIds);

$insertContribFull = $pdo->prepare('
    INSERT INTO contributions (type, associated_entry_id, proposed_by, accepted_by, status, proposed_at, responded_at, rejection_reason)
    VALUES (:type, :entry, :agent, :admin, :status, :proposed, :responded, :reason)
');

$contribCounts = array_fill(0, 5, ['location' => 0, 'route' => 0, 'vehicle' => 0]);

foreach ($contributions as $c) {
    $status = $c['status'] ?? 'accepted';
    $proposedAt = date('Y-m-d H:i:s', strtotime("-{$c['days_ago']} days"));
    $respondedAt = date('Y-m-d H:i:s', strtotime('-' . ($c['days_ago'] - rand(1, 3)) . ' days'));

    // Pick a random existing entry of the correct type
    $entryId = match ($c['type']) {
        'location' => $approvedLocIds[array_rand($approvedLocIds)],
        'route' => $approvedRouteIds[array_rand($approvedRouteIds)],
        'vehicle' => $approvedVehIds[array_rand($approvedVehIds)],
    };

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

// Update agents' contribution summaries
foreach ($contribCounts as $idx => $counts) {
    $pdo->prepare('UPDATE agents SET contributions_summary = :summary WHERE agent_id = :id')
        ->execute(['summary' => json_encode($counts), 'id' => $agentIds[$idx]]);
}
echo "   ✓ " . count($contributions) . " contributions seeded{$nl}";

/* ────────────────────────────────────────────────────────
   7.  ALERTS
   ──────────────────────────────────────────────────────── */
echo "{$nl}7. Seeding alerts...{$nl}";

$alerts = [
    [
        'name' => 'Road Widening at Tripureshwor',
        'desc' => 'Ongoing road widening project at Tripureshwor-Kalimati section. Expect delays on all routes passing through Tripureshwor. Detour via Teku available.',
        'routes' => ['Sajha Line 1: Lagankhel - Gongabu', 'Sajha Line 5: Swayambhunath - Suryabinayak', 'Micro: Kalanki - Ratnapark'],
        'expires' => '+30 days',
    ],
    [
        'name' => 'Bouddha Losar Festival Traffic',
        'desc' => 'Heavy foot traffic around Bouddhanath Stupa for Tibetan New Year (Losar). Routes via Chabahil and Bouddha may experience 20-30 minute delays.',
        'routes' => ['Micro: Ratnapark - Bouddhanath - Jorpati', 'Ring Road (Full Circuit)'],
        'expires' => '+5 days',
    ],
    [
        'name' => 'Koteshwor Flyover Construction',
        'desc' => 'Koteshwor flyover construction causing lane closures. Ring Road traffic heavily affected between Tinkune and Koteshwor during 10AM-4PM.',
        'routes' => ['Ring Road (Full Circuit)', 'Sajha Line 4: Ratnapark - Suryabinayak', 'Micro: Ratnapark - Koteshwor'],
        'expires' => '+60 days',
    ],
    [
        'name' => 'Banda (Strike) in Balkhu Area',
        'desc' => 'Transportation strike expected in Balkhu-Kalanki area tomorrow. Ring Road south services may be cancelled. Use alternative routes via Tripureshwor.',
        'routes' => ['Mini Bus: Kalanki - Lagankhel (Ring Road South)', 'Ring Road (Full Circuit)'],
        'expires' => '+2 days',
    ],
];

$insertAlert = $pdo->prepare('
    INSERT INTO alerts (name, description, issued_by, routes_affected, reported_at, expires_at)
    VALUES (:name, :desc, :admin, :routes, NOW(), :expires)
');

foreach ($alerts as $a) {
    $affected = [];
    foreach ($a['routes'] as $rn) {
        if (isset($routeIds[$rn]))
            $affected[] = $routeIds[$rn];
    }
    $insertAlert->execute([
        'name' => $a['name'],
        'desc' => $a['desc'],
        'admin' => $adminId,
        'routes' => json_encode($affected),
        'expires' => date('Y-m-d H:i:s', strtotime($a['expires'])),
    ]);
    echo "   ✓ {$a['name']}{$nl}";
}

/* ────────────────────────────────────────────────────────
   8.  SUGGESTIONS / FEEDBACK
   ──────────────────────────────────────────────────────── */
echo "{$nl}8. Seeding suggestions...{$nl}";

$suggestions = [
    [
        'type' => 'appreciation',
        'rating' => 5,
        'status' => 'reviewed',
        'message' => 'Sawari suggested Sajha Line 1 from Lagankhel to Gongabu with just one bus. Perfect! Much easier than asking random people at the bus stop.',
        'route' => 'Sajha Line 1: Lagankhel - Gongabu',
        'ip' => '192.168.1.101',
    ],
    [
        'type' => 'suggestion',
        'rating' => 4,
        'status' => 'pending',
        'message' => 'Please add night bus routes. Sajha stops at 9 PM but many people work late in Thamel and need to get to Lagankhel.',
        'route' => null,
        'ip' => '192.168.1.102',
    ],
    [
        'type' => 'complaint',
        'rating' => 2,
        'status' => 'pending',
        'message' => 'The fare shown for micro bus Kalanki-Ratnapark was NPR 25 but the conductor charged NPR 40. Please update fare data.',
        'route' => 'Micro: Kalanki - Ratnapark',
        'ip' => '192.168.1.103',
    ],
    [
        'type' => 'correction',
        'rating' => 3,
        'status' => 'pending',
        'message' => 'Sajha electric bus on Line 3 now starts from Shahid Gate, not Ratnapark. Schedule changed since December.',
        'route' => 'Sajha Line 3: Ratnapark - Airport',
        'ip' => '192.168.1.104',
    ],
    [
        'type' => 'appreciation',
        'rating' => 5,
        'status' => 'reviewed',
        'message' => 'Tourist from Japan here. Walking directions from Swayambhunath to Kalanki bus stop were spot on! Great app for visitors.',
        'route' => 'Sajha Line 5: Swayambhunath - Suryabinayak',
        'ip' => '10.0.0.55',
    ],
    [
        'type' => 'suggestion',
        'rating' => 4,
        'status' => 'pending',
        'message' => 'It would be great to show real-time bus locations. Maybe agents can update bus GPS positions via the app?',
        'route' => null,
        'ip' => '192.168.1.105',
    ],
    [
        'type' => 'complaint',
        'rating' => 2,
        'status' => 'resolved',
        'message' => 'Route from Satdobato to Bouddhanath suggested 3 transfers. That seems too many — surely there is a more direct way?',
        'route' => 'Tempo: Satdobato - Jawalakhel',
        'ip' => '192.168.1.106',
    ],
    [
        'type' => 'appreciation',
        'rating' => 5,
        'status' => 'reviewed',
        'message' => 'Student discount feature is awesome! Auto-calculated my fare at 50% off on the Koteshwor bus. Dhanyabad Sawari!',
        'route' => 'Micro: Ratnapark - Koteshwor',
        'ip' => '192.168.1.107',
    ],
    [
        'type' => 'suggestion',
        'rating' => 3,
        'status' => 'pending',
        'message' => 'Please add Ring Road bus timings. Mahanagar Yatayat buses are irregular during off-peak hours.',
        'route' => 'Ring Road (Full Circuit)',
        'ip' => '192.168.1.108',
    ],
    [
        'type' => 'appreciation',
        'rating' => 4,
        'status' => 'reviewed',
        'message' => 'Found the Bhaisepati to Ratnapark Sajha bus route using Sawari. Did not know Line 7 existed before!',
        'route' => 'Sajha Line 7: Bhaisepati - Ratnapark',
        'ip' => '192.168.1.109',
    ],
];

$insertSuggestion = $pdo->prepare('
    INSERT INTO suggestions (type, message, rating, related_route_id, ip_address, status, reviewed_by, submitted_at, reviewed_at)
    VALUES (:type, :msg, :rating, :route_id, :ip, :status, :reviewed_by, :submitted, :reviewed_at)
');

foreach ($suggestions as $i => $s) {
    $routeId = ($s['route'] && isset($routeIds[$s['route']])) ? $routeIds[$s['route']] : null;
    $submittedAt = date('Y-m-d H:i:s', strtotime('-' . (count($suggestions) - $i) * 3 . ' days'));
    $isReviewed = in_array($s['status'], ['reviewed', 'resolved']);

    $insertSuggestion->execute([
        'type' => $s['type'],
        'msg' => $s['message'],
        'rating' => $s['rating'],
        'route_id' => $routeId,
        'ip' => $s['ip'],
        'status' => $s['status'],
        'reviewed_by' => $isReviewed ? $adminId : null,
        'submitted' => $submittedAt,
        'reviewed_at' => $isReviewed ? date('Y-m-d H:i:s', strtotime($submittedAt . ' +1 day')) : null,
    ]);
    echo "   ✓ {$s['type']}: " . mb_substr($s['message'], 0, 55) . "...{$nl}";
}

/* ────────────────────────────────────────────────────────
   9.  SAMPLE TRIPS (search history)
   ──────────────────────────────────────────────────────── */
echo "{$nl}9. Seeding sample trips...{$nl}";

$trips = [
    // Single-route journeys
    ['from' => 'Lagankhel', 'to' => 'Gongabu', 'routes' => ['Sajha Line 1: Lagankhel - Gongabu']],
    ['from' => 'Ratnapark', 'to' => 'Airport', 'routes' => ['Sajha Line 3: Ratnapark - Airport']],
    ['from' => 'Ratnapark', 'to' => 'Suryabinayak', 'routes' => ['Sajha Line 4: Ratnapark - Suryabinayak']],
    ['from' => 'Kalanki', 'to' => 'Ratnapark', 'routes' => ['Micro: Kalanki - Ratnapark']],
    ['from' => 'Ratnapark', 'to' => 'Koteshwor', 'routes' => ['Micro: Ratnapark - Koteshwor']],
    ['from' => 'Ratnapark', 'to' => 'Jorpati', 'routes' => ['Micro: Ratnapark - Bouddhanath - Jorpati']],
    ['from' => 'Gongabu', 'to' => 'Ratnapark', 'routes' => ['Micro: Gongabu - Ratnapark']],
    ['from' => 'Bhaisepati', 'to' => 'Ratnapark', 'routes' => ['Sajha Line 7: Bhaisepati - Ratnapark']],
    ['from' => 'Satdobato', 'to' => 'Jawalakhel', 'routes' => ['Tempo: Satdobato - Jawalakhel']],
    ['from' => 'Koteshwor', 'to' => 'Suryabinayak', 'routes' => ['Mini Bus: Koteshwor - Suryabinayak']],
    ['from' => 'Kalanki', 'to' => 'Lagankhel', 'routes' => ['Mini Bus: Kalanki - Lagankhel (Ring Road South)']],
    ['from' => 'Sundhara', 'to' => 'Baneshwor', 'routes' => ['Micro: Sundhara - Baneshwor']],

    // Multi-transfer journeys
    ['from' => 'Lagankhel', 'to' => 'Bouddhanath', 'routes' => ['Sajha Line 1: Lagankhel - Gongabu', 'Micro: Ratnapark - Bouddhanath - Jorpati']],
    ['from' => 'Kalanki', 'to' => 'Airport', 'routes' => ['Micro: Kalanki - Ratnapark', 'Sajha Line 3: Ratnapark - Airport']],
    ['from' => 'Satdobato', 'to' => 'Ratnapark', 'routes' => ['Tempo: Satdobato - Jawalakhel', 'Sajha Line 7: Bhaisepati - Ratnapark']],
    ['from' => 'Bhaisepati', 'to' => 'Maharajgunj', 'routes' => ['Sajha Line 7: Bhaisepati - Ratnapark', 'Micro: Ratnapark - Maharajgunj']],
    ['from' => 'Gongabu', 'to' => 'Koteshwor', 'routes' => ['Micro: Gongabu - Ratnapark', 'Micro: Ratnapark - Koteshwor']],
    ['from' => 'Jorpati', 'to' => 'Kalanki', 'routes' => ['Micro: Ratnapark - Bouddhanath - Jorpati', 'Micro: Kalanki - Ratnapark']],
    ['from' => 'Balaju', 'to' => 'Baneshwor', 'routes' => ['Micro: Gongabu - Ratnapark', 'Micro: Sundhara - Baneshwor']],
    ['from' => 'Swayambhunath', 'to' => 'Thimi', 'routes' => ['Sajha Line 5: Swayambhunath - Suryabinayak']],
    // Ring Road trips
    ['from' => 'Kalanki', 'to' => 'Chabahil', 'routes' => ['Ring Road (Full Circuit)']],
    ['from' => 'Satdobato', 'to' => 'Gaushala', 'routes' => ['Ring Road (Full Circuit)']],
    ['from' => 'Balkhu', 'to' => 'Maharajgunj', 'routes' => ['Ring Road (Full Circuit)']],
    // Patan-area trips
    ['from' => 'Patan Dhoka', 'to' => 'Satdobato', 'routes' => ['Tempo: Patan Dhoka - Satdobato']],
    ['from' => 'Lagankhel', 'to' => 'Tinkune', 'routes' => ['Micro: Lagankhel - Sankhamul - Baneshwor']],
    // City-center trips
    ['from' => 'New Road', 'to' => 'Balaju', 'routes' => ['Micro: New Road - Balaju']],
    ['from' => 'Ratnapark', 'to' => 'Gaushala', 'routes' => ['Micro: Ratnapark - Gaushala']],
    ['from' => 'Jamal', 'to' => 'Lazimpat', 'routes' => ['Micro: Ratnapark - Maharajgunj']],
    ['from' => 'NAC', 'to' => 'Maitighar', 'routes' => ['Micro: Sundhara - Baneshwor']],
    ['from' => 'Kupondole', 'to' => 'Tripureshwor', 'routes' => ['Sajha Line 1: Lagankhel - Gongabu']],
];

$insertTrip = $pdo->prepare('
    INSERT INTO trips (ip_address, start_location_id, destination_location_id, routes_used, queried_at)
    VALUES (:ip, :start, :dest, :routes, :queried)
');

$ips = ['192.168.1.10', '10.0.0.5', '192.168.1.22', '172.16.0.8', '192.168.2.15', '10.0.1.3', '192.168.1.50', '10.0.0.12'];

foreach ($trips as $i => $t) {
    $usedRoutes = [];
    foreach ($t['routes'] as $rn) {
        if (isset($routeIds[$rn]))
            $usedRoutes[] = $routeIds[$rn];
    }
    if (!isset($locationIds[$t['from']]) || !isset($locationIds[$t['to']]))
        continue;

    $insertTrip->execute([
        'ip' => $ips[$i % count($ips)],
        'start' => $locationIds[$t['from']],
        'dest' => $locationIds[$t['to']],
        'routes' => json_encode($usedRoutes),
        'queried' => date('Y-m-d H:i:s', strtotime('-' . rand(0, 30) . ' days -' . rand(0, 23) . ' hours')),
    ]);
}
echo "   ✓ " . count($trips) . " sample trips seeded{$nl}";

/* ────────────────────────────────────────────────────────
   SUMMARY
   ──────────────────────────────────────────────────────── */
echo "{$nl}=== Seeding Complete ==={$nl}{$nl}";
echo "Accounts:{$nl}";
echo "  Admin : admin@sawari.com  / Admin@123{$nl}";
foreach ($agentsData as $i => $a) {
    echo "  Agent" . ($i + 1) . ": {$a['email']} / Agent@123 ({$a['name']}){$nl}";
}

echo "{$nl}Data:{$nl}";
echo "  " . count($locations) . " approved locations (+ " . count($pendingLocs) . " pending){$nl}";
echo "  " . count($routes) . " approved routes (+ 1 pending){$nl}";
echo "  " . count($vehicles) . " approved vehicles (+ 1 pending){$nl}";
echo "  " . count($contributions) . " contributions{$nl}";
echo "  " . count($alerts) . " active alerts{$nl}";
echo "  " . count($suggestions) . " suggestions{$nl}";
echo "  " . count($trips) . " sample trips{$nl}{$nl}";

echo "Vehicle images stored in: assets/images/uploads/vehicles/{$nl}";
echo "  sajha-yatayat.jpg, sajha-electric.jpg, mahanagar-yatayat.jpg,{$nl}";
echo "  micro-bus.jpg, safa-tempo.jpg, mini-bus.jpg{$nl}";
