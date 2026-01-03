<?php
class ControllerExtensionModuleWDCategoryList extends Controller {
	public function index($setting) {
		error_log("WD_CATEGORY_LIST DEBUG - index() called with setting: " . json_encode($setting));

		$this->load->language('extension/module/wd_category_list');

		$data['heading_title1'] = $this->language->get('heading_title1');
		$data['text_tax'] = $this->language->get('text_tax');
		$data['button_cart'] = $this->language->get('button_cart');
		$data['button_wishlist'] = $this->language->get('button_wishlist');
		$data['button_compare'] = $this->language->get('button_compare');

		$this->load->model('catalog/category');
		$this->load->model('catalog/product');
		$this->load->model('tool/image');

		$data['categories'] = array();

		// DEBUG: Add setting info to template
		$data['debug_setting'] = json_encode($setting, JSON_PRETTY_PRINT);

		if (!isset($setting['limit']) || !$setting['limit']) {
			$setting['limit'] = 4;
		}

		// Get categories from settings or load from parent_id=92
		if (!empty($setting['category'])) {
			$data['debug_path'] = 'IF BLOCK: Using categories from settings';
			error_log("WD_CATEGORY_LIST DEBUG - Using categories from settings");
			error_log("WD_CATEGORY_LIST DEBUG - setting['category'] = " . json_encode($setting['category']));
			$category_ids = array_slice($setting['category'], 0, (int)$setting['limit']);
			error_log("WD_CATEGORY_LIST DEBUG - category_ids to load: " . json_encode($category_ids));

			// Load each category
			foreach ($category_ids as $category_id) {
				error_log("WD_CATEGORY_LIST DEBUG - Loading category_id: " . $category_id);
				$category_info = $this->model_catalog_category->getCategory($category_id);
				error_log("WD_CATEGORY_LIST DEBUG - getCategory($category_id) returned: " . json_encode($category_info));
				if ($category_info) {
					error_log("WD_CATEGORY_LIST DEBUG - Category keys from getCategory: " . implode(", ", array_keys($category_info)));
					$this->processCategoryData($category_info, $setting, $data);
				} else {
					error_log("WD_CATEGORY_LIST DEBUG - getCategory($category_id) returned EMPTY/FALSE");
				}
			}
		} else {
			$data['debug_path'] = 'ELSE BLOCK: Loading categories from parent_id=92';
			error_log("WD_CATEGORY_LIST DEBUG - Loading categories from parent_id=92");
			$categories = $this->model_catalog_category->getCategories(92);
			error_log("WD_CATEGORY_LIST DEBUG - getCategories(92) returned: " . count($categories) . " categories");

			// Use categories directly without re-loading
			$selected_categories = array_slice($categories, 0, (int)$setting['limit']);
			// TEMPORARY DEBUG - output to HTML
			$data["debug_categories_raw"] = json_encode($selected_categories, JSON_PRETTY_PRINT);

			foreach ($selected_categories as $category_info) {
				error_log("WD_CATEGORY_LIST DEBUG - Category keys: " . implode(", ", array_keys($category_info)));
				error_log("WD_CATEGORY_LIST DEBUG - Category data: " . json_encode($category_info));
				$this->processCategoryData($category_info, $setting, $data);
			}
		}

		error_log("WD_CATEGORY_LIST DEBUG - Final data['categories'] count: " . count($data['categories']));

		return $this->load->view('extension/module/wd_category_list', $data);
	}

	private function processCategoryData($category_info, $setting, &$data) {
		error_log("WD_CATEGORY_LIST DEBUG - processCategoryData() received category_id: " . (isset($category_info['category_id']) ? $category_info['category_id'] : 'NOT SET'));

		$children_data = array();
		$filter_data = array();

		// Get children categories
		$children = $this->model_catalog_category->getCategories($category_info['category_id']);

		foreach($children as $child) {
			$filter_data = $this->model_catalog_product->getTotalProducts(array('filter_category_id' => $child['category_id'], 'filter_sub_category' => true));

			$childs_data = array();
			$child_2 = $this->model_catalog_category->getCategories($child['category_id']);

			foreach ($child_2 as $childs) {
				$filter_data_child = array(
					'filter_category_id'  => $childs['category_id'],
					'filter_sub_category' => true,
					'product_count' => $this->config->get('config_product_count') ? ' ' . $this->model_catalog_product->getTotalProducts($filter_data) . '' : ''
				);

				$childs_data[] = array(
					'name'  => $childs['name'],
					'href'  => $this->url->link('product/category', 'path=' . $category_info['category_id'] . '_' . $child['category_id'] . '_' . $childs['category_id'])
				);
			}

			$children_data[] = array(
				'category_id' => $child['category_id'],
				'name' => $child['name'],
				'childs' => $childs_data,
				'href' => $this->url->link('product/category', 'path=' . $category_info['category_id'] . '_' . $child['category_id'])
			);
		}

		// Resize image
		if ($category_info['image']) {
			$image = $this->model_tool_image->resize($category_info['image'], $setting['width'], $setting['height']);
		} else {
			$image = $this->model_tool_image->resize('placeholder.png', $setting['width'], $setting['height']);
		}

		// Add to data array
		$data['categories'][] = array(
			'category_id'  => $category_info['category_id'],
			'thumb'       => $image,
			'name'        => $category_info['name'],
			'product_count' => $this->config->get('config_product_count') && !empty($filter_data) ? ' ' . $this->model_catalog_product->getTotalProducts($filter_data) . '' : '',
			'children'    => $children_data,
			'description' => isset($category_info['description']) ? utf8_substr(strip_tags(html_entity_decode($category_info['description'], ENT_QUOTES, 'UTF-8')), 0, 100) . '..' : '',
			'href'        => $this->url->link('product/category', 'path=' . $category_info['category_id'])
		);

		error_log("WD_CATEGORY_LIST DEBUG - Added category: " . $category_info['name'] . " (ID: " . $category_info['category_id'] . ")");
	}
}
