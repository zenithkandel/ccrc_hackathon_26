-- =============================================
-- SAWARI — Test Data (Kathmandu Valley)
-- Real bus stops, vehicles, and routes
-- Run this AFTER schema.sql
-- =============================================

USE sawari;

-- =============================================
-- TEST AGENT
-- Password: agent123
-- Hash: password_hash('agent123', PASSWORD_BCRYPT)
-- =============================================
INSERT INTO agents (name, email, password, phone, points, contributions_count, approved_count, status) VALUES
('Ram Shrestha', 'ram@sawari.com', '$2y$12$0rH9NUD7Gs7cQPm5T0JOk.abuES0/S8mYaicx0F2Ttym/WneK3dAm', '+977 9801234567', 150, 15, 15, 'active');

-- =============================================
-- KATHMANDU VALLEY BUS STOPS (15 locations)
-- Real coordinates from OpenStreetMap
-- =============================================
INSERT INTO locations (name, description, latitude, longitude, type, status, approved_by, updated_at) VALUES
('Kalanki Chowk',        'Major intersection and bus terminal in western Kathmandu',        27.69350000, 85.28140000, 'stop', 'approved', 1, NOW()),
('Balkhu',               'Bus stop near Balkhu bridge and Tribhuvan University',             27.68420000, 85.29860000, 'stop', 'approved', 1, NOW()),
('Kalimati',             'Near Kalimati vegetable market area',                              27.69500000, 85.30500000, 'stop', 'approved', 1, NOW()),
('Tripureshwor',         'Major bus stop near Tripureshwor tower',                           27.69600000, 85.31700000, 'stop', 'approved', 1, NOW()),
('Thapathali',           'Bus stop near Thapathali bridge',                                  27.69200000, 85.32400000, 'stop', 'approved', 1, NOW()),
('Maitighar Mandala',    'Central roundabout near Singha Durbar',                            27.69400000, 85.32200000, 'stop', 'approved', 1, NOW()),
('Ratnapark',            'Central Kathmandu bus hub near Dharahara',                         27.70400000, 85.31400000, 'stop', 'approved', 1, NOW()),
('Jamal',                'Bus stop near Jamal area',                                        27.71000000, 85.31700000, 'stop', 'approved', 1, NOW()),
('Durbarmarg',           'Near Royal Palace Museum and Narayanhiti',                         27.71400000, 85.31900000, 'stop', 'approved', 1, NOW()),
('Putalisadak',          'Major commercial area bus stop',                                   27.70300000, 85.32400000, 'stop', 'approved', 1, NOW()),
('Koteshwor',            'Eastern Kathmandu junction near ring road',                        27.67750000, 85.34900000, 'stop', 'approved', 1, NOW()),
('Chabahil',             'Bus stop near Chabahil Ganesthan',                                 27.71800000, 85.34200000, 'stop', 'approved', 1, NOW()),
('Gongabu Bus Park',     'Main long-distance bus terminal (New Bus Park)',                   27.73000000, 85.31200000, 'stop', 'approved', 1, NOW()),
('Balaju',               'Northern Kathmandu near Balaju Industrial District',               27.72800000, 85.30400000, 'stop', 'approved', 1, NOW()),
('Maharajgunj',          'Bus stop near Teaching Hospital and UNDP',                         27.73400000, 85.32700000, 'stop', 'approved', 1, NOW());

-- =============================================
-- VEHICLES (5 real Kathmandu public transport)
-- =============================================
INSERT INTO vehicles (name, description, electric, starts_at, stops_at, status, approved_by, updated_at) VALUES
('Sajha Yatayat',          'Modern public bus service operated by Sajha Yatayat cooperative. Air-conditioned, clean buses on major routes.', 0, '06:00', '21:00', 'approved', 1, NOW()),
('Kathmandu Micro Bus',    'Blue and green micro buses running Ring Road and city routes. Seats 12-15 passengers.',                          0, '05:30', '21:00', 'approved', 1, NOW()),
('Safa Tempo',             'Three-wheeled electric tempo running fixed routes within Kathmandu. Eco-friendly option.',                       1, '06:00', '20:00', 'approved', 1, NOW()),
('Mayur Yatayat',          'Public bus service operating on longer urban routes. Standard city bus.',                                         0, '05:30', '20:30', 'approved', 1, NOW()),
('Valley Electric Bus',    'New electric bus service on pilot routes in Kathmandu valley.',                                                   1, '06:30', '19:30', 'approved', 1, NOW());

-- =============================================
-- ROUTES (3 real Kathmandu bus routes)
-- Using location_ids from the inserts above
-- Assuming locations start at ID 1
-- =============================================

-- Route 1: Kalanki → Ratnapark → Gongabu (via Kalimati)
-- Stops: Kalanki(1) → Balkhu(2) → Kalimati(3) → Tripureshwor(4) → Ratnapark(7) → Jamal(8) → Balaju(14) → Gongabu(13)
INSERT INTO routes (name, description, location_list, fare_base, fare_per_km, status, approved_by, updated_at) VALUES
('Kalanki – Ratnapark – Gongabu',
 'Major east-west route connecting Kalanki junction through city center to New Bus Park',
 '[{"location_id":1,"name":"Kalanki Chowk","latitude":27.6935,"longitude":85.2814},{"location_id":2,"name":"Balkhu","latitude":27.6842,"longitude":85.2986},{"location_id":3,"name":"Kalimati","latitude":27.695,"longitude":85.305},{"location_id":4,"name":"Tripureshwor","latitude":27.696,"longitude":85.317},{"location_id":7,"name":"Ratnapark","latitude":27.704,"longitude":85.314},{"location_id":8,"name":"Jamal","latitude":27.71,"longitude":85.317},{"location_id":14,"name":"Balaju","latitude":27.728,"longitude":85.304},{"location_id":13,"name":"Gongabu Bus Park","latitude":27.73,"longitude":85.312}]',
 20.00, 2.50, 'approved', 1, NOW());

-- Route 2: Ratnapark → Koteshwor (via Putalisadak, Thapathali)
-- Stops: Ratnapark(7) → Putalisadak(10) → Maitighar(6) → Thapathali(5) → Koteshwor(11)
INSERT INTO routes (name, description, location_list, fare_base, fare_per_km, status, approved_by, updated_at) VALUES
('Ratnapark – Koteshwor',
 'Route connecting central Kathmandu to eastern ring road junction via Putalisadak',
 '[{"location_id":7,"name":"Ratnapark","latitude":27.704,"longitude":85.314},{"location_id":10,"name":"Putalisadak","latitude":27.703,"longitude":85.324},{"location_id":6,"name":"Maitighar Mandala","latitude":27.694,"longitude":85.322},{"location_id":5,"name":"Thapathali","latitude":27.692,"longitude":85.324},{"location_id":11,"name":"Koteshwor","latitude":27.6775,"longitude":85.349}]',
 15.00, 2.50, 'approved', 1, NOW());

-- Route 3: Gongabu → Chabahil → Koteshwor (Ring Road partial)
-- Stops: Gongabu(13) → Maharajgunj(15) → Chabahil(12) → Koteshwor(11)
INSERT INTO routes (name, description, location_list, fare_base, fare_per_km, status, approved_by, updated_at) VALUES
('Gongabu – Ring Road – Koteshwor',
 'Partial ring road route running the northern and eastern sections',
 '[{"location_id":13,"name":"Gongabu Bus Park","latitude":27.73,"longitude":85.312},{"location_id":15,"name":"Maharajgunj","latitude":27.734,"longitude":85.327},{"location_id":12,"name":"Chabahil","latitude":27.718,"longitude":85.342},{"location_id":11,"name":"Koteshwor","latitude":27.6775,"longitude":85.349}]',
 18.00, 2.00, 'approved', 1, NOW());

-- =============================================
-- ASSIGN ROUTES TO VEHICLES (used_routes JSON)
-- =============================================
-- Sajha Yatayat runs routes 1 and 2
UPDATE vehicles SET used_routes = '[1, 2]' WHERE name = 'Sajha Yatayat';
-- Micro Bus runs all routes
UPDATE vehicles SET used_routes = '[1, 2, 3]' WHERE name = 'Kathmandu Micro Bus';
-- Safa Tempo runs route 2 only (shorter inner route)
UPDATE vehicles SET used_routes = '[2]' WHERE name = 'Safa Tempo';
-- Mayur Yatayat runs routes 1 and 3
UPDATE vehicles SET used_routes = '[1, 3]' WHERE name = 'Mayur Yatayat';
-- Electric bus runs route 2
UPDATE vehicles SET used_routes = '[2]' WHERE name = 'Valley Electric Bus';
