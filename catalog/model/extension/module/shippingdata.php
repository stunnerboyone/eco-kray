<?php
class ModelModuleShippingData extends Model {
	public function getNovaPoshtaCities($region, $search = '') {
        if (!$region && !$search) {
            return $this->getDefaultCities();
        }

        require_once(DIR_SYSTEM . 'helper/novaposhta.php');

        $novaposhta = new NovaPoshta($this->registry);

		$sql = "SELECT `" . $novaposhta->description_field . "` AS `description` FROM `" . DB_PREFIX . "novaposhta_cities` WHERE 1";

		if ($region) {
            $zone_info = $this->getZone($region);

            if ($zone_info) {
                $region = $novaposhta->getRegionRef($zone_info['name']);
            }

		    $sql .= " AND `Area` = '" . $this->db->escape($region) . "'";
        }

		if ($search) {
            $search = $this->db->escape($search);

			$sql .= " AND (`Description` LIKE '" . $search . "%' OR `DescriptionRu` LIKE '" . $search . "%')";
		}
		
		$sql .= " ORDER BY  `" . $novaposhta->description_field . "` COLLATE utf8_unicode_ci";
		
		return $this->db->query($sql)->rows;
	}
	
	public function getNovaPoshtaDepartments($city, $search = '') {
        require_once(DIR_SYSTEM . 'helper/novaposhta.php');

        $novaposhta = new NovaPoshta($this->registry);

        if (version_compare(VERSION, '3', '>=')) {
            $settings = $this->config->get('shipping_novaposhta');
        } else {
            $settings = $this->config->get('novaposhta');
        }

		$sql = "SELECT `" . $novaposhta->description_field . "` AS `description` FROM `" . DB_PREFIX . "novaposhta_departments` WHERE (`CityDescription` = '" . $this->db->escape($city) . "' OR `CityDescriptionRu` = '" . $this->db->escape($city) . "')";

        if (!empty($settings['shipping_methods']['poshtomat']['status'])) {
            $sql .= " AND `CategoryOfWarehouse` <> 'Postomat'";
        }
			
        if ($search) {
            $search = $this->db->escape($search);

            $sql .= " AND (`Description` LIKE '%" . $search . "%' OR `DescriptionRu` LIKE '%" . $search . "%')";
        }

		if (isset($settings['shipping_methods']['department']['department_types'])) {
			foreach ($settings['shipping_methods']['department']['department_types'] as $k => $v) {
                $settings['shipping_methods']['department']['department_types'][$k] = "'" . $v . "'";
			}

			$sql .= " AND `TypeOfWarehouse` IN (" . implode(',', $settings['shipping_methods']['department']['department_types']) . ")";
		}

        if (isset($settings['shipping_methods']['department']['department_statuses'])) {
            foreach ($settings['shipping_methods']['department']['department_statuses'] as $k => $v) {
                $settings['shipping_methods']['department']['department_statuses'][$k] = "'" . $v . "'";
            }

            $sql .= " AND `WarehouseStatus` IN (" . implode(',', $settings['shipping_methods']['department']['department_statuses']) . ")";
        }
		
		if (isset($this->session->data['shippingdata']['cart_weight']) && $settings['shipping_methods']['department']['filter_weight']) {
			$sql .= " AND (`TotalMaxWeightAllowed` >= '" . $this->session->data['shippingdata']['cart_weight'] . "' OR (`TotalMaxWeightAllowed` = 0 AND (`PlaceMaxWeightAllowed` >= '" . $this->session->data['shippingdata']['cart_weight'] . "' OR `PlaceMaxWeightAllowed` = 0)))";
		}

        if ($settings['shipping_methods']['department']['filter_dimensions'] && isset($this->session->data['shippingdata']['length'])) {
            $sql .= " AND (`ReceivingLimitationsOnDimensions_length` >= '" . $this->session->data['shippingdata']['length'] . "'
             AND `ReceivingLimitationsOnDimensions_width` >= '" . $this->session->data['shippingdata']['width'] . "'
             AND `ReceivingLimitationsOnDimensions_height` >= '" . $this->session->data['shippingdata']['height'] . "')";
        }

		$sql .= " ORDER BY `Number`+0";

		return $this->db->query($sql)->rows;	
	}

    public function getNovaPoshtaPoshtomats($city, $search = '') {
        require_once(DIR_SYSTEM . 'helper/novaposhta.php');

        $novaposhta = new NovaPoshta($this->registry);

        if (version_compare(VERSION, '3', '>=')) {
            $settings = $this->config->get('shipping_novaposhta');
        } else {
            $settings = $this->config->get('novaposhta');
        }

        $sql = "SELECT `" . $novaposhta->description_field . "` AS `description` FROM `" . DB_PREFIX . "novaposhta_departments` WHERE `CategoryOfWarehouse` = 'Postomat' AND (`CityDescription` = '" . $this->db->escape($city) . "' OR `CityDescriptionRu` = '" . $this->db->escape($city) . "')";

        if ($search) {
            $search = $this->db->escape($search);

            $sql .= " AND (`Description` LIKE '%" . $search . "%' OR `DescriptionRu` LIKE '%" . $search . "%')";
        }

        if (isset($this->session->data['shippingdata']['cart_weight']) && $settings['shipping_methods']['poshtomat']['filter_weight']) {
            $sql .= " AND (`TotalMaxWeightAllowed` >= '" . $this->session->data['shippingdata']['cart_weight'] . "' OR (`TotalMaxWeightAllowed` = 0 AND (`PlaceMaxWeightAllowed` >= '" . $this->session->data['shippingdata']['cart_weight'] . "' OR `PlaceMaxWeightAllowed` = 0)))";
        }

        if ($settings['shipping_methods']['poshtomat']['filter_dimensions'] && isset($this->session->data['shippingdata']['length'])) {
            $sql .= " AND (`ReceivingLimitationsOnDimensions_length` >= '" . $this->session->data['shippingdata']['length'] . "'
             AND `ReceivingLimitationsOnDimensions_width` >= '" . $this->session->data['shippingdata']['width'] . "'
             AND `ReceivingLimitationsOnDimensions_height` >= '" . $this->session->data['shippingdata']['height'] . "')";
        }

        $sql .= " ORDER BY `Number`+0";

        return $this->db->query($sql)->rows;
    }

    public function getNovaPoshtaSettlements($region, $search) {
        $data = array();

        if (version_compare(VERSION, '3', '>=')) {
            $settings = $this->config->get('shipping_novaposhta');
        } else {
            $settings = $this->config->get('novaposhta');
        }

        if (empty($settings['settlements'])) {
            return false;
        }

        require_once(DIR_SYSTEM . 'helper/novaposhta.php');

        $novaposhta = new NovaPoshta($this->registry);

        if ($region) {
            $zone_info = $this->getZone($region);

            if ($zone_info) {
                $region = $novaposhta->getRegionName($zone_info['name']);
            }
        }

        $result = $novaposhta->searchSettlements($search);

        foreach ($result as $v) {
            if (!$region || $region == $v['Area']) {
                $data[] = array(
                    'id' => $v['Ref'],
                    'description' => $v['MainDescription'],
                    'full_description' => $v['Present']
                );
            }
        }

        return $data;
    }

    public function getNovaPoshtaStreets($settlement, $search) {
        $data = array();

        if (version_compare(VERSION, '3', '>=')) {
            $settings = $this->config->get('shipping_novaposhta');
        } else {
            $settings = $this->config->get('novaposhta');
        }

        if (empty($settings['streets'])) {
            return false;
        }

        require_once(DIR_SYSTEM . 'helper/novaposhta.php');

        $novaposhta = new NovaPoshta($this->registry);

        $result = $novaposhta->searchSettlementStreets($settlement, $search);

        foreach ($result as $v) {
            $data[] = array(
                'description' => $v['Present']
            );
        }

        return $data;
    }

    public function getRozetkaDeliveryCities($search = '') {
	    if ($this->language->get('code') == 'en' || $this->language->get('code') == 'en-gb') {
            $district_text = ' dist.';
            $region_text = ' reg.';
        } else {
            $district_text = ' р-н.';
            $region_text = ' обл.';
        }

        $sql = "SELECT IF(`district_name` <> '', CONCAT(`name`, ', ', `district_name`, '" . $district_text . "', ', ', `region_name`, '" . $region_text . "'), `name`) as `description` FROM `" . DB_PREFIX . "rozetka_delivery_cities` WHERE 1";

        if ($search) {
            $search = $this->db->escape($search);

            $sql .= " AND (`name` LIKE '" . $search . "%' OR SUBSTRING_INDEX(`name`, ' ', -1) LIKE '" . $search . "%')";
        }

        $sql .= " ORDER BY `population` DESC";

        return $this->db->query($sql)->rows;
    }

    public function getRozetkaDeliveryDepartments($city, $search = '') {
        if (version_compare(VERSION, '3', '>=')) {
            $settings = $this->config->get('shipping_rozetka_delivery');
        } else {
            $settings = $this->config->get('rozetka_delivery');
        }

        $city_parts = explode(', ', $city);

        $sql = "SELECT `name` as `description` FROM `" . DB_PREFIX . "rozetka_delivery_departments` WHERE `can_give_out_tracks` = 1 AND `city_name` = '" . $this->db->escape($city_parts[0]) . "'";

        if (!empty($city_parts[1])) {
            $district = trim(strstr($city_parts[1], ' ', true));

            $sql .= " AND `district_name` = '" . $this->db->escape($district) . "'";
        }

        if (!empty($city_parts[2])) {
            $region = trim(strstr($city_parts[2], ' ', true));

            $sql .= " AND `region_name` = '" . $this->db->escape($region) . "'";
        }

        if ($search) {
            $sql .= " AND `name` LIKE '%" . $this->db->escape($search) . "%'";
        }

        if (isset($this->session->data['shippingdata']['cart_weight']) && $settings['shipping_methods']['department']['filter_weight']) {
            $sql .= " AND (`volumeWeight` >= '" . $this->session->data['shippingdata']['cart_weight'] . "' OR `volumeWeight` = 0)";
        }

        $sql .= " ORDER BY `name`";
        
        return $this->db->query($sql)->rows;
    }

    public function getUkrPoshtaCities($region, $search = '') {
        if (!$region && !$search) {
            return $this->getDefaultCities();
        }

        require_once(DIR_SYSTEM . 'helper/ukrposhta.php');

        $ukrposhta = new UkrPoshta($this->registry);

        if ($ukrposhta->lang == 'EN') {
            $district_text = ' dist.';
            $region_text = ' reg.';
        } else {
            $district_text = ' р-н.';
            $region_text = ' обл.';
        }

        if ($region) {
            $zone_info = $this->getZone($region);

            if ($zone_info) {
                $region = $ukrposhta->getRegionID($zone_info['name']);
            }

            $sql = "SELECT *, CONCAT(`SHORTCITYTYPE_" . $ukrposhta->lang . "`, ' ', `CITY_" . $ukrposhta->lang . "`, ', ', `DISTRICT_" . $ukrposhta->lang . "`, '" . $district_text . "') as `description` FROM `" . DB_PREFIX . "ukrposhta_cities` WHERE `REGION_ID` = '" . $this->db->escape($region) . "'";
        } else {
            $sql = "SELECT *, CONCAT(`SHORTCITYTYPE_" . $ukrposhta->lang . "`, ' ', `CITY_" . $ukrposhta->lang . "`, ', ', `DISTRICT_" . $ukrposhta->lang . "`, '" . $district_text . "', ', ', `REGION_" . $ukrposhta->lang . "`, '" . $region_text . "') as `description` FROM `" . DB_PREFIX . "ukrposhta_cities` WHERE 1";
        }

        if ($search) {
            $search_parts = explode(', ', $search);
            $search = $this->db->escape(trim(str_replace(array('м.', 'с-ще', 'с.'), '', $search_parts[0])));

            $sql .= " AND (`CITY_UA` LIKE '" . $search . "%' OR `CITY_EN` LIKE '" . $search . "%' OR `CITY_RU`  LIKE '" . $search . "%')";
        }

        $sql .= " ORDER BY `POPULATION` DESC";

        return $this->db->query($sql)->rows;
    }

    public function getUkrPoshtaDepartments($city, $search = '', $method = 'standard_department') {
        if (version_compare(VERSION, '3', '>=')) {
            $settings = $this->config->get('shipping_ukrposhta');
        } else {
            $settings = $this->config->get('ukrposhta');
        }

        require_once(DIR_SYSTEM . 'helper/ukrposhta.php');

        $ukrposhta = new UkrPoshta($this->registry);

        $city_parts = explode(', ', $city);
        $city = $this->db->escape(trim($city_parts[0]));

        $sql = "SELECT *, 
               `POSTCODE` as `postcode`, 
               CASE 
                   WHEN `TYPE_ACRONYM` = 'ПВ' THEN CONCAT(`POSTCODE`, ', ', `PDCITY_" . $ukrposhta->lang . "`, ' (', `TYPE_LONG`, ')') 
                   ELSE CONCAT(`POSTCODE`, ', ', `ADDRESS`) 
               END as `description` 
        FROM `" . DB_PREFIX . "ukrposhta_departments` 
        WHERE (`PDCITY_UA` = '" . $city . "' 
               OR `PDCITY_EN` = '" . $city . "' 
               OR `PDCITY_RU` = '" . $city . "')";

        if (isset($settings['shipping_methods'][$method]['department_types'])) {
            foreach ($settings['shipping_methods'][$method]['department_types'] as $k => $v) {
                $settings['shipping_methods'][$method]['department_types'][$k] = "'" . $v . "'";
            }

            $sql .= " AND `TYPE_ACRONYM` IN (" . implode(',', $settings['shipping_methods'][$method]['department_types']) . ")";
        }

        if (isset($settings['shipping_methods'][$method]['department_statuses'])) {
            foreach ($settings['shipping_methods'][$method]['department_statuses'] as $k => $v) {
                $settings['shipping_methods'][$method]['department_statuses'][$k] = "'" . $v . "'";
            }

            $sql .= " AND `LOCK_CODE` IN (" . implode(',', $settings['shipping_methods'][$method]['department_statuses']) . ")";
        }

        if (!empty($city_parts[1])) {
            $district = $this->db->escape(trim(strstr($city_parts[1], ' ', true)));

            $sql .= " AND (`DISTRICT_UA` = '" . $district . "' OR `DISTRICT_EN` = '" . $district . "' OR `DISTRICT_RU` = '" . $district . "')";
        }

        if (!empty($city_parts[2])) {
            $region = $this->db->escape(trim(strstr($city_parts[2], ' ', true)));

            $sql .= " AND (`REGION_UA` = '" . $region . "' OR `REGION_EN` = '" . $region . "' OR `REGION_RU` = '" . $region . "')";
        }

        if ($search) {
            $search = $this->db->escape($search);

            $sql .= " AND (`POSTCODE` LIKE '" . $search . "%' OR `ADDRESS` LIKE '%" . $search . "%')";
        }

        $sql .= " ORDER BY `POSTCODE`";

        return $this->db->query($sql)->rows;
    }

    public function getUkrPoshtaPostCodes($search = '', $method = 'standard_department') {
        require_once(DIR_SYSTEM . 'helper/ukrposhta.php');

        $ukrposhta = new UkrPoshta($this->registry);

        if (version_compare(VERSION, '3', '>=')) {
            $settings = $this->config->get('shipping_ukrposhta');
        } else {
            $settings = $this->config->get('ukrposhta');
        }

        if ($ukrposhta->lang == 'EN') {
            $district_text = ' dist.';
            $region_text = ' reg.';
        } else {
            $district_text = ' р-н.';
            $region_text = ' обл.';
        }

        $sql = "SELECT `POSTCODE` as `description`,
                        CONCAT(`PDCITY_" . $ukrposhta->lang . "`, ', ', `DISTRICT_" . $ukrposhta->lang . "`, '" . $district_text . "', ', ', `REGION_" . $ukrposhta->lang . "`, '" . $region_text . "') as `city`,
               CASE 
                   WHEN `TYPE_ACRONYM` = 'ПВ' THEN CONCAT(`POSTCODE`, ', ', `PDCITY_" . $ukrposhta->lang . "`, ' (', `TYPE_LONG`, ')', ', ', `DISTRICT_" . $ukrposhta->lang . "`, '" . $district_text . "', ', ', `REGION_" . $ukrposhta->lang . "`, '" . $region_text . "') 
                   ELSE CONCAT(`POSTCODE`, ', ', `ADDRESS`, ', ', `PDCITY_" . $ukrposhta->lang . "`, ', ', `DISTRICT_" . $ukrposhta->lang . "`, '" . $district_text . "', ', ', `REGION_" . $ukrposhta->lang . "`, '" . $region_text . "') 
               END as `full_description`,
               CASE 
                   WHEN `TYPE_ACRONYM` = 'ПВ' THEN CONCAT(`POSTCODE`, ', ', `PDCITY_" . $ukrposhta->lang . "`, ' (', `TYPE_LONG`, ')') 
                   ELSE CONCAT(`POSTCODE`, ', ', `ADDRESS`)
               END as `department`
        FROM `" . DB_PREFIX . "ukrposhta_departments` 
        WHERE 1";

        if (isset($settings['shipping_methods'][$method]['department_types'])) {
            foreach ($settings['shipping_methods'][$method]['department_types'] as $k => $v) {
                $settings['shipping_methods'][$method]['department_types'][$k] = "'" . $v . "'";
            }

            $sql .= " AND `TYPE_ACRONYM` IN (" . implode(',', $settings['shipping_methods'][$method]['department_types']) . ")";
        }

        if (isset($settings['shipping_methods'][$method]['department_statuses'])) {
            foreach ($settings['shipping_methods'][$method]['department_statuses'] as $k => $v) {
                $settings['shipping_methods'][$method]['department_statuses'][$k] = "'" . $v . "'";
            }

            $sql .= " AND `LOCK_CODE` IN (" . implode(',', $settings['shipping_methods'][$method]['department_statuses']) . ")";
        }

        if ($search) {
            $search = $this->db->escape($search);

            $sql .= " AND (`POSTCODE` LIKE '" . $search . "%' OR `ADDRESS` LIKE '%" . $search . "%')";
        }

        $sql .= " ORDER BY `POSTCODE`";

        return $this->db->query($sql)->rows;
    }

    public function getJustinCities($region, $search = '') {
        if (!$region && !$search) {
            return $this->getDefaultCities();
        }

        require_once(DIR_SYSTEM . 'helper/justin.php');

        $justin = new Justin($this->registry);

        if ($region) {
            $zone_info = $this->getZone($region);

            if ($zone_info) {
                $region = $justin->getRegionUUID($zone_info['name']);
            }

            $sql = "SELECT `descr` AS `description` FROM `" . DB_PREFIX . "justin_cities` WHERE `region_uuid` = '" . $this->db->escape($region) . "'";
        } else {
            $sql = "SELECT CONCAT(`descr`, ' (', `region_descr`, ' обл.)') AS `description` FROM `" . DB_PREFIX . "justin_cities` WHERE 1";
        }

        if ($search) {
            $sql .= " AND `descr` LIKE '" . $this->db->escape($search) . "%'";
        } else {
            $sql .= " AND `language` = '" . $justin->lang . "'";
        }

        $sql .= " GROUP BY `uuid` ORDER BY `descr` ";

        return $this->db->query($sql)->rows;
    }

    public function getJustinDepartments($city, $search = '') {
        if (version_compare(VERSION, '3', '>=')) {
            $settings = $this->config->get('shipping_justin');
        } else {
            $settings = $this->config->get('justin');
        }

        $t_array = explode('(', $city);

        $sql = "SELECT `descr` AS `description` FROM `" . DB_PREFIX . "justin_departments` WHERE `city_descr` = '" . $this->db->escape(trim($t_array[0])) . "'";

        if ($search) {
            $sql .= " AND `descr` LIKE '%" . $this->db->escape($search) . "%'";
        }

        if (isset($settings['shipping_methods']['department']['department_types'])) {
            foreach ($settings['shipping_methods']['department']['department_types'] as $k => $v) {
                $settings['shipping_methods']['department']['department_types'][$k] = "'" . $v . "'";
            }

            $sql .= " AND `branchType_uuid` IN (" . implode(',', $settings['shipping_methods']['department']['department_types']) . ")";
        }

        if (isset($settings['shipping_methods']['department']['department_statuses'])) {
            $sql .= " AND `StatusDepart` IN (" . implode(',', $settings['shipping_methods']['department']['department_statuses']) . ")";
        }

        if (isset($this->session->data['shippingdata']['cart_weight']) && $settings['shipping_methods']['department']['filter_weight']) {
            $sql .= " AND `weight_limit` >= '" . $this->session->data['shippingdata']['cart_weight'] . "'";
        }

        $sql .= " ORDER BY `departNumber`+0";

        return $this->db->query($sql)->rows;
    }

	private function getDefaultCities() {
        $data = array();

        if ($this->language->get('code') == 'ru' || $this->language->get('code') == 'ru-ru') {
            $description = 'description_ru';
        } else {
            $description = 'description';
        }

        $cities = array(
            array(
                'description'   => 'Київ',
                'description_ru' => 'Киев',
                'district_description' => ''
            ),
            array(
                'description'   => 'Харків',
                'description_ru' => 'Харьков',
                'district_description' => ''
            ),
            array(
                'description'   => 'Дніпро',
                'description_ru' => 'Днепр',
                'district_description' => ''
            ),
            array(
                'description'   => 'Одеса',
                'description_ru' => 'Одесса',
                'district_description' => ''

            ),
            /* Delivery is temporarily not carried out
            array(
                'description'   => 'Донецьк',
                'description_ru' => 'Донецк',
                'district_description' => ''
            ),
            */
            array(
                'description'   => 'Запоріжжя',
                'description_ru' => 'Запорожье',
                'district_description' => ''
            ),
            array(
                'description'   => 'Львів',
                'description_ru' => 'Львов',
                'district_description' => ''
            ),
            array(
                'description'   => 'Кривий Ріг',
                'description_ru' => 'Кривой Рог',
                'district_description' => ''
            ),
            array(
                'description'   => 'Миколаїв',
                'description_ru' => 'Николаев',
                'district_description' => ''
            ),
            array(
                'description'   => 'Маріуполь',
                'description_ru' => 'Мариуполь',
                'district_description' => ''
            ),
            /* Delivery is temporarily not carried out
           array(
               'description'   => 'Луганськ',
               'description_ru' => 'Луганск',
                'district_description' => ''
           ),
           */
            array(
                'description'   => 'Вінниця',
                'description_ru' => 'Винница',
                'district_description' => ''
            ),
            /* Delivery is temporarily not carried out
           array(
               'description'   => 'Севастополь',
               'description_ru' => 'Севастополь',
                'district_description' => ''
           ),
           */
            /* Delivery is temporarily not carried out
          array(
              'description'   => 'Сімферополь',
              'description_ru' => 'Симферополь',
                'district_description' => ''
          ),
          */
            array(
                'description'   => 'Херсон',
                'description_ru' => 'Херсон',
                'district_description' => ''
            ),
            array(
                'description'   => 'Полтава',
                'description_ru' => 'Полтава',
                'district_description' => ''
            ),
            array(
                'description'   => 'Чернігів',
                'description_ru' => 'Чернигов',
                'district_description' => ''
            ),
            array(
                'description'   => 'Черкаси',
                'description_ru' => 'Черкассы',
                'district_description' => ''
            ),
            array(
                'description'   => 'Суми',
                'description_ru' => 'Сумы',
                'district_description' => ''
            ),
            array(
                'description'   => 'Хмельницький',
                'description_ru' => 'Хмельницкий',
                'district_description' => ''
            ),
            array(
                'description'   => 'Житомир',
                'description_ru' => 'Житомир',
                'district_description' => ''
            ),
            array(
                'description'   => 'Кропивницький',
                'description_ru' => 'Кропивницкий',
                'district_description' => ''
            ),
            array(
                'description'   => 'Рівне',
                'description_ru' => 'Ровно',
                'district_description' => ''
            ),
            array(
                'description'   => 'Чернівці',
                'description_ru' => 'Черновцы',
                'district_description' => ''
            ),
            array(
                'description'   => 'Тернопіль',
                'description_ru' => 'Тернополь',
                'district_description' => ''
            ),
            array(
                'description'   => 'Івано-Франківськ',
                'description_ru' => 'Ивано-Франковск',
                'district_description' => ''
            ),
            array(
                'description'   => 'Луцьк',
                'description_ru' => 'Луцк',
                'district_description' => ''
            ),
            array(
                'description'   => 'Ужгород',
                'description_ru' => 'Ужгород',
                'district_description' => ''
            )
        );

        foreach ($cities as $city) {
            $data[] = array(
                'description' => $city[$description],
                'district_description' => ''
            );
        }

        return $data;
    }

    public function getZone($zone_id) {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "zone` WHERE `zone_id` = '" . (int)$zone_id . "' AND `status` = '1'");

        return $query->row;
    }

    public function getShippingMethod() {
        if (!empty($this->request->post['shipping_method']) && is_string($this->request->post['shipping_method'])) {
            $data = explode('.', $this->request->post['shipping_method']);
        } elseif (!empty($this->request->post['shipping']) && is_string($this->request->post['shipping'])) {
            $data = explode('.', $this->request->post['shipping']);
        } elseif (isset($this->session->data['shipping_method'], $this->session->data['shipping_method']['code']) && is_string($this->session->data['shipping_method']['code'])) {
            $data = explode('.', $this->session->data['shipping_method']['code']);
        } else {
            $data = array('', '');
        }

        return array (
            'method'     => $data[0],
            'sub_method' => $data[1]
        );
    }
}

class ModelExtensionModuleShippingData extends ModelModuleShippingData {

}