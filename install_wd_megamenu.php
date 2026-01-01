<?php
/**
 * WD Megamenu Module Installer
 * This script properly initializes the wd_megamenu module in OpenCart
 */

// Load OpenCart bootstrap
require_once('config.php');
require_once(DIR_SYSTEM . 'startup.php');

// Registry
$registry = new Registry();

// Database
$db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);
$registry->set('db', $db);

echo "=== WD Megamenu Module Installation ===\n\n";

// 1. Check if extension exists in oc_extension
echo "Step 1: Checking extension registration...\n";
$check = $db->query("SELECT * FROM " . DB_PREFIX . "extension WHERE `type` = 'module' AND `code` = 'wd_megamenu'");

if ($check->num_rows == 0) {
    echo "   Extension not found. Registering...\n";
    $db->query("INSERT INTO " . DB_PREFIX . "extension (`type`, `code`) VALUES ('module', 'wd_megamenu')");
    echo "   ✓ Extension registered\n";
} else {
    echo "   ✓ Extension already registered\n";
}
echo "\n";

// 2. Enable module globally
echo "Step 2: Enabling module globally...\n";
$check = $db->query("SELECT * FROM " . DB_PREFIX . "setting WHERE `key` = 'module_wd_megamenu_status'");

if ($check->num_rows == 0) {
    echo "   Module status not set. Enabling...\n";
    $db->query("INSERT INTO " . DB_PREFIX . "setting (store_id, `code`, `key`, `value`, serialized) VALUES (0, 'module_wd_megamenu', 'module_wd_megamenu_status', '1', 0)");
    echo "   ✓ Module enabled globally\n";
} else {
    $current_status = $check->row['value'];
    if ($current_status != '1') {
        echo "   Module is disabled (status=$current_status). Enabling...\n";
        $db->query("UPDATE " . DB_PREFIX . "setting SET `value` = '1' WHERE `key` = 'module_wd_megamenu_status'");
        echo "   ✓ Module enabled\n";
    } else {
        echo "   ✓ Module already enabled\n";
    }
}
echo "\n";

// 3. Check module tables
echo "Step 3: Checking module tables...\n";
$tables = array(
    DB_PREFIX . 'megamenu',
    DB_PREFIX . 'megamenu_top_item',
    DB_PREFIX . 'megamenu_top_item_description',
    DB_PREFIX . 'megamenu_sub_item',
    DB_PREFIX . 'megamenu_sub_item_description'
);

foreach ($tables as $table) {
    $check = $db->query("SHOW TABLES LIKE '" . $table . "'");
    if ($check->num_rows > 0) {
        echo "   ✓ Table $table exists\n";
    } else {
        echo "   ✗ WARNING: Table $table does not exist!\n";
        echo "     Run module install from admin panel: Extensions > Extensions > Modules > WD Megamenu > Install\n";
    }
}
echo "\n";

// 4. Check module instances
echo "Step 4: Checking module instances...\n";
$instances = $db->query("SELECT * FROM " . DB_PREFIX . "module WHERE `code` LIKE 'wd_megamenu%'");
if ($instances->num_rows > 0) {
    echo "   Found " . $instances->num_rows . " module instance(s):\n";
    foreach ($instances->rows as $instance) {
        $settings = json_decode($instance['setting'], true);
        $status = isset($settings['status']) ? ($settings['status'] ? 'ENABLED' : 'DISABLED') : 'NO STATUS';
        echo "   - ID: {$instance['module_id']}, Name: {$instance['name']}, Status: $status\n";
    }
} else {
    echo "   ✗ No module instances found\n";
    echo "     Create one in admin: Extensions > Extensions > Modules > WD Megamenu > Add New\n";
}
echo "\n";

// 5. Check layout assignments
echo "Step 5: Checking layout assignments...\n";
$layouts = $db->query("SELECT * FROM " . DB_PREFIX . "layout_module WHERE `code` LIKE 'wd_megamenu%' AND position = 'headertop'");
if ($layouts->num_rows > 0) {
    echo "   Found " . $layouts->num_rows . " layout assignment(s) for headertop:\n";
    foreach ($layouts->rows as $layout) {
        echo "   - Layout ID: {$layout['layout_id']}, Code: {$layout['code']}, Sort: {$layout['sort_order']}\n";
    }
} else {
    echo "   ✗ No layout assignments for headertop position\n";
    echo "     Assign in admin: Design > Layouts > [Layout Name] > Header Top\n";
}
echo "\n";

// 6. Check menus
echo "Step 6: Checking menus in database...\n";
$menus = $db->query("SELECT * FROM " . DB_PREFIX . "megamenu");
if ($menus->num_rows > 0) {
    echo "   Found " . $menus->num_rows . " menu(s):\n";
    foreach ($menus->rows as $menu) {
        $status = $menu['status'] ? 'ENABLED' : 'DISABLED';
        echo "   - ID: {$menu['menu_id']}, Name: {$menu['name']}, Type: {$menu['menu_type']}, Status: $status\n";
    }
} else {
    echo "   ✗ No menus found in database\n";
    echo "     Create menu in admin: Extensions > Modules > WD Megamenu > Menu Editor\n";
}
echo "\n";

echo "=== Installation Check Complete ===\n\n";
echo "NEXT STEPS:\n";
echo "1. Clear OpenCart cache: Delete files in system/storage/cache/\n";
echo "2. Refresh your website to see if menu appears\n";
echo "3. If menu still doesn't show, check admin panel configuration\n";
