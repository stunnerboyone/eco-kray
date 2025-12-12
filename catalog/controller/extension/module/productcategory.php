<?php
class ControllerExtensionModuleProductcategory extends Controller {
	public function index($setting) {
		$this->load->language('extension/module/productcategory');

		$data['lang'] = $this->language->get('code');

		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_tax'] = $this->language->get('text_tax');
		$data['button_cart'] = $this->language->get('button_cart');
		$data['button_wishlist'] = $this->language->get('button_wishlist');
		$data['button_compare'] = $this->language->get('button_compare');
		$data['bannersecond'] = $this->load->controller('common/bannersecond');

		$this->load->model('catalog/category');

		$this->load->model('catalog/product');

		$this->load->model('tool/image');

		$data['categories'] = array();

		if (!$setting['limit']) {
			$setting['limit'] = 4;
		}


		$data['slide_value'] = $setting['list_grid'];


		if (!empty($setting['category'])) {
			$categories = array_slice($setting['category'], 0, (int)$setting['limit']);
			$count = 0;
			foreach ($categories as $category_id) {
				$category_info = $this->model_catalog_category->getCategory($category_id);
				if ($category_info) {

					$products = array();

					$filter_data = array(
						'filter_category_id' => $category_id,
						'filter_sub_category' => false,
						'start'              => 0 * $setting['product_limit'],
						'limit'              => $setting['product_limit'],
					);

					$product_total = $this->model_catalog_product->getTotalProducts($filter_data);

					$results = $this->model_catalog_product->getProducts($filter_data);


					foreach ($results as $result) {
						if($result){
						if ($result['image']) {
							$image = $this->model_tool_image->resize($result['image'], $setting['width'], $setting['height']);
						} else {
							$image = $this->model_tool_image->resize('placeholder.png', $setting['width'], $setting['height']);
						}
		
						//added for image swap
					
						$images = $this->model_catalog_product->getProductImages($result['product_id']);
		
						if(isset($images[0]['image']) && !empty($images)){
						 $images = $images[0]['image']; 
						   }else
						   {
						   $images = $image;
						   }
						
					//
						if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
							$price = $this->currency->format($this->tax->calculate($result['price'], $result['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
						} else {
							$price = false;
						}

						if ((float)$result['special']) {
							$special = $this->currency->format($this->tax->calculate($result['special'], $result['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
						} else {
							$special = false;
						}

						if ($this->config->get('config_tax')) {
							$tax = $this->currency->format((float)$result['special'] ? $result['special'] : $result['price'], $this->session->data['currency']);
						} else {
							$tax = false;
						}

						if ($this->config->get('config_review_status')) {
							$rating = (int)$result['rating'];
						} else {
							$rating = false;
						}

						$products[] = array(
							'product_id'  => $result['product_id'],
							'thumb'       => $image,
							'name'        => $result['name'],
							'qty'    	  => $result['quantity'],
							'brand'        => $result['manufacturer'],
							'review'        => $result['reviews'],
							'description' => utf8_substr(trim(strip_tags(html_entity_decode($result['description'], ENT_QUOTES, 'UTF-8'))), 0, $this->config->get('theme_' . $this->config->get('config_theme') . '_product_description_length')) . '..',
							'price'       => $price,
							'special'     => $special,
							'tax'         => $tax,
							'rating'      => $rating,
							'percentsaving' => round((($result['price'] - $result['special'])/$result['price'])*100, 0),
							'href'        => $this->url->link('product/product', 'product_id=' . $result['product_id']),
							'quick'        => $this->url->link('product/quick_view','&product_id=' . $result['product_id']),
							'thumb_swap'  => $this->model_tool_image->resize($images , $setting['width'], $setting['height'])
							);
						}
				 	}
					$data['categories'][] = array(
						'category_id' => $category_info['category_id'],
						'products'	  => $products,
						'image' 	  => $this->model_tool_image->resize($category_info['image'], $setting['width'], $setting['height']),
						'description' => utf8_substr(strip_tags(html_entity_decode($category_info['description'], ENT_QUOTES, 'UTF-8')), 0, 100),
						'name'        => str_replace(' ', '', $category_info['name']),
						'nameview'    => $category_info['name'],
						'href'        => $this->url->link('product/category', 'path=' . $category_info['category_id']),
						'count'		  => $count
					);
				}
				$count++;
			}
			
		}
		if ($data['categories']) {

				return $this->load->view('extension/module/productcategory', $data);

		}
	}
}
