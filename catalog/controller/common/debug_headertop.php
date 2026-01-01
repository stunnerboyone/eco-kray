<?php
class ControllerCommonDebugHeadertop extends Controller {
    public function index() {
        header('Content-Type: text/plain; charset=utf-8');

        $this->load->model('design/layout');

        $route = 'common/home';
        $layout_id = 0;

        if (!$layout_id) {
            $layout_id = $this->model_design_layout->getLayout($route);
        }

        if (!$layout_id) {
            $layout_id = $this->config->get('config_layout_id');
        }

        echo "=== Debug Headertop Modules ===\n\n";
        echo "Layout ID: " . $layout_id . "\n\n";

        $this->load->model('setting/module');

        $modules = $this->model_design_layout->getLayoutModules($layout_id, 'headertop');

        echo "Modules found for headertop position: " . count($modules) . "\n\n";

        foreach ($modules as $key => $module) {
            echo "--- Module #" . ($key + 1) . " ---\n";
            echo "Code: " . $module['code'] . "\n";

            $part = explode('.', $module['code']);
            echo "Part[0] (module name): " . (isset($part[0]) ? $part[0] : 'NOT SET') . "\n";
            echo "Part[1] (module ID): " . (isset($part[1]) ? $part[1] : 'NOT SET') . "\n";

            if (isset($part[0])) {
                $status_key = 'module_' . $part[0] . '_status';
                $status = $this->config->get($status_key);
                echo "Module status config ($status_key): " . ($status ? 'ENABLED' : 'DISABLED/NOT SET') . "\n";
            }

            if (isset($part[1])) {
                $setting_info = $this->model_setting_module->getModule($part[1]);

                if ($setting_info) {
                    echo "Module instance found in database\n";
                    echo "Instance name: " . (isset($setting_info['name']) ? $setting_info['name'] : 'NOT SET') . "\n";
                    echo "Instance status: " . (isset($setting_info['status']) && $setting_info['status'] ? 'ENABLED' : 'DISABLED') . "\n";
                    echo "Instance code: " . (isset($setting_info['code']) ? $setting_info['code'] : 'NOT SET') . "\n";

                    if (isset($setting_info['menu'])) {
                        echo "Menu ID: " . $setting_info['menu'] . "\n";

                        // Try to load the menu
                        $this->load->model('webdigify/wd_megamenu');
                        $menu = $this->model_webdigify_wd_megamenu->getMenuById($setting_info['menu']);

                        if ($menu) {
                            echo "Menu found in database\n";
                            echo "Menu name: " . (isset($menu['name']) ? $menu['name'] : 'NOT SET') . "\n";
                            echo "Menu status: " . (isset($menu['status']) && $menu['status'] ? 'ENABLED' : 'DISABLED') . "\n";
                            echo "Menu type: " . (isset($menu['menu_type']) ? $menu['menu_type'] : 'NOT SET') . "\n";
                        } else {
                            echo "WARNING: Menu ID " . $setting_info['menu'] . " NOT FOUND in database!\n";
                        }
                    } else {
                        echo "WARNING: No menu ID set in module settings\n";
                    }

                    // Try to load the controller
                    echo "\nTrying to load controller...\n";
                    try {
                        $output = $this->load->controller('extension/module/' . $part[0], $setting_info);
                        if ($output) {
                            echo "Controller loaded successfully\n";
                            echo "Output length: " . strlen($output) . " characters\n";
                            echo "Output preview: " . substr(strip_tags($output), 0, 100) . "...\n";
                        } else {
                            echo "WARNING: Controller returned empty output\n";
                        }
                    } catch (Exception $e) {
                        echo "ERROR loading controller: " . $e->getMessage() . "\n";
                    }
                } else {
                    echo "WARNING: Module instance ID " . $part[1] . " NOT FOUND in database!\n";
                }
            }

            echo "\n";
        }

        echo "\n=== End Debug ===\n";
        exit;
    }
}
