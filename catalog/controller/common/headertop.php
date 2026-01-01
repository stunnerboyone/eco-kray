<?php
class ControllerCommonheadertop extends Controller {
	public function index() {
		$this->load->model('design/layout');
		
		if (isset($this->request->get['route'])) {
			$route = (string)$this->request->get['route'];
		} else {
			$route = 'common/home';
		}

		$layout_id = 0;

		if ($route == 'product/category' && isset($this->request->get['path'])) {
			$this->load->model('catalog/category');
			
			$path = explode('_', (string)$this->request->get['path']);

			$layout_id = $this->model_catalog_category->getCategoryLayoutId(end($path));
		}

		if ($route == 'product/product' && isset($this->request->get['product_id'])) {
			$this->load->model('catalog/product');
			
			$layout_id = $this->model_catalog_product->getProductLayoutId($this->request->get['product_id']);
		}

		if ($route == 'information/information' && isset($this->request->get['information_id'])) {
			$this->load->model('catalog/information');
			
			$layout_id = $this->model_catalog_information->getInformationLayoutId($this->request->get['information_id']);
		}

		if (!$layout_id) {
			$layout_id = $this->model_design_layout->getLayout($route);
		}

		if (!$layout_id) {
			$layout_id = $this->config->get('config_layout_id');
		}
		
		$this->load->model('setting/module');

		$data['modules'] = array();		
		
		$modules = $this->model_design_layout->getLayoutModules($layout_id, 'headertop');

		// DEBUG: Add HTML comment with module info
		$debug = "\n<!-- DEBUG HEADERTOP: Layout ID = $layout_id, Modules found = " . count($modules) . " -->\n";

		foreach ($modules as $module) {
			$part = explode('.', $module['code']);

			$debug .= "<!-- Module code: {$module['code']} -->\n";

			if (isset($part[0]) && $this->config->get('module_' . $part[0] . '_status')) {
				$module_data = $this->load->controller('extension/module/' . $part[0]);

				if ($module_data) {
					$data['modules'][] = $module_data;
				}
			}

			if (isset($part[1])) {
				$setting_info = $this->model_setting_module->getModule($part[1]);

				if ($setting_info) {
					$debug .= "<!-- Module instance found, status = " . (isset($setting_info['status']) ? $setting_info['status'] : 'not set') . " -->\n";

					if (isset($setting_info['status']) && $setting_info['status']) {
						$output = $this->load->controller('extension/module/' . $part[0], $setting_info);

						if ($output) {
							$data['modules'][] = $output;
							$debug .= "<!-- Module loaded successfully -->\n";
						} else {
							$debug .= "<!-- WARNING: Controller returned empty -->\n";
						}
					} else {
						$debug .= "<!-- WARNING: Module instance is DISABLED -->\n";
					}
				} else {
					$debug .= "<!-- WARNING: Module instance NOT FOUND -->\n";
				}
			}
		}

		$data['modules'][] = $debug;

		return $this->load->view('common/headertop', $data); 
	}
}