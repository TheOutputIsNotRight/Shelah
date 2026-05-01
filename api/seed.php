<?php
/**
 * Shelah — Database Setup & Seed Script
 * 
 * Visit this URL once after deploy to initialize the database:
 *   https://your-app.vercel.app/api/setup/seed.php
 * 
 * Then DELETE this file from your repo for security.
 */

header('Content-Type: text/html; charset=utf-8');
echo '<h1>Shelah — Database Setup</h1><pre>';

$dsn = getenv('DATABASE_URL');
if (!$dsn) {
    die('ERROR: DATABASE_URL environment variable not set.');
}

try {
    $pdo = new PDO($dsn, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    echo "✅ Database connected\n";
} catch (PDOException $e) {
    die('ERROR: ' . $e->getMessage());
}

// --- 1. Run schema (inlined to avoid filesystem issues on Vercel) ---
echo "\n--- Creating tables ---\n";
$schema = "
CREATE TABLE IF NOT EXISTS users (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(100),
    created_at TIMESTAMP DEFAULT NOW()
);
CREATE TABLE IF NOT EXISTS friendships (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID REFERENCES users(id) ON DELETE CASCADE,
    friend_id UUID REFERENCES users(id) ON DELETE CASCADE,
    status VARCHAR(20) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(user_id, friend_id)
);
CREATE TABLE IF NOT EXISTS location_types (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(100) NOT NULL,
    icon VARCHAR(50)
);
CREATE TABLE IF NOT EXISTS places (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    location_type_id UUID REFERENCES location_types(id),
    address TEXT,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    rating DECIMAL(2,1),
    popularity VARCHAR(20),
    budget_tier VARCHAR(20),
    price_per_person_egp INTEGER,
    thumbnail_url TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);
CREATE TABLE IF NOT EXISTS outings (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    creator_id UUID REFERENCES users(id),
    outing_type VARCHAR(20) NOT NULL,
    scheduled_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);
CREATE TABLE IF NOT EXISTS outing_members (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    outing_id UUID REFERENCES outings(id) ON DELETE CASCADE,
    user_id UUID REFERENCES users(id) ON DELETE CASCADE,
    invite_status VARCHAR(20) DEFAULT 'pending',
    requirements_submitted BOOLEAN DEFAULT FALSE,
    invited_by UUID REFERENCES users(id),
    invite_approved BOOLEAN DEFAULT NULL,
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(outing_id, user_id)
);
CREATE TABLE IF NOT EXISTS invite_approvals (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    outing_id UUID REFERENCES outings(id) ON DELETE CASCADE,
    candidate_user_id UUID REFERENCES users(id),
    voter_user_id UUID REFERENCES users(id),
    approved BOOLEAN NOT NULL,
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(outing_id, candidate_user_id, voter_user_id)
);
CREATE TABLE IF NOT EXISTS user_requirements (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    outing_id UUID REFERENCES outings(id) ON DELETE CASCADE,
    user_id UUID REFERENCES users(id) ON DELETE CASCADE,
    home_latitude DECIMAL(10, 8),
    home_longitude DECIMAL(11, 8),
    max_distance_km INTEGER,
    popularity_preference VARCHAR(20),
    min_rating DECIMAL(2,1),
    budget_tier VARCHAR(20),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(outing_id, user_id)
);
CREATE TABLE IF NOT EXISTS requirement_location_types (
    requirement_id UUID REFERENCES user_requirements(id) ON DELETE CASCADE,
    location_type_id UUID REFERENCES location_types(id),
    PRIMARY KEY (requirement_id, location_type_id)
);
CREATE TABLE IF NOT EXISTS place_votes (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    outing_id UUID REFERENCES outings(id) ON DELETE CASCADE,
    place_id UUID REFERENCES places(id),
    user_id UUID REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(outing_id, place_id, user_id)
);
";
$pdo->exec($schema);
echo "✅ All tables created\n";

// --- 2. Seed location types ---
echo "\n--- Seeding location types ---\n";
$types = [
    ['Coffee Shop', '☕'],
    ['Restaurant', '🍽️'],
    ['Museum', '🏛️'],
    ['Arcade/Games', '🕹️'],
    ['Rooftop Bar', '🍸'],
    ['Cinema', '🎬'],
    ['Escape Room', '🔐'],
    ['Art Gallery', '🎨'],
    ['Bookshop Café', '📚'],
    ['Park/Outdoor', '🌳'],
    ['Bowling', '🎳'],
    ['Karaoke', '🎤'],
    ['Shopping Mall', '🛍️'],
    ['Spa/Wellness', '💆'],
    ['Live Music Venue', '🎵'],
];

$stmt = $pdo->prepare('INSERT INTO location_types (name, icon) VALUES (:name, :icon) ON CONFLICT DO NOTHING');
foreach ($types as $t) {
    $stmt->execute(['name' => $t[0], 'icon' => $t[1]]);
    echo "  + {$t[1]} {$t[0]}\n";
}
echo "✅ Location types seeded\n";

// --- 3. Fetch location type IDs ---
$typeMap = [];
$rows = $pdo->query('SELECT id, name FROM location_types')->fetchAll();
foreach ($rows as $r) {
    $typeMap[$r['name']] = $r['id'];
}

// --- 4. Seed places ---
echo "\n--- Seeding sample Cairo places ---\n";
$places = [
    [
        'name' => 'Café Supreme — Zamalek',
        'type' => 'Coffee Shop',
        'address' => '26 July St, Zamalek, Cairo',
        'lat' => 30.0609, 'lng' => 31.2194,
        'rating' => 4.5, 'popularity' => 'mainstream',
        'budget' => 'moderate', 'price' => 120,
        'thumb' => null
    ],
    [
        'name' => 'Zooba — Downtown',
        'type' => 'Restaurant',
        'address' => '26 July Corridor, Downtown, Cairo',
        'lat' => 30.0455, 'lng' => 31.2354,
        'rating' => 4.3, 'popularity' => 'mainstream',
        'budget' => 'budget', 'price' => 80,
        'thumb' => null
    ],
    [
        'name' => 'The Egyptian Museum',
        'type' => 'Museum',
        'address' => 'Tahrir Square, Downtown, Cairo',
        'lat' => 30.0478, 'lng' => 31.2336,
        'rating' => 4.6, 'popularity' => 'mainstream',
        'budget' => 'budget', 'price' => 60,
        'thumb' => null
    ],
    [
        'name' => 'Player One Gaming Lounge',
        'type' => 'Arcade/Games',
        'address' => 'City Stars Mall, Nasr City',
        'lat' => 30.0722, 'lng' => 31.3456,
        'rating' => 4.1, 'popularity' => 'moderate',
        'budget' => 'moderate', 'price' => 150,
        'thumb' => null
    ],
    [
        'name' => 'Cairo Jazz Club',
        'type' => 'Live Music Venue',
        'address' => '197 Nile St, Agouza, Cairo',
        'lat' => 30.0553, 'lng' => 31.2085,
        'rating' => 4.4, 'popularity' => 'mainstream',
        'budget' => 'upscale', 'price' => 350,
        'thumb' => null
    ],
    [
        'name' => 'Roof Top — Nile Ritz',
        'type' => 'Rooftop Bar',
        'address' => 'Nile Ritz-Carlton, Downtown',
        'lat' => 30.0439, 'lng' => 31.2319,
        'rating' => 4.7, 'popularity' => 'mainstream',
        'budget' => 'luxury', 'price' => 600,
        'thumb' => null
    ],
    [
        'name' => 'Galaxy Cinema — Maadi',
        'type' => 'Cinema',
        'address' => 'Maadi Grand Mall, Maadi',
        'lat' => 29.9607, 'lng' => 31.2581,
        'rating' => 3.9, 'popularity' => 'moderate',
        'budget' => 'moderate', 'price' => 180,
        'thumb' => null
    ],
    [
        'name' => 'Escape Zone Cairo',
        'type' => 'Escape Room',
        'address' => '10 Ismail Mohamed, Zamalek',
        'lat' => 30.0585, 'lng' => 31.2240,
        'rating' => 4.5, 'popularity' => 'niche',
        'budget' => 'moderate', 'price' => 200,
        'thumb' => null
    ],
    [
        'name' => 'Art Corner Gallery',
        'type' => 'Art Gallery',
        'address' => '8 Mohamed Mazhar St, Zamalek',
        'lat' => 30.0612, 'lng' => 31.2200,
        'rating' => 4.2, 'popularity' => 'niche',
        'budget' => 'budget', 'price' => 50,
        'thumb' => null
    ],
    [
        'name' => 'Diwan — Zamalek',
        'type' => 'Bookshop Café',
        'address' => '159 26 July St, Zamalek',
        'lat' => 30.0600, 'lng' => 31.2210,
        'rating' => 4.4, 'popularity' => 'moderate',
        'budget' => 'moderate', 'price' => 100,
        'thumb' => null
    ],
    [
        'name' => 'Al-Azhar Park',
        'type' => 'Park/Outdoor',
        'address' => 'Al-Azhar Park, Salah Salem St',
        'lat' => 30.0389, 'lng' => 31.2624,
        'rating' => 4.6, 'popularity' => 'mainstream',
        'budget' => 'budget', 'price' => 40,
        'thumb' => null
    ],
    [
        'name' => 'Dandy Mega Mall Bowling',
        'type' => 'Bowling',
        'address' => 'Dandy Mall, New Cairo Ring Rd',
        'lat' => 30.0089, 'lng' => 31.4102,
        'rating' => 3.8, 'popularity' => 'moderate',
        'budget' => 'moderate', 'price' => 130,
        'thumb' => null
    ],
    [
        'name' => 'Tabla Lounge & Karaoke',
        'type' => 'Karaoke',
        'address' => '5 El Sad El Aaly St, Dokki',
        'lat' => 30.0364, 'lng' => 31.2120,
        'rating' => 4.0, 'popularity' => 'niche',
        'budget' => 'moderate', 'price' => 160,
        'thumb' => null
    ],
    [
        'name' => 'Mall of Egypt',
        'type' => 'Shopping Mall',
        'address' => '6th of October City',
        'lat' => 29.9723, 'lng' => 31.0144,
        'rating' => 4.5, 'popularity' => 'mainstream',
        'budget' => 'moderate', 'price' => 200,
        'thumb' => null
    ],
    [
        'name' => 'Ananda Spa — Four Seasons',
        'type' => 'Spa/Wellness',
        'address' => 'Four Seasons Nile Plaza, Garden City',
        'lat' => 30.0381, 'lng' => 31.2291,
        'rating' => 4.8, 'popularity' => 'niche',
        'budget' => 'luxury', 'price' => 900,
        'thumb' => null
    ],
    [
        'name' => 'Koshary Abou Tarek',
        'type' => 'Restaurant',
        'address' => '16 Maarouf St, Downtown Cairo',
        'lat' => 30.0501, 'lng' => 31.2432,
        'rating' => 4.2, 'popularity' => 'mainstream',
        'budget' => 'budget', 'price' => 45,
        'thumb' => null
    ],
    [
        'name' => 'The Grand Egyptian Museum',
        'type' => 'Museum',
        'address' => 'Al Remaya Square, Giza',
        'lat' => 29.9950, 'lng' => 31.1170,
        'rating' => 4.9, 'popularity' => 'mainstream',
        'budget' => 'moderate', 'price' => 200,
        'thumb' => null
    ],
];

$stmt = $pdo->prepare('
    INSERT INTO places (name, location_type_id, address, latitude, longitude, rating, popularity, budget_tier, price_per_person_egp, thumbnail_url)
    VALUES (:name, :type_id, :address, :lat, :lng, :rating, :popularity, :budget, :price, :thumb)
    ON CONFLICT DO NOTHING
');

foreach ($places as $p) {
    $typeId = $typeMap[$p['type']] ?? null;
    $stmt->execute([
        'name' => $p['name'],
        'type_id' => $typeId,
        'address' => $p['address'],
        'lat' => $p['lat'],
        'lng' => $p['lng'],
        'rating' => $p['rating'],
        'popularity' => $p['popularity'],
        'budget' => $p['budget'],
        'price' => $p['price'],
        'thumb' => $p['thumb']
    ]);
    echo "  + {$p['name']}\n";
}
echo "✅ " . count($places) . " sample places seeded\n";

echo "\n\n=============================\n";
echo "✅ SETUP COMPLETE!\n";
echo "=============================\n";
echo "You can now delete this file.\n";
echo '</pre>';
