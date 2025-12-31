<?php
/**
 * Simple script to create product categories
 * Run this once to create all necessary categories
 */

// Database connection settings - adjust if needed
$host = 'localhost';
$user = 'root';
$pass = 'root';
$dbname = 'opencart';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Connected to database\n\n";

    $categories = [
        'Наша продукція',
        'Натуральні Соки',
        'Пастила',
        'Сухофрукти',
        'Джеми/Соуси/Конфітюр',
        'Набори'
    ];

    foreach ($categories as $cat_name) {
        // Check if exists
        $stmt = $pdo->prepare("SELECT category_id FROM oc_category_description WHERE name = ? AND language_id = 1");
        $stmt->execute([$cat_name]);
        $existing = $stmt->fetch();

        if ($existing) {
            echo "✓ Category exists: $cat_name (ID: {$existing['category_id']})\n";
        } else {
            // Create category
            $stmt = $pdo->prepare("INSERT INTO oc_category (parent_id, top, `column`, sort_order, status, date_added, date_modified) VALUES (0, 1, 1, 0, 1, NOW(), NOW())");
            $stmt->execute();
            $category_id = $pdo->lastInsertId();

            // Add description
            $stmt = $pdo->prepare("INSERT INTO oc_category_description (category_id, language_id, name, description, meta_title, meta_description, meta_keyword) VALUES (?, 1, ?, '', ?, '', '')");
            $stmt->execute([$category_id, $cat_name, $cat_name]);

            // Add to store
            $stmt = $pdo->prepare("INSERT INTO oc_category_to_store (category_id, store_id) VALUES (?, 0)");
            $stmt->execute([$category_id]);

            // Add layout (optional)
            $stmt = $pdo->prepare("INSERT INTO oc_category_to_layout (category_id, store_id, layout_id) VALUES (?, 0, 0)");
            $stmt->execute([$category_id]);

            echo "✓ Created category: $cat_name (ID: $category_id)\n";
        }
    }

    echo "\n=== All Categories ===\n";
    $stmt = $pdo->query("
        SELECT c.category_id, cd.name, c.status
        FROM oc_category c
        LEFT JOIN oc_category_description cd ON c.category_id = cd.category_id
        WHERE cd.name IN ('Наша продукція', 'Натуральні Соки', 'Пастила', 'Сухофрукти', 'Джеми/Соуси/Конфітюр', 'Набори')
        AND cd.language_id = 1
        ORDER BY cd.name
    ");

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $status = $row['status'] ? 'Active' : 'Disabled';
        echo "  ID: {$row['category_id']} - {$row['name']} ($status)\n";
    }

    echo "\n✓ Done! Now run 1C sync again.\n";

} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "\nPlease check database connection settings in this file.\n";
}
