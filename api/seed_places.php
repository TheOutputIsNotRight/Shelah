<?php
/**
 * Shelah — Comprehensive Places Seeder
 * 
 * Run this script to seed the database with realistic places in Cairo & Giza.
 * Updates existing places by name or inserts them if they don't exist.
 */

header('Content-Type: text/html; charset=utf-8');
echo '<h1>Shelah — Seeding Places</h1><pre>';

$dbUrl = getenv('DATABASE_URL');
if (!$dbUrl) {
    // Try to load from .env if running locally
    $envPath = __DIR__ . '/../.env';
    if (file_exists($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            if ($name === 'DATABASE_URL') {
                $dbUrl = trim($value, '"\'');
                break;
            }
        }
    }
}

if (!$dbUrl) {
    die('ERROR: DATABASE_URL environment variable not set.');
}

$parsedUrl = parse_url($dbUrl);
$host = $parsedUrl['host'] ?? '';
$port = $parsedUrl['port'] ?? 5432;
$user = $parsedUrl['user'] ?? '';
$pass = $parsedUrl['pass'] ?? '';
$db = ltrim($parsedUrl['path'] ?? '', '/');

$dsn = "pgsql:host={$host};port={$port};dbname={$db};sslmode=require";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    echo "✅ Database connected\n";
} catch (PDOException $e) {
    die('ERROR: ' . $e->getMessage());
}

// Ensure location types are seeded first
echo "\n--- Verifying location types ---\n";
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

$stmtType = $pdo->prepare('INSERT INTO location_types (name, icon) VALUES (:name, :icon) ON CONFLICT DO NOTHING');
foreach ($types as $t) {
    $stmtType->execute(['name' => $t[0], 'icon' => $t[1]]);
}

$typeMap = [];
$rows = $pdo->query('SELECT id, name FROM location_types')->fetchAll();
foreach ($rows as $r) {
    $typeMap[$r['name']] = $r['id'];
}
echo "✅ Location types mapped\n";

// Comprehensive Places Data
$places = [
    // Coffee Shops
    ['name' => 'Espresso Lab — Point 90', 'type' => 'Coffee Shop', 'address' => 'Point 90 Mall, New Cairo', 'lat' => 30.0244, 'lng' => 31.4820, 'rating' => 4.5, 'pop' => 'mainstream', 'budget' => 'moderate', 'price' => 150, 'thumb' => 'https://images.unsplash.com/photo-1554118811-1e0d58224f24?auto=format&fit=crop&w=800&q=80'],
    ['name' => 'Holm Cafe', 'type' => 'Coffee Shop', 'address' => 'Zamalek, Cairo', 'lat' => 30.0632, 'lng' => 31.2201, 'rating' => 4.7, 'pop' => 'niche', 'budget' => 'moderate', 'price' => 200, 'thumb' => 'https://images.unsplash.com/photo-1497935586351-b67a49e012bf?auto=format&fit=crop&w=800&q=80'],
    ['name' => 'Seven Fortunes', 'type' => 'Coffee Shop', 'address' => 'Sheikh Zayed City', 'lat' => 30.0263, 'lng' => 30.9850, 'rating' => 4.6, 'pop' => 'mainstream', 'budget' => 'upscale', 'price' => 250, 'thumb' => 'https://images.unsplash.com/photo-1509042239860-f550ce710b93?auto=format&fit=crop&w=800&q=80'],
    ['name' => 'Brown Nose Coffee', 'type' => 'Coffee Shop', 'address' => 'Road 9, Maadi', 'lat' => 29.9575, 'lng' => 31.2660, 'rating' => 4.8, 'pop' => 'niche', 'budget' => 'moderate', 'price' => 180, 'thumb' => 'https://images.unsplash.com/photo-1511920170033-f8396924c348?auto=format&fit=crop&w=800&q=80'],

    // Restaurants
    ['name' => 'Andrea El Mariouteya', 'type' => 'Restaurant', 'address' => 'New Giza', 'lat' => 30.0280, 'lng' => 31.0601, 'rating' => 4.4, 'pop' => 'mainstream', 'budget' => 'upscale', 'price' => 400, 'thumb' => 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?auto=format&fit=crop&w=800&q=80'],
    ['name' => 'Kazoku', 'type' => 'Restaurant', 'address' => 'Swan Lake Compound, New Cairo', 'lat' => 30.0465, 'lng' => 31.4250, 'rating' => 4.8, 'pop' => 'mainstream', 'budget' => 'luxury', 'price' => 1200, 'thumb' => 'https://images.unsplash.com/photo-1550966871-3ed3cdb5ed0c?auto=format&fit=crop&w=800&q=80'],
    ['name' => 'Pier 88', 'type' => 'Restaurant', 'address' => 'Imperial Boat, Zamalek', 'lat' => 30.0592, 'lng' => 31.2223, 'rating' => 4.5, 'pop' => 'mainstream', 'budget' => 'luxury', 'price' => 1500, 'thumb' => 'https://images.unsplash.com/photo-1544148103-0773bf10d330?auto=format&fit=crop&w=800&q=80'],
    ['name' => 'Koshary El Tahrir', 'type' => 'Restaurant', 'address' => 'Downtown Cairo', 'lat' => 30.0478, 'lng' => 31.2384, 'rating' => 4.2, 'pop' => 'mainstream', 'budget' => 'budget', 'price' => 50, 'thumb' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/c/cd/Koshary_%28Egyptian_dish%29.jpg/800px-Koshary_%28Egyptian_dish%29.jpg'],
    ['name' => 'Zooba', 'type' => 'Restaurant', 'address' => '26th of July Corridor, Zamalek', 'lat' => 30.0610, 'lng' => 31.2215, 'rating' => 4.6, 'pop' => 'mainstream', 'budget' => 'moderate', 'price' => 200, 'thumb' => 'https://images.unsplash.com/photo-1504674900247-0877df9cc836?auto=format&fit=crop&w=800&q=80'],

    // Museums
    ['name' => 'National Museum of Egyptian Civilization', 'type' => 'Museum', 'address' => 'Fustat, Cairo', 'lat' => 30.0084, 'lng' => 31.2482, 'rating' => 4.9, 'pop' => 'mainstream', 'budget' => 'moderate', 'price' => 250, 'thumb' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/e/e0/National_Museum_of_Egyptian_Civilization_NMEC_3.jpg/800px-National_Museum_of_Egyptian_Civilization_NMEC_3.jpg'],
    ['name' => 'The Egyptian Museum', 'type' => 'Museum', 'address' => 'Tahrir Square, Cairo', 'lat' => 30.0478, 'lng' => 31.2336, 'rating' => 4.6, 'pop' => 'mainstream', 'budget' => 'moderate', 'price' => 200, 'thumb' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/e/e7/Cairo_Eg_Museum.jpg/800px-Cairo_Eg_Museum.jpg'],
    ['name' => 'The Grand Egyptian Museum', 'type' => 'Museum', 'address' => 'Al Remaya Square, Giza', 'lat' => 29.9950, 'lng' => 31.1170, 'rating' => 4.9, 'pop' => 'mainstream', 'budget' => 'upscale', 'price' => 350, 'thumb' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/9/90/Grand_Egyptian_Museum.jpg/800px-Grand_Egyptian_Museum.jpg'],
    ['name' => 'Museum of Islamic Art', 'type' => 'Museum', 'address' => 'Bab El-Khalk, Cairo', 'lat' => 30.0422, 'lng' => 31.2529, 'rating' => 4.8, 'pop' => 'moderate', 'budget' => 'budget', 'price' => 120, 'thumb' => 'https://images.unsplash.com/photo-1566127444941-8e124ffa823a?auto=format&fit=crop&w=800&q=80'],

    // Arcade/Games
    ['name' => 'Magic Planet', 'type' => 'Arcade/Games', 'address' => 'Mall of Egypt, 6th of October', 'lat' => 29.9723, 'lng' => 31.0144, 'rating' => 4.5, 'pop' => 'mainstream', 'budget' => 'moderate', 'price' => 300, 'thumb' => 'https://images.unsplash.com/photo-1552820728-8b83bb6b773f?auto=format&fit=crop&w=800&q=80'],
    ['name' => 'Xtreme Land', 'type' => 'Arcade/Games', 'address' => 'Mall of Arabia, 6th of October', 'lat' => 30.0068, 'lng' => 30.9739, 'rating' => 4.3, 'pop' => 'mainstream', 'budget' => 'moderate', 'price' => 250, 'thumb' => 'https://images.unsplash.com/photo-1518929468119-e5bf444c30f4?auto=format&fit=crop&w=800&q=80'],
    ['name' => 'Player One Gaming Lounge', 'type' => 'Arcade/Games', 'address' => 'City Stars Mall, Nasr City', 'lat' => 30.0722, 'lng' => 31.3456, 'rating' => 4.1, 'pop' => 'moderate', 'budget' => 'upscale', 'price' => 400, 'thumb' => 'https://images.unsplash.com/photo-1542751371-adc38448a05e?auto=format&fit=crop&w=800&q=80'],

    // Rooftop Bars
    ['name' => 'Crimson', 'type' => 'Rooftop Bar', 'address' => 'Zamalek, Cairo', 'lat' => 30.0645, 'lng' => 31.2230, 'rating' => 4.7, 'pop' => 'mainstream', 'budget' => 'luxury', 'price' => 1000, 'thumb' => 'https://images.unsplash.com/photo-1572116469696-31de0f17cc34?auto=format&fit=crop&w=800&q=80'],
    ['name' => 'The Roof Top', 'type' => 'Rooftop Bar', 'address' => 'Kempinski Nile Hotel, Garden City', 'lat' => 30.0381, 'lng' => 31.2291, 'rating' => 4.6, 'pop' => 'mainstream', 'budget' => 'luxury', 'price' => 1200, 'thumb' => 'https://images.unsplash.com/photo-1514933651103-005eec06c04b?auto=format&fit=crop&w=800&q=80'],
    ['name' => 'Estro', 'type' => 'Rooftop Bar', 'address' => 'Royal Maadi Hotel, Maadi', 'lat' => 29.9600, 'lng' => 31.2630, 'rating' => 4.8, 'pop' => 'niche', 'budget' => 'upscale', 'price' => 800, 'thumb' => 'https://images.unsplash.com/photo-1525268771113-32d9e9021a97?auto=format&fit=crop&w=800&q=80'],

    // Cinema
    ['name' => 'VOX Cinemas', 'type' => 'Cinema', 'address' => 'Mall of Egypt, 6th of October', 'lat' => 29.9723, 'lng' => 31.0144, 'rating' => 4.8, 'pop' => 'mainstream', 'budget' => 'upscale', 'price' => 250, 'thumb' => 'https://images.unsplash.com/photo-1517604931442-7e0c8ed2963c?auto=format&fit=crop&w=800&q=80'],
    ['name' => 'IMAX Americana Plaza', 'type' => 'Cinema', 'address' => 'Americana Plaza, Sheikh Zayed', 'lat' => 30.0365, 'lng' => 30.9851, 'rating' => 4.7, 'pop' => 'mainstream', 'budget' => 'upscale', 'price' => 220, 'thumb' => 'https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?auto=format&fit=crop&w=800&q=80'],
    ['name' => 'Zawya Cinema', 'type' => 'Cinema', 'address' => 'Downtown Cairo', 'lat' => 30.0505, 'lng' => 31.2400, 'rating' => 4.9, 'pop' => 'niche', 'budget' => 'budget', 'price' => 80, 'thumb' => 'https://images.unsplash.com/photo-1478720568477-152d9b164e26?auto=format&fit=crop&w=800&q=80'],

    // Escape Rooms
    ['name' => 'Escapology', 'type' => 'Escape Room', 'address' => 'Sheikh Zayed City', 'lat' => 30.0260, 'lng' => 30.9855, 'rating' => 4.8, 'pop' => 'moderate', 'budget' => 'upscale', 'price' => 350, 'thumb' => 'https://images.unsplash.com/photo-1605806616949-1e87b487cb2a?auto=format&fit=crop&w=800&q=80'],
    ['name' => 'The Room', 'type' => 'Escape Room', 'address' => 'New Cairo', 'lat' => 30.0245, 'lng' => 31.4825, 'rating' => 4.6, 'pop' => 'mainstream', 'budget' => 'moderate', 'price' => 300, 'thumb' => 'https://images.unsplash.com/photo-1519074069444-1ba4fff66d16?auto=format&fit=crop&w=800&q=80'],
    ['name' => 'Trapped', 'type' => 'Escape Room', 'address' => 'Maadi, Cairo', 'lat' => 29.9570, 'lng' => 31.2655, 'rating' => 4.5, 'pop' => 'niche', 'budget' => 'moderate', 'price' => 250, 'thumb' => 'https://images.unsplash.com/photo-1505663912202-ac22d4cb3707?auto=format&fit=crop&w=800&q=80'],

    // Art Gallery
    ['name' => 'Picasso Art Gallery', 'type' => 'Art Gallery', 'address' => 'Zamalek, Cairo', 'lat' => 30.0630, 'lng' => 31.2210, 'rating' => 4.7, 'pop' => 'niche', 'budget' => 'budget', 'price' => 0, 'thumb' => 'https://images.unsplash.com/photo-1531259683007-016a7b628fc3?auto=format&fit=crop&w=800&q=80'],
    ['name' => 'Zamalek Art Gallery', 'type' => 'Art Gallery', 'address' => 'Zamalek, Cairo', 'lat' => 30.0640, 'lng' => 31.2220, 'rating' => 4.8, 'pop' => 'moderate', 'budget' => 'budget', 'price' => 0, 'thumb' => 'https://images.unsplash.com/photo-1543857778-c4a1a3e0b2eb?auto=format&fit=crop&w=800&q=80'],
    ['name' => 'Townhouse Gallery', 'type' => 'Art Gallery', 'address' => 'Downtown Cairo', 'lat' => 30.0485, 'lng' => 31.2405, 'rating' => 4.6, 'pop' => 'niche', 'budget' => 'budget', 'price' => 0, 'thumb' => 'https://images.unsplash.com/photo-1561053720-76cd73ff22c3?auto=format&fit=crop&w=800&q=80'],

    // Bookshop Café
    ['name' => 'Diwan Bookstore', 'type' => 'Bookshop Café', 'address' => 'Zamalek, Cairo', 'lat' => 30.0600, 'lng' => 31.2210, 'rating' => 4.8, 'pop' => 'mainstream', 'budget' => 'moderate', 'price' => 150, 'thumb' => 'https://images.unsplash.com/photo-1481627834876-b7833e8f5570?auto=format&fit=crop&w=800&q=80'],
    ['name' => 'Sufi Bookstore', 'type' => 'Bookshop Café', 'address' => 'Zamalek, Cairo', 'lat' => 30.0620, 'lng' => 31.2205, 'rating' => 4.7, 'pop' => 'niche', 'budget' => 'budget', 'price' => 100, 'thumb' => 'https://images.unsplash.com/photo-1507842217343-583bb7270b66?auto=format&fit=crop&w=800&q=80'],

    // Park/Outdoor
    ['name' => 'Al-Azhar Park', 'type' => 'Park/Outdoor', 'address' => 'El-Darb El-Ahmar, Cairo', 'lat' => 30.0389, 'lng' => 31.2624, 'rating' => 4.8, 'pop' => 'mainstream', 'budget' => 'budget', 'price' => 40, 'thumb' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/1/1b/Al-Azhar_Park_Cairo.jpg/800px-Al-Azhar_Park_Cairo.jpg'],
    ['name' => 'Family Park', 'type' => 'Park/Outdoor', 'address' => 'New Cairo', 'lat' => 30.0700, 'lng' => 31.5000, 'rating' => 4.5, 'pop' => 'mainstream', 'budget' => 'moderate', 'price' => 100, 'thumb' => 'https://images.unsplash.com/photo-1542273917363-3b1817f69a2d?auto=format&fit=crop&w=800&q=80'],
    ['name' => 'Zed Park', 'type' => 'Park/Outdoor', 'address' => 'Sheikh Zayed City', 'lat' => 30.0450, 'lng' => 30.9950, 'rating' => 4.6, 'pop' => 'mainstream', 'budget' => 'upscale', 'price' => 250, 'thumb' => 'https://images.unsplash.com/photo-1574069695028-2ce85c6db20d?auto=format&fit=crop&w=800&q=80'],

    // Bowling
    ['name' => 'Bandar Bowling', 'type' => 'Bowling', 'address' => 'Maadi, Cairo', 'lat' => 29.9650, 'lng' => 31.2750, 'rating' => 4.4, 'pop' => 'moderate', 'budget' => 'moderate', 'price' => 150, 'thumb' => 'https://images.unsplash.com/photo-1589801383040-7e1caad5ba9a?auto=format&fit=crop&w=800&q=80'],
    ['name' => 'International Bowling Center', 'type' => 'Bowling', 'address' => 'Nasr City, Cairo', 'lat' => 30.0650, 'lng' => 31.3300, 'rating' => 4.2, 'pop' => 'mainstream', 'budget' => 'budget', 'price' => 100, 'thumb' => 'https://images.unsplash.com/photo-1511516080277-3e449b4c06f0?auto=format&fit=crop&w=800&q=80'],

    // Karaoke
    ['name' => 'Room Art Space & Cafe', 'type' => 'Karaoke', 'address' => 'Garden City, Cairo', 'lat' => 30.0350, 'lng' => 31.2300, 'rating' => 4.7, 'pop' => 'niche', 'budget' => 'moderate', 'price' => 200, 'thumb' => 'https://images.unsplash.com/photo-1516280440502-09dc39f28688?auto=format&fit=crop&w=800&q=80'],
    ['name' => 'Ten Eleven', 'type' => 'Karaoke', 'address' => 'Zamalek, Cairo', 'lat' => 30.0615, 'lng' => 31.2225, 'rating' => 4.5, 'pop' => 'moderate', 'budget' => 'upscale', 'price' => 300, 'thumb' => 'https://images.unsplash.com/photo-1522869635100-9f4c5e86aa37?auto=format&fit=crop&w=800&q=80'],

    // Shopping Mall
    ['name' => 'Cairo Festival City Mall', 'type' => 'Shopping Mall', 'address' => 'New Cairo', 'lat' => 30.0290, 'lng' => 31.4050, 'rating' => 4.8, 'pop' => 'mainstream', 'budget' => 'moderate', 'price' => 0, 'thumb' => 'https://images.unsplash.com/photo-1519999482648-25049ddd37b1?auto=format&fit=crop&w=800&q=80'],
    ['name' => 'Mall of Arabia', 'type' => 'Shopping Mall', 'address' => '6th of October', 'lat' => 30.0068, 'lng' => 30.9739, 'rating' => 4.7, 'pop' => 'mainstream', 'budget' => 'moderate', 'price' => 0, 'thumb' => 'https://images.unsplash.com/photo-1567359781514-3b964e2b04d6?auto=format&fit=crop&w=800&q=80'],

    // Spa/Wellness
    ['name' => 'The Spa at Four Seasons', 'type' => 'Spa/Wellness', 'address' => 'Nile Plaza, Garden City', 'lat' => 30.0381, 'lng' => 31.2291, 'rating' => 4.9, 'pop' => 'mainstream', 'budget' => 'luxury', 'price' => 2000, 'thumb' => 'https://images.unsplash.com/photo-1544161515-4ab6ce6db874?auto=format&fit=crop&w=800&q=80'],
    ['name' => 'Mandara Spa', 'type' => 'Spa/Wellness', 'address' => 'JW Marriott, New Cairo', 'lat' => 30.0705, 'lng' => 31.4150, 'rating' => 4.8, 'pop' => 'mainstream', 'budget' => 'luxury', 'price' => 2500, 'thumb' => 'https://images.unsplash.com/photo-1515377905703-c4788e51af15?auto=format&fit=crop&w=800&q=80'],

    // Live Music Venue
    ['name' => 'Cairo Jazz Club', 'type' => 'Live Music Venue', 'address' => 'Agouza, Cairo', 'lat' => 30.0553, 'lng' => 31.2085, 'rating' => 4.7, 'pop' => 'mainstream', 'budget' => 'upscale', 'price' => 500, 'thumb' => 'https://images.unsplash.com/photo-1516450360452-9312f5e86fc7?auto=format&fit=crop&w=800&q=80'],
    ['name' => 'El Sawy Culturewheel', 'type' => 'Live Music Venue', 'address' => 'Zamalek, Cairo', 'lat' => 30.0655, 'lng' => 31.2180, 'rating' => 4.5, 'pop' => 'mainstream', 'budget' => 'budget', 'price' => 150, 'thumb' => 'https://images.unsplash.com/photo-1459749411175-04bf5292ceea?auto=format&fit=crop&w=800&q=80'],
    ['name' => 'The Tap West', 'type' => 'Live Music Venue', 'address' => 'Sheikh Zayed City', 'lat' => 30.0270, 'lng' => 30.9860, 'rating' => 4.6, 'pop' => 'mainstream', 'budget' => 'upscale', 'price' => 450, 'thumb' => 'https://images.unsplash.com/photo-1526478806334-5fd488fcaabc?auto=format&fit=crop&w=800&q=80']
];

echo "\n--- Seeding ".count($places)." sample Cairo/Giza places ---\n";

$checkStmt = $pdo->prepare('SELECT id FROM places WHERE name = :name');
$updateStmt = $pdo->prepare('
    UPDATE places SET 
        location_type_id = :type_id, address = :address, latitude = :lat, longitude = :lng, 
        rating = :rating, popularity = :popularity, budget_tier = :budget, 
        price_per_person_egp = :price, thumbnail_url = :thumb
    WHERE id = :id
');
$insertStmt = $pdo->prepare('
    INSERT INTO places (name, location_type_id, address, latitude, longitude, rating, popularity, budget_tier, price_per_person_egp, thumbnail_url)
    VALUES (:name, :type_id, :address, :lat, :lng, :rating, :popularity, :budget, :price, :thumb)
');

$inserted = 0;
$updated = 0;

foreach ($places as $p) {
    $typeId = $typeMap[$p['type']] ?? null;
    
    $checkStmt->execute(['name' => $p['name']]);
    $existing = $checkStmt->fetch();

    $params = [
        'type_id' => $typeId,
        'address' => $p['address'],
        'lat' => $p['lat'],
        'lng' => $p['lng'],
        'rating' => $p['rating'],
        'popularity' => $p['pop'],
        'budget' => $p['budget'],
        'price' => $p['price'],
        'thumb' => $p['thumb']
    ];

    if ($existing) {
        $params['id'] = $existing['id'];
        $updateStmt->execute($params);
        $updated++;
        echo "  ~ Updated: {$p['name']}\n";
    } else {
        $params['name'] = $p['name'];
        $insertStmt->execute($params);
        $inserted++;
        echo "  + Inserted: {$p['name']}\n";
    }
}

echo "✅ Seeding complete! $inserted inserted, $updated updated.\n";

echo "\n\n=============================\n";
echo "✅ PLACES SEED COMPLETE!\n";
echo "=============================\n";
echo '</pre>';
