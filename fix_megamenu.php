<?php
// Enable and check ekokray_megamenu module
require_once('config.php');

// Database connection
$db = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);

if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

echo "=== EKOKRAY MEGAMENU FIX ===\n\n";

// 1. Enable module instance
echo "1. Enabling module instance...\n";
$db->query("UPDATE " . DB_PREFIX . "module SET status = 1 WHERE code LIKE 'ekokray_megamenu%'");
echo "   Affected rows: " . $db->affected_rows . "\n\n";

// 2. Check module status
echo "2. Module instances:\n";
$result = $db->query("SELECT module_id, name, code, status FROM " . DB_PREFIX . "module WHERE code LIKE 'ekokray_megamenu%'");
while ($row = $result->fetch_assoc()) {
    echo "   - ID: {$row['module_id']}, Name: {$row['name']}, Status: " . ($row['status'] ? 'ENABLED' : 'DISABLED') . "\n";
}
echo "\n";

// 3. Check menus
echo "3. Active menus:\n";
$result = $db->query("SELECT * FROM " . DB_PREFIX . "ekokray_menu WHERE status = 1");
if ($result->num_rows == 0) {
    echo "   WARNING: No active menus found!\n";
    echo "   Creating default menu...\n";

    $db->query("INSERT INTO " . DB_PREFIX . "ekokray_menu (name, status, mobile_breakpoint, cache_enabled, cache_duration, date_added, date_modified)
                VALUES ('Main Menu', 1, 992, 1, 3600, NOW(), NOW())");
    $menu_id = $db->insert_id;
    echo "   Created menu ID: $menu_id\n";
} else {
    while ($row = $result->fetch_assoc()) {
        echo "   - Menu ID: {$row['menu_id']}, Name: {$row['name']}\n";
        $menu_id = $row['menu_id'];
    }
}
echo "\n";

// 4. Check menu items
echo "4. Menu items for menu $menu_id:\n";
$result = $db->query("
    SELECT
        mi.item_id,
        mi.item_type,
        mi.category_id,
        mi.status,
        mid.title
    FROM " . DB_PREFIX . "ekokray_menu_item mi
    LEFT JOIN " . DB_PREFIX . "ekokray_menu_item_description mid ON mi.item_id = mid.item_id
    WHERE mi.menu_id = $menu_id AND mi.status = 1
    ORDER BY mi.sort_order
");

if ($result->num_rows == 0) {
    echo "   WARNING: No menu items found!\n";
    echo "   You need to add menu items in admin panel.\n";
} else {
    while ($row = $result->fetch_assoc()) {
        echo "   - Item ID: {$row['item_id']}, Type: {$row['item_type']}, Title: {$row['title']}\n";

        if ($row['item_type'] == 'category_tree' && $row['category_id']) {
            // Check if category has children
            $cat_result = $db->query("
                SELECT COUNT(*) as count
                FROM " . DB_PREFIX . "category
                WHERE parent_id = " . (int)$row['category_id'] . " AND status = 1
            ");
            $cat_row = $cat_result->fetch_assoc();
            echo "     Category {$row['category_id']} has {$cat_row['count']} subcategories\n";
        }
    }
}
echo "\n";

// 5. Check categories
echo "5. Categories check:\n";
$result = $db->query("
    SELECT COUNT(*) as total FROM " . DB_PREFIX . "category WHERE status = 1
");
$row = $result->fetch_assoc();
echo "   Total active categories: {$row['total']}\n";

$result = $db->query("
    SELECT COUNT(*) as total
    FROM " . DB_PREFIX . "category c
    LEFT JOIN " . DB_PREFIX . "category_description cd ON c.category_id = cd.category_id
    WHERE c.status = 1 AND cd.name IS NOT NULL
");
$row = $result->fetch_assoc();
echo "   Categories with descriptions: {$row['total']}\n";

// Check language
$result = $db->query("SELECT * FROM " . DB_PREFIX . "language");
echo "   Languages:\n";
while ($row = $result->fetch_assoc()) {
    echo "     - ID: {$row['language_id']}, Code: {$row['code']}, Name: {$row['name']}\n";
}
echo "\n";

// 6. Clear cache
echo "6. Clearing cache...\n";
$cache_dir = DIR_CACHE;
if (is_dir($cache_dir)) {
    $files = glob($cache_dir . 'cache.ekokray.*');
    foreach ($files as $file) {
        unlink($file);
    }
    echo "   Cache cleared\n";
}

echo "\n=== DONE ===\n";
echo "Please refresh your website to see changes.\n";

$db->close();
