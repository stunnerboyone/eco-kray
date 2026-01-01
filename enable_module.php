<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Enable EkoKray Megamenu Module</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .info { background: #e3f2fd; padding: 15px; border-left: 4px solid #2196F3; margin: 20px 0; }
        button { background: #4CAF50; color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        button:hover { background: #45a049; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Enable EkoKray Megamenu Module</h1>

<?php
if (isset($_POST['enable'])) {
    // Bootstrap OpenCart minimally
    $loader = require_once('system/startup.php');

    // Create minimal registry
    $registry = new Registry();

    // Database
    $db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);
    $registry->set('db', $db);

    echo '<div class="info"><h3>Initialization Process</h3></div>';

    // 1. Register extension
    echo '<p><strong>Step 1:</strong> Registering module in oc_extension...</p>';
    $check = $db->query("SELECT * FROM " . DB_PREFIX . "extension WHERE `type` = 'module' AND `code` = 'ekokray_megamenu'");

    if ($check->num_rows == 0) {
        $db->query("INSERT INTO " . DB_PREFIX . "extension (`type`, `code`) VALUES ('module', 'ekokray_megamenu')");
        echo '<p class="success">✓ Module registered successfully</p>';
    } else {
        echo '<p class="success">✓ Module already registered</p>';
    }

    // 2. Enable module globally
    echo '<p><strong>Step 2:</strong> Enabling module globally...</p>';

    // Remove old setting
    $db->query("DELETE FROM " . DB_PREFIX . "setting WHERE `key` = 'module_ekokray_megamenu_status'");

    // Add new setting
    $db->query("INSERT INTO " . DB_PREFIX . "setting (store_id, `code`, `key`, `value`, serialized) VALUES (0, 'module_ekokray_megamenu', 'module_ekokray_megamenu_status', '1', 0)");

    echo '<p class="success">✓ Module enabled globally (module_ekokray_megamenu_status = 1)</p>';

    // 3. Verify
    echo '<h3>Verification:</h3>';

    $status = $db->query("SELECT * FROM " . DB_PREFIX . "setting WHERE `key` = 'module_ekokray_megamenu_status'");
    if ($status->num_rows > 0 && $status->row['value'] == '1') {
        echo '<p class="success">✓ Module status confirmed: ENABLED</p>';
    } else {
        echo '<p class="error">✗ Module status not set correctly!</p>';
    }

    // Check instances
    $instances = $db->query("SELECT * FROM " . DB_PREFIX . "module WHERE `code` LIKE 'ekokray_megamenu%'");
    if ($instances->num_rows > 0) {
        echo '<p class="success">✓ Found ' . $instances->num_rows . ' module instance(s)</p>';
        foreach ($instances->rows as $inst) {
            echo '<pre>Module ID: ' . $inst['module_id'] . ', Name: ' . $inst['name'] . '</pre>';
        }
    } else {
        echo '<p class="error">⚠ No module instances found. You need to create one in admin panel.</p>';
    }

    echo '<div class="info">';
    echo '<h3>Success! Next steps:</h3>';
    echo '<ol>';
    echo '<li>Clear cache: Delete all files in <code>system/storage/cache/</code></li>';
    echo '<li>Refresh your website to see the module</li>';
    echo '<li>If module still doesn\'t appear, check admin panel: Design > Layouts > Home > Header Top</li>';
    echo '</ol>';
    echo '</div>';

    echo '<p><a href="' . $_SERVER['PHP_SELF'] . '"><button>Back</button></a></p>';
} else {
    ?>
        <div class="info">
            <p>Цей скрипт увімкне модуль <strong>ekokray_megamenu</strong> в системі OpenCart.</p>
            <p>Модуль має бути увімкнений глобально щоб завантажуватися через headertop контролер.</p>
        </div>

        <h3>Що зробить цей скрипт:</h3>
        <ol>
            <li>Зареєструє модуль в таблиці <code>oc_extension</code></li>
            <li>Встановить <code>module_ekokray_megamenu_status = 1</code> в <code>oc_setting</code></li>
            <li>Перевірить що все працює правильно</li>
        </ol>

        <form method="post">
            <button type="submit" name="enable">Enable Module Now</button>
        </form>

        <hr style="margin: 30px 0;">

        <h3>Альтернатива: SQL запит</h3>
        <p>Або виконайте цей SQL запит в phpMyAdmin:</p>
        <pre>-- Enable ekokray_megamenu module
INSERT IGNORE INTO oc_extension (type, code)
VALUES ('module', 'ekokray_megamenu');

DELETE FROM oc_setting WHERE `key` = 'module_ekokray_megamenu_status';

INSERT INTO oc_setting (store_id, `code`, `key`, `value`, serialized)
VALUES (0, 'module_ekokray_megamenu', 'module_ekokray_megamenu_status', '1', 0);</pre>
    <?php
}
?>

    </div>
</body>
</html>
