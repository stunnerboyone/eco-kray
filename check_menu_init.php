<?php
/**
 * Web-accessible script to check WD Megamenu initialization
 * Access via: http://your-domain.com/check_menu_init.php
 */

// Bootstrap OpenCart
require_once('index.php');

// Create a simple controller to check module status
class ControllerCheckMenuInit extends Controller {
    public function index() {
        header('Content-Type: text/html; charset=utf-8');

        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>WD Megamenu Check</title>';
        echo '<style>body{font-family:monospace;padding:20px;} .ok{color:green;} .error{color:red;} .warning{color:orange;}</style></head><body>';
        echo '<h1>WD Megamenu Module Initialization Check</h1>';

        // 1. Check global module status in config
        echo '<h2>1. Global Module Status</h2>';
        $status = $this->config->get('module_wd_megamenu_status');
        if ($status) {
            echo '<p class="ok">✓ Module is ENABLED globally (module_wd_megamenu_status = ' . $status . ')</p>';
        } else {
            echo '<p class="error">✗ Module is DISABLED or NOT SET in config (module_wd_megamenu_status = ' . var_export($status, true) . ')</p>';
            echo '<p class="warning">⚠ This is likely THE PROBLEM! Module needs to be enabled.</p>';
        }

        // 2. Check if extension is registered
        echo '<h2>2. Extension Registration</h2>';
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "extension WHERE type = 'module' AND code = 'wd_megamenu'");
        if ($query->num_rows > 0) {
            echo '<p class="ok">✓ Extension registered in oc_extension table</p>';
        } else {
            echo '<p class="error">✗ Extension NOT registered in oc_extension table</p>';
        }

        // 3. Check module instances
        echo '<h2>3. Module Instances</h2>';
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "module WHERE code LIKE 'wd_megamenu%'");
        if ($query->num_rows > 0) {
            echo '<p class="ok">✓ Found ' . $query->num_rows . ' module instance(s):</p><ul>';
            foreach ($query->rows as $row) {
                $settings = json_decode($row['setting'], true);
                $inst_status = isset($settings['status']) && $settings['status'] ? 'ENABLED' : 'DISABLED';
                $menu_id = isset($settings['menu']) ? $settings['menu'] : 'NOT SET';

                echo '<li>ID: ' . $row['module_id'] . ', Name: ' . $row['name'] . ', Status: <strong>' . $inst_status . '</strong>, Menu ID: ' . $menu_id . '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p class="error">✗ No module instances found</p>';
        }

        // 4. Check layout assignments
        echo '<h2>4. Layout Assignments (headertop position)</h2>';
        $query = $this->db->query("SELECT lm.*, l.name as layout_name FROM " . DB_PREFIX . "layout_module lm LEFT JOIN " . DB_PREFIX . "layout l ON lm.layout_id = l.layout_id WHERE lm.position = 'headertop' ORDER BY lm.sort_order");
        if ($query->num_rows > 0) {
            echo '<p class="ok">✓ Found ' . $query->num_rows . ' module(s) in headertop:</p><ul>';
            foreach ($query->rows as $row) {
                $highlight = (strpos($row['code'], 'wd_megamenu') !== false) ? ' style="background:yellow;"' : '';
                echo '<li' . $highlight . '>Layout: ' . $row['layout_name'] . ' (' . $row['layout_id'] . '), Code: <strong>' . $row['code'] . '</strong>, Sort: ' . $row['sort_order'] . '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p class="warning">⚠ No modules assigned to headertop position</p>';
        }

        // 5. Check menus in database
        echo '<h2>5. Menus in Database</h2>';
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "megamenu");
        if ($query->num_rows > 0) {
            echo '<p class="ok">✓ Found ' . $query->num_rows . ' menu(s):</p><ul>';
            foreach ($query->rows as $row) {
                $menu_status = $row['status'] ? 'ENABLED' : 'DISABLED';
                echo '<li>ID: ' . $row['menu_id'] . ', Name: ' . $row['name'] . ', Type: ' . $row['menu_type'] . ', Status: <strong>' . $menu_status . '</strong></li>';
            }
            echo '</ul>';
        } else {
            echo '<p class="error">✗ No menus found</p>';
        }

        // 6. Solution
        echo '<h2>Solution</h2>';
        if (!$status) {
            echo '<p class="warning"><strong>Main Problem:</strong> Module is not enabled globally.</p>';
            echo '<p>To fix this, run this SQL query in your database:</p>';
            echo '<pre style="background:#f0f0f0;padding:10px;border:1px solid #ccc;">';
            echo "-- Enable WD Megamenu module\n";
            echo "INSERT INTO " . DB_PREFIX . "setting (store_id, `code`, `key`, `value`, serialized)\n";
            echo "VALUES (0, 'module_wd_megamenu', 'module_wd_megamenu_status', '1', 0)\n";
            echo "ON DUPLICATE KEY UPDATE `value` = '1';";
            echo '</pre>';
            echo '<p>Or install the module from admin panel: Extensions → Extensions → Modules → WD Megamenu → Install button</p>';
        }

        echo '</body></html>';

        // Stop execution
        exit;
    }
}

// Run the check
$controller = new ControllerCheckMenuInit(new Registry());
$controller->index();
