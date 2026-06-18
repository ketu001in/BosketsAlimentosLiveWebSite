<?php
/**
 * Bosket's Alimentos — one-time installer.
 *
 * 1. Create a MySQL database + user in Hostinger hPanel and put the
 *    credentials in config.php.
 * 2. Upload all files, then open  https://yourdomain.com/install.php
 * 3. Fill in your admin account, click Install.
 * 4. DELETE THIS FILE from the server when done.
 */

require_once __DIR__ . '/config.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');

function pdo_connect(): PDO
{
    return new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
}

$messages = [];
$fatal = null;
$installed = false;

try {
    $pdo = pdo_connect();
} catch (PDOException $ex) {
    $fatal = 'Could not connect to the database. Check DB_HOST / DB_NAME / DB_USER / DB_PASS in config.php. '
           . 'Details: ' . $ex->getMessage();
}

$alreadyInstalled = false;
if (!$fatal) {
    $alreadyInstalled = (bool)$pdo->query("SHOW TABLES LIKE 'users'")->fetchColumn();
}

// ================================================================ helpers

/** Write a pretty gradient SVG placeholder image for demo recipes. */
function make_svg(string $relPath, string $emoji, string $title, string $c1, string $c2): void
{
    $abs = __DIR__ . '/' . $relPath;
    $dir = dirname($abs);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $t = htmlspecialchars($title, ENT_QUOTES);
    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 500">
  <defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
    <stop offset="0" stop-color="$c1"/><stop offset="1" stop-color="$c2"/>
  </linearGradient></defs>
  <rect width="800" height="500" fill="url(#g)"/>
  <circle cx="680" cy="80" r="140" fill="rgba(255,255,255,.10)"/>
  <circle cx="90" cy="430" r="110" fill="rgba(255,255,255,.08)"/>
  <text x="400" y="250" font-size="150" text-anchor="middle" dominant-baseline="middle">$emoji</text>
  <text x="400" y="420" font-size="34" text-anchor="middle" fill="rgba(255,255,255,.92)"
        font-family="Georgia, serif" font-weight="bold">$t</text>
</svg>
SVG;
    file_put_contents($abs, $svg);
}

function block_dir(string $dir): void
{
    if (!is_dir(__DIR__ . '/' . $dir)) mkdir(__DIR__ . '/' . $dir, 0755, true);
    // stop directory listing on hosts that ignore .htaccess Options
    file_put_contents(__DIR__ . '/' . $dir . '/index.html', '');
}

// ================================================================ install action

if (!$fatal && $_SERVER['REQUEST_METHOD'] === 'POST' && !$alreadyInstalled) {
    $adminUser  = trim($_POST['admin_user'] ?? 'admin');
    $adminEmail = trim($_POST['admin_email'] ?? '');
    $adminPass  = $_POST['admin_pass'] ?? '';
    $withDemo   = !empty($_POST['demo']);

    if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $adminUser)) {
        $fatal = 'Admin username must be 3–30 characters (letters, numbers, underscores).';
    } elseif (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        $fatal = 'Please enter a valid admin email.';
    } elseif (strlen($adminPass) < 8) {
        $fatal = 'Admin password must be at least 8 characters.';
    }

    if (!$fatal) {
        // -------------------------------------------------- schema
        $pdo->exec('SET NAMES utf8mb4');
        $schema = [
            "CREATE TABLE users (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(30) NOT NULL UNIQUE,
                email VARCHAR(190) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                display_name VARCHAR(60) NOT NULL,
                bio TEXT NULL,
                avatar VARCHAR(255) NULL,
                is_admin TINYINT(1) NOT NULL DEFAULT 0,
                is_banned TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE buddies (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                requester_id INT UNSIGNED NOT NULL,
                addressee_id INT UNSIGNED NOT NULL,
                status ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
                created_at DATETIME NOT NULL,
                responded_at DATETIME NULL,
                UNIQUE KEY uq_pair (requester_id, addressee_id),
                KEY idx_addressee (addressee_id, status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE ingredients (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL UNIQUE,
                created_by INT UNSIGNED NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE categories (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL UNIQUE,
                created_by INT UNSIGNED NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE cuisines (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL UNIQUE,
                created_by INT UNSIGNED NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE origins (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL UNIQUE,
                created_by INT UNSIGNED NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE recipes (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                title VARCHAR(150) NOT NULL,
                slug VARCHAR(170) NOT NULL UNIQUE,
                image VARCHAR(255) NULL,
                story TEXT NULL,
                youtube_url VARCHAR(255) NULL,
                category_id INT UNSIGNED NULL,
                cuisine_id INT UNSIGNED NULL,
                origin_id INT UNSIGNED NULL,
                verdict TEXT NULL,
                status ENUM('published','removed') NOT NULL DEFAULT 'published',
                is_featured TINYINT(1) NOT NULL DEFAULT 0,
                views INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                KEY idx_user (user_id),
                KEY idx_status_created (status, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE recipe_ingredients (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                recipe_id INT UNSIGNED NOT NULL,
                ingredient_id INT UNSIGNED NOT NULL,
                quantity VARCHAR(80) NULL,
                sort_order INT NOT NULL DEFAULT 0,
                KEY idx_recipe (recipe_id),
                KEY idx_ingredient (ingredient_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE recipe_steps (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                recipe_id INT UNSIGNED NOT NULL,
                step_no INT NOT NULL,
                instruction TEXT NOT NULL,
                media VARCHAR(255) NULL,
                media_type ENUM('image','video') NULL,
                KEY idx_recipe (recipe_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE wall_posts (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                body TEXT NULL,
                image VARCHAR(255) NULL,
                shared_recipe_id INT UNSIGNED NULL,
                status ENUM('visible','removed') NOT NULL DEFAULT 'visible',
                created_at DATETIME NOT NULL,
                KEY idx_user (user_id, status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE forum_categories (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL UNIQUE,
                description VARCHAR(200) NULL,
                created_by INT UNSIGNED NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE forum_topics (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                category_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                title VARCHAR(180) NOT NULL,
                body TEXT NOT NULL,
                views INT UNSIGNED NOT NULL DEFAULT 0,
                status ENUM('visible','removed') NOT NULL DEFAULT 'visible',
                created_at DATETIME NOT NULL,
                KEY idx_cat (category_id, status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE comments (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                target_type ENUM('recipe','wall','topic') NOT NULL,
                target_id INT UNSIGNED NOT NULL,
                body TEXT NOT NULL,
                status ENUM('visible','removed') NOT NULL DEFAULT 'visible',
                created_at DATETIME NOT NULL,
                KEY idx_target (target_type, target_id, status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE reactions (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                target_type ENUM('recipe','wall','topic','comment') NOT NULL,
                target_id INT UNSIGNED NOT NULL,
                reaction ENUM('like','love','yum','wow') NOT NULL,
                created_at DATETIME NOT NULL,
                UNIQUE KEY uq_user_target (user_id, target_type, target_id),
                KEY idx_target (target_type, target_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE notifications (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                actor_id INT UNSIGNED NULL,
                type VARCHAR(30) NOT NULL,
                target_type VARCHAR(20) NULL,
                target_id INT UNSIGNED NULL,
                message VARCHAR(255) NOT NULL,
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                KEY idx_user_read (user_id, is_read)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE password_resets (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                token_hash CHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                used_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                KEY idx_user (user_id),
                KEY idx_token (token_hash)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE contact_messages (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NULL,
                name VARCHAR(80) NOT NULL,
                email VARCHAR(190) NOT NULL,
                subject VARCHAR(150) NOT NULL,
                message TEXT NOT NULL,
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                KEY idx_read (is_read)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE messages (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                sender_id INT UNSIGNED NOT NULL,
                recipient_id INT UNSIGNED NOT NULL,
                body TEXT NOT NULL,
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                KEY idx_pair (sender_id, recipient_id, id),
                KEY idx_inbox (recipient_id, is_read)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE oauth_accounts (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                provider VARCHAR(20) NOT NULL,
                provider_uid VARCHAR(190) NOT NULL,
                email VARCHAR(190) NULL,
                created_at DATETIME NOT NULL,
                UNIQUE KEY uq_provider (provider, provider_uid),
                KEY idx_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];
        foreach ($schema as $sql) {
            $pdo->exec($sql);
        }
        $messages[] = '✅ Database tables created (19 tables).';

        // -------------------------------------------------- upload folders
        foreach (['uploads', 'uploads/avatars', 'uploads/recipes', 'uploads/steps', 'uploads/wall'] as $d) {
            block_dir($d);
        }
        $messages[] = '✅ Upload folders ready.';

        // -------------------------------------------------- admin account
        $ins = $pdo->prepare(
            'INSERT INTO users (username, email, password_hash, display_name, bio, is_admin, created_at)
             VALUES (?, ?, ?, ?, ?, 1, NOW())'
        );
        $ins->execute([$adminUser, $adminEmail, password_hash($adminPass, PASSWORD_DEFAULT),
                       'Site Admin', 'Keeper of the kitchen. 🌿']);
        $adminId = (int)$pdo->lastInsertId();
        $messages[] = "✅ Admin account \"$adminUser\" created.";

        // -------------------------------------------------- master lists (always seeded)
        $seedList = function (string $table, array $names) use ($pdo): array {
            $ids = [];
            $st = $pdo->prepare("INSERT INTO `$table` (name) VALUES (?)");
            foreach ($names as $n) {
                $st->execute([$n]);
                $ids[$n] = (int)$pdo->lastInsertId();
            }
            return $ids;
        };

        $ingredientIds = $seedList('ingredients', [
            'Paneer', 'Tofu', 'Onion', 'Tomato', 'Garlic', 'Ginger', 'Green Chilli', 'Coriander Leaves',
            'Cumin Seeds', 'Turmeric Powder', 'Red Chilli Powder', 'Garam Masala', 'Chaat Masala',
            'Basmati Rice', 'Arborio Rice', 'Pasta (Penne)', 'Spaghetti', 'Tortilla', 'Pav Buns', 'Burger Buns',
            'Nori Sheets', 'Sushi Rice', 'Kimchi', 'Gochujang Paste', 'Miso Paste', 'Soy Sauce',
            'Coconut Milk', 'Thai Green Curry Paste', 'Basil Leaves', 'Mozzarella Cheese', 'Cheddar Cheese',
            'Butter', 'Olive Oil', 'Lemon', 'Capsicum', 'Mushroom', 'Potato', 'Green Peas', 'Chickpeas',
            'Yogurt', 'Cream', 'Breadcrumbs', 'Chickpea Flour (Besan)', 'Mixed Vegetables', 'Spring Onion',
            'Sesame Seeds', 'Tamarind Chutney', 'Mint Leaves', 'Avocado', 'Cucumber',
        ]);

        $categoryIds = $seedList('categories', [
            'Starter', 'Main Course', 'Snack', 'Dessert', 'Breakfast', 'Beverage', 'Soup', 'Salad', 'Street Food',
        ]);

        $cuisineIds = $seedList('cuisines', [
            'Indian', 'Mexican', 'Italian', 'Japanese', 'Korean', 'Thai', 'Mediterranean',
            'Indo-Mexican', 'Indo-Italian', 'Indo-Japanese', 'Indo-Korean', 'Global Fusion',
        ]);

        $originIds = $seedList('origins', [
            'India', 'Mexico', 'Italy', 'Japan', 'Korea', 'Thailand', 'Middle East',
            'Mumbai Street Food', 'Home Kitchen Experiment',
        ]);

        $forumCatIds = $seedList('forum_categories', [
            'Fusion Experiments', 'Recipe Help & Substitutions', 'Kitchen Gear & Techniques',
            'Veg Lifestyle & Nutrition', 'Food Stories & Memories',
        ]);
        $pdo->exec("UPDATE forum_categories SET description = CASE name
            WHEN 'Fusion Experiments' THEN 'Mad-scientist cooking: what did you mash up this week?'
            WHEN 'Recipe Help & Substitutions' THEN 'Stuck mid-recipe? Ask the community.'
            WHEN 'Kitchen Gear & Techniques' THEN 'Knives, pans, fermentation jars and how to use them.'
            WHEN 'Veg Lifestyle & Nutrition' THEN 'Eating well, 100% vegetarian.'
            WHEN 'Food Stories & Memories' THEN 'The dishes that made us who we are.'
            ELSE description END");
        $messages[] = '✅ Master lists seeded (ingredients, categories, cuisines, origins, forum boards).';

        // -------------------------------------------------- demo content
        if ($withDemo) {
            $demoPass = password_hash('Demo@1234', PASSWORD_DEFAULT);
            $demoUsers = [
                ['maya_fusion',  'maya@example.com',  'Maya Patel',     'Gujarati heart, global palate. If it can be stuffed in a quesadilla, I will stuff it. 🌮', '#1f6e43', '🥑'],
                ['leo_cocina',   'leo@example.com',   'Leo Hernandez',  'Mexico City raised, Mumbai spiced. Vegetariano por amor.', '#e26a1f', '🌶️'],
                ['sakura_spice', 'sakura@example.com','Sakura Tanaka',  'Tokyo precision meets tadka chaos. Miso in everything.', '#7a4ec0', '🍙'],
                ['arjun_tadka',  'arjun@example.com', 'Arjun Mehta',    'Engineer by day, fermentation nerd by night. Kimchi + khichdi = life.', '#0e7490', '🥘'],
            ];
            $uids = [];
            $insU = $pdo->prepare(
                'INSERT INTO users (username, email, password_hash, display_name, bio, avatar, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW() - INTERVAL 40 DAY)'
            );
            foreach ($demoUsers as [$un, $em, $dn, $bio, $color, $emoji]) {
                $avatarPath = 'uploads/avatars/demo_' . $un . '.svg';
                make_svg($avatarPath, $emoji, '', $color, '#143d28');
                $insU->execute([$un, $em, $demoPass, $dn, $bio, $avatarPath]);
                $uids[$un] = (int)$pdo->lastInsertId();
            }

            // buddies (accepted) + one pending request to show the flow
            $insB = $pdo->prepare(
                "INSERT INTO buddies (requester_id, addressee_id, status, created_at, responded_at)
                 VALUES (?, ?, ?, NOW() - INTERVAL 30 DAY, NOW() - INTERVAL 29 DAY)"
            );
            $insB->execute([$uids['maya_fusion'], $uids['arjun_tadka'], 'accepted']);
            $insB->execute([$uids['leo_cocina'], $uids['sakura_spice'], 'accepted']);
            $insB->execute([$uids['maya_fusion'], $uids['leo_cocina'], 'accepted']);
            $pdo->prepare(
                "INSERT INTO buddies (requester_id, addressee_id, status, created_at)
                 VALUES (?, ?, 'pending', NOW() - INTERVAL 2 DAY)"
            )->execute([$uids['sakura_spice'], $uids['arjun_tadka']]);

            // ---------------- demo recipes
            $recipes = [
                [
                    'user' => 'maya_fusion', 'title' => 'Paneer Tikka Quesadilla', 'emoji' => '🌮',
                    'c1' => '#e26a1f', 'c2' => '#8a2d0b', 'featured' => 1,
                    'category' => 'Snack', 'cuisine' => 'Indo-Mexican', 'origin' => 'Home Kitchen Experiment',
                    'story' => "Born on a Friday night when half the family wanted tikka and the other half wanted tacos. Democracy failed, fusion won.",
                    'verdict' => "Secret: brush the tortilla with leftover tikka marinade before toasting. Trivia — quesadillas date back to 16th-century Mexico; paneer tikka to the Mughal kitchens. Tonight, they're married.",
                    'ingredients' => [
                        ['Paneer', '250 g, cubed'], ['Yogurt', '1/2 cup, thick'], ['Red Chilli Powder', '1 tsp'],
                        ['Garam Masala', '1 tsp'], ['Tortilla', '4 large'], ['Cheddar Cheese', '1 cup, grated'],
                        ['Capsicum', '1, sliced'], ['Onion', '1, sliced'], ['Chaat Masala', '1/2 tsp'],
                    ],
                    'steps' => [
                        'Whisk yogurt with red chilli powder, garam masala and a pinch of salt. Toss in the paneer cubes and let them marinate for 30 minutes.',
                        'Sear the marinated paneer on a hot pan for 2–3 minutes per side until charred at the edges. Set aside.',
                        'Lay a tortilla flat, scatter cheddar, paneer tikka, capsicum and onion over one half. Sprinkle chaat masala.',
                        'Fold, then toast on medium heat with a little butter until golden and the cheese surrenders — about 2 minutes per side.',
                        'Cut into wedges and serve with mint chutney or salsa. Or both. Definitely both.',
                    ],
                ],
                [
                    'user' => 'arjun_tadka', 'title' => 'Kimchi Khichdi', 'emoji' => '🥘',
                    'c1' => '#b91c1c', 'c2' => '#7c2d12', 'featured' => 1,
                    'category' => 'Main Course', 'cuisine' => 'Indo-Korean', 'origin' => 'Home Kitchen Experiment',
                    'story' => "Comfort food summit: India's khichdi meets Korea's kimchi. My grandmother was suspicious. She had seconds.",
                    'verdict' => "The kimchi goes in at TWO points — half cooked into the khichdi, half fresh on top. That contrast is the whole dish.",
                    'ingredients' => [
                        ['Basmati Rice', '1/2 cup'], ['Kimchi', '1 cup, chopped'], ['Ginger', '1 inch, grated'],
                        ['Cumin Seeds', '1 tsp'], ['Turmeric Powder', '1/2 tsp'], ['Butter', '1 tbsp'],
                        ['Spring Onion', '2, sliced'], ['Sesame Seeds', '1 tsp, toasted'],
                    ],
                    'steps' => [
                        'Rinse rice and moong dal (1/2 cup) until the water runs clear. Soak 15 minutes.',
                        'Heat butter in a pressure cooker. Crackle cumin seeds, add ginger and half the kimchi; sauté 2 minutes.',
                        'Add rice, dal, turmeric, salt and 3 cups water. Pressure cook for 3 whistles, then rest 10 minutes.',
                        'Stir to a loose, porridge-like consistency, adding hot water if needed.',
                        'Top each bowl with fresh kimchi, spring onion and toasted sesame. Eat in pyjamas for full effect.',
                    ],
                ],
                [
                    'user' => 'leo_cocina', 'title' => 'Masala Arancini', 'emoji' => '🍙',
                    'c1' => '#ca8a04', 'c2' => '#854d0e', 'featured' => 1,
                    'category' => 'Starter', 'cuisine' => 'Indo-Italian', 'origin' => 'Italy',
                    'story' => "Sicilian street food with a tadka twist — leftover masala rice never had it this good.",
                    'verdict' => "Hidden info: a tiny cube of mozzarella AND a half-teaspoon of tamarind chutney in the centre. The chutney melts into a sweet-sour surprise.",
                    'ingredients' => [
                        ['Arborio Rice', '1 cup'], ['Garam Masala', '1 tsp'], ['Turmeric Powder', '1/2 tsp'],
                        ['Green Peas', '1/2 cup'], ['Mozzarella Cheese', '100 g, cubed'], ['Tamarind Chutney', '2 tbsp'],
                        ['Breadcrumbs', '1.5 cups'], ['Chickpea Flour (Besan)', '1/2 cup'],
                    ],
                    'steps' => [
                        'Cook arborio rice risotto-style with turmeric, garam masala, peas and salt until creamy. Cool completely.',
                        'Take a lemon-sized ball of rice, press a hole, tuck in a mozzarella cube and a few drops of tamarind chutney, and seal.',
                        'Make a slurry of besan and water. Dip each ball, then roll in breadcrumbs.',
                        'Deep fry at 175°C until deep golden, about 4 minutes. Drain on paper.',
                        'Rest 2 minutes (molten cheese is a safety hazard and we love it) and serve with coriander chutney.',
                    ],
                ],
                [
                    'user' => 'sakura_spice', 'title' => 'Miso Butter Pav Bhaji', 'emoji' => '🍛',
                    'c1' => '#1f6e43', 'c2' => '#143d28', 'featured' => 1,
                    'category' => 'Street Food', 'cuisine' => 'Indo-Japanese', 'origin' => 'Mumbai Street Food',
                    'story' => "Mumbai's loudest dish meets Japan's quietest ingredient. The umami bomb nobody asked for and everybody finished.",
                    'verdict' => "Use WHITE miso only — red miso bullies the bhaji. Finish the pav with a miso-butter brush instead of plain butter and watch people's eyebrows.",
                    'ingredients' => [
                        ['Potato', '3, boiled'], ['Mixed Vegetables', '2 cups'], ['Tomato', '3, chopped'],
                        ['Miso Paste', '1.5 tbsp, white'], ['Butter', '3 tbsp'], ['Pav Buns', '8'],
                        ['Onion', '1, finely chopped'], ['Lemon', '1'],
                    ],
                    'steps' => [
                        'Boil and roughly mash the potatoes and mixed vegetables.',
                        'In a wide pan, melt 2 tbsp butter, soften onions, then cook tomatoes with pav bhaji masala (2 tbsp) until jammy.',
                        'Add mash and a cup of water; simmer 10 minutes, mashing as you go.',
                        'Off the heat, whisk the miso with a ladle of hot bhaji, then stir it back in. Do not boil after this — miso dies a little.',
                        'Toast pav with miso butter. Serve with raw onion, coriander and a lemon wedge.',
                    ],
                ],
                [
                    'user' => 'maya_fusion', 'title' => 'Thai Green Curry Pasta', 'emoji' => '🍝',
                    'c1' => '#2a8a55', 'c2' => '#1f6e43', 'featured' => 0,
                    'category' => 'Main Course', 'cuisine' => 'Thai', 'origin' => 'Thailand',
                    'story' => "When the rice ran out but the curry paste didn't. Necessity is the mother of fusion.",
                    'verdict' => "Reserve a cup of starchy pasta water — it makes the coconut sauce cling like a good gossip.",
                    'ingredients' => [
                        ['Pasta (Penne)', '250 g'], ['Thai Green Curry Paste', '2 tbsp'], ['Coconut Milk', '400 ml'],
                        ['Tofu', '200 g, cubed'], ['Basil Leaves', 'a generous handful'], ['Capsicum', '1, sliced'],
                    ],
                    'steps' => [
                        'Cook penne 1 minute short of al dente. Reserve a cup of pasta water.',
                        'Crisp the tofu cubes in a little oil; set aside.',
                        'Fry the green curry paste 1 minute, pour in coconut milk and simmer 5 minutes.',
                        'Toss in pasta, tofu, capsicum and a splash of pasta water. Simmer until the sauce hugs everything.',
                        'Finish with torn basil. Chopsticks or fork — dealer\'s choice.',
                    ],
                ],
                [
                    'user' => 'leo_cocina', 'title' => 'Falafel Bhaji Burger', 'emoji' => '🍔',
                    'c1' => '#0e7490', 'c2' => '#164e63', 'featured' => 0,
                    'category' => 'Snack', 'cuisine' => 'Mediterranean', 'origin' => 'Middle East',
                    'story' => "A falafel that took a detour through an Indian pakora stand on its way to the bun.",
                    'verdict' => "Double-fry the patties: once to cook, once to crisp. Soggy falafel is a crime in at least two cuisines.",
                    'ingredients' => [
                        ['Chickpeas', '1.5 cups, soaked overnight'], ['Coriander Leaves', '1 bunch'],
                        ['Green Chilli', '2'], ['Chaat Masala', '1 tsp'], ['Burger Buns', '4'],
                        ['Yogurt', '1/2 cup, whisked'], ['Mint Leaves', '10–12'],
                    ],
                    'steps' => [
                        'Blitz soaked (NOT boiled) chickpeas with coriander, chilli, chaat masala and salt to a coarse rubble.',
                        'Shape into 4 fat patties; rest in the fridge for 20 minutes.',
                        'Fry at 170°C until deep brown and crackly, about 5 minutes. Re-fry 1 minute for extra crunch.',
                        'Whip yogurt with mint and a pinch of salt for the raita-mayo.',
                        'Build: toasted bun, mint yogurt, patty, onions, more yogurt, lid on. Squish respectfully.',
                    ],
                ],
                [
                    'user' => 'sakura_spice', 'title' => 'Gochujang Chole Tacos', 'emoji' => '🌮',
                    'c1' => '#dc2626', 'c2' => '#991b1b', 'featured' => 0,
                    'category' => 'Street Food', 'cuisine' => 'Indo-Korean', 'origin' => 'Korea',
                    'story' => "Three countries on one plate. The UN of dinner.",
                    'verdict' => "Gochujang in, garam masala out at the end — fusion works best when one cuisine drives and the other navigates.",
                    'ingredients' => [
                        ['Chickpeas', '2 cups, boiled'], ['Gochujang Paste', '1.5 tbsp'], ['Tomato', '2, pureed'],
                        ['Tortilla', '6 small'], ['Onion', '1, pickled in lemon'], ['Cucumber', '1, ribboned'],
                    ],
                    'steps' => [
                        'Sauté onion-ginger-garlic, add tomato puree and cook until oil separates.',
                        'Stir in gochujang and the boiled chole with a splash of water; simmer 10 minutes.',
                        'Char tortillas directly on the flame for 10 seconds a side.',
                        'Load tacos with gochujang chole, pickled onion and cucumber ribbons.',
                        'Finish with sesame seeds and coriander. Eat over the sink — it drips with joy.',
                    ],
                ],
                [
                    'user' => 'arjun_tadka', 'title' => 'Tandoori Mushroom Sushi', 'emoji' => '🍣',
                    'c1' => '#7a4ec0', 'c2' => '#4c1d95', 'featured' => 0,
                    'category' => 'Starter', 'cuisine' => 'Indo-Japanese', 'origin' => 'Japan',
                    'story' => "Sushi night + leftover tandoori marinade = the roll my Japanese colleague now requests by name.",
                    'verdict' => "Trivia: 'tandoor' and 'sushi rice vinegar' both started as preservation tricks. This roll is 2,000 years of food history in one bite. Roll TIGHT.",
                    'ingredients' => [
                        ['Sushi Rice', '2 cups, cooked & seasoned'], ['Nori Sheets', '4'],
                        ['Mushroom', '200 g'], ['Yogurt', '1/4 cup'], ['Red Chilli Powder', '1 tsp'],
                        ['Avocado', '1, sliced'], ['Soy Sauce', 'to serve'],
                    ],
                    'steps' => [
                        'Marinate mushrooms in yogurt, chilli powder, salt and a squeeze of lemon for 20 minutes; roast at 220°C until charred, 15 minutes.',
                        'Lay nori shiny-side down, spread rice thinly leaving a 2 cm border.',
                        'Line tandoori mushrooms and avocado along the bottom edge.',
                        'Roll firmly with a bamboo mat, sealing the edge with water. Rest 2 minutes, then slice with a wet knife.',
                        'Serve with soy sauce spiked with a drop of mint chutney. Wasabi optional, courage mandatory.',
                    ],
                ],
            ];

            $insR = $pdo->prepare(
                "INSERT INTO recipes (user_id, title, slug, image, story, category_id, cuisine_id, origin_id,
                                      verdict, status, is_featured, views, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'published', ?, ?, NOW() - INTERVAL ? DAY)"
            );
            $insRI = $pdo->prepare(
                'INSERT INTO recipe_ingredients (recipe_id, ingredient_id, quantity, sort_order) VALUES (?, ?, ?, ?)'
            );
            $insRS = $pdo->prepare(
                'INSERT INTO recipe_steps (recipe_id, step_no, instruction) VALUES (?, ?, ?)'
            );

            $recipeIds = [];
            foreach ($recipes as $i => $r) {
                $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $r['title']), '-'));
                $img = 'uploads/recipes/demo_' . $slug . '.svg';
                make_svg($img, $r['emoji'], $r['title'], $r['c1'], $r['c2']);
                $insR->execute([
                    $uids[$r['user']], $r['title'], $slug, $img, $r['story'],
                    $categoryIds[$r['category']], $cuisineIds[$r['cuisine']], $originIds[$r['origin']],
                    $r['verdict'], $r['featured'], rand(60, 480), 28 - $i * 3,
                ]);
                $rid = (int)$pdo->lastInsertId();
                $recipeIds[] = $rid;
                foreach ($r['ingredients'] as $order => [$ing, $qty]) {
                    $insRI->execute([$rid, $ingredientIds[$ing], $qty, $order]);
                }
                foreach ($r['steps'] as $n => $stepText) {
                    $insRS->execute([$rid, $n + 1, $stepText]);
                }
            }

            // reactions + comments on recipes
            $reactions = ['like', 'love', 'yum', 'wow'];
            $insReact = $pdo->prepare(
                "INSERT IGNORE INTO reactions (user_id, target_type, target_id, reaction, created_at)
                 VALUES (?, 'recipe', ?, ?, NOW() - INTERVAL ? DAY)"
            );
            $insC = $pdo->prepare(
                "INSERT INTO comments (user_id, target_type, target_id, body, status, created_at)
                 VALUES (?, 'recipe', ?, ?, 'visible', NOW() - INTERVAL ? DAY)"
            );
            $commentPool = [
                'Made this tonight — absolute banger. The family went silent, which is the highest praise.',
                'Never thought these two cuisines would work together. I was wrong. Deliciously wrong.',
                'Tried it with tofu instead of paneer and it still slapped. Thanks for sharing!',
                'Bookmarked for the weekend. The verdict tip is gold.',
                'This is why I love this site. Pure fusion genius. 😋',
            ];
            $allUids = array_values($uids);
            foreach ($recipeIds as $rid) {
                shuffle($allUids);
                foreach (array_slice($allUids, 0, rand(2, 4)) as $ru) {
                    $insReact->execute([$ru, $rid, $reactions[array_rand($reactions)], rand(1, 20)]);
                }
                $insC->execute([$allUids[0], $rid, $commentPool[array_rand($commentPool)], rand(1, 15)]);
            }

            // wall posts
            $insW = $pdo->prepare(
                "INSERT INTO wall_posts (user_id, body, shared_recipe_id, status, created_at)
                 VALUES (?, ?, ?, 'visible', NOW() - INTERVAL ? DAY)"
            );
            $insW->execute([$uids['maya_fusion'], "Farmer's market haul today: purple basil, baby paneer-worthy milk, and a chilli the vendor swore was 'mild'. He lied. Stay tuned. 🌶️🔥", null, 6]);
            $insW->execute([$uids['arjun_tadka'], "Week 3 of the kimchi batch. It bubbles when I walk past. I think it likes me.", null, 4]);
            $insW->execute([$uids['leo_cocina'], "You HAVE to try Maya's quesadilla. I added smoked paprika to the marinade — next level.", $recipeIds[0], 3]);
            $insW->execute([$uids['sakura_spice'], "Unpopular opinion: miso belongs in chai. I said what I said. (Recipe testing in progress…)", null, 1]);

            // forum topics + replies
            $topics = [
                ['Fusion Experiments', 'maya_fusion', 'What is the weirdest fusion that actually WORKED for you?',
                 "Rules: it must be vegetarian, you must have actually cooked it, and you must defend it.\n\nI'll start: jalebi rabri tiramisu. Fight me."],
                ['Recipe Help & Substitutions', 'leo_cocina', 'Best paneer substitute that survives a tandoori marinade?',
                 "Extra-firm tofu falls apart on the grill and halloumi gets too salty with the masala. What do you all use for a vegan-friendly tikka that holds its shape?"],
                ['Kitchen Gear & Techniques', 'arjun_tadka', 'Pressure cooker vs Instant Pot for dal — settle this',
                 "My grandmother's 3-whistle method vs my Instant Pot's 12-minute preset. The IP is consistent, but I swear the whistles add flavour. Placebo? Science welcome."],
                ['Veg Lifestyle & Nutrition', 'sakura_spice', 'Protein-packed veg breakfasts that are not smoothies',
                 "I cannot drink another smoothie. Looking for savoury, high-protein, sub-20-minute breakfast ideas. Bonus points for fusion."],
                ['Food Stories & Memories', 'maya_fusion', 'The dish that made you fall in love with cooking',
                 "Mine: watching my dadi make rotis that puffed up like balloons, every single time. What's yours?"],
            ];
            $insT = $pdo->prepare(
                "INSERT INTO forum_topics (category_id, user_id, title, body, views, status, created_at)
                 VALUES (?, ?, ?, ?, ?, 'visible', NOW() - INTERVAL ? DAY)"
            );
            $insTC = $pdo->prepare(
                "INSERT INTO comments (user_id, target_type, target_id, body, status, created_at)
                 VALUES (?, 'topic', ?, ?, 'visible', NOW() - INTERVAL ? DAY)"
            );
            $replyPool = [
                'Following this thread so hard right now.',
                'Tried something similar last month — the trick is to go low and slow.',
                'Adding this to my weekend experiment list. Thanks!',
                'Hot take: the answer is always more ginger.',
                'My grandmother would disagree, but my grandmother also puts sugar in everything, so.',
            ];
            foreach ($topics as $i => [$cat, $user, $title, $body]) {
                $insT->execute([$forumCatIds[$cat], $uids[$user], $title, $body, rand(40, 300), 20 - $i * 2]);
                $tid = (int)$pdo->lastInsertId();
                shuffle($allUids);
                foreach (array_slice($allUids, 0, rand(2, 3)) as $ru) {
                    if ($ru !== $uids[$user]) {
                        $insTC->execute([$ru, $tid, $replyPool[array_rand($replyPool)], rand(1, 10)]);
                    }
                }
            }

            $messages[] = '✅ Demo content created: 4 members (password: Demo@1234), 8 recipes, wall posts, forum topics, comments and reactions.';
        }

        $installed = true;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Install · Bosket's Alimentos</title>
<style>
  body{font-family:system-ui,sans-serif;background:#f0f8f2;color:#22302a;margin:0;padding:40px 20px}
  .box{max-width:620px;margin:0 auto;background:#fff;border-radius:16px;padding:34px;box-shadow:0 8px 30px rgba(20,61,40,.12)}
  h1{color:#143d28;font-size:26px;margin-top:0}
  .ok{background:#e3f2e8;color:#143d28;padding:11px 16px;border-radius:10px;margin:8px 0;font-weight:500}
  .err{background:#fdecea;color:#c0392b;padding:11px 16px;border-radius:10px;margin:8px 0;font-weight:500}
  label{display:block;font-weight:600;margin-top:16px;font-size:14px}
  input[type=text],input[type=email],input[type=password]{width:100%;padding:11px;border:1.5px solid #e2e8e1;border-radius:10px;margin-top:6px;box-sizing:border-box;font-size:15px}
  .btn{display:inline-block;background:#1f6e43;color:#fff;border:0;border-radius:999px;padding:13px 34px;font-size:16px;font-weight:600;cursor:pointer;margin-top:24px}
  .btn:hover{background:#2a8a55}
  small{color:#5b6b62}
  .warn{background:#fdeadd;color:#8a4a12;padding:13px 16px;border-radius:10px;margin-top:20px;font-weight:600}
</style>
</head>
<body>
<div class="box">
  <h1>🌿 Bosket's Alimentos — Installer</h1>

  <?php if ($fatal): ?>
    <div class="err"><?= htmlspecialchars($fatal) ?></div>
    <p><a href="install.php">← Try again</a></p>

  <?php elseif ($installed): ?>
    <?php foreach ($messages as $m): ?><div class="ok"><?= htmlspecialchars($m) ?></div><?php endforeach; ?>
    <div class="warn">🚨 IMPORTANT: delete <code>install.php</code> from the server NOW (hPanel → File Manager). Anyone who can open it could reinstall your site.</div>
    <p style="margin-top:24px"><a class="btn" href="index.php">Open your website →</a></p>

  <?php elseif ($alreadyInstalled): ?>
    <div class="ok">✅ The database is already installed — you're good to go.</div>
    <div class="warn">🚨 Please delete <code>install.php</code> from the server now.</div>
    <p style="margin-top:24px"><a class="btn" href="index.php">Open your website →</a></p>

  <?php else: ?>
    <div class="ok">✅ Database connection works (<?= htmlspecialchars(DB_NAME) ?>).</div>
    <p>This will create all tables and your administrator account.</p>
    <form method="post">
      <label>Admin username
        <input type="text" name="admin_user" required pattern="[a-zA-Z0-9_]{3,30}" value="admin">
      </label>
      <label>Admin email
        <input type="email" name="admin_email" required placeholder="you@yourdomain.com">
      </label>
      <label>Admin password <small>(min 8 characters — pick a strong one)</small>
        <input type="password" name="admin_pass" required minlength="8">
      </label>
      <label style="font-weight:500">
        <input type="checkbox" name="demo" value="1" checked>
        Seed demo content (4 sample members, 8 fusion recipes, forum topics) — you can delete the demo members later from the Admin Panel
      </label>
      <button class="btn" type="submit">🚀 Install Bosket's Alimentos</button>
    </form>
  <?php endif; ?>
</div>
</body>
</html>
