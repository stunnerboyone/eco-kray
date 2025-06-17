<?php
class ControllerExtensionModuleWDmegamenu extends Controller {
    public function index($setting) {
        $this->load->language('extension/module/wd_megamenu');

        $this->load->model('webdigify/wd_megamenu');
        $this->load->model('tool/image');
        $this->load->model('localisation/language');
        $this->load->model('catalog/category');
        $this->load->model('catalog/product');

        $data = array();

        $data['warning'] = false;

        $module_id = rand(0, 10000);
        $data['module_id'] = $module_id;

        $data['items'] = array();

        $menu_id = $setting['menu'];

        $menu = $this->model_webdigify_wd_megamenu->getMenuById($menu_id);

        if($menu) {
            if($menu['status']) {
				$data['menu_type'] = $menu['menu_type'];
				
                $top_items = $this->model_webdigify_wd_megamenu->getTopItems($menu_id);

                $lang_code = $this->session->data['language'];

                $lang = $this->model_webdigify_wd_megamenu->getLanguageByCode($lang_code);

                foreach ($top_items as $top_item) {
					
					$data['text_blog'] = $this->language->get('text_blog');
					$data['all_blogs'] = $this->url->link('information/wd_blog/blogs');
					
					$this->load->model('setting/extension');
					$results = $this->model_setting_extension->getExtensions("module");
					foreach($results as $result){
						if($result['code'] === "wd_blog"){ $data['blog_enable'] = 1; }
					}
					
		
                    $sub_items_lv2 = array();

                    $sub_items2 = $this->model_webdigify_wd_megamenu->getSubItems($top_item['menu_item_id'], '2');

                    foreach ($sub_items2 as $sub_item2) {
                        $sub_items_lv3 = array();

                        $sub_items3 = $this->model_webdigify_wd_megamenu->getSubItems($sub_item2['sub_menu_item_id'], '3');

                        foreach ($sub_items3 as $sub_item3) {
                            $third_title = $this->model_webdigify_wd_megamenu->getSubItemDescriptionById($sub_item3['sub_menu_item_id']);

                            if($sub_item3['status']) {
                                $third_status = true;
                            } else {
                                $third_status = false;
                            }
                            
                            if(isset($third_title[$lang['language_id']])) {
                                $title = $third_title[$lang['language_id']];
                            } else {
                                $title = 'Third Level Item';
                            }

                            $sub_items_lv3[] = array(
                                'id'    => $sub_item3['sub_menu_item_id'],
                                'level' => $sub_item3['level'],
                                'status' => $third_status,
                                'link' => $sub_item3['link'],
                                'position' => $sub_item3['position'],
                                'title' => $title,
                            );
                        }

                        $second_title = $this->model_webdigify_wd_megamenu->getSubItemDescriptionById($sub_item2['sub_menu_item_id']);

                        if($sub_item2['status']) {
                            $second_status = true;
                        } else {
                            $second_status = false;
                        }

                        if(isset($second_title[$lang['language_id']])) {
                            $title = $second_title[$lang['language_id']];
                        } else {
                            $title = 'Second Level Item';
                        }

                        $sub_items_lv2[] = array(
                            'id'    => $sub_item2['sub_menu_item_id'],
                            'level' => $sub_item2['level'],
                            'status' => $second_status,
                            'link' => $sub_item2['link'],
                            'position' => $sub_item2['position'],
                            'title' => $title,
                            'sub_items' => $sub_items_lv3
                        );
                    }

                    $top_item_title = $this->model_webdigify_wd_megamenu->getTopItemDescriptionById($top_item['menu_item_id']);

                    if(isset($top_item_title[$lang['language_id']])) {
                        $top_level_title = $top_item_title[$lang['language_id']];
                    } else {
                        $top_level_title = 'Top Item';
                    }

                    if($top_item['status']) {
                        $top_item_status = true;
                    } else {
                        $top_item_status = false;
                    }

                    if($top_item['has_title']) {
                        $top_item_has_title = true;
                    } else {
                        $top_item_has_title = false;
                    }

                    if($top_item['has_link']) {
                        $top_item_has_link = true;
                    } else {
                        $top_item_has_link = false;
                    }

                    if($top_item['has_child']) {
                        $top_item_has_child = true;
                    } else {
                        $top_item_has_child = false;
                    }

                    if($top_item['icon']) {
                        $icon = $this->model_tool_image->resize($top_item['icon'], 15, 15);
                    } else {
                        $icon = false;
                    }

                    if($top_item['sub_menu_content']) {
                        $sub_content = json_decode($top_item['sub_menu_content'], true);
                    } else {
                        $sub_content = false;
                    }

                    if($top_item['sub_menu_content_columns']) {
                        $column = (int) $top_item['sub_menu_content_columns'];
                        if($column == 5) {
                            $cols = false;
                        } else {
                            $cols = 12 / $column;
                        }

                    } else {
                        $cols = 12;
                    }

                    if($top_item['category_id']) {
                        $top_category_info = $this->model_catalog_category->getCategory($top_item['category_id']);

                        if($top_category_info && $top_item_status) {
                            $top_item_status = true;
                        } else {
                            $top_item_status = false;
                        }
                    }

                    $sub_menu_content = array();

                    if($sub_content) {
                        foreach ($sub_content as $sub_type => $widgets) {
                            if($sub_type == "category") {
                                if($top_item_status) {
                                    if($widgets) {
                                        foreach ($widgets as $widget) {
                                            $category_id = $widget['category_id'];
                                            $category_info = $this->model_catalog_category->getCategory($category_id);

                                            if ($category_info) {
                                                $type = $widget['type'];
                                                $title = $category_info['name'];
                                                $link = $this->url->link('product/category', 'path=' . $top_item['category_id'] . '_' . $category_id);
                                                $w_cols = $widget['cols'];

                                                if($widget['show_image']) {
                                                    if ($category_info['image']) {
                                                        $image = $this->model_tool_image->resize($category_info['image'], 100, 250);
                                                    } else {
                                                        $image = false;
                                                    }
                                                } else {
                                                    $image = false;
                                                }

                                                $children = array();

                                                if($widget['show_child']) {
                                                    $results = $this->model_catalog_category->getCategories($category_id);

                                                    foreach ($results as $result) {
                                                        $children[] = array(
                                                            'title' => $result['name'],
                                                            'link' => $this->url->link('product/category', 'path=' . $top_item['category_id'] . '_' . $category_id . '_' . $result['category_id'])
                                                        );
                                                    }
                                                }

                                                $sub_menu_content['category'][] = array(
                                                    'id'        => $category_id,
                                                    'title'     => $title,
                                                    'link'      => $link,
                                                    'cols'      => $w_cols,
                                                    'image'     => $image,
                                                    'children'  => $children
                                                );
                                            }
                                        }
                                    }
                                }
                            }

                            if($sub_type == "widget") {
                                if($top_item_status) {
                                    if($widgets) {
                                        foreach ($widgets as $widget) {
                                            if($widget['type'] == "category") {
                                                $category_id = $widget['category_id'];
                                                $category_info = $this->model_catalog_category->getCategory($category_id);

                                                if ($category_info) {
                                                    $title = $category_info['name'];
                                                    $link = $this->url->link('product/category', 'path=' . $category_id);
                                                    $w_cols = $widget['cols'];

                                                    if($widget['show_image']) {
                                                        if ($category_info['image']) {
                                                            $image = $this->model_tool_image->resize($category_info['image'], 100, 250);
                                                        } else {
                                                            $image = false;
                                                        }
                                                    } else {
                                                        $image = false;
                                                    }

                                                    $children = array();

                                                    if($widget['show_child']) {
                                                        $results = $this->model_catalog_category->getCategories($category_id);

                                                        foreach ($results as $result) {
                                                            $children[] = array(
                                                                'title' => $result['name'],
                                                                'link' => $this->url->link('product/category', 'path=' . $category_id . '_' . $result['category_id'])
                                                            );
                                                        }
                                                    }

                                                    $sub_menu_content['widget'][] = array(
                                                        'type'      => $widget['type'],
                                                        'title'     => $title,
                                                        'link'      => $link,
                                                        'cols'      => $w_cols,
                                                        'image'     => $image,
                                                        'children'  => $children
                                                    );
                                                }
                                            }

                                            if($widget['type'] == 'html') {
                                                if($widget['show_title']) {
                                                    if(isset($widget['name'][$lang['language_id']])) {
                                                        $title = $widget['name'][$lang['language_id']];
                                                    } else {
                                                        $title = 'Widget HTML';
                                                    }
                                                } else {
                                                    $title = false;
                                                }

                                                $w_cols = $widget['cols'];

                                                if(isset($widget['content'][$lang['language_id']])) {
                                                    $html_content = html_entity_decode($widget['content'][$lang['language_id']], ENT_QUOTES, 'UTF-8');
                                                } else {
                                                    $html_content = '';
                                                }

                                                $sub_menu_content['widget'][] = array(
                                                    'type'      => $widget['type'],
                                                    'title'     => $title,
                                                    'cols'      => $w_cols,
                                                    'content'   => $html_content
                                                );
                                            }

                                            if($widget['type'] == 'product') {
                                                $product_id = $widget['product_id'];
                                                $product_info = $this->model_catalog_product->getProduct($product_id);

                                                if($product_info) {
                                                    $w_cols = $widget['cols'];
                                                    $title = $product_info['name'];

                                                    if($widget['show_image']) {
                                                        if ($product_info['image']) {
                                                            $image = $this->model_tool_image->resize($product_info['image'], 235, 273);
                                                        } else {
                                                            $image = false;
                                                        }
                                                    }
													
													if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
														$price = $this->currency->format($this->tax->calculate($product_info['price'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
													} else {
														$price = false;
													}

													if ((float)$product_info['special']) {
														$special = $this->currency->format($this->tax->calculate($product_info['special'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
													} else {
														$special = false;
                                                    }
                                                

                                                    $link = $this->url->link('product/product', '&product_id=' . $product_id);

                                                    $sub_menu_content['widget'][] = array(
                                                        'type'      => $widget['type'],
                                                        'title'     => $title,
                                                        'link'      => $link,
                                                        'cols'      => $w_cols,
														'price'       => $price,
                                                        'special'     => $special,
                                                        'image'     => $image
                                                    );
                                                }
                                            }

                                            if($widget['type'] == 'link') {
                                                if(isset($widget['name'][$lang['language_id']])) {
                                                    $title = $widget['name'][$lang['language_id']];
                                                } else {
                                                    $title = "Widget Link";
                                                }

                                                $sub_menu_content['widget'][] = array(
                                                    'type'      => $widget['type'],
                                                    'title'     => $title,
                                                    'cols'      => $widget['cols'],
                                                    'link'      => $widget['link']
                                                );
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }

                    if(isset($top_item['category_id']) && $top_item['sub_menu_content_type'] == 'category') {
                        $top_link = $this->url->link('product/category', 'path=' . $top_item['category_id']);
                    } else {
                        $top_link = $top_item['link'];
                    }

                    $data['items'][] = array(
                        'id'    => $top_item['menu_item_id'],
                        'sub_items' => $sub_items_lv2,
                        'status' => $top_item_status,
                        'has_title' => $top_item_has_title,
                        'has_link' => $top_item_has_link,
                        'has_child' => $top_item_has_child,
                        'category_id' => $top_item['category_id'],
                        'link' => $top_link,
                        'icon' => $icon,
                        'sub_menu_type' => $top_item['sub_menu_type'],
                        'sub_menu_content_type' => $top_item['sub_menu_content_type'],
                        'sub_menu_content_columns' => $cols,
                        'sub_menu_content' => $sub_menu_content,
                        'title' => $top_level_title
                    );
                }
            } else {
                $data['warning'] = true;
            }
        } else {
            $data['warning'] = true;
        }
        
        $data['menu_setting'] = array(
            'name'                          => $setting['name'],
            'status'                        => $setting['status'],
        );

        return $this->load->view('extension/module/wd_megamenu', $data);
    }
}