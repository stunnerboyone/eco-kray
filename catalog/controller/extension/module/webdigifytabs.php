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
				// Skip products with placeholder image on homepage
				if (empty($result['image']) || $result['image'] === 'placeholder.png' || strpos($result['image'], 'placeholder') !== false || strpos($result['image'], 'no_image') !== false) {
					continue;
				}
				$image = $this->model_tool_image->resize($result['image'], $setting['width'], $setting['height']);

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
				// Skip products with placeholder image on homepage
				if (empty($result['image']) || $result['image'] === 'placeholder.png' || strpos($result['image'], 'placeholder') !== false || strpos($result['image'], 'no_image') !== false) {
					continue;
				}
				$image = $this->model_tool_image->resize($result['image'], $setting['width'], $setting['height']);

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
				// Skip products with placeholder image on homepage
				if (empty($result['image']) || $result['image'] === 'placeholder.png' || strpos($result['image'], 'placeholder') !== false || strpos($result['image'], 'no_image') !== false) {
					continue;
				}
				$image = $this->model_tool_image->resize($result['image'], $setting['width'], $setting['height']);

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
	

		// Скільки товарів показувати у вкладці після всіх фільтрів
		$final_limit = 8; // 8 товарів = 2 ряди по 4 колонки на десктопі

		// Функція для заповнення недостатньої кількості товарів з категорій
		$fillProductsFromCategories = function(&$products, $limit) use ($setting) {
			$current_count = count($products);

			if ($current_count >= $limit) {
				return; // Вже достатньо товарів
			}

			$needed = $limit - $current_count;

			// Отримуємо ID товарів які вже є, щоб не дублювати
			$existing_ids = array_column($products, 'product_id');

			// Отримуємо основні категорії (parent_id = 92)
			$categories = $this->model_catalog_category->getCategories(92);

			$additional_products = array();

			foreach ($categories as $category) {
				if (count($additional_products) >= $needed) {
					break;
				}

				// Отримуємо товари з категорії
				$category_products = $this->model_catalog_product->getProducts(array(
					'filter_category_id' => $category['category_id'],
					'limit' => 2 // По 2 товари з кожної категорії
				));

				foreach ($category_products as $result) {
					// Пропускаємо якщо товар вже є або placeholder
					if (in_array($result['product_id'], $existing_ids)) {
						continue;
					}

					if (empty($result['image']) || $result['image'] === 'placeholder.png' || strpos($result['image'], 'placeholder') !== false || strpos($result['image'], 'no_image') !== false) {
						continue;
					}

					$image = $this->model_tool_image->resize($result['image'], $setting['width'], $setting['height']);

					// Image swap
					$images = $this->model_catalog_product->getProductImages($result['product_id']);
					if(isset($images[0]['image']) && !empty($images)){
						$images = $images[0]['image'];
					} else {
						$images = $image;
					}

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

					$additional_products[] = array(
						'product_id'  => $result['product_id'],
						'thumb'       => $image,
						'name'        => $result['name'],
						'brand'       => $result['manufacturer'],
						'catname'     => $category['name'],
						'review'      => $result['reviews'],
						'qty'         => $result['quantity'],
						'description' => utf8_substr(strip_tags(html_entity_decode($result['description'], ENT_QUOTES, 'UTF-8')), 0, $this->config->get($this->config->get('config_theme') . '_product_description_length')) . '..',
						'price'       => $price,
						'special'     => $special,
						'tax'         => $tax,
						'rating'      => $rating,
						'percentsaving' => ($result['price'] > 0 && $result['special'] > 0) ? round((($result['price'] - $result['special']) / $result['price']) * 100, 0) : 0,
						'href'        => $this->url->link('product/product', 'product_id=' . $result['product_id']),
						'quick'       => $this->url->link('product/quick_view','&product_id=' . $result['product_id']),
						'thumb_swap'  => $this->model_tool_image->resize($images , $setting['width'], $setting['height'])
					);

					$existing_ids[] = $result['product_id'];

					if (count($additional_products) >= $needed) {
						break 2;
					}
				}
			}

			// Додаємо додаткові товари до основного масиву
			$products = array_merge($products, $additional_products);
		};

		// Заповнюємо кожну вкладку товарами з категорій якщо потрібно
		if (!empty($data['specialproducts'])) {
			$fillProductsFromCategories($data['specialproducts'], $final_limit);
			$data['specialproducts'] = array_slice($data['specialproducts'], 0, $final_limit);
		}

		if (!empty($data['latestproducts'])) {
			$fillProductsFromCategories($data['latestproducts'], $final_limit);
			$data['latestproducts'] = array_slice($data['latestproducts'], 0, $final_limit);
		}

		if (!empty($data['bestsellersproducts'])) {
			$fillProductsFromCategories($data['bestsellersproducts'], $final_limit);
			$data['bestsellersproducts'] = array_slice($data['bestsellersproducts'], 0, $final_limit);
		}


			return $this->load->view('extension/module/webdigifytabs', $data);



	}
}