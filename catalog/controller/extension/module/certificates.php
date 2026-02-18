<?php
class ControllerExtensionModuleCertificates extends Controller {
	public function index($setting) {
		static $module = 0;

		$this->load->model('tool/image');

		$this->document->addStyle('catalog/view/javascript/jquery/owl-carousel/owl.carousel.min.css');
		$this->document->addScript('catalog/view/javascript/jquery/owl-carousel/owl.carousel.min.js');
		$this->document->addStyle('catalog/view/theme/EkoKray/stylesheet/certificates.css');

		$data['banners'] = array();

		if (isset($setting['images'])) {
			$images = $setting['images'];

			usort($images, function($a, $b) {
				return $a['sort_order'] - $b['sort_order'];
			});

			foreach ($images as $image) {
				if (is_file(DIR_IMAGE . $image['image'])) {
					$data['banners'][] = array(
						'title' => $image['title'],
						'link'  => isset($image['link']) ? $image['link'] : '',
						'image' => $this->model_tool_image->resize($image['image'], $setting['width'], $setting['height'])
					);
				}
			}
		}

		$data['module'] = $module++;

		return $this->load->view('extension/module/certificates', $data);
	}
}
