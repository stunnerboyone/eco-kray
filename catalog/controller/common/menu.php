<?php
class ControllerCommonMenu extends Controller {
	public function index() {
		$this->load->language('common/menu');

		$this->load->model('catalog/category');

		$this->load->model('catalog/product');

		$this->load->model('catalog/manufacturer');

		$this->load->model('tool/image');
		$data['all_blogs'] = $this->url->link('information/blogger/blogs');
		$type="module";
				
		$result=$this->model_setting_extension->getExtensions($type);
			
		foreach($result as $result){
				if($result['code']==="blogger"){
						$data['blog_enable'] =1;
			  }
		}

 		$categories = $this->model_catalog_category->getCategories(0);

 		/*brands*/
		$results = $this->model_catalog_manufacturer->getManufacturers();
		//print_r($results);

		foreach ($results as $result) {

			$data['manufacturers'][] = array(
				'name' => $result['name'],
				'thumb' => 'image/'. $result['image'],
				'href' => $this->url->link('product/manufacturer/info', 'manufacturer_id=' . $result['manufacturer_id'])
			);
		}
		/*brands*/
		$data['home'] = $this->url->link('common/home');
		$data['text_blog'] = $this->language->get('text_blog');
		$data['all_blogs'] = $this->url->link('information/blogger/blogs');
		$data['login'] = $this->url->link('account/login', '', true);
		$data['manufacturer'] = $this->url->link('product/manufacturer');
		$data['contact'] = $this->url->link('information/contact');
		$data['newcollection'] = $this->url->link('product/special', '', true);
		$data['voucher'] = $this->url->link('account/voucher', '', true);
		$data['affiliate'] = $this->url->link('affiliate/login', '', true);
		$data['special'] = $this->url->link('product/special');
		
		$data['search'] = $this->load->controller('common/search');
		foreach ($categories as $category) {

	 			if($category['image']){
	   				 $image = $this->model_tool_image->resize($category['image'], 100, 100);
						} else {
	    			$image = false;
				}

			if ($category['top']) {
				// Level 2
				$children_data = array();

				$children = $this->model_catalog_category->getCategories($category['category_id']);

				foreach ($children as $child) {
					$filter_data = array(
						'filter_category_id'  => $child['category_id'],
						'filter_sub_category' => true
					);

					/* 2 Level Sub Categories START */
					$childs_data = array();
					$child_2 = $this->model_catalog_category->getCategories($child['category_id']);

					foreach ($child_2 as $childs) {
						$filter_data1 = array(
							'filter_category_id'  => $childs['category_id'],
							'filter_sub_category' => true
						);


						$childs_data[] = array(
						'name'  => $childs['name'] . ($this->config->get('config_product_count') ? ' (' . $this->model_catalog_product->getTotalProducts($filter_data1) . ')' : ''),
						'href'  => $this->url->link('product/category', 'path=' . $category['category_id'] . '_' . $child['category_id'] . '_' . $childs['category_id']));
					}
					/* 2 Level Sub Categories END */

					$children_data[] = array(
						'name'  => $child['name'] . ($this->config->get('config_product_count') ? ' (' . $this->model_catalog_product->getTotalProducts($filter_data) . ')' : ''),
						'childs' => $childs_data,
						'column'   => $child['column'] ? $child['column'] : 1,
						'image'  => $child['image'] ? $this->model_tool_image->resize($child['image'], 225, 155) : false,
						'href'  => $this->url->link('product/category', 'path=' . $category['category_id'] . '_' . $child['category_id'])
					);
				}

				// Level 1
				$data['categories'][] = array(
					'name'     => $category['name'],
					'children' => $children_data,
					'column'   => $category['column'] ? $category['column'] : 1,
					//'image'    => $image,
					'href'     => $this->url->link('product/category', 'path=' . $category['category_id'])
					
				);
			}
		}
		return $this->load->view('common/menu', $data);
	}
}
