<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Enable Module via PHP</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto; }
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

    echo '<h2>Checking Module Structure...</h2>';

    // Check oc_module table structure
    $result = $db->query("SHOW COLUMNS FROM " . DB_PREFIX . "module");
    echo '<p><strong>Table oc_module columns:</strong></p><pre>';
    foreach ($result->rows as $col) {
        echo $col['Field'] . " (" . $col['Type'] . ")\n";
    }
    echo '</pre>';

    // Get module instance
    echo '<p><strong>Module instances:</strong></p>';
    $result = $db->query("SELECT * FROM " . DB_PREFIX . "module WHERE code LIKE 'ekokray_megamenu%'");

    if ($result->num_rows) {
        foreach ($result->rows as $module) {
            echo '<pre>';
            echo "Module ID: " . $module['module_id'] . "\n";
            echo "Name: " . $module['name'] . "\n";
            echo "Code: " . $module['code'] . "\n";

            // Unserialize settings to check status
            $settings = unserialize($module['setting']);
            echo "Current Status: " . (isset($settings['status']) && $settings['status'] ? 'ENABLED' : 'DISABLED') . "\n";

            if (!isset($settings['status']) || !$settings['status']) {
                // Enable the module
                $settings['status'] = 1;
                $serialized = serialize($settings);

                $db->query("UPDATE " . DB_PREFIX . "module SET setting = '" . $db->escape($serialized) . "' WHERE module_id = '" . (int)$module['module_id'] . "'");

                echo "\n✓ MODULE ENABLED!\n";
            }

            echo "Settings: " . print_r($settings, true) . "\n";
            echo '</pre>';
        }

        echo '<p class="success">✓ Module enabled successfully!</p>';
        echo '<p>Now refresh your website to see the megamenu.</p>';

    } else {
        echo '<p class="error">✗ No module instances found!</p>';
        echo '<p>You need to create a module instance in: Extensions > Modules > ekokray_megamenu</p>';
    }

} else {
?>
        <p>This script will enable the ekokray_megamenu module by updating the serialized settings.</p>
        <form method="post">
            <button type="submit" name="enable" style="padding: 10px 20px; font-size: 16px; cursor: pointer; background: #4CAF50; color: white; border: none; border-radius: 4px;">Enable Module</button>
        </form>
<?php
}
?>
    </div>
</body>
</html>
