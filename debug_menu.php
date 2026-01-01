<?php
// Debug script to check wd_megamenu configuration
header('Content-Type: text/plain');

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('config.php');

// Database connection
$db = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);

if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

echo "=== Checking wd_megamenu Module Configuration ===\n\n";

// Check if wd_megamenu extension is installed
echo "1. Checking oc_extension table:\n";
$result = $db->query("SELECT * FROM " . DB_PREFIX . "extension WHERE code = 'wd_megamenu'");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        print_r($row);
    }
} else {
    echo "   WARNING: wd_megamenu not found in extensions table!\n";
}
echo "\n";

// Check module instances
echo "2. Checking oc_module table for wd_megamenu instances:\n";
$result = $db->query("SELECT * FROM " . DB_PREFIX . "module WHERE code LIKE 'wd_megamenu%'");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "   Module ID: " . $row['module_id'] . "\n";
        echo "   Name: " . $row['name'] . "\n";
        echo "   Code: " . $row['code'] . "\n";
        echo "   Setting: " . substr($row['setting'], 0, 200) . "...\n\n";
    }
} else {
    echo "   WARNING: No wd_megamenu modules found!\n";
}
echo "\n";

// Check layout module assignments
echo "3. Checking oc_layout_module table for headertop position:\n";
$result = $db->query("SELECT lm.*, l.name as layout_name FROM " . DB_PREFIX . "layout_module lm JOIN " . DB_PREFIX . "layout l ON lm.layout_id = l.layout_id WHERE lm.position = 'headertop' ORDER BY lm.sort_order");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "   Layout: " . $row['layout_name'] . " (ID: " . $row['layout_id'] . ")\n";
        echo "   Code: " . $row['code'] . "\n";
        echo "   Position: " . $row['position'] . "\n";
        echo "   Sort Order: " . $row['sort_order'] . "\n\n";
    }
} else {
    echo "   WARNING: No modules assigned to headertop position!\n";
}
echo "\n";

// Check wd_megamenu menus
echo "4. Checking wd_megamenu menus:\n";
$result = $db->query("SELECT * FROM " . DB_PREFIX . "wd_megamenu");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "   Menu ID: " . $row['menu_id'] . "\n";
        echo "   Name: " . $row['name'] . "\n";
        echo "   Status: " . ($row['status'] ? 'Enabled' : 'Disabled') . "\n";
        echo "   Menu Type: " . $row['menu_type'] . "\n\n";
    }
} else {
    echo "   WARNING: No menus found in wd_megamenu table!\n";
}

$db->close();
?>
