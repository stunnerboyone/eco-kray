<?php
// Quick check of layout modules
error_reporting(0);

// Try to get DB connection from OpenCart
if (file_exists('system/startup.php')) {
    require_once('system/startup.php');

    // Start up
    $application_config = 'catalog';
    require_once(DIR_SYSTEM . 'framework.php');
}

// Простіше - підключимось напряму
$db_config = [
    'hostname' => 'localhost',
    'username' => 'root',
    'password' => '',
    'database' => 'ekokray',
    'prefix' => 'oc_'
];

// Try different connection methods
foreach (['localhost', '127.0.0.1', 'mysql'] as $host) {
    $mysqli = @new mysqli($host, $db_config['username'], $db_config['password'], $db_config['database']);
    if (!$mysqli->connect_error) {
        echo "Connected to: $host\n\n";
        break;
    }
}

if ($mysqli->connect_error) {
    echo "Не можу підключитись до БД. Спробуйте запустити в браузері.\n";
    echo "Або виконайте SQL запит вручну:\n\n";
    echo "SELECT lm.*, l.name as layout_name, m.name as module_name\n";
    echo "FROM oc_layout_module lm\n";
    echo "LEFT JOIN oc_layout l ON lm.layout_id = l.layout_id\n";
    echo "LEFT JOIN oc_module m ON SUBSTRING_INDEX(lm.code, '.', -1) = m.module_id\n";
    echo "WHERE lm.position = 'headertop'\n";
    echo "ORDER BY lm.sort_order;\n";
    exit;
}

echo "=== Модулі в позиції Header Top ===\n\n";

$result = $mysqli->query("
    SELECT lm.*, l.name as layout_name
    FROM oc_layout_module lm
    LEFT JOIN oc_layout l ON lm.layout_id = l.layout_id
    WHERE lm.position = 'headertop'
    ORDER BY lm.layout_id, lm.sort_order
");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "Layout: " . $row['layout_name'] . " (ID: " . $row['layout_id'] . ")\n";
        echo "  Code: " . $row['code'] . "\n";
        echo "  Sort Order: " . $row['sort_order'] . "\n";

        // Перевірити чи існує цей модуль
        if (strpos($row['code'], '.') !== false) {
            list($module_type, $module_id) = explode('.', $row['code']);

            // Перевірити в oc_module
            $mod = $mysqli->query("SELECT * FROM oc_module WHERE module_id = " . (int)$module_id);
            if ($mod && $mod->num_rows > 0) {
                $mod_data = $mod->fetch_assoc();
                echo "  Модуль: " . $mod_data['name'] . " (статус: " . ($mod_data['status'] ?? 'не встановлено') . ")\n";

                // Перевірити чи існують файли
                $controller_path = "catalog/controller/extension/module/$module_type.php";
                if (file_exists($controller_path)) {
                    echo "  ✓ Файл контролера існує\n";
                } else {
                    echo "  ✗ ФАЙЛ КОНТРОЛЕРА НЕ ІСНУЄ: $controller_path\n";
                    echo "  >>> ЦЕ ПРОБЛЕМА! Модуль в БД є, але файлів немає.\n";
                }
            } else {
                echo "  ✗ Модуль ID $module_id НЕ ЗНАЙДЕНО в oc_module\n";
            }
        }
        echo "\n";
    }
} else {
    echo "Модулів не знайдено в позиції headertop\n";
}

$mysqli->close();
