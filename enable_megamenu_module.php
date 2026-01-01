<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Enable EkoKray Megamenu</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Enable EkoKray Megamenu Module</h1>

<?php
if (isset($_POST['enable'])) {
    require_once('system/startup.php');

    $registry = new Registry();
    $db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);
    $registry->set('db', $db);

    echo '<h2>Enabling Module...</h2>';

    // 1. Enable module instance
    echo '<p><strong>Step 1:</strong> Enabling module instance in oc_module...</p>';
    $db->query("UPDATE " . DB_PREFIX . "module SET status = 1 WHERE code LIKE 'ekokray_megamenu%'");
    echo '<p class="success">✓ Module instance enabled (affected rows: ' . $db->countAffected() . ')</p>';

    // 2. Check module instances
    echo '<p><strong>Step 2:</strong> Checking module instances...</p>';
    $result = $db->query("SELECT module_id, name, code, status FROM " . DB_PREFIX . "module WHERE code LIKE 'ekokray_megamenu%'");
    if ($result->num_rows) {
        echo '<pre>';
        foreach ($result->rows as $row) {
            echo "Module ID: {$row['module_id']}, Name: {$row['name']}, Status: " . ($row['status'] ? 'ENABLED' : 'DISABLED') . "\n";
        }
        echo '</pre>';
    } else {
        echo '<p class="warning">⚠ No module instances found</p>';
    }

    // 3. Check menus
    echo '<p><strong>Step 3:</strong> Checking menus...</p>';
    $result = $db->query("SELECT * FROM " . DB_PREFIX . "ekokray_menu WHERE status = 1");
    if ($result->num_rows) {
        echo '<p class="success">✓ Found ' . $result->num_rows . ' active menu(s)</p>';
        echo '<pre>';
        foreach ($result->rows as $row) {
            echo "Menu ID: {$row['menu_id']}, Name: {$row['name']}\n";
        }
        echo '</pre>';
        $menu_id = $result->row['menu_id'];
    } else {
        echo '<p class="error">✗ No active menus found!</p>';
        echo '<p>Creating default menu...</p>';
        $db->query("INSERT INTO " . DB_PREFIX . "ekokray_menu (name, status, mobile_breakpoint, cache_enabled, cache_duration, date_added, date_modified)
                    VALUES ('Main Menu', 1, 992, 1, 3600, NOW(), NOW())");
        $menu_id = $db->getLastId();
        echo '<p class="success">✓ Created menu ID: ' . $menu_id . '</p>';
    }

    // 4. Check menu items
    if (isset($menu_id)) {
        echo '<p><strong>Step 4:</strong> Checking menu items for menu ' . $menu_id . '...</p>';
        $result = $db->query("
            SELECT
                mi.item_id,
                mi.item_type,
                mi.category_id,
                mi.status,
                mid.title,
                mid.language_id
            FROM " . DB_PREFIX . "ekokray_menu_item mi
            LEFT JOIN " . DB_PREFIX . "ekokray_menu_item_description mid ON mi.item_id = mid.item_id
            WHERE mi.menu_id = " . (int)$menu_id . " AND mi.status = 1
            ORDER BY mi.sort_order
        ");

        if ($result->num_rows) {
            echo '<p class="success">✓ Found ' . $result->num_rows . ' menu item(s)</p>';
            echo '<pre>';
            foreach ($result->rows as $row) {
                echo "Item ID: {$row['item_id']}, Type: {$row['item_type']}, Title: {$row['title']} (lang: {$row['language_id']})\n";

                if ($row['item_type'] == 'category_tree' && $row['category_id']) {
                    $cat_result = $db->query("
                        SELECT COUNT(*) as count
                        FROM " . DB_PREFIX . "category
                        WHERE parent_id = " . (int)$row['category_id'] . " AND status = 1
                    ");
                    echo "  → Category {$row['category_id']} has {$cat_result->row['count']} subcategories\n";
                }
            }
            echo '</pre>';
        } else {
            echo '<p class="warning">⚠ No menu items found! You need to add items in admin panel.</p>';
        }
    }

    // 5. Check categories
    echo '<p><strong>Step 5:</strong> Checking categories...</p>';
    $result = $db->query("SELECT COUNT(*) as total FROM " . DB_PREFIX . "category WHERE status = 1");
    echo '<p>Total active categories: ' . $result->row['total'] . '</p>';

    $result = $db->query("
        SELECT COUNT(DISTINCT c.category_id) as total
        FROM " . DB_PREFIX . "category c
        INNER JOIN " . DB_PREFIX . "category_description cd ON c.category_id = cd.category_id
        WHERE c.status = 1
    ");
    echo '<p>Categories with descriptions: ' . $result->row['total'] . '</p>';

    // Check languages
    $result = $db->query("SELECT * FROM " . DB_PREFIX . "language ORDER BY sort_order");
    echo '<p>Languages:</p><pre>';
    foreach ($result->rows as $row) {
        echo "ID: {$row['language_id']}, Code: {$row['code']}, Name: {$row['name']}\n";
    }
    echo '</pre>';

    echo '<hr><h2 class="success">✓ Module Enabled Successfully!</h2>';
    echo '<p>Please refresh your website to see the megamenu.</p>';

} else {
?>
        <p>This script will enable the ekokray_megamenu module instance.</p>
        <form method="post">
            <button type="submit" name="enable" style="padding: 10px 20px; font-size: 16px; cursor: pointer;">Enable Module</button>
        </form>
<?php
}
?>
    </div>
</body>
</html>
