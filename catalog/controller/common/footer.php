<?php
class ControllerCommonFooter extends Controller {
	public function index() {
		$text_view_all_results = $this->config->get('module_wd_live_search_view_all_results');
		$lang_id = $this->config->get('config_language_id');

		if (is_array($text_view_all_results) && isset($text_view_all_results[$lang_id]['name'])) {
   			 $data['text_view_all_results'] = $text_view_all_results[$lang_id]['name'];
		} else {
    			$data['text_view_all_results'] = ''; // або якийсь дефолтний текст
		}


		$data['module_wd_live_search_status'] = $this->config->get('module_wd_live_search_status');
		$data['module_wd_live_search_show_image'] = $this->config->get('module_wd_live_search_show_image');
		$data['module_wd_live_search_show_price'] = $this->config->get('module_wd_live_search_show_price');
		$data['module_wd_live_search_show_description'] = $this->config->get('module_wd_live_search_show_description');
		$data['module_wd_live_search_min_length'] = $this->config->get('module_wd_live_search_min_length');
		$data['module_wd_live_search_href'] = $this->url->link('product/search', 'search=');

		$this->load->language('common/footer');

		$this->load->model('catalog/information');

		$data['informations'] = array();

		foreach ($this->model_catalog_information->getInformations() as $result) {
			if ($result['bottom']) {
				$data['informations'][] = array(
					'title' => $result['title'],
					'href'  => $this->url->link('information/information', 'information_id=' . $result['information_id'])
				);
			}
		}

		$data['contact'] = $this->url->link('information/contact');
		$data['return'] = $this->url->link('account/return/add', '', true);
		$data['sitemap'] = $this->url->link('information/sitemap');
		$data['tracking'] = $this->url->link('information/tracking');
		$data['manufacturer'] = $this->url->link('product/manufacturer');
		$data['voucher'] = $this->url->link('account/voucher', '', true);
		$data['affiliate'] = $this->url->link('affiliate/login', '', true);
		$data['special'] = $this->url->link('product/special');
		$data['account'] = $this->url->link('account/account', '', true);
		$data['order'] = $this->url->link('account/order', '', true);
		$data['wishlist'] = $this->url->link('account/wishlist', '', true);
		$data['newsletter'] = $this->url->link('account/newsletter', '', true);
		$data['all_blogs'] = $this->url->link('information/blogger/blogs');
		$data['text_blog'] = $this->language->get('text_blog');
		$data['language'] = $this->load->controller('common/language');
		$data['currency'] = $this->load->controller('common/currency');
		

		$data['powered'] = sprintf($this->language->get('text_powered'), $this->config->get('config_name'), date('Y', time()));
		
		$data['footerleft'] = $this->load->controller('common/footerleft');
		$data['footerright'] = $this->load->controller('common/footerright');
		$data['footerbottom'] = $this->load->controller('common/footerbottom');

		// Manufacture

		$this->load->model('catalog/manufacturer');
		
		$data['manufacturer_list'] = array();
		
		$manufacturers = $this->model_catalog_manufacturer->getManufacturers();
		
		foreach ($manufacturers as $manufacturer_list) {
			$data['manufacturer_list'][] = array(
				'name' => $manufacturer_list['name'],
				'href' => $this->url->link('product/manufacturer/info', 'manufacturer_id=' . $manufacturer_list['manufacturer_id'])
			);
		}
		//End Manufacure

		// Whos Online
		if ($this->config->get('config_customer_online')) {
			$this->load->model('tool/online');

			if (isset($this->request->server['REMOTE_ADDR'])) {
				$ip = $this->request->server['REMOTE_ADDR'];
			} else {
				$ip = '';
			}

			if (isset($this->request->server['HTTP_HOST']) && isset($this->request->server['REQUEST_URI'])) {
				$url = ($this->request->server['HTTPS'] ? 'https://' : 'http://') . $this->request->server['HTTP_HOST'] . $this->request->server['REQUEST_URI'];
			} else {
				$url = '';
			}

			if (isset($this->request->server['HTTP_REFERER'])) {
				$referer = $this->request->server['HTTP_REFERER'];
			} else {
				$referer = '';
			}

			$this->model_tool_online->addOnline($ip, $this->customer->getId(), $url, $referer);

		}

				$data['footerleft'] = $this->load->controller('common/footerleft');
		$data['scripts'] = $this->document->getScripts('footer');
		
		return $this->load->view('common/footer', $data);
	}
}
