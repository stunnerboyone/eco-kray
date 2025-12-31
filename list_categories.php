<?php
/**
 * List all categories from database with their language_id
 */

// Simple database connection
$host = 'localhost';
$user = 'root';
$pass = 'root';
$dbname = 'opencart';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== ALL CATEGORIES IN DATABASE ===\n\n";

    $stmt = $pdo->query("
        SELECT
            c.category_id,
            c.parent_id,
            cd.language_id,
            cd.name
        FROM oc_category c
        LEFT JOIN oc_category_description cd ON c.category_id = cd.category_id
        ORDER BY c.parent_id, cd.language_id, cd.name
    ");

    $current_parent = null;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($current_parent !== $row['parent_id']) {
            $current_parent = $row['parent_id'];
            echo "\n--- Parent ID: " . ($current_parent == 0 ? 'ROOT' : $current_parent) . " ---\n";
        }

        printf("ID: %3d | Lang: %d | Name: %s\n",
            $row['category_id'],
            $row['language_id'],
            $row['name']
        );
    }

    echo "\n=== Categories with 'Наша продукція' or similar ===\n\n";

    $stmt = $pdo->query("
        SELECT
            c.category_id,
            c.parent_id,
            cd.language_id,
            cd.name
        FROM oc_category c
        LEFT JOIN oc_category_description cd ON c.category_id = cd.category_id
        WHERE cd.name LIKE '%продукц%' OR cd.name LIKE '%Натуральн%' OR cd.name LIKE '%Пастил%'
        ORDER BY cd.name
    ");

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        printf("ID: %3d | Parent: %3d | Lang: %d | Name: %s\n",
            $row['category_id'],
            $row['parent_id'],
            $row['language_id'],
            $row['name']
        );
    }

} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "\nTrying alternative connection...\n";

    // Try with socket
    try {
        $pdo = new PDO("mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=$dbname;charset=utf8mb4", $user, $pass);
        echo "Connected via socket!\n";
        // Repeat queries here if needed
    } catch (PDOException $e2) {
        echo "Socket connection also failed: " . $e2->getMessage() . "\n";
    }
}
