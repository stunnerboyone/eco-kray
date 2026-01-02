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

		$categories = $this->model_catalog_category->getCategories(0);
		error_log("WD_CATEGORY_LIST DEBUG - getCategories(0) returned: " . count($categories) . " categories");

		if (!$setting['limit']) {
			$setting['limit'] = 4;
		}

		// Determine which categories to display
		if (!empty($setting['category'])) {
			error_log("WD_CATEGORY_LIST DEBUG - setting['category']: " . json_encode($setting['category']));
			$category_ids = array_slice($setting['category'], 0, (int)$setting['limit']);
			error_log("WD_CATEGORY_LIST DEBUG - Processing " . count($category_ids) . " categories from settings");
		} else {
			error_log("WD_CATEGORY_LIST DEBUG - setting['category'] is EMPTY, using top categories");
			// Use top-level categories if no specific categories are set
			$category_ids = array();
			foreach (array_slice($categories, 0, (int)$setting['limit']) as $cat) {
				$category_ids[] = $cat['category_id'];
			}
			error_log("WD_CATEGORY_LIST DEBUG - Using " . count($category_ids) . " top categories: " . json_encode($category_ids));
		}

		if (!empty($category_ids)) {
			foreach ($category_ids as $category_id) {
				error_log("WD_CATEGORY_LIST DEBUG - Loading category_id: " . $category_id);
				$category_info = $this->model_catalog_category->getCategory($category_id);

				if ($category_info) {
					error_log("WD_CATEGORY_LIST DEBUG - Category loaded: " . $category_info['name']);
				} else {
					error_log("WD_CATEGORY_LIST DEBUG - Category NOT found for ID: " . $category_id);
				}

				$children_data = array();

				if ($category_info) {

					$children = $this->model_catalog_category->getCategories($category_info['category_id']);

					foreach($children as $child) {
						$filter_data = $this->model_catalog_product->getTotalProducts(array('filter_category_id' => $child['category_id'], 'filter_sub_category' => true));


						$childs_data = array();
						$child_2 = $this->model_catalog_category->getCategories($child['category_id']);

						foreach ($child_2 as $childs) {
							$filter_data = array(
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

					if ($category_info['image']) {
						$image = $this->model_tool_image->resize($category_info['image'], $setting['width'], $setting['height']);
					} else {
						$image = $this->model_tool_image->resize('placeholder.png', $setting['width'], $setting['height']);
					}

					$data['categories'][] = array(
						'category_id'  => $category_info['category_id'],
						'thumb'       => $image,
						'name'        => $category_info['name'],
						'product_count' => $this->config->get('config_product_count') ? ' ' . $this->model_catalog_product->getTotalProducts($filter_data) . '' : '',
						'children'    => $children_data,
						'description' => utf8_substr(strip_tags(html_entity_decode($category_info['description'], ENT_QUOTES, 'UTF-8')), 0, $this->config->get('config_product_description_length')) . '..',
						'href'        => $this->url->link('product/category', 'path=' . $category_info['category_id'])
					);
				}
			}
		}

		error_log("WD_CATEGORY_LIST DEBUG - Final data['categories'] count: " . count($data['categories']));
		error_log("WD_CATEGORY_LIST DEBUG - Final data['categories']: " . json_encode($data['categories']));

		return $this->load->view('extension/module/wd_category_list', $data);
	}
}
