<?php
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/../config/db.php';

$db = Database::getInstance();

echo "Starting DB Seeder...\n";

// 1. Insert Categories
$categories = [
    ['name' => 'Restoran', 'slug' => 'restoran', 'icon' => 'fa-utensils'],
    ['name' => 'Kafe & Tatlı', 'slug' => 'kafe-tatli', 'icon' => 'fa-mug-hot'],
    ['name' => 'Güzellik Salonu', 'slug' => 'guzellik-salonu', 'icon' => 'fa-scissors'],
    ['name' => 'Market', 'slug' => 'market', 'icon' => 'fa-store']
];

foreach ($categories as $cat) {
    try {
        $db->execute("INSERT IGNORE INTO categories (name, slug, icon) VALUES (?, ?, ?)", [$cat['name'], $cat['slug'], $cat['icon']]);
        echo "Category '{$cat['name']}' added.\n";
    } catch(Exception $e) {}
}

// 2. Insert Users
$users = [
    ['name' => 'Ahmet Yılmaz', 'email' => 'ahmet@test.com', 'password' => password_hash('password123', PASSWORD_DEFAULT)],
    ['name' => 'Ayşe Demir', 'email' => 'ayse@test.com', 'password' => password_hash('password123', PASSWORD_DEFAULT)],
    ['name' => 'Mehmet Can', 'email' => 'mehmet@test.com', 'password' => password_hash('password123', PASSWORD_DEFAULT)]
];

$userIds = [];
foreach ($users as $user) {
    try {
        $db->execute("INSERT IGNORE INTO users (name, email, password, role) VALUES (?, ?, ?, 'user')", [$user['name'], $user['email'], $user['password']]);
        $u = $db->fetchOne("SELECT id FROM users WHERE email = ?", [$user['email']]);
        if ($u) {
            $userIds[] = $u['id'];
        }
        echo "User '{$user['name']}' added.\n";
    } catch(Exception $e) { echo $e->getMessage(); }
}

// 3. Insert Businesses
$catRest = $db->fetchOne("SELECT id FROM categories WHERE slug = 'restoran'")['id'];
$catKafe = $db->fetchOne("SELECT id FROM categories WHERE slug = 'kafe-tatli'")['id'];
$catGuzel = $db->fetchOne("SELECT id FROM categories WHERE slug = 'guzellik-salonu'")['id'];

$businesses = [
    ['name' => 'Has Döner Hatay', 'slug' => 'has-doner-hatay', 'category_id' => $catRest, 'district' => 'Antakya', 'description' => 'Odun ateşinde pişen efsane Hatay döneri.', 'phone' => '05321112233', 'is_premium' => 1],
    ['name' => 'Künefeci Ali Usta', 'slug' => 'kunefeci-ali-usta', 'category_id' => $catKafe, 'district' => 'İskenderun', 'description' => 'Tarihi Antakya künefesi, közde pişmiş haliyle.', 'phone' => '05554443322', 'is_premium' => 1],
    ['name' => 'Sıla Güzellik', 'slug' => 'sila-guzellik', 'category_id' => $catGuzel, 'district' => 'Defne', 'description' => 'Profesyonel cilt bakımı, lazer epilasyon ve saç tasarımı.', 'phone' => '05059998877', 'is_premium' => 0],
    ['name' => 'Tepsi Kebabı Center', 'slug' => 'tepsi-kebabi-center', 'category_id' => $catRest, 'district' => 'Antakya', 'description' => 'Kasaptan taze etlerle hazırlanan muhteşem tepsi ve kağıt kebabı.', 'phone' => '05330001122', 'is_premium' => 0]
];

$busIds = [];
foreach ($businesses as $bus) {
    try {
        $db->execute("INSERT IGNORE INTO businesses (name, slug, category_id, city, district, description, phone, is_premium) VALUES (?, ?, ?, 'Hatay', ?, ?, ?, ?)", 
        [$bus['name'], $bus['slug'], $bus['category_id'], $bus['district'], $bus['description'], $bus['phone'], $bus['is_premium']]);
        
        $b = $db->fetchOne("SELECT id FROM businesses WHERE slug = ?", [$bus['slug']]);
        if ($b) {
            $busIds[] = $b['id'];
        }
        echo "Business '{$bus['name']}' added.\n";
    } catch(Exception $e) {}
}

// 4. Insert Reviews
$reviews = [
    ['rating' => 5, 'comment' => 'Harika bir lezzet, mutlaka tavsiye ederim! Ailemle geldik çok memnun kaldık.'],
    ['rating' => 4, 'comment' => 'Güzeldi ama servis biraz yavaştı. Yine de lezzetli.'],
    ['rating' => 5, 'comment' => 'Kesinlikle bölgedeki en iyisi. Fiyatlar da gayet makul.'],
    ['rating' => 3, 'comment' => 'Ortalama bir deneyimdi. Beklentimi tam karşılamadı.'],
    ['rating' => 5, 'comment' => 'Temiz, güvenilir ve işini iyi yapan bir yer.']
];

$reviewModel = new Review();
$favoriteModel = new Favorite();

foreach ($busIds as $b_id) {
    // Add 2-3 random reviews for each business
    $randNum = rand(2, 3);
    for($i=0; $i<$randNum; $i++) {
        $u_id = $userIds[array_rand($userIds)];
        $rev = $reviews[array_rand($reviews)];
        
        try {
            $db->execute("INSERT INTO reviews (business_id, user_id, rating, comment, status) VALUES (?, ?, ?, ?, 'approved')", [$b_id, $u_id, $rev['rating'], $rev['comment']]);
            echo "Review added for Business ID {$b_id} by User ID {$u_id}.\n";
        } catch(Exception $e) {}
    }
    
    // Add 1-2 favorites
    $favRand = rand(1, 2);
    for($i=0; $i<$favRand; $i++) {
        $u_id = $userIds[array_rand($userIds)];
        try {
            $db->execute("INSERT IGNORE INTO user_favorites (user_id, business_id) VALUES (?, ?)", [$u_id, $b_id]);
            echo "Favorite added for Business ID {$b_id} by User ID {$u_id}.\n";
        } catch(Exception $e) {}
    }
    
    // Update average rating
    require_once __DIR__ . '/../models/Business.php';
    (new Business())->updateAverageRating($b_id);
}

echo "\nDemo Data Seeded Successfully!\n";
echo "Test Accounts:\n";
echo "Email: ahmet@test.com / Password: password123\n";
echo "Email: ayse@test.com / Password: password123\n";
