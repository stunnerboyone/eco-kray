<?php
class ControllerExtensionModuleWebdigifytabs extends Controller {
	public function index($setting) {
		$this->load->language('extension/module/webdigifytabs');

		$this->load->model('catalog/product');
		$this->load->model('catalog/category');
		$this->load->model('tool/image');

		$data['bannerfirst'] = $this->load->controller('common/bannerfirst');

		// special product
		
		$data['specialproducts'] = array();

		$filter_data = array(
			'sort'  => 'pd.name',
			'order' => 'ASC',
			'start' => 0,
			'limit' => $setting['limit']
		);

		$results = $this->model_catalog_product->getProductSpecials($filter_data);

		if ($results) {
			foreach ($results as $result) {
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
					$rating = $result['rating'];
				} else {
					$rating = false;
				}

				$categories = $this->model_catalog_product->getCategories($result['product_id']);
				if ($categories){
					$categories_info = $this->model_catalog_category->getCategory($categories[0]['category_id']);
				}

				$data['specialproducts'][] = array(
					'product_id'  => $result['product_id'],
					'thumb'       => $image,
					'name'        => $result['name'],
					'brand'        => $result['manufacturer'],
					'catname'       => $categories_info['name'],
					'review'        => $result['reviews'],
					'qty'    	  => $result['quantity'],
					'description' => utf8_substr(strip_tags(html_entity_decode($result['description'], ENT_QUOTES, 'UTF-8')), 0, $this->config->get($this->config->get('config_theme') . '_product_description_length')) . '..',
					'price'       => $price,
					'special'     => $special,
					'tax'         => $tax,
					'rating'      => $rating,
					'percentsaving'  => round((($result['price'] - $result['special'])/$result['price'])*100, 0),
					'href'        => $this->url->link('product/product', 'product_id=' . $result['product_id']),
					'quick'        => $this->url->link('product/quick_view','&product_id=' . $result['product_id']),
					'thumb_swap'  => $this->model_tool_image->resize($images , $setting['width'], $setting['height'])
				);
			}
			
		}
		
		//latest product
		
		$data['latestproducts'] = array();

		$filter_data = array(
			'sort'  => 'p.date_added',
			'order' => 'DESC',
			'start' => 0,
			'limit' => $setting['limit']
		);

		$results = $this->model_catalog_product->getLatestProducts($setting['limit']);

		if ($results) {
			foreach ($results as $result) {
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
					$rating = $result['rating'];
				} else {
					$rating = false;
				}
				$categories = $this->model_catalog_product->getCategories($result['product_id']);
				if ($categories){
					$categories_info = $this->model_catalog_category->getCategory($categories[0]['category_id']);
				}

				$data['latestproducts'][] = array(
					'product_id'  => $result['product_id'],
					'thumb'       => $image,
					'name'        => $result['name'],
					'brand'        => $result['manufacturer'],
					'catname' => isset($categories_info['name']) ? $categories_info['name'] : '',
					'review'        => $result['reviews'],
					'qty'    	  => $result['quantity'],
					'description' => utf8_substr(strip_tags(html_entity_decode($result['description'], ENT_QUOTES, 'UTF-8')), 0, $this->config->get($this->config->get('config_theme') . '_product_description_length')) . '..',
					'price'       => $price,
					'special'     => $special,
					'tax'         => $tax,
					'rating'      => $rating,
					'percentsaving' => ($result['price'] > 0 && $result['special'] > 0) ? round((($result['price'] - $result['special']) / $result['price']) * 100, 0) : 0,					
					'href'        => $this->url->link('product/product', 'product_id=' . $result['product_id']),
					'quick'        => $this->url->link('product/quick_view','&product_id=' . $result['product_id']),
					'thumb_swap'  => $this->model_tool_image->resize($images , $setting['width'], $setting['height'])
				);
			}
		}
		
		// bestsellets
		
		$data['bestsellersproducts'] = array();

		$results = $this->model_catalog_product->getBestSellerProducts($setting['limit']);

		if ($results) {
			foreach ($results as $result) {
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
					$rating = $result['rating'];
				} else {
					$rating = false;
				}

				$this->load->model('catalog/product');
				$this->load->model('catalog/category');
							
				$category_name = '';
				$categories = $this->model_catalog_product->getCategories((int)$result['product_id']);
							
				if (!empty($categories)) {
				    // можна взяти першу категорію або пройтись по всіх і взяти першу з назвою
				    foreach ($categories as $cat) {
				        if (empty($cat['category_id'])) continue;
				        $info = $this->model_catalog_category->getCategory((int)$cat['category_id']);
				        if (!empty($info) && !empty($info['name'])) {
				            $category_name = $info['name'];
				            break;
				        }
				    }
				}


				$data['bestsellersproducts'][] = array(
					'product_id'  => $result['product_id'],
					'thumb'       => $image,
					'name'        => $result['name'],
					'brand'        => $result['manufacturer'],

					'catname' => $category_name,
					'review'        => $result['reviews'],
					'qty'    	  => $result['quantity'],
					'description' => utf8_substr(strip_tags(html_entity_decode($result['description'], ENT_QUOTES, 'UTF-8')), 0, $this->config->get($this->config->get('config_theme') . '_product_description_length')) . '..',
					'price'       => $price,
					'special'     => $special,
					'tax'         => $tax,
					'rating'      => $rating,
					'percentsaving' => ($result['price'] > 0) ? round((($result['price'] - $result['special']) / $result['price']) * 100, 0) : 0,
					'href'        => $this->url->link('product/product', 'product_id=' . $result['product_id']),
					'quick'        => $this->url->link('product/quick_view','&product_id=' . $result['product_id']),
					'thumb_swap'  => $this->model_tool_image->resize($images , $setting['width'], $setting['height'])
				);
			}
		}
	
			return $this->load->view('extension/module/webdigifytabs', $data); 
	}
}