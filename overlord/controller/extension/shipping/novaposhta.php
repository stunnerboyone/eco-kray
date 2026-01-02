<?php

/**
 * Nova Poshta Shipping Module - Admin Controller
 *
 * This controller handles all admin-side functionality for the Nova Poshta shipping module
 * including settings management, waybill creation, order tracking, and data synchronization
 *
 * @version 4.1.0
 */
class ControllerShippingNovaPoshta extends Controller
{
	protected $extension = 'novaposhta';
	protected $version = '4.1.0';
	protected $data = array();
	private $error = array();
	private $settings = null;

	/**
	 * Constructor - Initialize controller and Nova Poshta helper
	 *
	 * @param object $registry OpenCart registry object
	 */
	public function __construct($registry)
	{
		$this->registry = $registry;
		require_once DIR_SYSTEM . 'helper/' . $this->extension . '.php';
		// Initialize Nova Poshta API helper class
		$registry->set($this->extension, new NovaPoshta($registry));
		$this->settings = $this->extensionSettings(true);
	}

	/**
	 * Install module - Create required database tables
	 */
	public function install()
	{
		if (version_compare(VERSION, '2.3', '>=')) {
			$this->load->model('extension/shipping/' . $this->extension);
			$this->{'model_extension_shipping_' . $this->extension}->creatTables();
		} else {
			$this->load->model('shipping/' . $this->extension);
			$this->{'model_shipping_' . $this->extension}->creatTables();
		}
	}

	public function uninstall()
	{
		if (version_compare(VERSION, '2.3', '>=')) {
			$this->load->model('extension/shipping/' . $this->extension);
			$this->{'model_extension_shipping_' . $this->extension}->deleteTables();
		} else {
			$this->load->model('shipping/' . $this->extension);
			$this->{'model_shipping_' . $this->extension}->deleteTables();
		}
	}

	public function index()
	{
		if (version_compare(VERSION, '2.3', '>=')) {
			$this->load->language('extension/shipping/' . $this->extension);
		} else {
			$this->load->language('shipping/' . $this->extension);
		}

		$this->load->model('setting/setting');

		if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validate()) {
			$this->session->data['success'] = $this->language->get('text_success_settings');

			if (version_compare(VERSION, '3', '>=')) {
				$this->model_setting_setting->editSetting('shipping_' . $this->extension, $this->request->post, $this->request->post['store_id']);

				if (isset($this->request->post['exit'])) {
					$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping', true));
				}
			} else {
				if (version_compare(VERSION, '2.3', '>=')) {
					$this->model_setting_setting->editSetting($this->extension, $this->request->post, $this->request->post['store_id']);

					if (isset($this->request->post['exit'])) {
						$this->response->redirect($this->url->link('extension/extension', 'token=' . $this->session->data['token'] . '&type=shipping', true));
					}
				} else {
					if (version_compare(VERSION, '2', '>=')) {
						$this->model_setting_setting->editSetting($this->extension, $this->request->post, $this->request->post['store_id']);

						if (isset($this->request->get['exit'])) {
							$this->response->redirect($this->url->link('extension/shipping', 'token=' . $this->session->data['token'], 'SSL'));
						}
					} else {
						$this->model_setting_setting->editSetting($this->extension, $this->request->post, $this->request->post['store_id']);

						if (isset($this->request->get['exit'])) {
							$this->redirect($this->url->link('extension/shipping', 'token=' . $this->session->data['token'], 'SSL'));
						}
					}
				}
			}
		}

		$this->document->setTitle($this->language->get('heading_title'));

		if (version_compare(VERSION, '3', '>=')) {
			$data['action'] = $this->url->link('extension/shipping/' . $this->extension, 'user_token=' . $this->session->data['user_token'], true);
			$data['action_settings'] = $this->url->link('extension/shipping/' . $this->extension . '/extensionSettings', 'user_token=' . $this->session->data['user_token'], true);
			$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping', true);
			$data['breadcrumbs'] = array();
			$data['breadcrumbs'][] = array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true));
			$data['breadcrumbs'][] = array('text' => $this->language->get('text_shipping'), 'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping', true));
			$data['breadcrumbs'][] = array('text' => $this->language->get('heading_title'), 'href' => $this->url->link('extension/shipping/' . $this->extension, 'user_token=' . $this->session->data['user_token'], true));
			$data['user_token'] = $this->session->data['user_token'];
			$extension_code = 'shipping_' . $this->extension;
			$extension_path = 'extension/';
		} else {
			if (version_compare(VERSION, '2.3', '>=')) {
				$data['action'] = $this->url->link('extension/shipping/' . $this->extension, 'token=' . $this->session->data['token'], true);
				$data['action_settings'] = $this->url->link('extension/shipping/' . $this->extension . '/extensionSettings', 'token=' . $this->session->data['token'], true);
				$data['cancel'] = $this->url->link('extension/extension', 'token=' . $this->session->data['token'] . '&type=shipping', true);
				$data['breadcrumbs'] = array();
				$data['breadcrumbs'][] = array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', 'token=' . $this->session->data['token'], true));
				$data['breadcrumbs'][] = array('text' => $this->language->get('text_shipping'), 'href' => $this->url->link('extension/extension', 'token=' . $this->session->data['token'] . '&type=shipping', true));
				$data['breadcrumbs'][] = array('text' => $this->language->get('heading_title'), 'href' => $this->url->link('extension/shipping/' . $this->extension, 'token=' . $this->session->data['token'], true));
				$data['token'] = $this->session->data['token'];
				$extension_code = $this->extension;
				$extension_path = 'extension/';
			} else {
				$data['action'] = $this->url->link('extension/shipping/' . $this->extension, 'token=' . $this->session->data['token'], 'SSL');
				$data['action_settings'] = $this->url->link('shipping/' . $this->extension . '/extensionSettings', 'token=' . $this->session->data['token'], 'SSL');
				$data['cancel'] = $this->url->link('extension/shipping', 'token=' . $this->session->data['token'], 'SSL');
				$data['breadcrumbs'] = array();

				if (version_compare(VERSION, '2', '>=')) {
					$data['breadcrumbs'][] = array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', 'token=' . $this->session->data['token'], 'SSL'));
				} else {
					$data['breadcrumbs'][] = array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/home', 'token=' . $this->session->data['token'], 'SSL'));
				}

				$data['breadcrumbs'][] = array('text' => $this->language->get('text_shipping'), 'href' => $this->url->link('extension/shipping', 'token=' . $this->session->data['token'], 'SSL'));
				$data['breadcrumbs'][] = array('text' => $this->language->get('heading_title'), 'href' => $this->url->link('shipping/' . $this->extension, 'token=' . $this->session->data['token'], 'SSL'));
				$data['token'] = $this->session->data['token'];
				$extension_code = $this->extension;
				$extension_path = '';
			}
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			if (isset($this->session->data['warning'])) {
				$data['error_warning'] = $this->session->data['warning'];
				unset($this->session->data['warning']);
			} else {
				$data['error_warning'] = '';
			}
		}

		if (isset($this->error['error_key_api'])) {
			$data['error_key_api'] = $this->error['error_key_api'];
		} else {
			$data['error_key_api'] = '';
		}

		if (version_compare(VERSION, '3', '<')) {
			$language_variables = array('heading_title', 'heading_verifying_api_access', 'heading_adding_custom_method', 'button_save', 'button_save_and_exit', 'button_download_basic_settings', 'button_import_settings', 'button_export_settings', 'button_cancel', 'button_update', 'button_add', 'button_generate', 'button_copy', 'button_run', 'tab_general', 'tab_tariffs', 'tab_database', 'tab_sender', 'tab_recipient', 'tab_departure', 'tab_payment', 'tab_consignment_note', 'tab_cron', 'tab_support', 'column_weight', 'column_department_service_cost', 'column_doors_service_cost', 'column_tariff_transfer_in_department', 'column_tariff_zone_city', 'column_tariff_zone_region', 'column_tariff_zone_ukraine', 'column_doors_pickup', 'column_doors_delivery', 'column_delivery_period', 'column_delivery_type', 'column_calculation_base', 'column_tariff_limit', 'column_percent', 'column_fixed_amount', 'column_minimum_payment', 'column_type', 'column_date', 'column_amount', 'column_description', 'column_action', 'column_postal_company_status', 'column_store_status', 'column_implementation_delay', 'column_notification', 'column_message_template', 'entry_status', 'entry_debugging_mode', 'entry_sort_order', 'entry_key_api', 'entry_image', 'entry_image_output_place', 'entry_curl_connecttimeout', 'entry_curl_timeout', 'entry_method_status', 'entry_name', 'entry_geo_zone', 'entry_tax_class', 'entry_minimum_order_amount', 'entry_maximum_order_amount', 'entry_free_shipping', 'entry_free_cost_text', 'entry_cost', 'entry_api_calculation', 'entry_tariff_calculation', 'entry_delivery_period', 'entry_departments_filter_weight', 'entry_departments_filter_dimensions', 'entry_department_types', 'entry_department_statuses', 'entry_discount', 'entry_declared_cost_commission', 'entry_declared_cost_minimum_commission', 'entry_declared_cost_commission_bottom', 'entry_references', 'entry_region', 'entry_city', 'entry_department', 'entry_settlement', 'entry_street', 'entry_house', 'entry_flat', 'entry_address', 'entry_address_pick_up', 'entry_sender', 'entry_recipient', 'entry_contact_person', 'entry_edrpou', 'entry_phone', 'entry_preferred_delivery_date', 'entry_preferred_delivery_time', 'entry_autodetection_departure_type', 'entry_departure_type', 'entry_calculate_volume', 'entry_calculate_volume_type', 'entry_seats_amount', 'entry_declared_cost', 'entry_declared_cost_default', 'entry_declared_cost_minimum', 'entry_departure_description', 'entry_departure_additional_information', 'entry_general_parameters', 'entry_manual_processing', 'entry_avia_delivery', 'entry_use_parameters', 'entry_weight', 'entry_weight_minimum', 'entry_dimensions', 'entry_allowance', 'entry_pack', 'entry_pack_type', 'entry_autodetection_pack_type', 'entry_delivery_payer', 'entry_third_person', 'entry_payment_type', 'entry_payment_cod', 'entry_calculate_cod', 'entry_calculate_declared_cost_comm', 'entry_backward_delivery', 'entry_backward_delivery_payer', 'entry_payment_control', 'entry_display_all_consignments', 'entry_displayed_information', 'entry_print_format', 'entry_number_of_copies', 'entry_template_type', 'entry_print_type', 'entry_print_start', 'entry_compatible_shipping_method', 'entry_consignment_create', 'entry_consignment_edit', 'entry_consignment_delete', 'entry_consignment_assignment_to_order', 'entry_menu_text', 'entry_key_cron', 'entry_departures_tracking', 'entry_tracking_statuses', 'entry_admin_notification', 'entry_customer_notification', 'entry_customer_notification_default', 'entry_email', 'entry_sms', 'entry_code', 'help_status', 'help_debugging_mode', 'help_sort_order', 'help_key_api', 'help_image', 'help_image_output_place', 'help_curl_connecttimeout', 'help_curl_timeout', 'help_method_status', 'help_name', 'help_geo_zone', 'help_tax_class', 'help_minimum_order_amount', 'help_maximum_order_amount', 'help_free_shipping', 'help_free_cost_text', 'help_cost', 'help_api_calculation', 'help_tariff_calculation', 'help_delivery_period', 'help_departments_filter_weight', 'help_departments_filter_dimensions', 'help_department_types', 'help_department_statuses', 'help_discount', 'help_declared_cost_commission', 'help_declared_cost_minimum_commission', 'help_declared_cost_commission_bottom', 'help_update_references', 'help_update_regions', 'help_update_cities', 'help_update_departments', 'help_update_settlements', 'help_update_streets', 'help_sender', 'help_sender_contact_person', 'help_sender_region', 'help_sender_city', 'help_sender_department', 'help_sender_address', 'help_sender_address_pick_up', 'help_recipient', 'help_recipient_contact_person', 'help_recipient_edrpou', 'help_recipient_contact_person_phone', 'help_recipient_region', 'help_recipient_city', 'help_recipient_department', 'help_recipient_address', 'help_recipient_street', 'help_recipient_house', 'help_recipient_flat', 'help_preferred_delivery_date', 'help_preferred_delivery_time', 'help_autodetection_departure_type', 'help_departure_type', 'help_calculate_volume', 'help_calculate_volume_type', 'help_seats_amount', 'help_declared_cost', 'help_declared_cost_default', 'help_declared_cost_minimum', 'help_departure_description', 'help_departure_additional_information', 'help_general_parameters', 'help_manual_processing', 'help_avia_delivery', 'help_use_parameters', 'help_weight', 'help_weight_minimum', 'help_dimensions', 'help_allowance', 'help_pack', 'help_pack_type', 'help_autodetection_pack_type', 'help_delivery_payer', 'help_third_person', 'help_payment_type', 'help_payment_cod', 'help_calculate_cod', 'help_calculate_declared_cost_comm', 'help_backward_delivery', 'help_backward_delivery_payer', 'help_payment_control', 'help_display_all_consignments', 'help_displayed_information', 'help_print_format', 'help_number_of_copies', 'help_template_type', 'help_print_type', 'help_print_start', 'help_compatible_shipping_method', 'help_consignment_create', 'help_consignment_edit', 'help_consignment_delete', 'help_consignment_assignment_to_order', 'help_key_cron', 'help_tracking_statuses', 'text_confirm', 'text_contacts', 'text_instruction', 'text_documentation_api', 'text_official_website', 'text_settings', 'text_global', 'text_department', 'text_doors', 'text_poshtomat', 'text_enabled', 'text_disabled', 'text_all_zones', 'text_yes', 'text_no', 'text_select', 'text_none', 'text_verifying_api_access', 'text_parcel_tariffs', 'text_cod_tariffs', 'text_all', 'text_grn', 'text_pc', 'text_kg', 'text_cm', 'text_pct', 'text_default_departure_options', 'text_pack', 'text_no_backward_delivery', 'text_consignment_note_list', 'text_print_settings', 'text_integration_with_orders', 'text_base_update', 'text_departures_tracking', 'text_settings_departures_statuses', 'text_message_template_macros', 'text_help_cron', 'text_order_template_macros', 'text_products_template_macros', 'text_cn_template_macros', 'text_macros');

			foreach ($language_variables as $l_v) {
				$data[$l_v] = $this->language->get($l_v);
			}
		}

		$data['text_help_integration_with_orders'] = sprintf($this->language->get('text_help_integration_with_orders'), DB_PREFIX, $this->extension);
		$this->load->model('localisation/language');
		$data['languages'] = $this->model_localisation_language->getLanguages();
		$data['language_id'] = '';

		foreach ($data['languages'] as $language) {
			if ($language['code'] == $this->config->get('config_admin_language')) {
				$data['language_id'] = $language['language_id'];
			}

			if (version_compare(VERSION, '2.2', '>=')) {
				$data['language_flag'][$language['language_id']] = 'language/' . $language['code'] . '/' . $language['code'] . '.png';
			} else {
				$data['language_flag'][$language['language_id']] = 'view/image/flags/' . $language['image'];
			}
		}

		if (isset($this->request->get['store_id'])) {
			$store_settings = $this->model_setting_setting->getSetting($extension_code, $this->request->get['store_id']);

			if (isset($store_settings[$extension_code . '_status'])) {
				$this->config->set($extension_code . '_status', $store_settings[$extension_code . '_status']);
			}

			if (isset($store_settings[$extension_code . '_sort_order'])) {
				$this->config->set($extension_code . '_sort_order', $store_settings[$extension_code . '_sort_order']);
			}

			if (isset($store_settings[$extension_code])) {
				$this->settings = $store_settings[$extension_code];
			}

			$data['store_id'] = $this->request->get['store_id'];
		} else {
			$data['store_id'] = $this->config->get('config_store_id');
		}

		$store_name = $this->config->get('config_name');

		if (is_array($store_name)) {
			$store_name = $store_name[$data['language_id']];
		}

		$data['stores'][] = array('store_id' => 0, 'name' => $store_name . $this->language->get('text_default'));
		$this->load->model('setting/store');
		$stores = $this->model_setting_store->getStores();

		foreach ($stores as $store) {
			$data['stores'][] = array('store_id' => $store['store_id'], 'name' => $store['name']);
		}
		$this->load->model('tool/image');
		$data['placeholder'] = $this->model_tool_image->resize('no_image.png', 100, 100);
		$this->load->model('localisation/geo_zone');
		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();
		$this->load->model('localisation/tax_class');
		$data['tax_classes'] = $this->model_localisation_tax_class->getTaxClasses();
		$this->load->model('localisation/zone');
		$data['zones'] = $this->model_localisation_zone->getZonesByCountryId($this->config->get('config_country_id'));
		$this->load->model('localisation/order_status');
		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		if (isset($this->request->post[$extension_code . '_status'])) {
			$data[$this->extension . '_status'] = $this->request->post[$extension_code . '_status'];
		} else {
			$data[$this->extension . '_status'] = $this->config->get($extension_code . '_status');
		}

		if (isset($this->request->post[$extension_code . '_sort_order'])) {
			$data[$this->extension . '_sort_order'] = $this->request->post[$extension_code . '_sort_order'];
		} else {
			$data[$this->extension . '_sort_order'] = $this->config->get($extension_code . '_sort_order');
		}

		if (isset($this->request->post[$extension_code])) {
			$data[$this->extension] = $this->request->post[$extension_code];
		} else {
			$data[$this->extension] = $this->settings;
		}

		if (isset($this->request->post[$extension_code]['image']) && is_file(DIR_IMAGE . $this->request->post[$extension_code]['image'])) {
			$data['thumb'] = $this->model_tool_image->resize($this->request->post[$extension_code]['image'], 100, 100);
		} else {
			if (!empty($this->settings['image']) && is_file(DIR_IMAGE . $this->settings['image'])) {
				$data['thumb'] = $this->model_tool_image->resize($this->settings['image'], 100, 100);
			} else {
				$data['thumb'] = $this->model_tool_image->resize('no_image.png', 100, 100);
			}
		}

		$references = $this->novaposhta->getReferences();

		if (isset($references['department_types']) && is_array($references['department_types'])) {
			$data['department_types'] = $references['department_types'];
		} else {
			$data['department_types'] = array();
		}

		if (isset($references['department_statuses']) && is_array($references['department_statuses'])) {
			$data['department_statuses'] = $references['department_statuses'];
		} else {
			$data['department_statuses'] = array();
		}

		if (isset($references['delivery_types']) && is_array($references['delivery_types'])) {
			$data['delivery_types'] = $references['delivery_types'];
		} else {
			$data['delivery_types'] = array();
		}

		if (isset($references['database']) && is_array($references['database'])) {
			$data['database'] = $references['database'];
		} else {
			$data['database'] = array();
		}

		if (isset($references['senders']) && is_array($references['senders'])) {
			$data['senders'] = $references['senders'];
		} else {
			$data['senders'] = array();
		}

		if (isset($references['sender_contact_persons']) && isset($references['sender_contact_persons'][$data[$this->extension]['sender']]) && is_array($references['sender_contact_persons'][$data[$this->extension]['sender']])) {
			$data['sender_contact_persons'] = $references['sender_contact_persons'][$data[$this->extension]['sender']];
		} else {
			$data['sender_contact_persons'] = array();
		}

		if (isset($references['cargo_types']) && is_array($references['cargo_types'])) {
			$data['cargo_types'] = $references['cargo_types'];
		} else {
			$data['cargo_types'] = array();
		}

		if (isset($references['pack_types']) && is_array($references['pack_types'])) {
			$data['pack_types'] = $references['pack_types'];
		} else {
			$data['pack_types'] = array();
		}

		if (isset($references['payer_types']) && is_array($references['payer_types'])) {
			$data['payer_types'] = $references['payer_types'];
		} else {
			$data['payer_types'] = array();
		}

		if (isset($references['third_persons']) && is_array($references['third_persons'])) {
			$data['third_persons'] = $references['third_persons'];
		} else {
			$data['third_persons'] = array();
		}

		if (isset($references['payment_types']) && is_array($references['payment_types'])) {
			$data['payment_types'] = $references['payment_types'];
		} else {
			$data['payment_types'] = array();
		}

		if (isset($references['backward_delivery_types']) && is_array($references['backward_delivery_types'])) {
			$data['backward_delivery_types'] = $references['backward_delivery_types'];
		} else {
			$data['backward_delivery_types'] = array();
		}

		if (isset($references['backward_delivery_payers']) && is_array($references['backward_delivery_payers'])) {
			$data['backward_delivery_payers'] = $references['backward_delivery_payers'];
		} else {
			$data['backward_delivery_payers'] = array();
		}

		if (isset($references['document_statuses']) && is_array($references['document_statuses'])) {
			$data['document_statuses'] = $references['document_statuses'];
		} else {
			$data['document_statuses'] = array();
		}

		$data['regions'] = $this->novaposhta->getRegions();
		$data['image_output_places'] = array('title' => $this->language->get('text_image_output_place_title'), 'img_key' => $this->language->get('text_image_output_place_img_key'));
		$data['calculate_volume_types'] = array('sum_all_products' => $this->language->get('text_sum_all_products_volume'), 'largest_product' => $this->language->get('text_largest_product_volume'));
		$data['use_parameters'] = array('products_without_parameters' => $this->language->get('text_products_without_parameters'), 'all_products' => $this->language->get('text_all_products'), 'whole_order' => $this->language->get('text_whole_order'));
		$data['calculate_cod_types'] = array('disabled' => $this->language->get('text_disabled'), 'enabled' => $this->language->get('text_enabled'), 'all_payment_methods' => $this->language->get('text_enable_for_all_payment_methods'));
		$data['consignment_displayed_information'] = array('cn_identifier' => $this->language->get('column_cn_identifier'), 'cn_number' => $this->language->get('column_cn_number'), 'order_number' => $this->language->get('column_order_number'), 'registry' => $this->language->get('column_registry'), 'create_date' => $this->language->get('column_create_date'), 'actual_shipping_date' => $this->language->get('column_actual_shipping_date'), 'preferred_shipping_date' => $this->language->get('column_preferred_shipping_date'), 'estimated_shipping_date' => $this->language->get('column_estimated_shipping_date'), 'recipient_date' => $this->language->get('column_recipient_date'), 'last_updated_status_date' => $this->language->get('column_last_updated_status_date'), 'sender' => $this->language->get('column_sender'), 'sender_contact_person' => $this->language->get('column_sender_contact_person'), 'sender_address' => $this->language->get('column_sender_address'), 'recipient' => $this->language->get('column_recipient'), 'recipient_contact_person' => $this->language->get('column_recipient_contact_person'), 'recipient_address' => $this->language->get('column_recipient_address'), 'weight' => $this->language->get('column_weight'), 'seats_amount' => $this->language->get('column_seats_amount'), 'declared_cost' => $this->language->get('column_declared_cost'), 'payment_control' => $this->language->get('column_payment_control'), 'shipping_cost' => $this->language->get('column_shipping_cost'), 'backward_delivery' => $this->language->get('column_backward_delivery'), 'service_type' => $this->language->get('column_service_type'), 'description' => $this->language->get('column_description'), 'additional_information' => $this->language->get('column_additional_information'), 'payer_type' => $this->language->get('column_payer_type'), 'payment_method' => $this->language->get('column_payment_method'), 'departure_type' => $this->language->get('column_departure_type'), 'packing_number' => $this->language->get('column_packing_number'), 'rejection_reason' => $this->language->get('column_rejection_reason'), 'status' => $this->language->get('column_status'));
		$data['print_formats'] = array('document_A4' => $this->language->get('text_cn_a4'), 'document_A5' => $this->language->get('text_cn_a5'), 'mark_85_85' => $this->language->get('text_mark_85_85'), 'mark_100_100' => $this->language->get('text_mark_100_100'), 'registry' => $this->language->get('text_registry'));
		$data['template_types'] = array('html' => $this->language->get('text_html'), 'pdf' => $this->language->get('text_pdf'));
		$data['print_types'] = array('horPrint' => $this->language->get('text_horizontally'), 'verPrint' => $this->language->get('text_vertically'));
		$data['time'] = array('hour' => $this->language->get('text_hour'), 'day' => $this->language->get('text_day'), 'month' => $this->language->get('text_month'), 'year' => $this->language->get('text_year'));

		if ($this->config->get('config_secure')) {
			$protocol = 'https://';
		} else {
			$protocol = 'http://';
		}

		if (!empty($this->settings['key_cron'])) {
			$key_cron = $this->settings['key_cron'];
		} else {
			$key_cron = '';
		}


		// Безпечне отримання base URL з конфігу замість HTTP_HOST (захист від XSS/Host Header Injection)
		$config_url = $this->config->get('config_url');
		if (!empty($config_url)) {
			// Видалити protocol з config_url якщо він там є
			$base_host = preg_replace('#^https?://#', '', $config_url);
			$base_host = rtrim($base_host, '/');
		} else {
			// Fallback: якщо config_url не налаштований, використовуємо HTTP_HOST з валідацією
			if (isset($_SERVER['HTTP_HOST']) && preg_match('/^[a-zA-Z0-9\-.:]+$/', $_SERVER['HTTP_HOST'])) {
				$base_host = $_SERVER['HTTP_HOST'];
			} else {
				$base_host = 'localhost'; // Безпечний fallback
			}
		}
		$base_url = $protocol . $base_host;
		$cron_path = '/usr/bin/wget --no-check-certificate -O - -q -t 1';
		$data['cron_update_references'] = $cron_path . " '" . $base_url . '/index.php?route=' . $extension_path . 'module/' . $this->extension . '_cron/update&type=references&key=' . $key_cron . "'";
		$data['cron_update_regions'] = $cron_path . " '" . $base_url . '/index.php?route=' . $extension_path . 'module/' . $this->extension . '_cron/update&type=regions&key=' . $key_cron . "'";
		$data['cron_update_cities'] = $cron_path . " '" . $base_url . '/index.php?route=' . $extension_path . 'module/' . $this->extension . '_cron/update&type=cities&key=' . $key_cron . "'";
		$data['cron_update_departments'] = $cron_path . " '" . $base_url . '/index.php?route=' . $extension_path . 'module/' . $this->extension . '_cron/update&type=departments&key=' . $key_cron . "'";
		$data['cron_departures_tracking'] = $cron_path . " '" . $base_url . '/index.php?route=' . $extension_path . 'module/' . $this->extension . '_cron/departuresTracking&key=' . $key_cron . "'";
		$data['cron_departures_tracking_href'] = $base_url . '/index.php?route=' . $extension_path . 'module/' . $this->extension . '_cron/departuresTracking&key=' . $key_cron;
		$data['v'] = $this->version;
		// Official Nova Poshta documentation links
		$data['documentation_api_href'] = 'https://developers.novaposhta.ua/documentation';
		$data['official_website_href'] = 'https://novaposhta.ua/';

		if ($this->language->get('code') == 'ua' || $this->language->get('code') == 'uk' || $this->language->get('code') == 'uk-ua' || $this->language->get('code') == 'ua-uk') {
			$data['translations_code'] = 'ua_UA';
		} else {
			if ($this->language->get('code') == 'ru' || $this->language->get('code') == 'ru-ru') {
				$data['translations_code'] = 'ru_RU';
			} else {
				$data['translations_code'] = 'en_US';
			}
		}

		$data['totals'] = $this->getExtensions('total');
		$data['payment_methods'] = $this->getExtensions('payment');
		$data['shipping_methods'] = $this->getExtensions('shipping');

		if (version_compare(VERSION, '2.3', '>=')) {
			$data['header'] = $this->load->controller('common/header');
			$data['column_left'] = $this->load->controller('common/column_left');
			$data['footer'] = $this->load->controller('common/footer');
			$this->response->setOutput($this->load->view('extension/shipping/' . $this->extension, $data));
		} else {
			if (version_compare(VERSION, '2', '>=')) {
				$data['header'] = $this->load->controller('common/header');
				$data['column_left'] = $this->load->controller('common/column_left');
				$data['footer'] = $this->load->controller('common/footer');
				$this->response->setOutput($this->load->view('shipping/' . $this->extension . '.tpl', $data));
			} else {
				$data['header'] = $this->getChild('common/header');
				$data['footer'] = $this->getChild('common/footer');
				$this->template = 'shipping/' . $this->extension . '.tpl';
				// Load required libraries for older OpenCart versions
				$patterns = array('/<script type="text\\/javascript" src="view\\/javascript\\/jquery\\/jquery-(.+)\\.min\\.js"><\\/script>/', '/<script type="text\\/javascript" src="view\\/javascript\\/jquery\\/ui\\/jquery-ui-(.+)\\.js"><\\/script>/');
				$replacements = array("<script type=\"text/javascript\" src=\"https://code.jquery.com/jquery-2.1.1.min.js\"></script>\r\n\t\t\t    <script type=\"text/javascript\" src=\"https://code.jquery.com/jquery-migrate-1.2.1.min.js\"></script>\r\n\t\t\t    <script type=\"text/javascript\" src=\"https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js\"></script>\r\n\t\t\t    <link rel=\"stylesheet\" type=\"text/css\" href=\"https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css\" media=\"screen\" />\r\n\t\t\t    <link rel=\"stylesheet\" type=\"text/css\" href=\"https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css\" media=\"screen\" />", '<script type="text/javascript" src="https://code.jquery.com/ui/1.8.24/jquery-ui.min.js"></script>');
				$data['header'] = preg_replace($patterns, $replacements, $data['header']);
				$this->data = $data;
				$this->response->setOutput($this->render());
			}
		}
	}

	public function getCNForm()
	{
		if (version_compare(VERSION, '2.3', '>=')) {
			$this->load->language('extension/shipping/' . $this->extension);
			$this->load->model('extension/shipping/' . $this->extension);
		} else {
			$this->load->language('shipping/' . $this->extension);
			$this->load->model('shipping/' . $this->extension);
		}

		if ($this->request->server['REQUEST_METHOD'] == 'POST') {
			$json = array();

			if ($this->validate() && $this->validateCNForm()) {
				$json['success'] = $this->request->post;
			} else {
				$json = $this->error;
			}

			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));
		} else {
			if (isset($this->error['warning'])) {
				$data['error_warning'][] = $this->error['warning'];
			} else {
				$data['error_warning'] = array();
			}

			if (isset($this->request->get['order_id'])) {
				$order_id = $this->request->get['order_id'];
				$this->load->model('sale/order');
				$order_info = $this->model_sale_order->getOrder($order_id);

				if (!$order_info) {
					$data['error_warning'][] = $this->language->get('error_get_order');
				}
			} else {
				$order_id = 0;
				$order_info = array();
			}

			if (!empty($order_info['novaposhta_cn_ref'])) {
				$cn_ref = $order_info['novaposhta_cn_ref'];
			} else {
				if (isset($this->request->get['cn_ref'])) {
					$cn_ref = $this->request->get['cn_ref'];
				} else {
					$cn_ref = '';
				}
			}

			if ($cn_ref) {
				$cn = $this->novaposhta->getCN($cn_ref);

				if (!$cn) {
					$data['error_warning'] = $this->novaposhta->error;
					$data['error_warning'][] = $this->language->get('error_get_cn');
				}
			} else {
				$cn = array();
			}

			$this->document->setTitle($this->language->get('heading_title'));

			if (version_compare(VERSION, '3', '>=')) {
				$data['breadcrumbs'] = array();
				$data['breadcrumbs'][] = array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true));
				$data['breadcrumbs'][] = array('text' => $this->language->get('text_orders'), 'href' => $this->url->link('sale/order', 'user_token=' . $this->session->data['user_token'], true));
				$data['breadcrumbs'][] = array('text' => $this->language->get('text_order'), 'href' => $this->url->link('sale/order/info&order_id=' . $order_id, 'user_token=' . $this->session->data['user_token'], true));
				$data['cn_list'] = $this->url->link('extension/shipping/novaposhta/getCNList', 'user_token=' . $this->session->data['user_token'], true);
				$data['cancel'] = $this->url->link('sale/order', 'user_token=' . $this->session->data['user_token'], true);
				$data['user_token'] = $this->session->data['user_token'];
				$model_name = 'model_extension_shipping_novaposhta';
			} else {
				if (version_compare(VERSION, '2.3', '>=')) {
					$data['breadcrumbs'] = array();
					$data['breadcrumbs'][] = array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', 'token=' . $this->session->data['token'], true));
					$data['breadcrumbs'][] = array('text' => $this->language->get('text_orders'), 'href' => $this->url->link('sale/order', 'token=' . $this->session->data['token'], true));
					$data['breadcrumbs'][] = array('text' => $this->language->get('text_order'), 'href' => $this->url->link('sale/order/info&order_id=' . $order_id, 'token=' . $this->session->data['token'], true));
					$data['cn_list'] = $this->url->link('extension/shipping/novaposhta/getCNList', 'token=' . $this->session->data['token'], true);
					$data['cancel'] = $this->url->link('sale/order', 'token=' . $this->session->data['token'], true);
					$data['token'] = $this->session->data['token'];
					$model_name = 'model_extension_shipping_novaposhta';
				} else {
					$data['breadcrumbs'] = array();

					if (version_compare(VERSION, '2', '>=')) {
						$data['breadcrumbs'][] = array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', 'token=' . $this->session->data['token'], 'SSL'));
					} else {
						$data['breadcrumbs'][] = array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/home', 'token=' . $this->session->data['token'], 'SSL'));
					}

					$data['breadcrumbs'][] = array('text' => $this->language->get('text_orders'), 'href' => $this->url->link('sale/order', 'token=' . $this->session->data['token'], 'SSL'));
					$data['breadcrumbs'][] = array('text' => $this->language->get('text_order'), 'href' => $this->url->link('sale/order/info&order_id=' . $order_id, 'token=' . $this->session->data['token'], 'SSL'));
					$data['cn_list'] = $this->url->link('shipping/novaposhta/getCNList', 'token=' . $this->session->data['token'], 'SSL');
					$data['cancel'] = $this->url->link('sale/order', 'token=' . $this->session->data['token'], 'SSL');
					$data['token'] = $this->session->data['token'];
					$model_name = 'model_shipping_novaposhta';
				}
			}

			if (version_compare(VERSION, '3', '<')) {
				$data['heading_title'] = $this->language->get('heading_title');
				$data['heading_adding_recipient'] = $this->language->get('heading_adding_recipient');
				$data['heading_options_seat_calc'] = $this->language->get('heading_options_seat_calc');
				$data['heading_components_list'] = $this->language->get('heading_components_list');
				$data['button_cn_list'] = $this->language->get('button_cn_list');
				$data['button_cancel'] = $this->language->get('button_cancel');
				$data['button_save_cn'] = $this->language->get('button_save_cn');
				$data['button_address_pick_up'] = $this->language->get('button_address_pick_up');
				$data['button_department_delivery'] = $this->language->get('button_department_delivery');
				$data['button_doors_delivery'] = $this->language->get('button_doors_delivery');
				$data['button_poshtomat_delivery'] = $this->language->get('button_poshtomat_delivery');
				$data['button_options_seat_calc'] = $this->language->get('button_options_seat_calc');
				$data['button_add'] = $this->language->get('button_add');
				$data['button_components_list'] = $this->language->get('button_components_list');
				$data['column_number'] = $this->language->get('column_number');
				$data['column_weight'] = $this->language->get('column_weight');
				$data['column_length'] = $this->language->get('column_length');
				$data['column_width'] = $this->language->get('column_width');
				$data['column_height'] = $this->language->get('column_height');
				$data['column_volume'] = $this->language->get('column_volume');
				$data['column_volume_weight'] = $this->language->get('column_volume_weight');
				$data['column_description'] = $this->language->get('column_description');
				$data['column_price'] = $this->language->get('column_price');
				$data['column_action'] = $this->language->get('column_action');
				$data['entry_sender'] = $this->language->get('entry_sender');
				$data['entry_recipient'] = $this->language->get('entry_recipient');
				$data['entry_third_person'] = $this->language->get('entry_third_person');
				$data['entry_region'] = $this->language->get('entry_region');
				$data['entry_city'] = $this->language->get('entry_city');
				$data['entry_address'] = $this->language->get('entry_address');
				$data['entry_department'] = $this->language->get('entry_department');
				$data['entry_poshtomat'] = $this->language->get('entry_poshtomat');
				$data['entry_settlement'] = $this->language->get('entry_settlement');
				$data['entry_street'] = $this->language->get('entry_street');
				$data['entry_house'] = $this->language->get('entry_house');
				$data['entry_flat'] = $this->language->get('entry_flat');
				$data['entry_contact_person'] = $this->language->get('entry_contact_person');
				$data['entry_edrpou'] = $this->language->get('entry_edrpou');
				$data['entry_phone'] = $this->language->get('entry_phone');
				$data['entry_departure_type'] = $this->language->get('entry_departure_type');
				$data['entry_general_parameters'] = $this->language->get('entry_general_parameters');
				$data['entry_weight'] = $this->language->get('entry_weight');
				$data['entry_length'] = $this->language->get('entry_length');
				$data['entry_width'] = $this->language->get('entry_width');
				$data['entry_height'] = $this->language->get('entry_height');
				$data['entry_volume_weight'] = $this->language->get('entry_volume_weight');
				$data['entry_weight_general'] = $this->language->get('entry_weight_general');
				$data['entry_volume_general'] = $this->language->get('entry_volume_general');
				$data['entry_volume_weight_general'] = $this->language->get('entry_volume_weight_general');
				$data['entry_manual_processing'] = $this->language->get('entry_manual_processing');
				$data['entry_pack_type'] = $this->language->get('entry_pack_type');
				$data['entry_seats_amount'] = $this->language->get('entry_seats_amount');
				$data['entry_declared_cost'] = $this->language->get('entry_declared_cost');
				$data['entry_departure_description'] = $this->language->get('entry_departure_description');
				$data['entry_delivery_payer'] = $this->language->get('entry_delivery_payer');
				$data['entry_payment_type'] = $this->language->get('entry_payment_type');
				$data['entry_backward_delivery'] = $this->language->get('entry_backward_delivery');
				$data['entry_backward_delivery_total'] = $this->language->get('entry_backward_delivery_total');
				$data['entry_payment_control'] = $this->language->get('entry_payment_control');
				$data['entry_backward_delivery_payer'] = $this->language->get('entry_backward_delivery_payer');
				$data['entry_departure_date'] = $this->language->get('entry_departure_date');
				$data['entry_preferred_delivery_date'] = $this->language->get('entry_preferred_delivery_date');
				$data['entry_preferred_delivery_time'] = $this->language->get('entry_preferred_delivery_time');
				$data['entry_order_number'] = $this->language->get('entry_order_number');
				$data['entry_packing_number'] = $this->language->get('entry_packing_number');
				$data['entry_departure_additional_information'] = $this->language->get('entry_departure_additional_information');
				$data['entry_avia_delivery'] = $this->language->get('entry_avia_delivery');
				$data['entry_rise_on_floor'] = $this->language->get('entry_rise_on_floor');
				$data['entry_elevator'] = $this->language->get('entry_elevator');
				$data['text_select'] = $this->language->get('text_select');
				$data['text_sender'] = $this->language->get('text_sender');
				$data['text_recipient'] = $this->language->get('text_recipient');
				$data['text_departure_options'] = $this->language->get('text_departure_options');
				$data['text_payment'] = $this->language->get('text_payment');
				$data['text_additionally'] = $this->language->get('text_additionally');
				$data['text_seat'] = $this->language->get('text_seat');
				$data['text_declared_cost'] = $this->language->get('text_declared_cost');
				$data['text_during_day'] = $this->language->get('text_during_day');
				$data['text_no_backward_delivery'] = $this->language->get('text_no_backward_delivery');
				$data['text_grn'] = $this->language->get('text_grn');
				$data['text_cubic_meter'] = $this->language->get('text_cubic_meter');
				$data['text_cm'] = $this->language->get('text_cm');
				$data['text_kg'] = $this->language->get('text_kg');
				$data['text_pc'] = $this->language->get('text_pc');
				$data['text_or'] = $this->language->get('text_or');
			}

			if ($cn) {
				$data['text_form'] = $this->language->get('text_form_edit');
			} else {
				$data['text_form'] = $this->language->get('text_form_create');
			}

			if ($cn) {
				$data['sender'] = $cn['SenderRef'];
				$data['sender_contact_person'] = $cn['ContactSenderRef'];
				$data['sender_contact_person_phone'] = $cn['SendersPhone'];
				$data['sender_region'] = $cn['AreaSenderRef'];
				$data['sender_city'] = $cn['CitySenderRef'];

				if ($cn['ServiceTypeRef'] == 'WarehouseWarehouse' || $cn['ServiceTypeRef'] == 'WarehouseDoors') {
					$data['sender_address_pick_up'] = false;
					$data['sender_department'] = $cn['SenderAddressRef'];
					$data['sender_address'] = '';
				} else {
					$data['sender_address_pick_up'] = true;
					$data['sender_department'] = '';
					$data['sender_address'] = $cn['SenderAddressRef'];
				}

				$data['recipient'] = $cn['RecipientRef'];
				$data['recipient_name'] = $cn['Recipient'];
				$data['recipient_contact_person'] = $cn['ContactRecipient'];
				$data['recipient_edrpou'] = $cn['RecipientEDRPOU'];
				$data['recipient_contact_person_phone'] = $cn['RecipientsPhone'];
				$data['recipient_region_name'] = '';
				$data['recipient_region'] = $cn['AreaRecipientRef'];

				if (isset($cn['RecipientCategoryOfWarehouse']) && $cn['RecipientCategoryOfWarehouse'] == 'Branch') {
					$data['recipient_address_type'] = 'department';
					$data['recipient_district_name'] = '';
					$data['recipient_city_name'] = $cn['CityRecipient'];
					$data['recipient_city'] = $cn['CityRecipientRef'];
					$data['recipient_department_name'] = $cn['RecipientAddress'];
					$data['recipient_department'] = $cn['RecipientAddressRef'];
					$data['recipient_poshtomat'] = '';
					$data['recipient_settlement_name'] = '';
					$data['recipient_settlement'] = '';
					$data['recipient_street_name'] = '';
					$data['recipient_street'] = '';
					$data['recipient_house'] = '';
					$data['recipient_flat'] = '';
				} else {
					if (isset($cn['RecipientCategoryOfWarehouse']) && $cn['RecipientCategoryOfWarehouse'] == 'Postomat') {
						$data['recipient_address_type'] = 'poshtomat';
						$data['recipient_district_name'] = '';
						$data['recipient_city_name'] = $cn['CityRecipient'];
						$data['recipient_city'] = $cn['CityRecipientRef'];
						$data['recipient_department_name'] = $cn['RecipientAddress'];
						$data['recipient_department'] = '';
						$data['recipient_poshtomat'] = $cn['RecipientAddressRef'];
						$data['recipient_settlement_name'] = '';
						$data['recipient_settlement'] = '';
						$data['recipient_street_name'] = '';
						$data['recipient_street'] = '';
						$data['recipient_house'] = '';
						$data['recipient_flat'] = '';
					} else {
						$data['recipient_address_type'] = 'doors';
						$data['recipient_district_name'] = $cn['OriginalGeoData']['RecipientAreaRegions'];
						$data['recipient_city_name'] = $cn['OriginalGeoData']['RecipientCityName'];
						$data['recipient_city'] = '';
						$data['recipient_department_name'] = '';
						$data['recipient_department'] = '';
						$data['recipient_poshtomat'] = '';
						$data['recipient_settlement_name'] = $cn['OriginalGeoData']['RecipientCityName'];
						$data['recipient_settlement'] = '';
						$data['recipient_street_name'] = str_replace($cn['OriginalGeoData']['RecipientCityName'] . ', ', '', $cn['OriginalGeoData']['RecipientAddressName']);
						$data['recipient_street'] = '';
						$data['recipient_house'] = $cn['OriginalGeoData']['RecipientHouse'];
						$data['recipient_flat'] = $cn['OriginalGeoData']['RecipientFlat'];

						if ($data['recipient_settlement_name']) {
							$region = $this->novaposhta->getRegion($data['recipient_region']);
							$settlements = $this->novaposhta->searchSettlements($data['recipient_settlement_name'] . ' ' . $region['Description']);

							foreach ($settlements as $settlement) {
								if (!$data['recipient_district_name'] || $data['recipient_district_name'] == $settlement['Region']) {
									$data['recipient_settlement'] = $settlement['Ref'];

									break;
								}
							}
						}

						if ($data['recipient_street_name'] && $data['recipient_settlement']) {
							$streets = $this->novaposhta->searchSettlementStreets($data['recipient_settlement'], $data['recipient_street_name'], 1);

							if (isset($streets[0])) {
								$data['recipient_street'] = $streets[0]['SettlementStreetRef'];
							}
						}
					}
				}

				$data['departure'] = $cn['CargoTypeRef'];
				$data['weight_general'] = $cn['Weight'];
				$data['volume_general'] = $cn['VolumeGeneral'];
				$data['volume_weight_general'] = $cn['VolumeWeight'];
				$data['seats_amount'] = $cn['SeatsAmount'];
				$data['declared_cost'] = $cn['Cost'];
				$data['departure_description'] = $cn['Description'];
				$data['pack'] = '';

				if (isset($cn['CargoDetails']) && $cn['CargoTypeRef'] == 'TiresWheels') {
					foreach ($cn['CargoDetails'] as $cargo) {
						$data['tires_and_wheels'][$cargo['CargoDescriptionRef']] = $cargo['Amount'];
					}
				} else {
					$data['tires_and_wheels'] = array();
				}

				if (!empty($cn['OptionsSeat'])) {
					$data['general_parameters'] = false;
					$data['parcels'] = $cn['OptionsSeat'];

					foreach ($data['parcels'] as &$parcel) {
						$parcel['length'] = $parcel['volumetricLength'];
						$parcel['width'] = $parcel['volumetricWidth'];
						$parcel['height'] = $parcel['volumetricHeight'];
						$parcel['volume_weight'] = $parcel['volumetricWeight'];
						$parcel['manual_processing'] = $parcel['specialCargo'];
						$parcel['pack_type'] = $parcel['packRef'];

						if (!empty($parcel['packRef'])) {
							$parcel['pack'] = true;
						} else {
							$parcel['pack'] = false;
						}
					}
				} else {
					$data['general_parameters'] = true;
					$data['parcels'] = array();
				}

				$data['delivery_payer'] = $cn['PayerTypeRef'];
				$data['third_person'] = $cn['ThirdPersonRef'];
				$data['payment_type'] = $cn['PaymentMethodRef'];
				$data['payment_control'] = $cn['AfterpaymentOnGoodsCost'];

				if (isset($cn['BackwardDeliveryData'][0])) {
					$data['backward_delivery'] = $cn['BackwardDeliveryData'][0]['CargoTypeRef'];
				} else {
					$data['backward_delivery'] = 'N';
				}

				if (isset($cn['BackwardDeliveryData'][0])) {
					$data['backward_delivery_total'] = $cn['BackwardDeliveryData'][0]['RedeliveryString'];
				} else {
					$data['backward_delivery_total'] = '';
				}

				if (isset($cn['BackwardDeliveryData'][0])) {
					$data['backward_delivery_payer'] = $cn['BackwardDeliveryData'][0]['PayerTypeRef'];
				} else {
					$data['backward_delivery_payer'] = $this->settings['backward_delivery_payer'];
				}

				$data['departure_date'] = date('d.m.Y', strtotime($cn['DateTime']));

				if ($cn['PreferredDeliveryDate'] != '0001-01-01 00:00:00') {
					$data['preferred_delivery_date'] = date('d.m.Y', strtotime($cn['PreferredDeliveryDate']));
				} else {
					$data['preferred_delivery_date'] = '';
				}

				$data['time_interval'] = $cn['TimeIntervalRef'];
				$data['packing_number'] = $cn['PackingNumber'];
				$data['order_number'] = $cn['InfoRegClientBarcodes'];
				$data['additional_information'] = $cn['AdditionalInformation'];
				$data['avia_delivery'] = $cn['AviaDelivery'];
				$data['rise_on_floor'] = $cn['NumberOfFloorsLifting'];
				$data['elevator'] = $cn['Elevator'];
				$data['time_intervals'] = $this->novaposhta->getTimeIntervals($data['recipient_settlement'], $data['preferred_delivery_date']);
			} else {
				if ($order_info) {
					$order_products = $this->$model_name->getOrderProducts($order_id);
					$order_totals = $this->model_sale_order->getOrderTotals($order_id);

					if ($order_info['store_id'] != $this->config->get('config_store_id')) {
						if (version_compare(VERSION, '3', '>=')) {
							$extension_code = 'shipping_' . $this->extension;
						} else {
							$extension_code = $this->extension;
						}

						$this->load->model('setting/setting');
						$store_settings = $this->model_setting_setting->getSetting($extension_code, $order_info['store_id']);

						if (isset($store_settings[$extension_code])) {
							$this->settings = $store_settings[$extension_code];
						}
					}

					$find_order = array('{order_id}', '{invoice_no}', '{invoice_prefix}', '{store_name}', '{store_url}', '{customer}', '{firstname}', '{lastname}', '{email}', '{telephone}', '{fax}', '{payment_firstname}', '{payment_lastname}', '{payment_company}', '{payment_address_1}', '{payment_address_2}', '{payment_postcode}', '{payment_city}', '{payment_zone}', '{payment_zone_id}', '{payment_country}', '{shipping_firstname}', '{shipping_lastname}', '{shipping_company}', '{shipping_address_1}', '{shipping_address_2}', '{shipping_postcode}', '{shipping_city}', '{shipping_zone}', '{shipping_zone_id}', '{shipping_country}', '{comment}', '{total}', '{commission}', '{date_added}', '{date_modified}');
					$replace_order = array('order_id' => $order_info['order_id'], 'invoice_no' => $order_info['invoice_no'], 'invoice_prefix' => $order_info['invoice_prefix'], 'store_name' => $order_info['store_name'], 'store_url' => $order_info['store_url'], 'customer' => $order_info['customer'], 'firstname' => $order_info['firstname'], 'lastname' => $order_info['lastname'], 'email' => $order_info['email'], 'telephone' => $order_info['telephone'], 'fax' => (isset($order_info['fax']) ? $order_info['fax'] : ''), 'payment_firstname' => $order_info['payment_firstname'], 'payment_lastname' => $order_info['payment_lastname'], 'payment_company' => $order_info['payment_company'], 'payment_address_1' => $order_info['payment_address_1'], 'payment_address_2' => $order_info['payment_address_2'], 'payment_postcode' => $order_info['payment_postcode'], 'payment_city' => $order_info['payment_city'], 'payment_zone' => $order_info['payment_zone'], 'payment_zone_id' => $order_info['payment_zone_id'], 'payment_country' => $order_info['payment_country'], 'shipping_firstname' => $order_info['shipping_firstname'], 'shipping_lastname' => $order_info['shipping_lastname'], 'shipping_company' => $order_info['shipping_company'], 'shipping_address_1' => $order_info['shipping_address_1'], 'shipping_address_2' => $order_info['shipping_address_2'], 'shipping_postcode' => $order_info['shipping_postcode'], 'shipping_city' => $order_info['shipping_city'], 'shipping_zone' => $order_info['shipping_zone'], 'shipping_zone_id' => $order_info['shipping_zone_id'], 'shipping_country' => $order_info['shipping_country'], 'comment' => $order_info['comment'], 'total' => $order_info['total'], 'commission' => $order_info['commission'], 'date_added' => $order_info['date_added'], 'date_modified' => $order_info['date_modified']);

					foreach ($this->$model_name->getSimpleFields($order_id) as $k => $v) {
						if (!in_array('{' . $k . '}', $find_order)) {
							$find_order[] = '{' . $k . '}';
							$replace_order[$k] = $v;
						}
					}
					$find_product = array('{product_id}', '{name}', '{model}', '{option}', '{sku}', '{ean}', '{upc}', '{jan}', '{isbn}', '{mpn}', '{quantity}');
					$data['sender'] = $this->settings['sender'];
					$data['sender_contact_person'] = $this->settings['sender_contact_person'];
					$data['sender_region'] = $this->settings['sender_region'];
					$data['sender_city'] = $this->settings['sender_city'];
					$data['sender_department'] = $this->settings['sender_department'];
					$data['sender_address'] = $this->settings['sender_address'];
					$data['sender_address_pick_up'] = $this->settings['sender_address_pick_up'];
					$data['recipient'] = $this->settings['recipient'];
					$data['recipient_name'] = trim(str_replace($find_order, $replace_order, $this->settings['recipient_name']));
					$data['recipient_contact_person'] = preg_replace('/ {2,}/', ' ', mb_convert_case(trim(str_replace($find_order, $replace_order, $this->settings['recipient_contact_person'])), MB_CASE_TITLE, 'UTF-8'));
					$data['recipient_edrpou'] = trim(str_replace($find_order, $replace_order, $this->settings['recipient_edrpou']));
					$data['recipient_contact_person_phone'] = preg_replace('/[^0-9]/', '', str_replace($find_order, $replace_order, $this->settings['recipient_contact_person_phone']));
					$data['recipient_address_type'] = 'department';
					$data['recipient_region_name'] = trim(str_replace($find_order, $replace_order, $this->settings['recipient_region']));
					$data['recipient_region'] = $this->novaposhta->getRegionRef($data['recipient_region_name']);
					$data['recipient_district_name'] = '';
					$data['recipient_city_name'] = trim(str_replace($find_order, $replace_order, $this->settings['recipient_city']));
					$data['recipient_city'] = $this->novaposhta->getCityRef($data['recipient_city_name']);
					$data['recipient_settlement_name'] = $data['recipient_city_name'];
					$data['recipient_settlement'] = '';
					$data['recipient_department_name'] = trim(str_replace($find_order, $replace_order, $this->settings['recipient_department']));
					$data['recipient_department'] = $this->novaposhta->getDepartmentRef($data['recipient_department_name'], $data['recipient_city']);
					$data['recipient_poshtomat'] = $data['recipient_department'];
					$data['recipient_address'] = trim(str_replace($find_order, $replace_order, $this->settings['recipient_address']));
					$data['recipient_street_name'] = trim(str_replace($find_order, $replace_order, $this->settings['recipient_street']));
					$data['recipient_street'] = '';
					$data['recipient_house'] = trim(str_replace($find_order, $replace_order, $this->settings['recipient_house']));
					$data['recipient_flat'] = trim(str_replace($find_order, $replace_order, $this->settings['recipient_flat']));

					if (utf8_strlen($data['recipient_contact_person_phone']) == 10) {
						$data['recipient_contact_person_phone'] = '38' . $data['recipient_contact_person_phone'];
					}

					if (!$data['recipient_department'] && !preg_match('/відділення|отделение|поштомат|почтомат|склад нп/ui', $data['recipient_department_name']) && (!isset($order_info['shipping_code']) || $order_info['shipping_code'] == 'novaposhta.doors')) {
						$data['recipient_address_type'] = 'doors';
						$settlements = $this->novaposhta->searchSettlements($data['recipient_settlement_name'] . ' ' . $data['recipient_region_name']);

						foreach ($settlements as $settlement) {
							if (!$data['recipient_district_name'] || $data['recipient_district_name'] == $settlement['Region']) {
								$data['recipient_district_name'] = $settlement['Region'];
								$data['recipient_settlement'] = $settlement['Ref'];

								break;
							}
						}

						if ($data['recipient_address'] && !$data['recipient_street_name']) {
							$address = $this->parseAddress($data['recipient_address']);
							$data['recipient_street_name'] = $address['street_type'] . ' ' . $address['street'];
							$data['recipient_house'] = $address['house'];
							$data['recipient_flat'] = $address['flat'];
						}

						if ($data['recipient_street_name'] && $data['recipient_settlement']) {
							$streets = $this->novaposhta->searchSettlementStreets($data['recipient_settlement'], $data['recipient_street_name'], 1);

							if (isset($streets[0])) {
								$data['recipient_street'] = $streets[0]['SettlementStreetRef'];
							}
						}
					} else {
						if (preg_match('/поштомат|почтомат/ui', $data['recipient_department_name']) || isset($order_info['shipping_code']) && $order_info['shipping_code'] == 'novaposhta.poshtomat') {
							$data['recipient_address_type'] = 'poshtomat';
						}
					}

					$departure = $this->novaposhta->getDeparture($order_products);
					$departure_seats = $this->novaposhta->getDepartureSeats($order_products);

					if ($this->settings['autodetection_departure_type']) {
						$data['departure'] = $this->novaposhta->getDepartureType($departure);
					} else {
						if (!empty($this->settings['departure_type'])) {
							$data['departure'] = $this->settings['departure_type'];
						} else {
							$data['departure'] = 'Parcel';
						}
					}

					if ($this->settings['seats_amount'] && $this->settings['seats_amount'] != $departure_seats) {
						$data['seats_amount'] = $this->settings['seats_amount'];

						for ($i = 1; $i <= $data['seats_amount']; $i++) {
							$data['parcels'][] = array('weight' => max(round($departure['weight'] / $data['seats_amount'], 2), $this->settings['weight_minimum']), 'length' => round($departure['length'] / $data['seats_amount'], 2), 'width' => round($departure['width'] / $data['seats_amount'], 2), 'height' => round($departure['height'] / $data['seats_amount'], 2));
						}
					} else {
						$data['seats_amount'] = $departure_seats;

						foreach ($departure['parcels'] as $parcel) {
							$data['parcels'][] = array('weight' => max($parcel['weight'], $this->settings['weight_minimum']), 'length' => $parcel['length'], 'width' => $parcel['width'], 'height' => $parcel['height']);
						}
					}

					$data['general_parameters'] = $this->settings['general_parameters'];
					$data['manual_processing'] = $this->settings['manual_processing'];
					$data['pack'] = $this->settings['autodetection_pack_type'];
					$data['tires_and_wheels'] = array();
					$data['weight_general'] = $departure['weight'];
					$data['volume_general'] = $departure['volume'];
					$data['volume_weight_general'] = $data['volume_general'] * 250;
					$data['declared_cost'] = $this->convertToUAH($this->getDeclaredCost($order_totals), $order_info['currency_code'], $order_info['currency_value']);
					$data['backward_delivery_total'] = $data['declared_cost'];
					$data['departure_description'] = '';

					foreach ($data['parcels'] as &$parcel) {
						$parcel['manual_processing'] = $this->settings['manual_processing'];
						$parcel['pack'] = $this->settings['autodetection_pack_type'];

						if (!empty($this->settings['pack_type'])) {
							if ($this->settings['autodetection_pack_type']) {
								$parcel['pack_type'] = $this->novaposhta->getPackType($parcel);
							} else {
								$parcel['pack_type'] = $this->settings['pack_type'][0];
							}
						} else {
							$parcel['pack_type'] = false;
						}
					}
					$template = explode('|', $this->settings['departure_description']);

					if ($template[0]) {
						$data['departure_description'] .= preg_replace(array('/\\s\\s+/', '/\\r\\r+/', '/\\n\\n+/'), ' ', trim(str_replace($find_order, $replace_order, $template[0])));
					}

					if (isset($template[1])) {
						foreach ($order_products as $k => $product) {
							$replace_product = array('product_id' => $product['product_id'], 'name' => $product['name'], 'model' => $product['model'], 'option' => '', 'sku' => $product['sku'], 'ean' => $product['ean'], 'upc' => $product['upc'], 'jan' => $product['jan'], 'isbn' => $product['isbn'], 'mpn' => $product['mpn'], 'quantity' => $product['quantity']);

							if ($product['option']) {
								foreach ($product['option'] as $option) {
									$replace_product['option'] .= $option['name'] . ': ' . $option['value'];
								}
							}

							$data['departure_description'] .= preg_replace(array('/\\s\\s+/', '/\\r\\r+/', '/\\n\\n+/'), ' ', trim(str_replace($find_product, $replace_product, $template[1])));
						}
					}

					$data['departure_description'] = mb_substr($data['departure_description'], 0, 119);

					if ($this->settings['shipping_methods'][$data['recipient_address_type']]['free_shipping'] && $this->settings['shipping_methods'][$data['recipient_address_type']]['free_shipping'] <= $this->convertToUAH($order_totals[count($order_totals) - 1]['value'], $order_info['currency_code'], $order_info['currency_value'])) {
						$data['delivery_payer'] = 'Sender';
					} else {
						$data['delivery_payer'] = $this->settings['delivery_payer'];
					}

					if (isset($order_info['payment_code']) && isset($this->settings['payment_cod']) && (in_array($order_info['payment_code'], $this->settings['payment_cod']) || in_array(stristr($order_info['payment_code'], '.', true), $this->settings['payment_cod']))) {
						$data['backward_delivery'] = 'Money';
					} else {
						if (!isset($order_info['payment_code']) && isset($this->settings['payment_cod'])) {
							$data['backward_delivery'] = $this->settings['backward_delivery'];

							foreach ($this->settings['payment_cod'] as $payment) {
								if (is_array($payment) && $payment['name'] == $order_info['payment_method']) {
									$data['backward_delivery'] = 'Money';

									break;
								}

								$this->load->language('payment/' . $payment);
								$name = $this->language->get('heading_title');

								if ($order_info['payment_method'] == $name) {
									$data['backward_delivery'] = 'Money';

									break;
								}
							}
						} else {
							$data['backward_delivery'] = $this->settings['backward_delivery'];
						}
					}

					if ((!$data['backward_delivery'] || $data['backward_delivery'] == 'N' || !$data['declared_cost']) && $this->settings['declared_cost_default']) {
						$data['declared_cost'] = $this->settings['declared_cost_default'];
					} else {
						if ($this->settings['declared_cost_minimum'] && $data['declared_cost'] < $this->settings['declared_cost_minimum']) {
							$data['declared_cost'] = $this->settings['declared_cost_minimum'];
						}
					}

					if (version_compare(VERSION, '3', '>=')) {
						$cod_plus_settings = $this->config->get('payment_cod_plus');
					} else {
						$cod_plus_settings = $this->config->get('cod_plus');
					}

					if (isset($cod_plus_settings[$order_info['shipping_code']])) {
						$cod_plus_free = $cod_plus_settings[$order_info['shipping_code']]['free'];
					} else {
						if (isset($cod_plus_settings[stristr($order_info['shipping_code'], '.', true)])) {
							$cod_plus_free = $cod_plus_settings[stristr($order_info['shipping_code'], '.', true)]['free'];
						} else {
							$cod_plus_free = 0;
						}
					}

					if ($cod_plus_free && $cod_plus_free <= $data['declared_cost']) {
						$data['backward_delivery_payer'] = 'Sender';
					} else {
						$data['backward_delivery_payer'] = $this->settings['backward_delivery_payer'];
					}

					$data['third_person'] = $this->settings['third_person'];
					$data['payment_type'] = $this->settings['payment_type'];

					if ($data['backward_delivery'] == 'Money' && !empty($this->settings['payment_control'])) {
						$data['backward_delivery'] = 'N';
						$data['payment_control'] = $this->convertToUAH($this->getPaymentControl($order_totals), $order_info['currency_code'], $order_info['currency_value']);
					} else {
						$data['payment_control'] = '';
					}

					$data['departure_date'] = date('d.m.Y');
					$data['preferred_delivery_date'] = str_replace($find_order, $replace_order, $this->settings['preferred_delivery_date']);
					$data['time_interval'] = str_replace($find_order, $replace_order, $this->settings['preferred_delivery_time']);
					$data['order_number'] = $order_id;
					$data['packing_number'] = '';

					if ($data['time_interval']) {
						$preferred_delivery_time = str_replace(':', '', $data['time_interval']);
						$data['time_intervals'] = $this->novaposhta->getTimeIntervals($data['recipient_city'], $data['preferred_delivery_date']);

						if (!empty($data['time_intervals']) && is_array($data['time_intervals'])) {
							foreach ($data['time_intervals'] as $interval) {
								$start = str_replace(':', '', $interval['Start']);
								$end = str_replace(':', '', $interval['End']);

								if ($start <= $preferred_delivery_time && $preferred_delivery_time <= $end) {
									$data['time_interval'] = $interval['Number'];

									break;
								}
							}
						}
					}

					$data['additional_information'] = '';
					$template = explode('|', $this->settings['departure_additional_information']);

					if ($template[0]) {
						$data['additional_information'] .= preg_replace(array('/\\s\\s+/', '/\\r\\r+/', '/\\n\\n+/'), ' ', trim(str_replace($find_order, $replace_order, $template[0])));
					}

					if (isset($template[1])) {
						foreach ($order_products as $k => $product) {
							$replace_product = array('product_id' => $product['product_id'], 'name' => $product['name'], 'model' => $product['model'], 'option' => '', 'sku' => $product['sku'], 'ean' => $product['ean'], 'upc' => $product['upc'], 'jan' => $product['jan'], 'isbn' => $product['isbn'], 'mpn' => $product['mpn'], 'quantity' => $product['quantity']);

							if ($product['option']) {
								foreach ($product['option'] as $option) {
									$replace_product['option'] .= $option['name'] . ': ' . $option['value'];
								}
							}

							$data['additional_information'] .= preg_replace(array('/\\s\\s+/', '/\\r\\r+/', '/\\n\\n+/'), ' ', trim(str_replace($find_product, $replace_product, $template[1])));
						}
					}

					$data['additional_information'] = mb_substr($data['additional_information'], 0, 99);
					$data['avia_delivery'] = $this->settings['avia_delivery'];
					$data['rise_on_floor'] = '';
					$data['elevator'] = '';
				} else {
					$data['sender'] = $this->settings['sender'];
					$data['sender_contact_person'] = $this->settings['sender_contact_person'];
					$data['sender_region'] = $this->settings['sender_region'];
					$data['sender_city'] = $this->settings['sender_city'];
					$data['sender_department'] = $this->settings['sender_department'];
					$data['sender_address'] = $this->settings['sender_address'];
					$data['sender_address_pick_up'] = $this->settings['sender_address_pick_up'];
					$data['recipient'] = $this->settings['recipient'];
					$data['recipient_name'] = $this->settings['recipient_name'];
					$data['recipient_contact_person'] = '';
					$data['recipient_edrpou'] = '';
					$data['recipient_contact_person_phone'] = '';
					$data['recipient_address_type'] = 'department';
					$data['recipient_region_name'] = '';
					$data['recipient_region'] = '';
					$data['recipient_district_name'] = '';
					$data['recipient_city_name'] = '';
					$data['recipient_city'] = '';
					$data['recipient_settlement_name'] = '';
					$data['recipient_settlement'] = '';
					$data['recipient_department_name'] = '';
					$data['recipient_department'] = '';
					$data['recipient_poshtomat'] = '';
					$data['recipient_street_name'] = '';
					$data['recipient_street'] = '';
					$data['recipient_house'] = '';
					$data['recipient_flat'] = '';
					$departure = $this->novaposhta->getDeparture(false);

					if ($this->settings['autodetection_departure_type']) {
						$data['departure'] = $this->novaposhta->getDepartureType($departure);
					} else {
						if (!empty($this->settings['departure_type'])) {
							$data['departure'] = $this->settings['departure_type'];
						} else {
							$data['departure'] = 'Parcel';
						}
					}

					$data['general_parameters'] = $this->settings['general_parameters'];
					$data['manual_processing'] = $this->settings['manual_processing'];
					$data['pack'] = $this->settings['autodetection_pack_type'];
					$data['tires_and_wheels'] = array();
					$data['parcels'] = $departure['parcels'];
					$data['weight_general'] = $departure['weight'];
					$data['volume_general'] = $departure['volume'];
					$data['volume_weight_general'] = $data['volume_general'] * 250;
					$data['seats_amount'] = $this->settings['seats_amount'];
					$data['declared_cost'] = $this->settings['declared_cost_default'];
					$data['departure_description'] = $this->settings['departure_description'];

					foreach ($data['parcels'] as &$parcel) {
						$parcel['manual_processing'] = $this->settings['manual_processing'];
						$parcel['pack'] = $this->settings['autodetection_pack_type'];

						if (!empty($this->settings['pack_type'])) {
							if ($this->settings['autodetection_pack_type']) {
								$parcel['pack_type'] = $this->novaposhta->getPackType($parcel);
							} else {
								$parcel['pack_type'] = $this->settings['pack_type'][0];
							}
						} else {
							$parcel['pack_type'] = false;
						}
					}
					$data['delivery_payer'] = $this->settings['delivery_payer'];
					$data['third_person'] = $this->settings['third_person'];
					$data['payment_type'] = $this->settings['payment_type'];
					$data['backward_delivery'] = $this->settings['backward_delivery'];
					$data['backward_delivery_total'] = $data['declared_cost'];
					$data['backward_delivery_payer'] = $this->settings['backward_delivery_payer'];
					$data['payment_control'] = '';
					$data['departure_date'] = date('d.m.Y');
					$data['preferred_delivery_date'] = '';
					$data['time_interval'] = '';
					$data['order_number'] = '';
					$data['packing_number'] = '';
					$data['additional_information'] = '';
					$data['avia_delivery'] = $this->settings['avia_delivery'];
					$data['rise_on_floor'] = '';
					$data['elevator'] = '';
				}
			}

			$data['references'] = $this->novaposhta->getReferences();

			if (isset($data['references']['senders']) && is_array($data['references']['senders'])) {
				$data['senders'] = $data['references']['senders'];
			} else {
				$data['senders'] = array();
			}

			if (isset($data['references']['sender_options'][$data['sender']]) && is_array($data['references']['sender_options'][$data['sender']])) {
				$data['sender_options'] = $data['references']['sender_options'][$data['sender']];
			} else {
				$data['sender_options'] = array();
			}

			if (isset($data['references']['sender_contact_persons'][$data['sender']]) && is_array($data['references']['sender_contact_persons'][$data['sender']])) {
				$data['sender_contact_persons'] = $data['references']['sender_contact_persons'][$data['sender']];
			} else {
				$data['sender_contact_persons'] = array();
			}

			$data['regions'] = $this->novaposhta->getRegions();

			if (isset($data['references']['tires_and_wheels']) && is_array($data['references']['tires_and_wheels'])) {
				foreach ($data['references']['tires_and_wheels'] as $i => $v) {
					$data['references']['tires_and_wheels'][$i]['Description'] = $v[$this->novaposhta->description_field];
					unset($data['references']['tires_and_wheels'][$i]['DescriptionRu']);
				}
			}

			$data['totals'] = array();

			if (isset($order_totals)) {
				foreach ($order_totals as $total) {
					$data['totals'][] = array('title' => strip_tags($total['title']), 'price' => $this->convertToUAH($total['value'], $order_info['currency_code'], $order_info['currency_value']), 'status' => (isset($this->settings['declared_cost']) && in_array($total['code'], (array) $this->settings['declared_cost']) ? 1 : 0));
				}
			}

			$data['order_id'] = $order_id;
			$data['cn_ref'] = $cn_ref;
			$data['v'] = $this->version;

			if ($this->language->get('code') == 'ua' || $this->language->get('code') == 'uk' || $this->language->get('code') == 'uk-ua' || $this->language->get('code') == 'ua-uk') {
				$data['translations_code'] = 'ua_UA';
			} else {
				if ($this->language->get('code') == 'ru' || $this->language->get('code') == 'ru-ru') {
					$data['translations_code'] = 'ru_RU';
				} else {
					$data['translations_code'] = 'en_US';
				}
			}

			if (version_compare(VERSION, '2.3', '>=')) {
				$data['header'] = $this->load->controller('common/header');
				$data['column_left'] = $this->load->controller('common/column_left');
				$data['footer'] = $this->load->controller('common/footer');
				$this->response->setOutput($this->load->view('extension/shipping/' . $this->extension . '_cn_form', $data));
			} else {
				if (version_compare(VERSION, '2', '>=')) {
					$data['header'] = $this->load->controller('common/header');
					$data['column_left'] = $this->load->controller('common/column_left');
					$data['footer'] = $this->load->controller('common/footer');
					$this->response->setOutput($this->load->view('shipping/' . $this->extension . '_cn_form.tpl', $data));
				} else {
					$data['header'] = $this->getChild('common/header');
					$data['footer'] = $this->getChild('common/footer');
					$this->template = 'shipping/' . $this->extension . '_cn_form.tpl';
					// Load required libraries for older OpenCart versions
					$patterns = array('/<script type="text\\/javascript" src="view\\/javascript\\/jquery\\/jquery-(.+)\\.min\\.js"><\\/script>/', '/<script type="text\\/javascript" src="view\\/javascript\\/jquery\\/ui\\/jquery-ui-(.+)\\.js"><\\/script>/');
					$replacements = array("<script type=\"text/javascript\" src=\"https://code.jquery.com/jquery-2.1.1.min.js\"></script>\r\n                    <script type=\"text/javascript\" src=\"https://code.jquery.com/jquery-migrate-1.2.1.min.js\"></script>\r\n                    <script type=\"text/javascript\" src=\"https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js\"></script>\r\n                    <script type=\"text/javascript\" src=\"https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js\"></script>\r\n                    <script type=\"text/javascript\" src=\"https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datetimepicker/4.17.47/js/bootstrap-datetimepicker.min.js\"></script>\r\n                    <link rel=\"stylesheet\" type=\"text/css\" href=\"https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css\" media=\"screen\" />\r\n                    <link rel=\"stylesheet\" type=\"text/css\" href=\"https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css\" media=\"screen\" />\r\n                    <link rel=\"stylesheet\" type=\"text/css\" href=\"https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datetimepicker/4.17.47/css/bootstrap-datetimepicker.min.css\" media=\"screen\" />", '<script type="text/javascript" src="https://code.jquery.com/ui/1.8.24/jquery-ui.min.js"></script>');
					$data['header'] = preg_replace($patterns, $replacements, $data['header']);
					$this->data = $data;
					$this->response->setOutput($this->render());
				}
			}
		}
	}

	public function getCNList()
	{
		if (version_compare(VERSION, '2.3', '>=')) {
			$this->load->language('extension/shipping/' . $this->extension);
		} else {
			$this->load->language('shipping/' . $this->extension);
		}

		$this->document->setTitle($this->language->get('heading_title'));
		$url = '';

		if (isset($this->request->get['filter_cn_number'])) {
			$filter_cn_number = $this->request->get['filter_cn_number'];
			$url .= '&filter_cn_number=' . urlencode(html_entity_decode($this->request->get['filter_cn_number'], ENT_QUOTES, 'UTF-8'));
		} else {
			$filter_cn_number = '';
		}

		if (isset($this->request->get['filter_cn_type'])) {
			$filter_cn_type = $this->request->get['filter_cn_type'];

			foreach ($this->request->get['filter_cn_type'] as $v) {
				$url .= '&filter_cn_type[]=' . urlencode(html_entity_decode($v, ENT_QUOTES, 'UTF-8'));
			}
		} else {
			$filter_cn_type = array();
		}

		if (isset($this->request->get['filter_departure_date_from'])) {
			$filter_departure_date_from = $this->request->get['filter_departure_date_from'];
			$url .= '&filter_departure_date_from=' . urlencode(html_entity_decode($this->request->get['filter_departure_date_from'], ENT_QUOTES, 'UTF-8'));
		} else {
			$filter_departure_date_from = date('d.m.Y');
		}

		if (isset($this->request->get['filter_departure_date_to'])) {
			$filter_departure_date_to = $this->request->get['filter_departure_date_to'];
			$url .= '&filter_departure_date_to=' . urlencode(html_entity_decode($this->request->get['filter_departure_date_to'], ENT_QUOTES, 'UTF-8'));
		} else {
			$filter_departure_date_to = '';
		}

		if (isset($this->request->get['page'])) {
			$page = $this->request->get['page'];
		} else {
			$page = 1;
		}

		if ($this->settings['print_format'] == 'document_A4') {
			$print_type = 'orders';
			$print_format = 'printDocument';
			$page_format = 'A4';
			$copies = $this->settings['number_of_copies'];
		} else {
			if ($this->settings['print_format'] == 'document_A5') {
				$print_type = 'orders';
				$print_format = 'printDocument';
				$page_format = 'A5';
				$copies = $this->settings['number_of_copies'];
			} else {
				if ($this->settings['print_format'] == 'mark_85_85') {
					$print_type = 'orders';
					$print_format = 'printMarking85x85';
					$copies = $this->settings['number_of_copies'];

					if ($this->settings['template_type'] == 'html') {
						$print_direction = $this->settings['print_type'];
						$position = $this->settings['print_start'];
					}
				} else {
					if ($this->settings['print_format'] == 'mark_100_100') {
						$print_type = 'orders';
						$print_format = 'printMarking100x100';
						$copies = $this->settings['number_of_copies'];

						if ($this->settings['template_type'] == 'html') {
							$print_direction = $this->settings['print_type'];
							$position = $this->settings['print_start'];
						}
					} else {
						if ($this->settings['print_format'] == 'registry') {
							$print_type = 'scanSheet';
							$print_format = 'printScanSheet';
						}
					}
				}
			}
		}

		$data['customized_printing'] = 'https://my.novaposhta.ua/' . $print_type . '/' . $print_format . '/apiKey/' . $this->novaposhta->key_api . '/type/' . $this->settings['template_type'];

		if (isset($page_format)) {
			$data['customized_printing'] .= '/pageFormat/' . $page_format;
		}

		if (isset($print_direction)) {
			$data['customized_printing'] .= '/printDirection/' . $print_direction . '/position/' . $position;
		}

		if (!empty($copies)) {
			$data['customized_printing'] .= '/copies/' . $copies;
		}

		$data['print_cn_a4_pdf'] = 'https://my.novaposhta.ua/orders/printDocument/apiKey/' . $this->novaposhta->key_api . '/type/pdf/pageFormat/A4';
		$data['print_cn_a4_html'] = 'https://my.novaposhta.ua/orders/printDocument/apiKey/' . $this->novaposhta->key_api . '/type/html/pageFormat/A4';
		$data['print_cn_a5_pdf'] = 'https://my.novaposhta.ua/orders/printDocument/apiKey/' . $this->novaposhta->key_api . '/type/pdf/pageFormat/A5';
		$data['print_cn_a5_html'] = 'https://my.novaposhta.ua/orders/printDocument/apiKey/' . $this->novaposhta->key_api . '/type/html/pageFormat/A5';
		$data['print_mark_85_85_pdf'] = 'https://my.novaposhta.ua/orders/printMarking85x85/apiKey/' . $this->novaposhta->key_api . '/type/pdf';
		$data['print_mark_85_85_html'] = 'https://my.novaposhta.ua/orders/printMarking85x85/apiKey/' . $this->novaposhta->key_api . '/type/html';
		$data['print_mark_100_100_pdf'] = 'https://my.novaposhta.ua/orders/printMarking100x100/apiKey/' . $this->novaposhta->key_api . '/type/pdf';
		$data['print_mark_100_100_html'] = 'https://my.novaposhta.ua/orders/printMarking100x100/apiKey/' . $this->novaposhta->key_api . '/type/html';
		$data['print_registry_pdf'] = 'https://my.novaposhta.ua/scanSheet/printScanSheet/apiKey/' . $this->novaposhta->key_api . '/type/pdf';
		$data['print_registry_html'] = 'https://my.novaposhta.ua/scanSheet/printScanSheet/apiKey/' . $this->novaposhta->key_api . '/type/html';

		if (version_compare(VERSION, '3', '>=')) {
			$data['breadcrumbs'] = array();
			$data['breadcrumbs'][] = array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true));
			$data['breadcrumbs'][] = array('text' => $this->language->get('text_orders'), 'href' => $this->url->link('sale/order', 'user_token=' . $this->session->data['user_token'], true));
			$data['add'] = $this->url->link('extension/shipping/novaposhta/getCNForm', 'user_token=' . $this->session->data['user_token'], true);
			$data['back_to_orders'] = $this->url->link('sale/order', 'user_token=' . $this->session->data['user_token'], true);
			$data['user_token'] = $this->session->data['user_token'];
			$config_admin_limit = $this->config->get('config_limit_admin');
		} else {
			if (version_compare(VERSION, '2.3', '>=')) {
				$data['breadcrumbs'] = array();
				$data['breadcrumbs'][] = array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', 'token=' . $this->session->data['token'], true));
				$data['breadcrumbs'][] = array('text' => $this->language->get('text_orders'), 'href' => $this->url->link('sale/order', 'token=' . $this->session->data['token'], true));
				$data['add'] = $this->url->link('extension/shipping/novaposhta/getCNForm', 'token=' . $this->session->data['token'], true);
				$data['back_to_orders'] = $this->url->link('sale/order', 'token=' . $this->session->data['token'], true);
				$data['token'] = $this->session->data['token'];
				$config_admin_limit = $this->config->get('config_limit_admin');
			} else {
				$data['breadcrumbs'] = array();

				if (version_compare(VERSION, '2', '>=')) {
					$data['breadcrumbs'][] = array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', 'token=' . $this->session->data['token'], 'SSL'));
					$config_admin_limit = $this->config->get('config_limit_admin');
				} else {
					$data['breadcrumbs'][] = array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/home', 'token=' . $this->session->data['token'], 'SSL'));
					$config_admin_limit = $this->config->get('config_admin_limit');
				}

				$data['breadcrumbs'][] = array('text' => $this->language->get('text_orders'), 'href' => $this->url->link('sale/order', 'token=' . $this->session->data['token'], 'SSL'));
				$data['add'] = $this->url->link('shipping/novaposhta/getCNForm', 'token=' . $this->session->data['token'], 'SSL');
				$data['back_to_orders'] = $this->url->link('sale/order', 'token=' . $this->session->data['token'], 'SSL');
				$data['token'] = $this->session->data['token'];
			}
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			$data['cn_number'] = $this->session->data['cn'];
			unset($this->session->data['success'], $this->session->data['cn']);
		} else {
			$data['success'] = '';
			$data['cn_number'] = '';
		}

		if (version_compare(VERSION, '3', '<')) {
			$data['heading_title'] = $this->language->get('heading_title');
			$data['heading_adding_to_registry'] = $this->language->get('heading_adding_to_registry');
			$data['button_filter'] = $this->language->get('button_filter');
			$data['button_add'] = $this->language->get('button_add');
			$data['button_delete_registry'] = $this->language->get('button_delete_registry');
			$data['button_back_to_orders'] = $this->language->get('button_back_to_orders');
			$data['column_cn_identifier'] = $this->language->get('column_cn_identifier');
			$data['column_cn_number'] = $this->language->get('column_cn_number');
			$data['column_order_number'] = $this->language->get('column_order_number');
			$data['column_registry'] = $this->language->get('column_registry');
			$data['column_create_date'] = $this->language->get('column_create_date');
			$data['column_actual_shipping_date'] = $this->language->get('column_actual_shipping_date');
			$data['column_preferred_shipping_date'] = $this->language->get('column_preferred_shipping_date');
			$data['column_estimated_shipping_date'] = $this->language->get('column_estimated_shipping_date');
			$data['column_recipient_date'] = $this->language->get('column_recipient_date');
			$data['column_last_updated_status_date'] = $this->language->get('column_last_updated_status_date');
			$data['column_sender'] = $this->language->get('column_sender');
			$data['column_sender_contact_person'] = $this->language->get('column_sender_contact_person');
			$data['column_sender_address'] = $this->language->get('column_sender_address');
			$data['column_recipient'] = $this->language->get('column_recipient');
			$data['column_recipient_contact_person'] = $this->language->get('column_recipient_contact_person');
			$data['column_recipient_address'] = $this->language->get('column_recipient_address');
			$data['column_weight'] = $this->language->get('column_weight');
			$data['column_seats_amount'] = $this->language->get('column_seats_amount');
			$data['column_declared_cost'] = $this->language->get('column_declared_cost');
			$data['column_payment_control'] = $this->language->get('column_payment_control');
			$data['column_shipping_cost'] = $this->language->get('column_shipping_cost');
			$data['column_backward_delivery'] = $this->language->get('column_backward_delivery');
			$data['column_service_type'] = $this->language->get('column_service_type');
			$data['column_description'] = $this->language->get('column_description');
			$data['column_additional_information'] = $this->language->get('column_additional_information');
			$data['column_payer_type'] = $this->language->get('column_payer_type');
			$data['column_payment_method'] = $this->language->get('column_payment_method');
			$data['column_departure_type'] = $this->language->get('column_departure_type');
			$data['column_packing_number'] = $this->language->get('column_packing_number');
			$data['column_rejection_reason'] = $this->language->get('column_rejection_reason');
			$data['column_status'] = $this->language->get('column_status');
			$data['column_action'] = $this->language->get('column_action');
			$data['entry_cn_number'] = $this->language->get('entry_cn_number');
			$data['entry_cn_type'] = $this->language->get('entry_cn_type');
			$data['entry_departure_date'] = $this->language->get('entry_departure_date');
			$data['entry_order_number'] = $this->language->get('entry_order_number');
			$data['entry_create_date'] = $this->language->get('entry_create_date');
			$data['entry_registry'] = $this->language->get('entry_registry');
			$data['entry_print_format'] = $this->language->get('entry_print_format');
			$data['entry_number_of_copies'] = $this->language->get('entry_number_of_copies');
			$data['entry_template_type'] = $this->language->get('entry_template_type');
			$data['entry_print_type'] = $this->language->get('entry_print_type');
			$data['entry_print_start'] = $this->language->get('entry_print_start');
			$data['text_consignment_note_list'] = $this->language->get('text_consignment_note_list');
			$data['text_order'] = $this->language->get('text_order');
			$data['text_add_to_registry'] = $this->language->get('text_add_to_registry');
			$data['text_delete_from_registry'] = $this->language->get('text_delete_from_registry');
			$data['text_add'] = $this->language->get('text_add');
			$data['text_edit'] = $this->language->get('text_edit');
			$data['text_assignment_order'] = $this->language->get('text_assignment_order');
			$data['text_print_settings'] = $this->language->get('text_print_settings');
			$data['text_delete'] = $this->language->get('text_delete');
			$data['text_customized_printing'] = $this->language->get('text_customized_printing');
			$data['text_download_pdf'] = $this->language->get('text_download_pdf');
			$data['text_print_html'] = $this->language->get('text_print_html');
			$data['text_cn_a4'] = $this->language->get('text_cn_a4');
			$data['text_cn_a5'] = $this->language->get('text_cn_a5');
			$data['text_mark_85_85'] = $this->language->get('text_mark_85_85');
			$data['text_mark_100_100'] = $this->language->get('text_mark_100_100');
			$data['text_registry'] = $this->language->get('text_registry');
			$data['text_select'] = $this->language->get('text_select');
			$data['text_no_results'] = $this->language->get('text_no_results');
			$data['text_confirm'] = $this->language->get('text_confirm');
		}

		$data['registers'] = $this->novaposhta->getRegistries();
		$filters = array();

		if ($filter_cn_number) {
			$filters['IntDocNumber'] = $filter_cn_number;
		}

		foreach ($filter_cn_type as $f) {
			$filters[$f] = 1;
		}
		$cns = $this->novaposhta->getCNList($filter_departure_date_from, $filter_departure_date_to, $filters);

		if ($cns && is_array($cns)) {
			if (version_compare(VERSION, '2.3', '>=')) {
				$this->load->model('extension/shipping/' . $this->extension);
				$model_name = 'model_extension_shipping_novaposhta';
			} else {
				$this->load->model('shipping/' . $this->extension);
				$model_name = 'model_shipping_novaposhta';
			}

			$service_types = $this->novaposhta->getReferences();

			foreach ($service_types as $i => $service_type) {
				foreach ($service_type as $k => $v) {
					if (isset($v['Ref'])) {
						$service_types[$i][$v['Ref']] = $v['Description'];
						unset($service_types[$i][$k]);
					}
				}
			}

			foreach ($cns as $k => $cn) {
				$order = $this->$model_name->getOrderByDocumentNumber($cn['IntDocNumber']);

				if (!$this->settings['display_all_consignments'] && !$order) {
					unset($cns[$k]);

					continue;
				}

				if ($order) {
					if (version_compare(VERSION, '3', '>=')) {
						$cns[$k]['order'] = $this->url->link('sale/order/info', 'user_token=' . $this->session->data['user_token'] . '&order_id=' . $order['order_id'], true);
					} else {
						if (version_compare(VERSION, '2.3', '>=')) {
							$cns[$k]['order'] = $this->url->link('sale/order/info', 'token=' . $this->session->data['token'] . '&order_id=' . $order['order_id'], true);
						} else {
							$cns[$k]['order'] = $this->url->link('sale/order/info', 'token=' . $this->session->data['token'] . '&order_id=' . $order['order_id'], 'SSL');
						}
					}

					$cns[$k]['order_id'] = $order['order_id'];
				}

				$cns[$k]['create_date'] = date('d-m-Y H:i', strtotime($cn['CreateTime']));
				$cns[$k]['actual_shipping_date'] = date('d-m-Y H:i', strtotime($cn['DateTime']));

				if (strtotime($cn['PreferredDeliveryDate'])) {
					$cns[$k]['preferred_shipping_date'] = date('d-m-Y H:i', strtotime($cn['PreferredDeliveryDate']));
				} else {
					$cns[$k]['preferred_shipping_date'] = '';
				}

				$cns[$k]['estimated_shipping_date'] = date('d-m-Y H:i', strtotime($cn['EstimatedDeliveryDate']));

				if ($cn['RecipientDateTime'] !== null && strtotime($cn['RecipientDateTime']) !== false) {
					$cns[$k]['recipient_date'] = date('d-m-Y H:i', strtotime($cn['RecipientDateTime']));
				} else {
					$cns[$k]['recipient_date'] = '';
				}

				$cns[$k]['last_updated_status_date'] = date('d-m-Y H:i', strtotime($cn['DateLastUpdatedStatus']));
				$cns[$k]['sender'] = $cn['SenderDescription'];
				$cns[$k]['sender_contact_person'] = $cn['SenderContactPerson'];

				if ($cn['SendersPhone']) {
					$cns[$k]['sender_contact_person'] .= ' ' . $cn['SendersPhone'];
				}

				$cns[$k]['sender_address'] = $cn['CitySenderDescription'] . ', ' . $cn['SenderAddressDescription'];
				$cns[$k]['recipient'] = $cn['RecipientDescription'];
				$cns[$k]['recipient_contact_person'] = $cn['RecipientContactPerson'] . ' ' . $cn['RecipientContactPhone'];
				$cns[$k]['recipient_address'] = $cn['CityRecipientDescription'] . ', ' . $cn['RecipientAddressDescription'];
				$cns[$k]['declared_cost'] = $this->currency->format($cn['Cost'], 'UAH', 1);
				$cns[$k]['payment_control'] = $this->currency->format($cn['AfterpaymentOnGoodsCost'], 'UAH', 1);
				$cns[$k]['shipping_cost'] = $this->currency->format($cn['CostOnSite'], 'UAH', 1);

				if ($cn['BackwardDeliveryCargoType']) {
					$cns[$k]['backward_delivery'] = $cn['BackwardDeliveryCargoType'] . ': ' . $this->currency->format($cn['BackwardDeliverySum'], 'UAH', 1);
				} else {
					$cns[$k]['backward_delivery'] = '';
				}

				$cns[$k]['service_type'] = $service_types['service_types'][$cn['ServiceType']];
				$cns[$k]['payer_type'] = $service_types['payer_types'][$cn['PayerType']];
				$cns[$k]['payment_method'] = $service_types['payment_types'][$cn['PaymentMethod']];
				$cns[$k]['departure_type'] = $service_types['cargo_types'][$cn['CargoType']];
				$cns[$k]['status'] = '(' . $this->language->get('entry_code') . ' ' . $cn['StateId'] . ') ' . $cn['StateName'];
			}
			$cns_total = count($cns);
			$cns = $this->novaposhta->multiSort($cns, 'IntDocNumber', 'DESC');
			$cns = array_slice($cns, ($page - 1) * $config_admin_limit, $config_admin_limit);
		} else {
			$cns_total = 0;
		}

		$data['cns'] = $cns;
		$pagination = new Pagination();
		$pagination->total = $cns_total;
		$pagination->page = $page;

		if (version_compare(VERSION, '2', '>=')) {
			$pagination->limit = $config_admin_limit;

			if (version_compare(VERSION, '3', '>=')) {
				$pagination->url = $this->url->link('extension/shipping/novaposhta/getCNList', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}', true);
			} else {
				if (version_compare(VERSION, '2.3', '>=')) {
					$pagination->url = $this->url->link('extension/shipping/novaposhta/getCNList', 'token=' . $this->session->data['token'] . $url . '&page={page}', true);
				} else {
					$pagination->url = $this->url->link('shipping/novaposhta/getCNList', 'token=' . $this->session->data['token'] . $url . '&page={page}', 'SSL');
				}
			}

			$data['results'] = sprintf($this->language->get('text_pagination'), ($cns_total ? ($page - 1) * $config_admin_limit + 1 : 0), ($cns_total - $config_admin_limit < ($page - 1) * $config_admin_limit ? $cns_total : ($page - 1) * $config_admin_limit + $config_admin_limit), $cns_total, ceil($cns_total / $config_admin_limit));
		} else {
			$pagination->limit = $config_admin_limit;
			$pagination->text = $this->language->get('text_pagination');
			$pagination->url = $this->url->link('shipping/novaposhta/getCNList', 'token=' . $this->session->data['token'] . $url . '&page={page}', 'SSL');
		}

		$data['pagination'] = $pagination->render();
		$data['key_api'] = $this->novaposhta->key_api;
		$data['filters'] = array('RedeliveryMoney' => $this->language->get('text_redelivery_money'), 'UnassembledCargo' => $this->language->get('text_unassembled_cargo'));
		$data['print_formats'] = array('document_A4' => $this->language->get('text_cn_a4'), 'document_A5' => $this->language->get('text_cn_a5'), 'mark_85_85' => $this->language->get('text_mark_85_85'), 'mark_100_100' => $this->language->get('text_mark_100_100'), 'registry' => $this->language->get('text_registry'));
		$data['template_types'] = array('html' => $this->language->get('text_html'), 'pdf' => $this->language->get('text_pdf'));
		$data['print_types'] = array('horPrint' => $this->language->get('text_horizontally'), 'verPrint' => $this->language->get('text_vertically'));

		if (!empty($this->settings['consignment_displayed_information'])) {
			$data['displayed_information'] = $this->settings['consignment_displayed_information'];
		} else {
			$data['displayed_information'] = array();
		}

		$data['filter_cn_number'] = $filter_cn_number;
		$data['filter_cn_type'] = $filter_cn_type;
		$data['filter_departure_date_from'] = $filter_departure_date_from;
		$data['filter_departure_date_to'] = $filter_departure_date_to;
		$data['v'] = $this->version;

		if ($this->language->get('code') == 'ua' || $this->language->get('code') == 'uk' || $this->language->get('code') == 'uk-ua' || $this->language->get('code') == 'ua-uk') {
			$data['translations_code'] = 'ua_UA';
		} else {
			if ($this->language->get('code') == 'ru' || $this->language->get('code') == 'ru-ru') {
				$data['translations_code'] = 'ru_RU';
			} else {
				$data['translations_code'] = 'en_US';
			}
		}

		if (version_compare(VERSION, '2.3', '>=')) {
			$data['header'] = $this->load->controller('common/header');
			$data['column_left'] = $this->load->controller('common/column_left');
			$data['footer'] = $this->load->controller('common/footer');
			$this->response->setOutput($this->load->view('extension/shipping/' . $this->extension . '_cn_list', $data));
		} else {
			if (version_compare(VERSION, '2', '>=')) {
				$data['header'] = $this->load->controller('common/header');
				$data['column_left'] = $this->load->controller('common/column_left');
				$data['footer'] = $this->load->controller('common/footer');
				$this->response->setOutput($this->load->view('shipping/' . $this->extension . '_cn_list.tpl', $data));
			} else {
				$data['header'] = $this->getChild('common/header');
				$data['footer'] = $this->getChild('common/footer');
				$this->template = 'shipping/' . $this->extension . '_cn_list.tpl';
				// Load required libraries for older OpenCart versions
				$patterns = array('/<script type="text\\/javascript" src="view\\/javascript\\/jquery\\/jquery-(.+)\\.min\\.js"><\\/script>/', '/<script type="text\\/javascript" src="view\\/javascript\\/jquery\\/ui\\/jquery-ui-(.+)\\.js"><\\/script>/');
				$replacements = array("<script type=\"text/javascript\" src=\"https://code.jquery.com/jquery-2.1.1.min.js\"></script>\r\n                 <script type=\"text/javascript\" src=\"https://code.jquery.com/jquery-migrate-1.2.1.min.js\"></script>\r\n                 <script type=\"text/javascript\" src=\"https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js\"></script>\r\n                 <script type=\"text/javascript\" src=\"https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js\"></script>\r\n                 <script type=\"text/javascript\" src=\"https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datetimepicker/4.17.47/js/bootstrap-datetimepicker.min.js\"></script>\r\n                 <link rel=\"stylesheet\" type=\"text/css\" href=\"https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css\" media=\"screen\" />\r\n                 <link rel=\"stylesheet\" type=\"text/css\" href=\"https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css\" media=\"screen\" />\r\n                 <link rel=\"stylesheet\" type=\"text/css\" href=\"https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datetimepicker/4.17.47/css/bootstrap-datetimepicker.min.css\" media=\"screen\" />", '<script type="text/javascript" src="https://code.jquery.com/ui/1.8.24/jquery-ui.min.js"></script>');
				$data['header'] = preg_replace($patterns, $replacements, $data['header']);
				$this->data = $data;
				$this->response->setOutput($this->render());
			}
		}
	}

	public function saveCN()
	{
		if (version_compare(VERSION, '2.3', '>=')) {
			$this->load->language('extension/shipping/' . $this->extension);
			$this->load->model('extension/shipping/' . $this->extension);
			$model_name = 'model_extension_shipping_novaposhta';
		} else {
			$this->load->language('shipping/' . $this->extension);
			$this->load->model('shipping/' . $this->extension);
			$model_name = 'model_shipping_novaposhta';
		}

		if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validate() && $this->validateCNForm()) {
			$properties_cn = array('NewAddress' => 1, 'Sender' => $this->request->post['sender'], 'ContactSender' => $this->request->post['sender_contact_person'], 'SendersPhone' => $this->request->post['sender_contact_person_phone'], 'CitySender' => $this->request->post['sender_city'], 'Recipient' => $this->request->post['recipient'], 'RecipientsPhone' => $this->request->post['recipient_contact_person_phone'], 'CargoType' => $this->request->post['departure_type'], 'SeatsAmount' => $this->request->post['seats_amount'], 'Cost' => $this->request->post['declared_cost'], 'Description' => $this->request->post['departure_description'], 'PayerType' => $this->request->post['delivery_payer'], 'PaymentMethod' => $this->request->post['payment_type'], 'DateTime' => $this->request->post['departure_date']);

			if (empty($this->request->post['sender_address_pick_up'])) {
				$properties_cn['SenderAddress'] = $this->request->post['sender_department'];
				$sender_address_type = 'Warehouse';
			} else {
				$properties_cn['SenderAddress'] = $this->request->post['sender_address'];
				$sender_address_type = 'Doors';
			}

			if ($this->request->post['recipient_address_type'] == 'doors') {
				$recipient_address_type = 'Doors';
			} else {
				$recipient_address_type = 'Warehouse';
			}

			$properties_cn['ServiceType'] = $sender_address_type . $recipient_address_type;

			if ($this->request->post['recipient_address_type'] == 'doors') {
				$counterparty_addresses = $this->novaposhta->getCounterpartyAddresses($properties_cn['Recipient'], 'Recipient', $this->request->post['recipient_settlement']);

				if ($counterparty_addresses) {
					foreach ($counterparty_addresses as $k => $v) {
						if ($v['BuildingDescription'] == $this->request->post['recipient_house'] && (!$this->request->post['recipient_flat'] && mb_stripos($v['Description'], 'кв.') === false || $this->request->post['recipient_flat'] && mb_stripos($v['Description'], 'кв. ' . $this->request->post['recipient_flat']) !== false)) {
							$properties_cn['CityRecipient'] = $this->request->post['recipient_settlement'];
							$properties_cn['RecipientAddress'] = $k;

							break;
						}
					}
				}

				if (empty($properties_cn['RecipientAddress'])) {
					$properties_cn['RecipientArea'] = $this->request->post['recipient_region_name'];
					$properties_cn['RecipientAreaRegions'] = $this->request->post['recipient_district_name'];
					$properties_cn['RecipientCityName'] = $this->request->post['recipient_settlement_name'];
					$properties_cn['RecipientAddressName'] = $this->request->post['recipient_street_name'];
					$properties_cn['RecipientHouse'] = $this->request->post['recipient_house'];
					$properties_cn['RecipientFlat'] = $this->request->post['recipient_flat'];
				}
			} else {
				if ($this->request->post['recipient_address_type'] == 'department') {
					$properties_cn['CityRecipient'] = $this->request->post['recipient_city'];
					$properties_cn['RecipientAddress'] = $this->request->post['recipient_department'];
				} else {
					$properties_cn['CityRecipient'] = $this->request->post['recipient_city'];
					$properties_cn['RecipientAddress'] = $this->request->post['recipient_poshtomat'];
				}
			}

			$recipient = $this->novaposhta->getCounterparty('Recipient', $this->request->post['recipient']);

			if ($recipient && $recipient['CounterpartyType'] == 'Organization') {
				$properties_cn['RecipientContactName'] = preg_replace('/ {2,}/', ' ', mb_convert_case(trim($this->request->post['recipient_contact_person']), MB_CASE_TITLE, 'UTF-8'));
				$properties_cn['RecipientType'] = $recipient['CounterpartyType'];
				$properties_cn['OwnershipForm'] = $recipient['OwnershipFormRef'];
			} else {
				$properties_cn['RecipientName'] = preg_replace('/ {2,}/', ' ', mb_convert_case(trim($this->request->post['recipient_contact_person']), MB_CASE_TITLE, 'UTF-8'));
				$properties_cn['RecipientType'] = $recipient['CounterpartyType'];
			}

			if ($this->request->post['departure_type'] == 'Parcel' || $this->request->post['departure_type'] == 'Cargo') {
				if (empty($this->request->post['general_parameters'])) {
					$weight = 0;

					foreach ($this->request->post['parcels'] as $i => $parcel) {
						$properties_cn['OptionsSeat'][$i] = array('weight' => $parcel['weight'], 'volumetricLength' => $parcel['length'], 'volumetricWidth' => $parcel['width'], 'volumetricHeight' => $parcel['height'], 'volumetricVolume' => max(round(($parcel['length'] * $parcel['width'] * $parcel['height']) / 1000000, 4), 0.0004));
						$weight += $parcel['weight'];

						if (!empty($parcel['manual_processing'])) {
							$properties_cn['OptionsSeat'][$i]['specialCargo'] = 1;
						}

						if (!empty($parcel['pack']) && !empty($parcel['pack_type'])) {
							$properties_cn['OptionsSeat'][$i]['packRef'] = $parcel['pack_type'];
						}
					}
					$properties_cn['Weight'] = $weight;
				} else {
					$properties_cn['Weight'] = $this->request->post['weight_general'];
					$properties_cn['VolumeGeneral'] = $this->request->post['volume_general'];
					$properties_cn['VolumeWeight'] = $this->request->post['volume_weight_general'];

					if (!empty($this->request->post['pack_general']) && !empty($this->request->post['pack_type_general'])) {
						$properties_cn['OptionsSeat'][0] = array('weight' => $this->request->post['weight_general'], 'volumetricVolume' => $this->request->post['volume_general'], 'volumetricWeight' => $this->request->post['volume_weight_general']);

						if (!empty($this->request->post['pack_type_general'])) {
							$properties_cn['OptionsSeat'][0]['packRef'] = $this->request->post['pack_type_general'];
						}
					}
				}
			} else {
				if ($this->request->post['departure_type'] == 'Documents') {
					$properties_cn['Weight'] = $this->request->post['weight_general'];
				} else {
					if ($this->request->post['departure_type'] == 'TiresWheels') {
						foreach ($this->request->post['tires_and_wheels'] as $ref => $amount) {
							if ($amount) {
								$properties_cn['CargoDetails'][] = array('CargoDescription' => $ref, 'Amount' => $amount);
							}
						}
					}
				}
			}

			if (isset($this->request->post['third_person'])) {
				$properties_cn['ThirdPerson'] = $this->request->post['third_person'];
			}

			if ($this->request->post['backward_delivery'] && $this->request->post['backward_delivery'] != 'N' && $this->request->post['backward_delivery'] == 'Money') {
				$properties_cn['BackwardDeliveryData'][0] = array('CargoType' => $this->request->post['backward_delivery'], 'PayerType' => $this->request->post['backward_delivery_payer'], 'RedeliveryString' => $this->request->post['backward_delivery_total']);
			}

			if (!empty($this->request->post['payment_control'])) {
				$properties_cn['AfterpaymentOnGoodsCost'] = $this->request->post['payment_control'];
			}

			if (!empty($this->request->post['preferred_delivery_date'])) {
				$properties_cn['PreferredDeliveryDate'] = $this->request->post['preferred_delivery_date'];
			}

			if (!empty($this->request->post['time_interval'])) {
				$properties_cn['TimeInterval'] = $this->request->post['time_interval'];
			}

			if (!empty($this->request->post['order_number'])) {
				$properties_cn['InfoRegClientBarcodes'] = $this->request->post['order_number'];
			}

			if (!empty($this->request->post['packing_number'])) {
				$properties_cn['PackingNumber'] = $this->request->post['packing_number'];
			}

			if (!empty($this->request->post['additional_information'])) {
				$properties_cn['AdditionalInformation'] = $this->request->post['additional_information'];
			}

			if (!empty($this->request->post['avia_delivery'])) {
				$properties_cn['AviaDelivery'] = 1;
			}

			if (!empty($this->request->post['rise_on_floor'])) {
				$properties_cn['NumberOfFloorsLifting'] = $this->request->post['rise_on_floor'];
			}

			if (!empty($this->request->post['elevator'])) {
				$properties_cn['Elevator'] = 1;
			}

			if (!empty($this->request->get['cn_ref'])) {
				$properties_cn['Ref'] = $this->request->get['cn_ref'];
			}

			$result = $this->novaposhta->saveCN($properties_cn);

			if ($result) {
				if (!empty($this->request->get['order_id'])) {
					$this->$model_name->addCNToOrder($this->request->get['order_id'], $result['IntDocNumber'], $result['Ref']);
				}
			} else {
				$this->error['warning'] = $this->novaposhta->error;
				$this->error['warning'][] = $this->language->get('error_cn_save');
			}
		}

		if ($this->error) {
			$json = $this->error;
		} else {
			if (!empty($result['IntDocNumber'])) {
				$this->session->data['cn'] = $result['IntDocNumber'];
				$this->session->data['success'] = $this->language->get('text_cn_success_save');
				$json['success'] = $this->request->post['departure_date'];
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function deleteCN()
	{
		if (version_compare(VERSION, '2.3', '>=')) {
			$this->load->language('extension/shipping/' . $this->extension);
			$this->load->model('extension/shipping/' . $this->extension);
			$model_name = 'model_extension_shipping_novaposhta';
		} else {
			$this->load->language('shipping/' . $this->extension);
			$this->load->model('shipping/' . $this->extension);
			$model_name = 'model_shipping_novaposhta';
		}

		$json = array();

		if ($this->validate() && isset($this->request->post['refs'])) {
			if (!empty($this->request->post['orders'])) {
				$this->$model_name->deleteCNFromOrder($this->request->post['orders']);
			}

			$data = $this->novaposhta->deleteCN($this->request->post['refs']);

			if ($data) {
				$json['success']['refs'] = $data;
				$json['success']['text'] = $this->language->get('text_success_delete');
			} else {
				$json['warning'] = $this->novaposhta->error;
				$json['warning'][] = $this->language->get('error_delete');
			}
		} else {
			$json['warning'][] = $this->error['warning'];
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function registryCN()
	{
		if (version_compare(VERSION, '2.3', '>=')) {
			$this->load->language('extension/shipping/' . $this->extension);
		} else {
			$this->load->language('shipping/' . $this->extension);
		}

		$json = array();

		if (isset($this->request->post['action'])) {
			$action = $this->request->post['action'];
		} else {
			$action = '';
		}

		if (isset($this->request->post['cns'])) {
			$cns = $this->request->post['cns'];
		} else {
			$cns = array();
		}

		if (isset($this->request->post['registry'])) {
			$registry = $this->request->post['registry'];
		} else {
			$registry = '';
		}

		if (!empty($this->request->post['date'])) {
			$date = date('d.m.Y', strtotime(urlencode(html_entity_decode($this->request->post['date'], ENT_QUOTES, 'UTF-8'))));
		} else {
			$date = '';
		}

		if ($action == 'add' && $this->validate()) {
			$result = $this->novaposhta->saveRegistry($cns, $registry, $date);

			if (!empty($result['Success']) && is_array($result['Success'])) {
				$json['success']['text'] = $this->language->get('text_success_added_to_registry');

				foreach ($result['Success'] as $v) {
					$json['success']['cns'][] = $v['Number'];
				}
			}

			if (!empty($result['Errors']) && is_array($result['Errors'])) {
				foreach ($result['Errors'] as $v) {
					if (isset($v['Error'])) {
						$error_text = $v['Error'];
					} else {
						$error_text = $v;
					}

					$json['error'][] = $error_text;
				}
			}

			if (!empty($result['Warnings']) && is_array($result['Warnings'])) {
				foreach ($result['Warnings'] as $v) {
					if (isset($v['Warnings'])) {
						$error_text = $v['Warning'];
					} else {
						$error_text = $v;
					}

					$json['error'][] = $error_text;
				}
			}

			if (!empty($result['Data']['Errors']) && is_array($result['Data']['Errors'])) {
				foreach ($result['Data']['Errors'] as $v) {
					$error_text = $v['Error'];

					if (isset($v['Number'])) {
						$error_text .= ' ' . $v['Number'];
					}

					$json['error'][] = $error_text;
				}
			}

			if (!empty($result['Data']['Warnings']) && is_array($result['Data']['Warnings'])) {
				foreach ($result['Data']['Warnings'] as $v) {
					$error_text = $v['Warning'];

					if (isset($v['Number'])) {
						$error_text .= ' ' . $v['Number'];
					}

					$json['error'][] = $error_text;
				}
			}

			if (empty($result) || !$result['Ref'] || empty($result['Success'])) {
				$json['error'][] = $this->language->get('error_added_to_registry');
			}
		} else {
			if ($action == 'delete' && $this->validate()) {
				$result = $this->novaposhta->deleteCNFromRegistry($cns, $registry);

				if (empty($result)) {
					$json['error'][] = $this->language->get('error_deleted_from_registry');
				}

				if (!empty($result['Success']) && is_array($result['Success'])) {
					$json['success']['text'] = $this->language->get('text_success_deleted_from_registry');

					foreach ($result['Success'] as $v) {
						$json['success']['cns'][] = $v['Number'];
					}
				}

				if (!empty($result['Errors']) && is_array($result['Errors'])) {
					foreach ($result['Errors'] as $v) {
						$error_text = $v['Error'];

						if (isset($v['Number'])) {
							$error_text .= ' ' . $v['Number'];
						}

						$json['error'][] = $error_text;
					}
					$json['error'][] = $this->language->get('error_deleted_from_registry');
				}

				if (!empty($result['Warnings']) && is_array($result['Warnings'])) {
					foreach ($result['Warnings'] as $v) {
						$error_text = $v['Warning'];

						if (isset($v['Number'])) {
							$error_text .= ' ' . $v['Number'];
						}

						$json['error'][] = $error_text;
					}
				}
			} else {
				if ($action == 'delete_registry' && $this->validate()) {
					$result = $this->novaposhta->deleteRegistry(array($registry));

					if (empty($result)) {
						$json['error'][] = $this->language->get('error_deleted_registry');
					}

					if (!empty($result['Success']) && is_array($result['Success'])) {
						$json['success']['text'] = $this->language->get('text_success_deleted_registry');

						foreach ($result['Success'] as $v) {
							$json['success']['sheets'][] = $v['Number'];
						}
					}

					if (!empty($result['Errors']) && is_array($result['Errors'])) {
						foreach ($result['Errors'] as $v) {
							$error_text = $v['Error'];

							if (isset($v['Number'])) {
								$error_text .= ' ' . $v['Number'];
							}

							$json['error'][] = $error_text;
						}
						$json['error'][] = $this->language->get('error_deleted_registry');
					}
				} else {
					$json = $this->error;
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function addCNToOrder()
	{
		if (version_compare(VERSION, '2.3', '>=')) {
			$this->load->language('extension/shipping/' . $this->extension);
			$this->load->model('extension/shipping/' . $this->extension);
			$model_name = 'model_extension_shipping_novaposhta';
		} else {
			$this->load->language('shipping/' . $this->extension);
			$this->load->model('shipping/' . $this->extension);
			$model_name = 'model_shipping_novaposhta';
		}

		$json = array();

		if ($this->validate() && isset($this->request->post['order_id'])) {
			if (isset($this->request->post['cn_number']) && isset($this->request->post['cn_ref'])) {
				$result = $this->$model_name->addCNToOrder($this->request->post['order_id'], $this->request->post['cn_number'], $this->request->post['cn_ref']);
			} else {
				$documents = $this->novaposhta->getCNList('', '', array('IntDocNumber' => $this->request->post['cn_number']));

				if (isset($documents[0])) {
					$result = $this->$model_name->addCNToOrder($this->request->post['order_id'], $documents[0]['IntDocNumber'], $documents[0]['Ref']);
				}

				if (empty($result)) {
					$document = $this->novaposhta->tracking(array(array('DocumentNumber' => $this->request->post['cn_number'], 'Phone' => '')));

					if ($document) {
						$result = $this->$model_name->addCNToOrder($this->request->post['order_id'], $this->request->post['cn_number']);
					}
				}
			}

			if (empty($result)) {
				$json['error'] = $this->language->get('error_cn_assignment');
			} else {
				$json['success'] = $this->language->get('text_cn_success_assignment');
			}
		} else {
			$json['error'] = $this->error['warning'];
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function deleteCNFromOrder()
	{
		if (version_compare(VERSION, '2.3', '>=')) {
			$this->load->language('extension/shipping/' . $this->extension);
			$this->load->model('extension/shipping/' . $this->extension);
			$model_name = 'model_extension_shipping_novaposhta';
		} else {
			$this->load->language('shipping/' . $this->extension);
			$this->load->model('shipping/' . $this->extension);
			$model_name = 'model_shipping_novaposhta';
		}

		$json = array();

		if ($this->validate() && isset($this->request->post['order_id'])) {
			$order = $this->$model_name->getOrder($this->request->post['order_id']);

			if (!empty($order['novaposhta_cn_number'])) {
				$this->novaposhta->deleteCN(array($order['novaposhta_cn_ref']));
				$this->$model_name->deleteCNFromOrder(array($this->request->post['order_id']));
				$json['success'] = $this->language->get('text_success_delete');
			} else {
				$json['error'] = $this->language->get('error_delete');
			}
		} else {
			$json['error'] = $this->error['warning'];
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function verifyingAPIaccess()
	{
		if (version_compare(VERSION, '2.3', '>=')) {
			$this->load->language('extension/shipping/' . $this->extension);
		} else {
			$this->load->language('shipping/' . $this->extension);
		}

		$json = array();

		if (isset($this->request->post['action'])) {
			$action = $this->request->post['action'];
		} else {
			$action = '';
		}

		if (isset($this->request->post['key'])) {
			$key = $this->request->post['key'];
		} else {
			$key = '';
		}

		$this->novaposhta->key_api = $key;

		if (!$this->validate()) {
			$json['error'][] = $this->language->get('error_permission');
		} else {
			if ($action == 'check') {
				$data = $this->novaposhta->getCounterparties('Sender');

				if (!$data) {
					$json['error'][] = $this->language->get('error_key_api');
				} else {
					$json['next_action'] = 'references';
					$json['next_action_text'] = $this->language->get('text_references_updating');
				}
			} else {
				if ($action == 'references') {
					$database = $this->novaposhta->getReferences('database');
					$data = $this->novaposhta->update('references');

					if ($data === false) {
						$json['error'][] = $this->language->get('error_update');
					} else {
						if (empty($database)) {
							$json['next_action'] = 'regions';
							$json['next_action_text'] = $this->language->get('text_regions_updating');
						} else {
							if (!empty($this->settings['recipient_name'])) {
								$recipient = current($this->novaposhta->getCounterparties('Recipient', $this->settings['recipient_name']));
								$json['recipient'] = $recipient['Ref'];
								$this->cache->delete('novaposhta_recipients');
							}

							$json['next_action'] = 'saving';
							$json['next_action_text'] = $this->language->get('text_saving_settings');
						}
					}
				} else {
					if ($action == 'regions') {
						$data = $this->novaposhta->update('regions');

						if ($data === false) {
							$json['error'][] = $this->language->get('error_update');
						} else {
							$json['next_action'] = 'cities';
							$json['next_action_text'] = $this->language->get('text_cities_updating');
						}
					} else {
						if ($action == 'cities') {
							$data = $this->novaposhta->update('cities');

							if ($data === false) {
								$json['error'][] = $this->language->get('error_update');
							} else {
								$json['next_action'] = 'departments';
								$json['next_action_text'] = $this->language->get('text_departments_updating');
							}
						} else {
							if ($action == 'departments') {
								$data = $this->novaposhta->update('departments');

								if ($data === false) {
									$json['error'][] = $this->language->get('error_update');
								} else {
									$json['next_action'] = 'saving';
									$json['next_action_text'] = $this->language->get('text_saving_settings');
								}
							}
						}
					}
				}
			}
		}

		if (!empty($this->novaposhta->error) && isset($json['error'])) {
			$json['error'] = array_merge($json['error'], $this->novaposhta->error);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function update()
	{
		if (version_compare(VERSION, '2.3', '>=')) {
			$this->load->language('extension/shipping/' . $this->extension);
		} else {
			$this->load->language('shipping/' . $this->extension);
		}

		$json = array();

		if (!$this->validate()) {
			$json['error'] = $this->error['warning'];
		} else {
			if (isset($this->request->get['type'])) {
				$type = $this->request->get['type'];
			}

			$amount = $this->novaposhta->update($type);

			if ($amount === false) {
				$json['error'] = $this->language->get('error_update');
			} else {
				$json['success'] = $this->language->get('text_update_success');
				$json['amount'] = $amount;
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function getNPData()
	{
		$json = array();

		if (isset($this->request->post['action'])) {
			$action = $this->request->post['action'];
		} else {
			$action = '';
		}

		if (isset($this->request->post['filter'])) {
			$filter = $this->request->post['filter'];
		} else {
			$filter = '';
		}

		if ($action == 'getSenderContactPersons') {
			$sender_contact_persons = $this->novaposhta->getReferences('sender_contact_persons');

			if (isset($sender_contact_persons[$filter])) {
				$json = $sender_contact_persons[$filter];
			}
		} else {
			if ($action == 'getRecipients') {
				$json = $this->cache->get('novaposhta_recipients');

				if (!$json) {
					$json = $this->novaposhta->getCounterparties('Recipient');
					$this->cache->set('novaposhta_recipients', $json);
				}
			} else {
				if ($action == 'getCities') {
					$json = $this->novaposhta->getCities($filter);
				} else {
					if ($action == 'getDepartments') {
						if (isset($this->request->post['type'])) {
							$type = $this->request->post['type'];
						} else {
							$type = '';
						}

						$json = $this->novaposhta->getDepartments($filter, $type);
					} else {
						if ($action == 'getAddress') {
							$json = $this->novaposhta->getSenderAddresses($this->request->post['sender'], $filter);
						} else {
							if ($action == 'getSenderOptions') {
								$sender_options = $this->novaposhta->getReferences('sender_options');

								if (isset($sender_options[$filter])) {
									$json = $sender_options[$filter];
								}
							} else {
								if ($action == 'getTimeIntervals') {
									if (isset($this->request->post['delivery_date'])) {
										$delivery_date = $this->request->post['delivery_date'];
									} else {
										$delivery_date = '';
									}

									$json = $this->novaposhta->getTimeIntervals($this->novaposhta->getCityRef($filter), $delivery_date);
								}
							}
						}
					}
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function autocomplete()
	{
		$json = array();

		if (isset($this->request->post['recipient_name'])) {
			$recipients = $this->novaposhta->getCounterparties('Recipient', $this->request->post['recipient_name']);

			if ($recipients) {
				$recipients = array_slice($recipients, 0, 10);

				foreach ($recipients as $k => $recipient) {
					$recipients[$k]['FullDescription'] = $recipient['OwnershipFormDescription'] . ' ' . $recipient['Description'];

					if ($recipient['CityDescription']) {
						$recipients[$k]['FullDescription'] .= ' (' . $recipient['CityDescription'] . ')';
					}
				}
				$json = $recipients;
			}
		} else {
			if (isset($this->request->post['settlement'])) {
				$settlements = $this->novaposhta->searchSettlements($this->request->post['settlement']);

				if ($settlements) {
					foreach ($settlements as $settlement) {
						$json[] = array('Ref' => $settlement['Ref'], 'Description' => $settlement['MainDescription'], 'Area' => $settlement['Area'], 'Region' => $settlement['Region'], 'FullDescription' => $settlement['Present']);
					}
				}
			} else {
				if (isset($this->request->post['street'])) {
					$streets = $this->novaposhta->searchSettlementStreets($this->request->post['filter'], $this->request->post['street'], 20);

					if ($streets) {
						foreach ($streets as $street) {
							$json[] = array('Ref' => $street['SettlementStreetRef'], 'Description' => $street['Present']);
						}
					}
				} else {
					if (!empty($this->request->post['departure_description'])) {
						$limit = 5;
						$descriptions = $this->novaposhta->getReferences('cargo_description');

						foreach ($descriptions as $description) {
							if (preg_match('/^(' . preg_quote($this->request->post['departure_description']) . ').+/iu', $description[$this->novaposhta->description_field])) {
								$limit--;
								$json[] = $description;
							}

							if (!$limit) {
								break;
							}
						}
					}
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function generateKey()
	{
		$data['code'] = md5(time() + rand()) . rand() . md5(time());
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($data));
	}

	public function extensionSettings($config = false)
	{
		if (version_compare(VERSION, '3', '>=')) {
			$this->load->language('extension/shipping/' . $this->extension);
			$extension_code = 'shipping_' . $this->extension;
		} else {
			if (version_compare(VERSION, '2.3', '>=')) {
				$this->load->language('extension/shipping/' . $this->extension);
				$extension_code = $this->extension;
			} else {
				$this->load->language('shipping/' . $this->extension);
				$extension_code = $this->extension;
			}
		}

		if (isset($this->request->get['type'])) {
			$type = $this->request->get['type'];
		} else {
			$type = '';
		}

		if ($config) {
			$settings = $this->config->get($extension_code);

			if (empty($settings)) {
				$settings = array('debugging_mode' => 0, 'key_api' => '', 'image' => '', 'image_output_place' => '', 'curl_connecttimeout' => '', 'curl_timeout' => '', 'shipping_methods' => array('department' => array('status' => 0, 'name' => array('', '', '', '', ''), 'geo_zone_id' => '', 'tax_class_id' => '', 'minimum_order_amount' => '', 'maximum_order_amount' => '', 'free_shipping' => '', 'free_cost_text' => array('', '', '', '', ''), 'cost' => 0, 'api_calculation' => 0, 'tariff_calculation' => 0, 'delivery_period' => 0, 'filter_weight' => 0, 'filter_dimensions' => 0, 'department_types' => array(), 'department_statuses' => array()), 'doors' => array('status' => 0, 'name' => array('', '', '', '', ''), 'geo_zone_id' => '', 'tax_class_id' => '', 'minimum_order_amount' => '', 'maximum_order_amount' => '', 'free_shipping' => '', 'free_cost_text' => array('', '', '', '', ''), 'cost' => 0, 'api_calculation' => 0, 'tariff_calculation' => 0, 'delivery_period' => 0), 'poshtomat' => array('status' => 0, 'name' => array('', '', '', '', ''), 'geo_zone_id' => '', 'tax_class_id' => '', 'minimum_order_amount' => '', 'maximum_order_amount' => '', 'free_shipping' => '', 'free_cost_text' => array('', '', '', '', ''), 'cost' => 0, 'api_calculation' => 0, 'tariff_calculation' => 0, 'delivery_period' => 0, 'filter_weight' => 0, 'filter_dimensions' => 0)), 'tariffs' => array('parcel' => array('department_delivery_period' => '', 'city_delivery_period' => '', 'region_delivery_period' => '', 'ukraine_delivery_period' => '', 'discount' => '', 'declared_cost_commission' => '', 'declared_cost_minimum_commission' => '', 'declared_cost_commission_bottom' => '')), 'sender' => '', 'sender_contact_person' => '', 'sender_region' => '', 'sender_city' => '', 'sender_department' => '', 'sender_address' => '', 'sender_address_type' => '', 'recipient' => '', 'recipient_name' => '', 'recipient_contact_person' => '', 'recipient_contact_person_phone' => '', 'recipient_edrpou' => '', 'recipient_region' => '', 'recipient_city' => '', 'recipient_department' => '', 'recipient_address' => '', 'recipient_street' => '', 'recipient_house' => '', 'recipient_flat' => '', 'preferred_delivery_date' => '', 'preferred_delivery_time' => '', 'autodetection_departure_type' => 0, 'departure_type' => '', 'calculate_volume' => 0, 'calculate_volume_type' => '', 'seats_amount' => '', 'declared_cost' => array(), 'declared_cost_default' => '', 'declared_cost_minimum' => '', 'departure_description' => '', 'departure_additional_information' => '', 'use_parameters' => '', 'weight' => '', 'weight_minimum' => '', 'dimensions_l' => '', 'dimensions_w' => '', 'dimensions_h' => '', 'allowance_l' => '', 'allowance_w' => '', 'allowance_h' => '', 'pack' => '', 'pack_type' => '', 'autodetection_pack_type' => '', 'delivery_payer' => '', 'third_person' => '', 'payment_type' => '', 'payment_cod' => array(), 'calculate_cod' => '', 'calculate_declared_cost_commision' => '', 'backward_delivery' => '', 'backward_delivery_payer' => '', 'payment_control' => array(), 'display_all_consignments' => 0, 'consignment_displayed_information' => array(), 'print_format' => '', 'number_of_copies' => '', 'template_type' => '', 'print_type' => '', 'print_start' => '', 'compatible_shipping_method' => array(), 'consignment_create' => 0, 'consignment_create_text' => array('', '', '', ''), 'consignment_edit' => 0, 'consignment_edit_text' => array('', '', '', ''), 'consignment_delete' => 0, 'consignment_delete_text' => array('', '', '', ''), 'consignment_assignment_to_order' => 0, 'consignment_assignment_to_order_text' => array('', '', '', ''), 'key_cron' => '', 'tracking_statuses' => array(), 'settings_tracking_statuses' => array());
			}

			return $settings;
		}

		if ($this->validate()) {
			$this->load->model('setting/setting');

			if ($type == 'basic') {
				$json = array();
				// Default basic settings for Nova Poshta module
                $basic_settings = array (
                'novaposhta_status' => 1,
                'novaposhta_sort_order' => 1,
                'novaposhta' =>
                array (
                'shipping_methods' =>
                array (
                'department' =>
                array (
                'status' => 1,
                'cost' => 1,
                'api_calculation' => 1,
                'tariff_calculation' => 1,
                'delivery_period' => 1,
                'department_statuses' =>
                array (
                0 => 'Working',
                ),
                ),
                'doors' =>
                array (
                'status' => 1,
                'cost' => 1,
                'api_calculation' => 1,
                'tariff_calculation' => 1,
                'delivery_period' => 1,
                ),
                'poshtomat' =>
                array (
                'status' => 1,
                'cost' => 1,
                'api_calculation' => 1,
                'tariff_calculation' => 1,
                'delivery_period' => 1,
                ),
                ),
                'tariffs' =>
                array (
                'parcel' =>
                array (
                'department_delivery_period' => 1,
                'city_delivery_period' => 1,
                'region_delivery_period' => 1,
                'ukraine_delivery_period' => 2,
                1 =>
                array (
                'weight' => 2,
                'department' => 25,
                'city' => 50,
                'region' => 70,
                'Ukraine' => 70,
                'overpay_doors_pickup' => 35,
                'overpay_doors_delivery' => 35,
                ),
                2 =>
                array (
                'weight' => 10,
                'department' => 35,
                'city' => 80,
                'region' => 100,
                'Ukraine' => 100,
                'overpay_doors_pickup' => 35,
                'overpay_doors_delivery' => 35,
                ),
                3 =>
                array (
                'weight' => 30,
                'department' => 50,
                'city' => 120,
                'region' => 140,
                'Ukraine' => 140,
                'overpay_doors_pickup' => 35,
                'overpay_doors_delivery' => 35,
                ),
                'declared_cost_commission' => 0.5,
                'declared_cost_minimum_commission' => '',
                'declared_cost_commission_bottom' => 500,
                ),
                'cod' =>
                array (
                1 =>
                array (
                'delivery_type' => 'department',
                'calculation_base' =>
                array (
                0 => 'sub_total',
                ),
                'tariff_limit' => '',
                'percent' => 2,
                'fixed_amount' => 20,
                'minimum_payment' => '',
                ),
                ),
                ),
                'settlements' => 1,
                'streets' => 1,
                'sender_address_type' => 'Warehouse',
                'recipient_contact_person' => '{shipping_lastname} {shipping_firstname}',
                'recipient_contact_person_phone' => '{telephone}',
                'recipient_region' => '{shipping_zone}',
                'recipient_city' => '{shipping_city}',
                'recipient_department' => '{shipping_address_1}',
                'recipient_address' => '{shipping_address_1}',
                'recipient_street' => '{shipping_street}',
                'recipient_house' => '{shipping_house}',
                'recipient_flat' => '{shipping_flat}',
                'departure_type' => 'Parcel',
                'seats_amount' => 1,
                'declared_cost' =>
                array (
                0 => 'total',
                ),
                'declared_cost_default' => 500,
                'departure_additional_information' => 'Товары:|{name}-{quantity} шт',
                'use_parameters' => 'products_without_parameters',
                'delivery_payer' => 'Recipient',
                'payment_type' => 'Cash',
                'payment_cod' =>
                array (
                0 => 'cod',
                1 => 'cod_plus',
                ),
                'calculate_cod' => 'disabled',
                'calculate_declared_cost_commision' => '1',
                'backward_delivery' => 'N',
                'backward_delivery_payer' => 'Recipient',
                'display_all_consignments' => 1,
                'consignment_displayed_information' =>
                array (
                0 => 'cn_number',
                1 => 'order_number',
                2 => 'registry',
                3 => 'estimated_shipping_date',
                4 => 'recipient',
                5 => 'recipient_address',
                6 => 'weight',
                7 => 'announced_price',
                8 => 'shipping_cost',
                9 => 'backward_shipping',
                10 => 'status',
                ),
                'compatible_shipping_method' =>
                array (
                0 => 'novaposhta',
                ),
                'consignment_create' => 1,
                'consignment_edit' => 1,
                'consignment_delete' => 1,
                'consignment_assignment_to_order' => 1,
                0 => '',
                'tracking_statuses' =>
                array (
                0 => 1,
                1 => 2,
                2 => 3,
                3 => 12,
                ),
                'settings_tracking_statuses' =>
                array (
                0 =>
                array (
                'shipment_statuses' =>
                array (
                0 => 4,
                1 => 41,
                ),
                'store_status' => 2,
                'implementation_delay' =>
                array (
                'type' => 'hour',
                'value' => '',
                ),
                'admin_notification' => 0,
                'customer_notification' => 1,
                'customer_notification_default' => 0,
                'email' =>
                array (
                1 => '<p>Доброго дня!&nbsp;</p><p>Ваше замовлення №<span style="background-color: rgb(249, 242, 244); color: rgb(199, 37, 78); font-family: Menlo, Monaco, Consolas, &quot;Courier New&quot;, monospace; font-size: 10.8px;">{order_id}</span>&nbsp;готується до відправки.</p><p>Номер накладної&nbsp;<code style="font-size: 10.8px;">{Number}</code></p>',
                2 => '<p>Доброго дня!&nbsp;</p><p>Ваше замовлення №<span style="background-color: rgb(249, 242, 244); color: rgb(199, 37, 78); font-family: Menlo, Monaco, Consolas, &quot;Courier New&quot;, monospace; font-size: 10.8px;">{order_id}</span>&nbsp;готується до відправки.</p><p>Номер накладної&nbsp;<code style="font-size: 10.8px;">{Number}</code></p>',
                3 => '<p>Доброго дня!&nbsp;</p><p>Ваше замовлення №<span style="background-color: rgb(249, 242, 244); color: rgb(199, 37, 78); font-family: Menlo, Monaco, Consolas, &quot;Courier New&quot;, monospace; font-size: 10.8px;">{order_id}</span>&nbsp;готується до відправки.</p><p>Номер накладної&nbsp;<code style="font-size: 10.8px;">{Number}</code></p>',
                4 => '',
                ),
                'sms' =>
                array (
                1 => 'Замовлення відпралено. Номер ТТН {Number}',
                2 => 'Замовлення відпралено. Номер ТТН {Number}',
                3 => 'Замовлення відпралено. Номер ТТН {Number}',
                4 => '',
                ),
                ),
                1 =>
                array (
                'shipment_statuses' =>
                array (
                0 => 7,
                1 => 8,
                ),
                'store_status' => 3,
                'implementation_delay' =>
                array (
                'type' => 'hour',
                'value' => '',
                ),
                'admin_notification' => 0,
                'customer_notification' => 1,
                'customer_notification_default' => 0,
                'email' =>
                array (
                1 => '<p>Доброго дня!&nbsp;</p><p>Ваше замовлення №<span style="background-color: rgb(249, 242, 244); color: rgb(199, 37, 78); font-family: Menlo, Monaco, Consolas, &quot;Courier New&quot;, monospace; font-size: 10.8px;">{order_id}</span>&nbsp;чекає на вас.</p><p>Номер накладної&nbsp;<code style="font-size: 10.8px;">{Number}</code></p>',
                2 => '<p>Доброго дня!&nbsp;</p><p>Ваше замовлення №<span style="background-color: rgb(249, 242, 244); color: rgb(199, 37, 78); font-family: Menlo, Monaco, Consolas, &quot;Courier New&quot;, monospace; font-size: 10.8px;">{order_id}</span>&nbsp;чекає на вас.</p><p>Номер накладної&nbsp;<code style="font-size: 10.8px;">{Number}</code></p>',
                3 => '<p>Доброго дня!&nbsp;</p><p>Ваше замовлення №<span style="background-color: rgb(249, 242, 244); color: rgb(199, 37, 78); font-family: Menlo, Monaco, Consolas, &quot;Courier New&quot;, monospace; font-size: 10.8px;">{order_id}</span>&nbsp;чекає на вас.</p><p>Номер накладної&nbsp;<code style="font-size: 10.8px;">{Number}</code></p>',
                4 => '',
                ),
                'sms' =>
                array (
                1 => 'Замовлення доставлено. Номер ТТН {Number}',
                2 => 'Замовлення доставлено. Номер ТТН {Number}',
                3 => 'Замовлення доставлено. Номер ТТН {Number}',
                4 => '',
                ),
                ),
                2 =>
                array (
                'shipment_statuses' =>
                array (
                0 => 9,
                1 => 10,
                2 => 11,
                3 => 106,
                ),
                'store_status' => 5,
                'implementation_delay' =>
                array (
                'type' => 'hour',
                'value' => '',
                ),
                'admin_notification' => 1,
                'customer_notification' => 0,
                'customer_notification_default' => 0,
                'email' =>
                array (
                1 => '<p>Доброго дня!&nbsp;</p><p>Замовлення №<span style="background-color: rgb(249, 242, 244); color: rgb(199, 37, 78); font-family: Menlo, Monaco, Consolas, &quot;Courier New&quot;, monospace; font-size: 10.8px;">{order_id}</span>&nbsp;забрано.</p><p>Номер накладної&nbsp;<code style="font-size: 10.8px;">{Number}</code></p>',
                2 => '<p>Доброго дня!&nbsp;</p><p>Замовлення №<span style="background-color: rgb(249, 242, 244); color: rgb(199, 37, 78); font-family: Menlo, Monaco, Consolas, &quot;Courier New&quot;, monospace; font-size: 10.8px;">{order_id}</span>&nbsp;забрано.</p><p>Номер накладної&nbsp;<code style="font-size: 10.8px;">{Number}</code></p>',
                3 => '<p>Доброго дня!&nbsp;</p><p>Замовлення №<span style="background-color: rgb(249, 242, 244); color: rgb(199, 37, 78); font-family: Menlo, Monaco, Consolas, &quot;Courier New&quot;, monospace; font-size: 10.8px;">{order_id}</span>&nbsp;забрано.</p><p>Номер накладної&nbsp;<code style="font-size: 10.8px;">{Number}</code></p>',
                4 => '',
                ),
                'sms' =>
                array (
                1 => '',
                2 => '',
                3 => '',
                4 => '',
                ),
                ),
                ),
                ),
                );
                
				if (version_compare(VERSION, '3', '>=')) {
					foreach ($basic_settings as $k => $v) {
						$basic_settings['shipping_' . $k] = $v;
						unset($basic_settings[$k]);
					}
				}

				if (!empty($basic_settings) && is_array($basic_settings)) {
					$current_settings = $this->model_setting_setting->getSetting($extension_code);
					$this->model_setting_setting->editSetting($extension_code, array_replace_recursive($current_settings, $basic_settings));
					$json['success'] = $this->language->get('text_success_download_basic_settings');
				} else {
					$json['error'] = $this->language->get('error_download_basic_settings');
				}

				$this->response->addHeader('Content-Type: application/json');
				$this->response->setOutput(json_encode($json));
			} else {
				if ($type == 'export') {
					$this->response->addheader('Pragma: public');
					$this->response->addheader('Expires: 0');
					$this->response->addheader('Content-Description: File Transfer');
					$this->response->addheader('Content-Type: application/octet-stream');
					$this->response->addheader('Content-Disposition: attachment; filename="' . $this->extension . '_settings_' . date('Y-m-d_H-i-s', time()) . '.txt"');
					$this->response->addheader('Content-Transfer-Encoding: binary');
					$settings = $this->model_setting_setting->getSetting($extension_code);
					$this->response->setOutput(json_encode($settings));
				} else {
					if ($type == 'import') {
						if (is_uploaded_file($this->request->files['import']['tmp_name'])) {
							$content = file_get_contents($this->request->files['import']['tmp_name']);
						} else {
							$content = false;
						}

						if ($content) {
							$this->model_setting_setting->editSetting($extension_code, json_decode($content, true));
							$this->session->data['success'] = $this->language->get('text_success_import_settings');
						} else {
							$this->session->data['warning'] = $this->language->get('error_import_settings');
						}

						if (version_compare(VERSION, '3', '>=')) {
							$this->response->redirect($this->url->link('extension/shipping/' . $this->extension, 'user_token=' . $this->session->data['user_token'], true));
						} else {
							if (version_compare(VERSION, '2.3', '>=')) {
								$this->response->redirect($this->url->link('extension/shipping/' . $this->extension, 'token=' . $this->session->data['token'], true));
							} else {
								if (version_compare(VERSION, '2', '>=')) {
									$this->response->redirect($this->url->link('shipping/' . $this->extension, 'token=' . $this->session->data['token'], 'SSL'));
								} else {
									$this->redirect($this->url->link('shipping/' . $this->extension, 'token=' . $this->session->data['token'], 'SSL'));
								}
							}
						}
					}
				}
			}
		} else {
			if ($type == 'basic') {
				$json['error'] = $this->error['warning'];
				$this->response->addHeader('Content-Type: application/json');
				$this->response->setOutput(json_encode($json));
			} else {
				$this->session->data['warning'] = $this->error['warning'];

				if (version_compare(VERSION, '3', '>=')) {
					$this->response->redirect($this->url->link('extension/shipping/' . $this->extension, 'user_token=' . $this->session->data['user_token'], true));
				} else {
					if (version_compare(VERSION, '2.3', '>=')) {
						$this->response->redirect($this->url->link('extension/shipping/' . $this->extension, 'token=' . $this->session->data['token'], true));
					} else {
						if (version_compare(VERSION, '2', '>=')) {
							$this->response->redirect($this->url->link('shipping/' . $this->extension, 'token=' . $this->session->data['token'], 'SSL'));
						} else {
							$this->redirect($this->url->link('shipping/' . $this->extension, 'token=' . $this->session->data['token'], 'SSL'));
						}
					}
				}
			}
		}
	}

	public function addRecipient()
	{
		if (version_compare(VERSION, '2.3', '>=')) {
			$this->load->language('extension/shipping/' . $this->extension);
		} else {
			$this->load->language('shipping/' . $this->extension);
		}

		if (isset($this->request->post['edrpou'])) {
			$edrpou = $this->request->post['edrpou'];
		} else {
			$edrpou = '';
		}

		$json = array();

		if (!$this->validate()) {
			$json['error'] = $this->error['warning'];
		} else {
			$data = array('CounterpartyType' => 'Organization', 'CounterpartyProperty' => 'Recipient', 'EDRPOU' => $edrpou);
			$result = $this->novaposhta->saveCounterparties($data);

			if ($result) {
				$this->cache->delete('novaposhta_recipients');
				$json['success'] = $this->language->get('text_recipient_success_add');
			} else {
				$json['error'] = $this->language->get('error_add_recipient');
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	private function getDeclaredCost($order_totals)
	{
		$declared_cost = 0;

		foreach ($order_totals as $total) {
			if (isset($this->settings['declared_cost']) && in_array($total['code'], (array) $this->settings['declared_cost'])) {
				$declared_cost += $total['value'];
			}
		}

		return $declared_cost;
	}

	private function getPaymentControl($order_totals)
	{
		$payment_control = 0;

		foreach ($order_totals as $total) {
			if (isset($this->settings['payment_control']) && in_array($total['code'], (array) $this->settings['payment_control'])) {
				$payment_control += $total['value'];
			}
		}

		return $payment_control;
	}

	private function getOwnreshipForm($name)
	{
		$ownership_forms = $this->novaposhta->getReferences('ownership_forms');
		$data = $ownership_forms[0];

		foreach ($ownership_forms as $ownership_form) {
			if (preg_match('/^(' . preg_quote($name) . ')/iu', $ownership_form['Description'])) {
				$data = $ownership_form;

				break;
			}
		}

		return $data;
	}

	private function parseAddress($address)
	{
		$data = array();
		$matches = array();
		$pattern = '/\\b(с|улиця|вул|улица|ул|провулок|пров|переулок|пер|просп|проспект|пр|пр-т|площа|площадь|пл|узвіз|спуск|бульвар|бул|б-р|шосе|шоссе|ш|дорога|проїзд|проезд|алея|будинок|буд|дом|д|квартира|кв)\\b\\.*/ui';
		preg_match($pattern, $address, $matches);
		$address = explode(',', preg_replace($pattern, '', $address));
		$data['street'] = (isset($address[0]) ? trim($address[0]) : '');
		$data['street_type'] = (isset($matches[0]) ? $matches[0] : 'вул.');
		$data['house'] = (isset($address[1]) ? trim($address[1]) : '');
		$data['flat'] = (isset($address[2]) ? trim($address[2]) : '');

		return $data;
	}

	private function convertToUAH($value, $currency_code, $currency_value)
	{
		if (!$currency_value || $currency_code != 'UAH') {
			$currency_value = $this->currency->getValue('UAH');
		}

		if ($currency_value != 1) {
			$value *= $currency_value;
		}

		return round($value, (int) $this->currency->getDecimalPlace('UAH'));
	}

	private function getExtensions($type)
	{
		$data = array();

		if (version_compare(VERSION, '3', '>=')) {
			$this->load->model('setting/extension');
			$methods = $this->model_setting_extension->getInstalled($type);
			$extension_code = $type . '_';
			$extension_path = 'extension/';
		} else {
			if (version_compare(VERSION, '2.3', '>=')) {
				$this->load->model('extension/extension');
				$methods = $this->model_extension_extension->getInstalled($type);
				$extension_code = '';
				$extension_path = 'extension/';
			} else {
				if (version_compare(VERSION, '2', '>=')) {
					$this->load->model('extension/extension');
					$methods = $this->model_extension_extension->getInstalled($type);
					$extension_code = '';
					$extension_path = '';
				} else {
					$this->load->model('setting/extension');
					$methods = $this->model_setting_extension->getInstalled($type);
					$extension_code = '';
					$extension_path = '';
				}
			}
		}

		foreach ($methods as $method) {
			if ($this->config->get($extension_code . $method . '_status')) {
				if (version_compare(VERSION, '3.0.0.0', '>')) {
					$temp_language_data = $this->load->language($extension_path . $type . '/' . $method, 'temp');
					$data[$method] = $temp_language_data['temp']->get('heading_title');
				} else {
					$this->load->language($extension_path . $type . '/' . $method);
					$data[$method] = $this->language->get('heading_title');
				}
			}
		}

		return $data;
	}

	private function validate()
	{
		if (version_compare(VERSION, '3', '>=')) {
			$extension_code = 'shipping_' . $this->extension;
			$extension_path = 'extension/';
		} else {
			if (version_compare(VERSION, '2.3', '>=')) {
				$extension_code = $this->extension;
				$extension_path = 'extension/';
			} else {
				$extension_code = $this->extension;
				$extension_path = '';
			}
		}

		// Validate user permissions
		if (!$this->user->hasPermission('modify', $extension_path . 'shipping/novaposhta')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		// Validate API key format (must be 32 characters)
		if (isset($this->request->post[$extension_code]['key_api']) && utf8_strlen($this->request->post[$extension_code]['key_api']) != 32) {
			$this->error['warning'] = $this->language->get('error_settings_saving');
			$this->error['error_key_api'] = $this->language->get('error_key_api');
		}

		return !$this->error;
	}

	private function validateCNForm()
	{
		$array_matches = array();

		if (isset($this->request->post['sender'])) {
			$senders = $this->novaposhta->getReferences('senders');

			if (!array_key_exists($this->request->post['sender'], $senders)) {
				$this->error['errors']['sender'] = $this->language->get('error_sender');
			}
		}

		if (isset($this->request->post['sender_contact_person'])) {
			$sender_contact_persons = $this->novaposhta->getReferences('sender_contact_persons');

			if (isset($this->request->post['f_sender'])) {
				$sender = $this->request->post['f_sender'];
			} else {
				if (isset($this->request->post['sender'])) {
					$sender = $this->request->post['sender'];
				} else {
					$sender = '';
				}
			}

			if (!$sender || isset($sender_contact_persons[$sender]) && !array_key_exists($this->request->post['sender_contact_person'], $sender_contact_persons[$sender])) {
				$this->error['errors']['sender_contact_person'] = $this->language->get('error_sender_contact_person');
			}
		}

		if (isset($this->request->post['sender_region'])) {
			if (!$this->request->post['sender_region']) {
				$this->error['errors']['sender_region'] = $this->language->get('error_field');
			} else {
				if (!$this->novaposhta->getRegion($this->request->post['sender_region'])) {
					$this->error['errors']['sender_region'] = $this->language->get('error_region');
				}
			}
		}

		if (isset($this->request->post['sender_city'])) {
			if (!$this->request->post['sender_city']) {
				$this->error['errors']['sender_city'] = $this->language->get('error_field');
			} else {
				if (!$this->novaposhta->getCityName($this->request->post['sender_city'])) {
					$this->error['errors']['sender_city'] = $this->language->get('error_city');
				}
			}
		}

		if (isset($this->request->post['sender_department'])) {
			if (!$this->request->post['sender_department']) {
				$this->error['errors']['sender_department'] = $this->language->get('error_field');
			} else {
				if (!$this->novaposhta->getDepartment($this->request->post['sender_department'])) {
					$this->error['errors']['sender_department'] = $this->language->get('error_department');
				}
			}
		}

		if (isset($this->request->post['sender_address'])) {
			if (isset($this->request->post['f_sender'])) {
				$sender = $this->request->post['f_sender'];
			} else {
				if (isset($this->request->post['sender'])) {
					$sender = $this->request->post['sender'];
				} else {
					$sender = '';
				}
			}

			if (isset($this->request->post['sender_city'])) {
				$city = $this->request->post['sender_city'];
			} else {
				$city = '';
			}

			$sender_addresses = $this->novaposhta->getSenderAddresses($sender, $city);

			if (!$this->request->post['sender_address']) {
				$this->error['errors']['sender_address'] = $this->language->get('error_field');
			} else {
				if (empty($sender_addresses) || empty($sender_addresses[$this->request->post['sender_address']])) {
					$this->error['errors']['sender_address'] = $this->language->get('error_address');
				}
			}
		}

		if (isset($this->request->post['recipient']) && !$this->request->post['recipient']) {
			$this->error['errors']['recipient'] = $this->language->get('error_field');
		}

		if (isset($this->request->post['recipient_contact_person'])) {
			if (!preg_match("/[А-яҐґЄєIіЇїё\\-\\`']{2,}\\s+[А-яҐґЄєIіЇїё\\-\\`']{2,}/iu", trim($this->request->post['recipient_contact_person']), $array_matches['recipient_contact_person'])) {
				$this->error['errors']['recipient_contact_person'] = $this->language->get('error_full_name_correct');
			} else {
				if (preg_match("/[^А-яҐґЄєIіЇїё\\-\\`'\\s]+/iu", $this->request->post['recipient_contact_person'], $array_matches['recipient_contact_person'])) {
					$this->error['errors']['recipient_contact_person'] = $this->language->get('error_characters');
				}
			}
		}

		if (isset($this->request->post['recipient_contact_person_phone']) && !preg_match('/^(38)[0-9]{10}$/', $this->request->post['recipient_contact_person_phone'], $array_matches['recipient_contact_person_phone'])) {
			$this->error['errors']['recipient_contact_person_phone'] = $this->language->get('error_phone');
		}

		if (!empty($this->request->post['recipient_region']) && !$this->novaposhta->getRegion($this->request->post['recipient_region'])) {
			$this->error['errors']['recipient_region'] = $this->language->get('error_region');
		}

		if (isset($this->request->post['recipient_city'])) {
			if (!$this->request->post['recipient_city']) {
				$this->error['errors']['recipient_city'] = $this->language->get('error_field');
			} else {
				if (!$this->novaposhta->getCityName($this->request->post['recipient_city'])) {
					$this->error['errors']['recipient_city'] = $this->language->get('error_city');
				}
			}
		}

		if (isset($this->request->post['recipient_department'])) {
			if (!$this->request->post['recipient_department']) {
				$this->error['errors']['recipient_department'] = $this->language->get('error_field');
			} else {
				if (!$this->novaposhta->getDepartment($this->request->post['recipient_department'])) {
					$this->error['errors']['recipient_department'] = $this->language->get('error_department');
				}
			}
		}

		if (isset($this->request->post['recipient_poshtomat'])) {
			if (!$this->request->post['recipient_poshtomat']) {
				$this->error['errors']['recipient_poshtomat'] = $this->language->get('error_field');
			} else {
				if (!$this->novaposhta->getDepartment($this->request->post['recipient_poshtomat'])) {
					$this->error['errors']['recipient_poshtomat'] = $this->language->get('error_department');
				}
			}
		}

		if (isset($this->request->post['recipient_settlement_name'])) {
			if (!$this->request->post['recipient_settlement_name']) {
				$this->error['errors']['recipient_settlement_name'] = $this->language->get('error_field');
			} else {
				if (empty($this->request->post['recipient_settlement'])) {
					$this->error['errors']['recipient_settlement_name'] = $this->language->get('error_settlement');
				}
			}
		}

		if (isset($this->request->post['recipient_street_name'])) {
			if (!$this->request->post['recipient_street_name']) {
				$this->error['errors']['recipient_street_name'] = $this->language->get('error_field');
			} else {
				if (empty($this->request->post['recipient_street'])) {
					$this->error['errors']['recipient_street_name'] = $this->language->get('error_street');
				}
			}
		}

		if (isset($this->request->post['recipient_house']) && !$this->request->post['recipient_house']) {
			$this->error['errors']['recipient_house'] = $this->language->get('error_field');
		}

		if (isset($this->request->post['parcels']) && is_array($this->request->post['parcels'])) {
			foreach ($this->request->post['parcels'] as $i => $parcel) {
				if (isset($parcel['weight']) && (!preg_match('/^[0-9]+(\\.|\\,)?[0-9]*$/', $parcel['weight'], $array_matches['parcels'][$i]['weight']) || !$parcel['weight'])) {
					$this->error['errors']['parcels'][$i]['weight'] = $this->language->get('error_weight');
				}

				if (isset($parcel['length']) && !preg_match('/^[1-9]{1}[0-9]*$/', $parcel['length'], $array_matches['parcels'][$i]['length'])) {
					$this->error['errors']['parcels'][$i]['length'] = $this->language->get('error_length');
				}

				if (isset($parcel['width']) && !preg_match('/^[1-9]{1}[0-9]*$/', $parcel['width'], $array_matches['parcels'][$i]['width'])) {
					$this->error['errors']['parcels'][$i]['width'] = $this->language->get('error_width');
				}

				if (isset($parcel['height']) && !preg_match('/^[1-9]{1}[0-9]*$/', $parcel['height'], $array_matches['parcels'][$i]['height'])) {
					$this->error['errors']['parcels'][$i]['height'] = $this->language->get('error_height');
				}
			}
		}

		if (isset($this->request->post['weight_general']) && (!preg_match('/^[0-9]+(\\.|\\,)?[0-9]*$/', $this->request->post['weight_general'], $array_matches['weight_general']) || !$this->request->post['weight_general'])) {
			$this->error['errors']['weight_general'] = $this->language->get('error_weight');
		}

		if (!empty($this->request->post['volume_general']) && (!preg_match('/^[0-9]+(\\.|\\,)?[0-9]*$/', $this->request->post['volume_general'], $array_matches['volume_general']) || !$this->request->post['volume_general'])) {
			$this->error['errors']['volume_general'] = $this->language->get('error_volume');
		}

		if (isset($this->request->post['seats_amount']) && !preg_match('/^[1-9]{1}[0-9]*$/', $this->request->post['seats_amount'], $array_matches['seats_amount'])) {
			$this->error['errors']['seats_amount'] = $this->language->get('error_seats_amount');
		}

		if (isset($this->request->post['declared_cost']) && (!preg_match('/^[0-9]+(\\.|\\,)?[0-9]*$/', $this->request->post['declared_cost'], $array_matches['declared_cost']) || !$this->request->post['declared_cost'])) {
			$this->error['errors']['declared_cost'] = $this->language->get('error_sum');
		}

		if (isset($this->request->post['departure_description']) && (utf8_strlen($this->request->post['departure_description']) < 3 || 120 < utf8_strlen($this->request->post['departure_description']))) {
			$this->error['errors']['departure_description'] = $this->language->get('error_departure_description');
		}

		if (isset($this->request->post['delivery_payer']) && !$this->request->post['delivery_payer']) {
			$this->error['errors']['delivery_payer'] = $this->language->get('error_field');
		}

		if (isset($this->request->post['third_person'])) {
			$third_persons = $this->novaposhta->getReferences('third_persons');

			if (!array_key_exists($this->request->post['third_person'], $third_persons)) {
				$this->error['errors']['third_person'] = $this->language->get('error_third_person');
			}
		}

		if (isset($this->request->post['payment_type']) && !$this->request->post['payment_type']) {
			$this->error['errors']['payment_type'] = $this->language->get('error_field');
		}

		if (isset($this->request->post['backward_delivery']) && !$this->request->post['backward_delivery']) {
			$this->error['errors']['backward_delivery'] = $this->language->get('error_field');
		}

		if (isset($this->request->post['backward_delivery_total']) && (!preg_match('/^[0-9]+(\\.|\\,)?[0-9]*$/', $this->request->post['backward_delivery_total'], $array_matches['backward_delivery_total']) || !$this->request->post['backward_delivery_total'])) {
			$this->error['errors']['backward_delivery_total'] = $this->language->get('error_sum');
		}

		if (isset($this->request->post['backward_delivery_payer']) && !$this->request->post['backward_delivery_payer']) {
			$this->error['errors']['backward_delivery_payer'] = $this->language->get('error_field');
		}

		if (isset($this->request->post['payment_control']) && !preg_match('/^[0-9]+(\\.|\\,)?[0-9]*$/', $this->request->post['payment_control'], $array_matches['payment_control']) && $this->request->post['payment_control']) {
			$this->error['errors']['payment_control'] = $this->language->get('error_sum');
		}

		if (isset($this->request->post['departure_date'])) {
			if (!preg_match('/(0[1-9]|1[0-9]|2[0-9]|3[01])\\.(0[1-9]|1[012])\\.(20)\\d\\d/', $this->request->post['departure_date'], $array_matches['departure_date'])) {
				$this->error['errors']['departure_date'] = $this->language->get('error_date');
			} else {
				if ($this->novaposhta->dateDiff($this->request->post['departure_date']) < 0) {
					$this->error['errors']['departure_date'] = $this->language->get('error_date_past');
				}
			}
		}

		if (isset($this->request->post['preferred_delivery_date']) && $this->request->post['preferred_delivery_date']) {
			if (!preg_match('/(0[1-9]|1[0-9]|2[0-9]|3[01])\\.(0[1-9]|1[012])\\.(20)\\d\\d/', $this->request->post['preferred_delivery_date'], $array_matches['preferred_delivery_date'])) {
				$this->error['errors']['preferred_delivery_date'] = $this->language->get('error_date');
			} else {
				if ($this->novaposhta->dateDiff($this->request->post['preferred_delivery_date']) < 0) {
					$this->error['errors']['preferred_delivery_date'] = $this->language->get('error_date_past');
				}
			}
		}

		if (isset($this->request->post['additional_information']) && 100 < utf8_strlen($this->request->post['additional_information'])) {
			$this->error['errors']['additional_information'] = $this->language->get('error_departure_additional_information');
		}

		return !$this->error;
	}
}

class ControllerExtensionShippingNovaPoshta extends ControllerShippingNovaPoshta
{
}

