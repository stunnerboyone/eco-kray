<?php

class NovaPoshta
{
	public $key_api = null;
	public $description_field = null;
	public $error = array();
	private $api_url = 'https://api.novaposhta.ua/v2.0/json/';
	private $settings = null;
	private $registry = null;

	public function __construct($registry)
	{
		$this->registry = $registry;

		if (version_compare(VERSION, '3', '>=')) {
			$this->settings = $this->config->get('shipping_novaposhta');
		} else {
			$this->settings = $this->config->get('novaposhta');
		}

		if (isset($this->settings['key_api'])) {
			$this->key_api = $this->settings['key_api'];
		} else {
			$this->key_api = '';
		}

		switch ($this->language->get('code')) {
			case 'ru':
			case 'ru-ru':
				$this->description_field = 'DescriptionRu';

				break;

			default:
				$this->description_field = 'Description';

				break;
		}
	}

	public function __get($name)
	{
		return $this->registry->get($name);
	}

	public function apiRequest($model, $method, $properties = array())
	{
		$request = array('apiKey' => $this->key_api, 'modelName' => $model, 'calledMethod' => $method);

		if (!empty($properties)) {
			$request['methodProperties'] = $properties;
		}

		$json = json_encode($request);
		$options = array(CURLOPT_HTTPHEADER => array('Content-Type: application/json'), CURLOPT_HEADER => false, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $json, CURLOPT_RETURNTRANSFER => true);

		if (isset($this->settings['curl_connecttimeout'])) {
			$options[CURLOPT_CONNECTTIMEOUT] = $this->settings['curl_connecttimeout'];
		}

		if (isset($this->settings['curl_timeout']) && isset($this->settings['curl_connecttimeout']) && $this->settings['curl_connecttimeout'] < $this->settings['curl_timeout']) {
			$options[CURLOPT_TIMEOUT] = $this->settings['curl_timeout'];
		}

		$ch = curl_init($this->api_url);
		curl_setopt_array($ch, $options);
		$response = curl_exec($ch);

		if (!empty($this->settings['debugging_mode'])) {
			$this->log->write('Nova Poshta API request: ' . $json);
			$this->log->write('Nova Poshta API response: ' . $response);

			if ($response === false) {
				$this->log->write('cURL error: ' . curl_error($ch));
			}
		}

		curl_close($ch);
		$response = json_decode($response, true);
		$this->parseErrors($response);

		if (isset($response['success']) && isset($response['data']) && $response['success']) {
			$data = $response['data'];
		} else {
			$data = false;
		}

		return $data;
	}

	private function parseErrors($response)
	{
		$error_types = array('errorCodes' => 'errors', 'warningCodes' => 'warnings', 'infoCodes' => 'info');

		if (!empty($response['errorCodes']) || !empty($response['warningCodes']) || !empty($response['infoCodes'])) {
			$errors_list = $this->getReferences('errors');

			if (!is_array($errors_list)) {
				$errors_list = array();
			}
		}

		foreach ($error_types as $code => $type) {
			if (!empty($response[$type]) && is_array($response[$type])) {
				foreach ($response[$type] as $k => $error) {
					if (is_array($error)) {
						foreach ($error as $i => $e) {
							if (isset($response[$code][$k][$i]) && array_key_exists($response[$code][$k][$i], $errors_list)) {
								$error_text = 'Nova Poshta ' . $type . ': ' . $errors_list[$response[$code][$k][$i]]['Description'];
							} else {
								$error_text = 'Nova Poshta ' . $type . ': ' . $e;
							}

							if ($type != 'info') {
								$this->error[] = $error_text;
							}

							if ($this->settings['debugging_mode']) {
								$this->log->write($error_text);
							}
						}
					} else {
						if (isset($response[$code][$k]) && isset($errors_list[$response[$code][$k]]) && isset($errors_list[$response[$code][$k]]['Description'])) {
							$error_text = 'Nova Poshta ' . $type . ': ' . $errors_list[$response[$code][$k]]['Description'];
						} else {
							$error_text = 'Nova Poshta ' . $type . ': ' . $error;
						}

						if ($type != 'info') {
							$this->error[] = $error_text;
						}

						if (!empty($this->settings['debugging_mode'])) {
							$this->log->write($error_text);
						}
					}
				}
			}
		}
	}

	public function update($type)
	{
		$count = 0;

		if ($type == 'regions') {
			$data = $this->apiRequest('Address', 'getAreas');

			if ($data) {
				$this->db->query('TRUNCATE TABLE `' . DB_PREFIX . 'novaposhta_regions`');

				foreach ($data as $v) {
					$sql = 'INSERT INTO `' . DB_PREFIX . "novaposhta_regions` (`Ref`, `AreasCenter`, `Description`, `DescriptionRu`) VALUES (\n                        '" . (string) $v['Ref'] . "',\n                        '" . (string) $v['AreasCenter'] . "',\n                        '" . $this->db->escape((string) $v['Description']) . "', \n\t\t\t\t\t\t'" . $this->db->escape((string) $v['DescriptionRu']) . "'\n\t\t\t\t\t)";

					try {
						$this->db->query($sql);
						$count++;
					} catch (Exception $e) {
						if ($this->settings['debugging_mode']) {
							$this->log->write($e->getMessage());
						}
					}
				}
			}
		} else {
			if ($type == 'cities') {
				for ($page = 1; $data = $this->apiRequest('Address', 'getCities', array('Page' => $page, 'Limit' => 500)); $page++) {
					if ($page == 1) {
						$this->db->query('TRUNCATE TABLE `' . DB_PREFIX . 'novaposhta_cities`');
					}

					foreach ($data as $v) {
						if (!$v['Description'] && !$v['DescriptionRu']) {
							continue;
						}

						if (!$v['Description']) {
							$v['Description'] = $v['DescriptionRu'];
						} else {
							if (!$v['DescriptionRu']) {
								$v['DescriptionRu'] = $v['Description'];
							}
						}

						$sql = 'INSERT INTO `' . DB_PREFIX . "novaposhta_cities` (`CityID`, `Ref`, `Description`, `DescriptionRu`, `Area`, `AreaDescription`, `AreaDescriptionRu`, `SettlementType`, `SettlementTypeDescription`, `SettlementTypeDescriptionRu`, `Delivery1`, `Delivery2`, `Delivery3`, `Delivery4`, `Delivery5`, `Delivery6`, `Delivery7`, `PreventEntryNewStreetsUser`, `IsBranch`, `SpecialCashCheck`) VALUES (\n                        '" . (int) $v['CityID'] . "',\n                        '" . $v['Ref'] . "',\n                        '" . $this->db->escape((string) $v['Description']) . "', \n\t\t\t\t\t\t'" . $this->db->escape((string) $v['DescriptionRu']) . "', \t\t\t\t\t\t \n\t\t\t\t\t\t'" . $v['Area'] . "',\n\t\t\t\t\t\t'" . $this->db->escape((string) $v['AreaDescription']) . "',\n\t\t\t\t\t\t'" . $this->db->escape((string) $v['AreaDescriptionRu']) . "',\n\t\t\t\t\t    '" . $v['SettlementType'] . "',\n\t\t\t\t\t\t'" . ((isset($v['SettlementTypeDescription']) ? $this->db->escape((string) $v['SettlementTypeDescription']) : '')) . "',\n\t\t\t\t\t\t'" . ((isset($v['SettlementTypeDescriptionRu']) ? $this->db->escape((string) $v['SettlementTypeDescriptionRu']) : '')) . "',\n\t\t\t\t\t\t'" . (int) $v['Delivery1'] . "', \n\t\t\t\t\t\t'" . (int) $v['Delivery2'] . "', \n\t\t\t\t\t\t'" . (int) $v['Delivery3'] . "', \n\t\t\t\t\t\t'" . (int) $v['Delivery4'] . "', \n\t\t\t\t\t\t'" . (int) $v['Delivery5'] . "', \n\t\t\t\t\t\t'" . (int) $v['Delivery6'] . "', \n\t\t\t\t\t\t'" . (int) $v['Delivery7'] . "',\n\t\t\t\t\t\t'" . $this->db->escape((string) $v['PreventEntryNewStreetsUser']) . "',\n\t\t\t\t\t\t'" . (int) $v['IsBranch'] . "', \n\t\t\t\t\t\t'" . (int) $v['SpecialCashCheck'] . "'\n\t\t\t\t\t) ON DUPLICATE KEY UPDATE\n\t\t\t\t\t    `Description` = VALUES(`Description`),\n                        `DescriptionRu` = VALUES(`DescriptionRu`),\n                        `Area` = VALUES(`Area`),\n                        `AreaDescription` = VALUES(`AreaDescription`),\n                        `AreaDescriptionRu` = VALUES(`AreaDescriptionRu`),\n                        `SettlementType` = VALUES(`SettlementType`),\n                        `SettlementTypeDescription` = VALUES(`SettlementTypeDescription`),\n                        `SettlementTypeDescriptionRu` = VALUES(`SettlementTypeDescriptionRu`),\n                        `Delivery1` = VALUES(`Delivery1`),\n                        `Delivery2` = VALUES(`Delivery2`),\n                        `Delivery3` = VALUES(`Delivery3`),\n                        `Delivery4` = VALUES(`Delivery4`),\n                        `Delivery5` = VALUES(`Delivery5`),\n                        `Delivery6` = VALUES(`Delivery6`),\n                        `Delivery7` = VALUES(`Delivery7`),\n                        `PreventEntryNewStreetsUser` = VALUES(`PreventEntryNewStreetsUser`),\n                        `IsBranch` = VALUES(`IsBranch`),\n                        `SpecialCashCheck` = VALUES(`SpecialCashCheck`)";

						try {
							$this->db->query($sql);
							$count++;
						} catch (Exception $e) {
							if ($this->settings['debugging_mode']) {
								$this->log->write($e->getMessage());
							}
						}
					}
				}
			} else {
				if ($type == 'departments') {
					for ($page = 1; $data = $this->apiRequest('Address', 'getWarehouses', array('Page' => $page, 'Limit' => 4000)); $page++) {
						if ($page == 1) {
							$this->db->query('TRUNCATE TABLE `' . DB_PREFIX . 'novaposhta_departments`');
						}

						foreach ($data as $v) {
							if (!$v['Description'] && !$v['DescriptionRu']) {
								continue;
							}

							if (!$v['Description']) {
								$v['Description'] = $v['DescriptionRu'];
							} else {
								if (!$v['DescriptionRu']) {
									$v['DescriptionRu'] = $v['Description'];
								}
							}

							$description = preg_replace(array('/"([^"]*)"/', '/"/'), array('«$1»', ''), $v['Description']);
							$description_ru = preg_replace(array('/"([^"]*)"/', '/"/'), array('«$1»', ''), $v['DescriptionRu']);
							$sql = 'INSERT INTO `' . DB_PREFIX . "novaposhta_departments` (`SiteKey`, `Ref`, `Description`, `DescriptionRu`, `ShortAddress`, `ShortAddressRu`, `TypeOfWarehouse`, `CityRef`, `CityDescription`, `CityDescriptionRu`, `SettlementRef`, `SettlementDescription`, `SettlementAreaDescription`, `SettlementRegionsDescription`, `SettlementTypeDescription`, `Number`, `Phone`,  `Longitude`, `Latitude`, `PostFinance`, `BicycleParking`, `PaymentAccess`, `POSTerminal`, `InternationalShipping`, `SelfServiceWorkplacesCount`, `TotalMaxWeightAllowed`, `PlaceMaxWeightAllowed`, `SendingLimitationsOnDimensions_length`, `SendingLimitationsOnDimensions_width`, `SendingLimitationsOnDimensions_height`, `ReceivingLimitationsOnDimensions_length`, `ReceivingLimitationsOnDimensions_width`, `ReceivingLimitationsOnDimensions_height`, `Reception_monday`, `Reception_tuesday`, `Reception_wednesday`, `Reception_thursday`, `Reception_friday`, `Reception_saturday`, `Reception_sunday`, `Delivery_monday`, `Delivery_tuesday`, `Delivery_wednesday`, `Delivery_thursday`, `Delivery_friday`, `Delivery_saturday`, `Delivery_sunday`, `Schedule_monday`, `Schedule_tuesday`, `Schedule_wednesday`, `Schedule_thursday`, `Schedule_friday`, `Schedule_saturday`, `Schedule_sunday`, `DistrictCode`, `WarehouseStatus`, `WarehouseStatusDate`, `CategoryOfWarehouse`, `Direct`, `RegionCity`) VALUES (\n                        '" . (int) $v['SiteKey'] . "',\n                        '" . $v['Ref'] . "',\n\t\t\t\t\t\t'" . $this->db->escape($description) . "',\n\t\t\t\t\t\t'" . $this->db->escape($description_ru) . "',\n\t\t\t\t\t\t'" . $this->db->escape($v['ShortAddress']) . "',\n\t\t\t\t\t\t'" . $this->db->escape($v['ShortAddressRu']) . "',\n\t\t\t\t\t\t'" . $v['TypeOfWarehouse'] . "',\n\t\t\t\t\t\t'" . $v['CityRef'] . "',\n\t\t\t\t\t\t'" . $this->db->escape($v['CityDescription']) . "',\n\t\t\t\t\t\t'" . $this->db->escape($v['CityDescriptionRu']) . "',\n\t\t\t\t\t\t'" . $v['SettlementRef'] . "',\n\t\t\t\t\t\t'" . $this->db->escape($v['SettlementDescription']) . "',\n\t\t\t\t\t\t'" . $this->db->escape($v['SettlementAreaDescription']) . "',\n\t\t\t\t\t\t'" . $this->db->escape($v['SettlementRegionsDescription']) . "',\n\t\t\t\t\t\t'" . $this->db->escape($v['SettlementTypeDescription']) . "',\n\t\t\t\t\t\t'" . (int) $v['Number'] . "',\n\t\t\t\t\t\t'" . $v['Phone'] . "',\n\t\t\t\t\t\t'" . (double) $v['Longitude'] . "', \n\t\t\t\t\t\t'" . (double) $v['Latitude'] . "',\n\t\t\t\t\t\t'" . $v['PostFinance'] . "', \n\t\t\t\t\t\t'" . $v['BicycleParking'] . "', \n\t\t\t\t\t\t'" . $v['PaymentAccess'] . "', \n\t\t\t\t\t\t'" . $v['POSTerminal'] . "', \n\t\t\t\t\t\t'" . $v['InternationalShipping'] . "', \n\t\t\t\t\t\t'" . $v['SelfServiceWorkplacesCount'] . "',\n\t\t\t\t\t\t'" . $v['TotalMaxWeightAllowed'] . "', \n\t\t\t\t\t\t'" . $v['PlaceMaxWeightAllowed'] . "', \n\t\t\t\t\t\t'" . $v['SendingLimitationsOnDimensions']['Length'] . "', \n\t\t\t\t\t\t'" . $v['SendingLimitationsOnDimensions']['Width'] . "', \n\t\t\t\t\t\t'" . $v['SendingLimitationsOnDimensions']['Height'] . "',\n\t\t\t\t\t\t'" . $v['ReceivingLimitationsOnDimensions']['Length'] . "', \n\t\t\t\t\t\t'" . $v['ReceivingLimitationsOnDimensions']['Width'] . "', \n\t\t\t\t\t\t'" . $v['ReceivingLimitationsOnDimensions']['Height'] . "',\n\t\t\t\t\t\t'" . $v['Reception']['Monday'] . "',\n\t\t\t\t\t\t'" . $v['Reception']['Tuesday'] . "',\n\t\t\t\t\t\t'" . $v['Reception']['Wednesday'] . "',\n\t\t\t\t\t\t'" . $v['Reception']['Thursday'] . "',\n\t\t\t\t\t\t'" . $v['Reception']['Friday'] . "',\n\t\t\t\t\t\t'" . $v['Reception']['Saturday'] . "',\n\t\t\t\t\t\t'" . $v['Reception']['Sunday'] . "',\n\t\t\t\t\t\t'" . $v['Delivery']['Monday'] . "',\n\t\t\t\t\t\t'" . $v['Delivery']['Tuesday'] . "',\n\t\t\t\t\t\t'" . $v['Delivery']['Wednesday'] . "',\n\t\t\t\t\t\t'" . $v['Delivery']['Thursday'] . "',\n\t\t\t\t\t\t'" . $v['Delivery']['Friday'] . "',\n\t\t\t\t\t\t'" . $v['Delivery']['Saturday'] . "',\n\t\t\t\t\t\t'" . $v['Delivery']['Sunday'] . "',\n\t\t\t\t\t\t'" . $v['Schedule']['Monday'] . "',\n\t\t\t\t\t\t'" . $v['Schedule']['Tuesday'] . "',\n\t\t\t\t\t\t'" . $v['Schedule']['Wednesday'] . "',\n\t\t\t\t\t\t'" . $v['Schedule']['Thursday'] . "',\n\t\t\t\t\t\t'" . $v['Schedule']['Friday'] . "',\n\t\t\t\t\t\t'" . $v['Schedule']['Saturday'] . "',\n\t\t\t\t\t\t'" . $v['Schedule']['Sunday'] . "', \n\t\t\t\t\t\t'" . $this->db->escape($v['DistrictCode']) . "',\n\t\t\t\t\t\t'" . $v['WarehouseStatus'] . "',\n\t\t\t\t\t\t'" . $v['WarehouseStatusDate'] . "',\n\t\t\t\t\t\t'" . $v['CategoryOfWarehouse'] . "',\n\t\t\t\t\t\t'" . $v['Direct'] . "',\n\t\t\t\t\t\t'" . $this->db->escape($v['RegionCity']) . "'\n\t\t\t\t\t)";

							try {
								$this->db->query($sql);
								$count++;
							} catch (Exception $e) {
								if ($this->settings['debugging_mode']) {
									$this->log->write($e->getMessage());
								}
							}
						}
						unset($data);
					}
				} else {
					if ($type == 'references') {
						$post = array('domain' => $this->getDomain(), 'extension' => 'novaposhta');
						$options = array(CURLOPT_HEADER => false, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $post, CURLOPT_RETURNTRANSFER => true);

						if (isset($this->settings['curl_connecttimeout'])) {
							$options[CURLOPT_CONNECTTIMEOUT] = $this->settings['curl_connecttimeout'];
						}

						if (isset($this->settings['curl_timeout']) && isset($this->settings['curl_connecttimeout']) && $this->settings['curl_connecttimeout'] < $this->settings['curl_timeout']) {
							$options[CURLOPT_TIMEOUT] = $this->settings['curl_timeout'];
						}

						$ch = curl_init('https://oc-max.com/index.php?route=extension/module/ocmax/getData');
						curl_setopt_array($ch, $options);
						$response = curl_exec($ch);
						curl_close($ch);
						$data = json_decode($response, true);
						$data['senders'] = $this->getCounterparties('Sender');
						$data['third_persons'] = $this->getCounterparties('ThirdPerson');

						if ($data['senders']) {
							foreach ($data['senders'] as $sender) {
								$data['sender_options'][$sender['Ref']] = $this->getCounterpartyOptions($sender['Ref']);
								$data['sender_contact_persons'][$sender['Ref']] = $this->getContactPersons($sender['Ref']);
								$data['sender_addresses'][$sender['Ref']] = $this->getCounterpartyAddresses($sender['Ref'], 'Sender');
							}
						}

						$data['department_types'] = $this->apiRequest('Address', 'getWarehouseTypes');
						$data['service_types'] = $this->apiRequest('Common', 'getServiceTypes');
						$data['cargo_types'] = $this->apiRequest('Common', 'getCargoTypes');
						$data['pack_types'] = $this->apiRequest('Common', 'getPackList');
						$data['tires_and_wheels'] = $this->apiRequest('Common', 'getTiresWheelsList');
						$data['payer_types'] = $this->apiRequest('Common', 'getTypesOfPayers');
						$data['payment_types'] = $this->apiRequest('Common', 'getPaymentForms');
						$data['backward_delivery_types'] = $this->apiRequest('Common', 'getBackwardDeliveryCargoTypes');
						$data['backward_delivery_payers'] = $this->apiRequest('Common', 'getTypesOfPayersForRedelivery');
						$data['cargo_description'] = array_merge($this->apiRequest('Common', 'getCargoDescriptionList', array('Page' => 1)), $this->apiRequest('Common', 'getCargoDescriptionList', array('Page' => 2)));
						$data['ownership_forms'] = $this->apiRequest('Common', 'getOwnershipFormsList');
						$data['counterparties_types'] = $this->apiRequest('Common', 'getTypesOfCounterparties');
						$data['errors'] = $this->getErrors();

						if (0 < count($data, COUNT_RECURSIVE) - count($data)) {
							foreach ($data as $k => $v) {
								$this->db->query('INSERT INTO `' . DB_PREFIX . "novaposhta_references` (`type`, `value`) VALUES ('" . $k . "', '" . $this->db->escape(json_encode($v)) . "') ON DUPLICATE KEY UPDATE `value`='" . $this->db->escape(json_encode($v)) . "'");
							}
						}

						$count = count($data);
						$this->db->query('UPDATE `' . DB_PREFIX . "novaposhta_references` SET `value`= REPLACE(`value`, '\\'', '`')");
					}
				}
			}
		}

		if ($count) {
			$database = $this->getReferences('database');
			$database[$type]['update_datetime'] = date('d.m.Y H:i');
			$database[$type]['amount'] = $count;
			$this->db->query('INSERT INTO `' . DB_PREFIX . "novaposhta_references` (`type`, `value`) VALUES ('database', '" . json_encode($database) . "') ON DUPLICATE KEY UPDATE `value`='" . json_encode($database) . "'");
		}

		return $count;
	}

	public function regions()
	{
		return array('АРК' => array('Крим', 'АРК', 'Крым', 'АРК', 'Krym', 'Crimea'), 'Вінницька' => array('Вінниця', 'Вінницька', 'Винница', 'Винницкая', 'Vinnitsa', 'Vinnitskaya'), 'Волинська' => array('Волинь', 'Волинська', 'Волынь', 'Волынская', 'Volyn', 'Volynskaya'), 'Дніпропетровська' => array('Дніпро', 'Дніпропетровськ', 'Дніпропетровська', 'Днепропетровск', 'Днепропетровская', 'Dnipropetrovsk', 'Dnepropetrovskaya'), 'Донецька' => array('Донецьк', 'Донецька', 'Донецк', 'Донецкая', 'Donetsk', 'Donetskaya'), 'Житомирська' => array('Житомир', 'Житомирська', 'Житомир', 'Житомирская', 'Zhytomyr', 'Zhitomirskaya'), 'Закарпатська' => array('Закарпаття', 'Закарпатська', 'Закарпатье', 'Закарпатская', 'Zakarpattya', 'Zakarpatskaya'), 'Запорізька' => array('Запоріжжя', 'Запорізька', 'Запорожье', 'Запорожская', 'Zaporizhia', 'Zaporozhskaya'), 'Івано-Франківська' => array('Івано-Франківськ', 'Івано-Франківська', 'Ивано-Франковск', 'Ивано-Франковская', 'Ivano-Frankivsk', 'Ivano-Frankovskaya'), 'Київська' => array('Київ', 'Київська', 'Киев', 'Киевская', 'Kyiv', 'Kiyevskaya'), 'Київ' => array('Київ', 'Київська', 'Киев', 'Киевская', 'Kyiv', 'Kiyevskaya'), 'Кіровоградська' => array('Кіровоград', 'Кіровоградська', 'Кировоград', 'Кировоградская', 'Kirovohrad', 'Kirovogradskaya'), 'Луганська' => array('Луганськ', 'Луганська', 'Луганск', 'Луганская', 'Lugansk', 'Luganskaya'), 'Львівська' => array('Львів', 'Львівська', 'Львов', 'Львовская', 'Lviv', "L'vovskaya"), 'Миколаївська' => array('Миколаїв', 'Миколаївська', 'Николаев', 'Николаевская', 'Mykolaiv', 'Nikolayevskaya'), 'Одеська' => array('Одеса', 'Одеська', 'Одесса', 'Одесская', 'Odessa', 'Odesskaya'), 'Полтавська' => array('Полтава', 'Полтавська', 'Полтава', 'Полтавская', 'Poltava', 'Poltavskaya'), 'Рівненська' => array('Рівне', 'Рівненська', 'Ровно', 'Ровненская', 'Ровенская', 'Rivne', 'Rovenskaya'), 'Сумська' => array('Суми', 'Сумська', 'Сумы', 'Сумская', 'Sumy', 'Sumskaya'), 'Тернопільська' => array('Тернопіль', 'Тернопільська', 'Тернополь', 'Тернопольская', 'Ternopil', "Ternopol'skaya"), 'Харківська' => array('Харків', 'Харківська', 'Харьков', 'Харьковская', 'Kharkov', "Khar'kovskaya"), 'Херсонська' => array('Херсон', 'Херсонська', 'Херсон', 'Херсонская', 'Herson', 'Khersonskaya'), 'Хмельницька' => array('Хмельницьк', 'Хмельницька', 'Хмельницкий', 'Хмельницкая', 'Khmelnytsky', "Khmel'nitskaya"), 'Черкаська' => array('Черкаси', 'Черкаська', 'Черкассы', 'Черкасская', 'Cherkassy', 'Cherkasskaya'), 'Чернівецька' => array('Чернівці', 'Чернівецька', 'Черновцы', 'Черновицкая', 'Chernivtsi', 'Chernovitskaya'), 'Чернігівська' => array('Чернігів', 'Чернігівська', 'Чернигов', 'Черниговская', 'Chernihiv', 'Chernigovskaya'));
	}

	public function getRegion($ref)
	{
		$result = $this->db->query('SELECT * FROM `' . DB_PREFIX . "novaposhta_regions` WHERE `Ref` = '" . $this->db->escape($ref) . "'")->row;

		return $result;
	}

	public function getRegionRef($zone)
	{
		if (!$zone) {
			return false;
		}

		if ((int) $zone !== 0) {
			$result = $this->db->query('SELECT * FROM `' . DB_PREFIX . "zone` WHERE `zone_id` = '" . (int) $zone . "' AND `status` = '1'")->row;

			if ($result) {
				$zone = $result['name'];
			}
		}

		$id = false;
		$sub_name = mb_substr($zone, 0, 6, 'UTF-8');

		foreach ($this->regions() as $d => $v) {
			$match = preg_grep('/^' . preg_quote($sub_name) . '/ui', $v);

			if (!empty($match)) {
				$result = $this->db->query('SELECT * FROM `' . DB_PREFIX . "novaposhta_regions` WHERE `Description` = '" . $this->db->escape($d) . "' LIMIT 1")->row;

				if ($result) {
					$id = $result['Ref'];

					break;
				}
			}
		}

		return $id;
	}

	public function getRegionName($name)
	{
		$id = false;
		$sub_name = mb_substr($name, 0, 6, 'UTF-8');

		foreach ($this->regions() as $d => $v) {
			$match = preg_grep('/^' . preg_quote($sub_name) . '/ui', $v);

			if (!empty($match)) {
				$result = $this->db->query('SELECT * FROM `' . DB_PREFIX . "novaposhta_regions` WHERE `Description` = '" . $this->db->escape($d) . "' LIMIT 1")->row;

				if ($result) {
					$id = $result['Description'];

					break;
				}
			}
		}

		return $id;
	}

	public function getZoneIDByName($name)
	{
		$data = array();
		$zones = $this->db->query('SELECT * FROM `' . DB_PREFIX . "zone` WHERE `country_id` = '" . (int) $this->config->get('config_country_id') . "' AND `status` = '1' ORDER BY `name`")->rows;
		$regions = $this->areas();
		$up_region = $regions[$name];

		foreach ($zones as $zone) {
			$sub_name = mb_substr($zone['name'], 0, 6, 'UTF-8');
			$match = preg_grep('/^' . preg_quote($sub_name) . '/ui', $up_region);

			if (!empty($match)) {
				$data = $zone;

				break;
			}
		}

		return (!empty($data['zone_id']) ? $data['zone_id'] : false);
	}

	public function getAreaName($ref)
	{
		$data = '';
		$novaposhta_areas = $this->getAreas();

		foreach ($novaposhta_areas as $d => $v) {
			if ($ref == $v['Ref']) {
				$data = $d;

				break;
			}
		}

		return $data;
	}

	public function getRegions()
	{
		$result = $this->db->query('SELECT `Ref`, `' . $this->description_field . '` as `Description` FROM `' . DB_PREFIX . 'novaposhta_regions` ORDER BY `Description` COLLATE utf8_unicode_ci')->rows;

		return $result;
	}

	public function getCityArea($ref)
	{
		$result = $this->db->query('SELECT `Area` FROM `' . DB_PREFIX . "novaposhta_cities` WHERE `Ref` = '" . $this->db->escape($ref) . "'")->row;

		return ($result ? $result['Area'] : '');
	}

	public function getCityRef($name, $region = null)
	{
		$sql = 'SELECT `Ref` FROM `' . DB_PREFIX . "novaposhta_cities` WHERE (`Description` = '" . $this->db->escape($name) . "' OR `DescriptionRu` = '" . $this->db->escape($name) . "')";

		if ($region) {
			$sql .= " AND `Area` = '" . $this->db->escape($region) . "'";
		}

		$result = $this->db->query($sql)->row;

		if (!empty($result['Ref'])) {
			return $result['Ref'];
		}

		return false;
	}

	public function getCityName($ref)
	{
		$result = $this->db->query('SELECT `' . $this->description_field . '` FROM `' . DB_PREFIX . "novaposhta_cities` WHERE `Ref` = '" . $this->db->escape($ref) . "'")->row;

		return ($result ? $result[$this->description_field] : false);
	}

	public function getCities($region = null)
	{
		$sql = 'SELECT `Ref`, `' . $this->description_field . '` as `description`, `Area' . $this->description_field . '` as `region_description` FROM `' . DB_PREFIX . 'novaposhta_cities` WHERE 1';

		if ($region) {
			$sql .= " AND `Area` = '" . $this->db->escape($region) . "'";
		}

		$sql .= ' ORDER BY `' . $this->description_field . '` COLLATE utf8_unicode_ci';

		return $this->db->query($sql)->rows;
	}

	public function getDepartment($ref)
	{
		$result = $this->db->query('SELECT * FROM `' . DB_PREFIX . "novaposhta_departments` WHERE `Ref` = '" . $this->db->escape($ref) . "'")->row;

		return $result;
	}

	public function getDepartmentRef($name, $city = null)
	{
		$sql = 'SELECT `Ref` FROM `' . DB_PREFIX . "novaposhta_departments` WHERE (`Description` = '" . $this->db->escape($name) . "' OR `DescriptionRu` = '" . $this->db->escape($name) . "')";

		if ($city) {
			$sql .= " AND `CityRef` = '" . $this->db->escape($city) . "'";
		}

		$result = $this->db->query($sql)->row;

		if (!empty($result['Ref'])) {
			return $result['Ref'];
		}

		return false;
	}

	public function getDepartmentsByCityRef($city_ref, $search = '')
	{
		$sql = 'SELECT *, `' . $this->description_field . '` as `Description`, `Ref` FROM `' . DB_PREFIX . "novaposhta_departments` WHERE `CityRef` = '" . $this->db->escape($city_ref) . "'";

		if ($search) {
			$sql .= " AND (`Description` LIKE '%" . $this->db->escape($search) . "%' OR `DescriptionRu` LIKE '%" . $this->db->escape($search) . "%')";
		}

		$results = $this->db->query($sql)->rows;

		return $results;
	}

	public function getDepartmentsByCityName($city_name, $search = '')
	{
		$city_name = $this->db->escape($city_name);
		$sql = 'SELECT *, `' . $this->description_field . '` as `Description` FROM `' . DB_PREFIX . "novaposhta_departments` WHERE (`CityDescription` = '" . $city_name . "' OR `CityDescriptionRu` = '" . $city_name . "')";

		if ($search) {
			$sql .= ' AND `' . $this->description_field . "` LIKE '%" . $this->db->escape($search) . "%'";
		}

		$results = $this->db->query($sql)->rows;

		return $results;
	}

	public function getDepartments($city, $type = null)
	{
		$sql = 'SELECT `Ref`, `Number`, `' . $this->description_field . '` as `description` FROM `' . DB_PREFIX . "novaposhta_departments` WHERE `CityRef` = '" . $this->db->escape($city) . "'";

		if ($type == 'department') {
			$sql .= " AND `CategoryOfWarehouse` <> 'Postomat'";
		} else {
			if ($type == 'poshtomat') {
				$sql .= " AND `CategoryOfWarehouse` = 'Postomat'";
			}
		}

		$sql .= ' ORDER BY `Number`';

		return $this->db->query($sql)->rows;
	}

	public function searchSettlements($search = '', $limit = 0)
	{
		$data = array();
		$properties = array();

		if ($search) {
			$properties['CityName'] = $search;
		}

		if ($limit) {
			$properties['Limit'] = $limit;
		}

		$result = $this->apiRequest('Address', 'searchSettlements', $properties);

		if (!empty($result[0]['TotalCount'])) {
			$data = $result[0]['Addresses'];
		}

		return $data;
	}

	public function searchSettlementStreets($settlement, $search = '', $limit = 0)
	{
		$data = array();
		$properties = array('SettlementRef' => $settlement);

		if ($search) {
			$properties['StreetName'] = $search;
		}

		if ($limit) {
			$properties['Limit'] = $limit;
		}

		$result = $this->apiRequest('Address', 'searchSettlementStreets', $properties);

		if (!empty($result[0]['TotalCount'])) {
			$data = $result[0]['Addresses'];
		}

		return $data;
	}

	public function getReferences($type = '')
	{
		$data = array();

		if ($type) {
			$result = $this->db->query('SELECT `value` FROM `' . DB_PREFIX . "novaposhta_references` WHERE `type` = '" . $type . "'")->row;

			if (isset($result['value'])) {
				$data = json_decode($result['value'], true);

				if (is_array($data)) {
					foreach ($data as $k => $v) {
						if (!empty($v[$this->description_field]) && $this->description_field != 'Description') {
							$data[$k]['Description'] = $v[$this->description_field];
						}
					}
				}
			}
		} else {
			$results = $this->db->query('SELECT `type`, `value` FROM `' . DB_PREFIX . "novaposhta_references` WHERE `type` != 'cargo_description' AND `type` != 'errors'")->rows;

			if (is_array($results)) {
				foreach ($results as $r) {
					$data[$r['type']] = json_decode($r['value'], true);

					if (is_array($data[$r['type']])) {
						foreach ($data[$r['type']] as $k => $v) {
							if (!empty($v[$this->description_field]) && $this->description_field != 'Description') {
								$data[$r['type']][$k]['Description'] = $v[$this->description_field];
							}
						}
					}
				}
			}
		}

		return $data;
	}

	public function getCounterparty($counterparty_type, $ref = '')
	{
		$properties = array('CounterpartyProperty' => $counterparty_type);

		if ($ref) {
			$properties['Ref'] = $ref;
		}

		$data = $this->apiRequest('Counterparty', 'getCounterparties', $properties);

		if (!empty($data[0])) {
			return $data[0];
		}

		return false;
	}

	public function getCounterparties($counterparty_type, $search = '', $ref = '', $city_ref = '')
	{
		$properties = array('CounterpartyProperty' => $counterparty_type);

		if ($search && !preg_match("/[^А-яҐґЄєIіЇїё0-9\\-\\`'\\s]+/iu", $search)) {
			$properties['FindByString'] = $search;
		}

		if ($ref) {
			$properties['Ref'] = $ref;
		}

		if ($city_ref) {
			$properties['CityRef'] = $city_ref;
		}

		$data = $this->apiRequest('Counterparty', 'getCounterparties', $properties);

		if (is_array($data)) {
			foreach ($data as $k => $v) {
				$data[$v['Ref']] = $v;
				unset($data[$k]);
			}
		}

		return $data;
	}

	public function saveCounterparties($properties)
	{
		$data = $this->apiRequest('Counterparty', 'save', $properties);

		return (isset($data[0]) ? $data[0] : $data);
	}

	public function getCounterpartyOptions($ref)
	{
		$properties = array('Ref' => $ref);
		$data = $this->apiRequest('Counterparty', 'getCounterpartyOptions', $properties);

		return (isset($data[0]) ? $data[0] : $data);
	}

	public function getContactPersons($ref, $search = '')
	{
		$properties = array('Ref' => $ref);

		if ($search) {
			$properties['FindByString'] = $search;
		}

		$data = $this->apiRequest('Counterparty', 'getCounterpartyContactPersons', $properties);

		if (is_array($data)) {
			foreach ($data as $k => $v) {
				$data[$v['Ref']] = $v;
				unset($data[$k]);
			}
		}

		return $data;
	}

	public function saveContactPerson($properties)
	{
		$data = $this->apiRequest('ContactPerson', 'save', $properties);

		return (isset($data[0]) ? $data[0] : false);
	}

	public function updateContactPerson($properties)
	{
		$data = $this->apiRequest('ContactPerson', 'update', $properties);

		return $data;
	}

	public function getCounterpartyAddresses($counterparty_ref, $counterparty_type, $city_ref = '')
	{
		$properties = array('Ref' => $counterparty_ref, 'CounterpartyProperty' => $counterparty_type);

		if ($city_ref) {
			$properties['CityRef'] = $city_ref;
		}

		$data = $this->apiRequest('Counterparty', 'getCounterpartyAddresses', $properties);

		if (is_array($data)) {
			foreach ($data as $k => $v) {
				$data[$v['Ref']] = $v;
				unset($data[$k]);
			}
		}

		return $data;
	}

	public function saveAddress($properties)
	{
		$data = $this->apiRequest('Address', 'save', $properties);

		return (isset($data[0]) ? $data[0] : false);
	}

	public function getSenderAddresses($counterparty_ref, $city_ref)
	{
		$results = array();
		$addresses = $this->novaposhta->getReferences('sender_addresses');

		if (isset($addresses[$counterparty_ref])) {
			foreach ($addresses[$counterparty_ref] as $k => $sender_address) {
				if ($sender_address['CityRef'] == $city_ref) {
					$sender_address['description'] = $sender_address['Description'];
					$results[$k] = $sender_address;
				}
			}
		}

		return $results;
	}

	public function getTimeIntervals($ref, $date = '')
	{
		$properties = array('RecipientCityRef' => $ref, 'DateTime' => $date);
		$data = $this->apiRequest('Common', 'getTimeIntervals', $properties);

		return $data;
	}

	public function getErrors()
	{
		$data = $this->apiRequest('CommonGeneral', 'getMessageCodeText');

		if (is_array($data)) {
			foreach ($data as $k => $v) {
				$data[$v['MessageCode']] = array('Description' => $v['MessageDescriptionUA'], 'DescriptionRu' => $v['MessageDescriptionRU'], 'DescriptionEn' => $v['MessageText']);
				unset($data[$k]);
			}
		}

		return $data;
	}

	public function getDocumentPrice($properties)
	{
		$cost = 0;
		$data = $this->apiRequest('InternetDocument', 'getDocumentPrice', $properties);

		if (isset($data[0])) {
			$cost += $data[0]['Cost'];

			if (!empty($data[0]['CostPack'])) {
				$cost += $data[0]['CostPack'];
			}

			if (!empty($data[0]['CostRedelivery'])) {
				$cost += $data[0]['CostRedelivery'];
			}
		}

		return $cost;
	}

	public function getDocumentDeliveryDate($properties)
	{
		$data = $this->apiRequest('InternetDocument', 'getDocumentDeliveryDate', $properties);

		return ($data ? $this->dateDiff($data[0]['DeliveryDate']['date']) : 0);
	}

	public function saveCN($properties)
	{
		$method = (isset($properties['Ref']) ? 'update' : 'save');
		$data = $this->apiRequest('InternetDocument', $method, $properties);

		return (isset($data[0]) ? $data[0] : false);
	}

	public function getCN($ref)
	{
		$properties = array('Ref' => $ref);
		$data = $this->apiRequest('InternetDocument', 'getDocument', $properties);

		return (isset($data[0]) ? $data[0] : false);
	}

	public function getCNList($date_from = '', $date_to = '', $properties = array())
	{
		$properties['GetFullList'] = 1;

		if ($date_from && $date_to) {
			$properties['DateTimeFrom'] = $date_from;
			$properties['DateTimeTo'] = $date_to;
		} else {
			if ($date_from) {
				$properties['DateTime'] = $date_from;
			}
		}

		$data = $this->apiRequest('InternetDocument', 'getDocumentList', $properties);

		return $data;
	}

	public function deleteCN($refs)
	{
		$properties = array('DocumentRefs' => $refs);
		$data = $this->apiRequest('InternetDocument', 'delete', $properties);

		return $data;
	}

	public function saveRegistry($cns, $ref = false, $date = false)
	{
		$properties['DocumentRefs'] = $cns;

		if ($ref) {
			$properties['Ref'] = $ref;
		}

		if ($date) {
			$properties['Date'] = $date;
		}

		$data = $this->apiRequest('ScanSheet', 'insertDocuments', $properties);

		if (!empty($data[0])) {
			return $data[0];
		}

		return false;
	}

	public function getRegistry($ref, $sender)
	{
		$properties = array('Ref' => $ref, 'CounterpartyRef' => $sender);
		$data = $this->apiRequest('ScanSheet', 'getScanSheet', $properties);

		return $data;
	}

	public function getRegistries()
	{
		$data = $this->apiRequest('ScanSheet', 'getScanSheetList');

		return $data;
	}

	public function deleteCNFromRegistry($cns, $ref)
	{
		$properties = array('Ref' => $ref, 'DocumentRefs' => $cns);
		$data = $this->apiRequest('ScanSheet', 'removeDocuments', $properties);

		if (!empty($data['DocumentRefs'])) {
			return $data['DocumentRefs'];
		}

		return false;
	}

	public function deleteRegistry($refs)
	{
		$properties['ScanSheetRefs'] = $refs;
		$data = $this->apiRequest('ScanSheet', 'deleteScanSheet', $properties);

		if (!empty($data['ScanSheetRefs'])) {
			return $data['ScanSheetRefs'];
		}

		return false;
	}

	public function tracking($documents = array())
	{
		$properties = array('Documents' => $documents);
		$data = $this->apiRequest('TrackingDocument', 'getStatusDocuments', $properties);

		return $data;
	}

	public function getDeparture($products)
	{
		$data['weight'] = 0;
		$data['length'] = 0;
		$data['width'] = 0;
		$data['height'] = 0;
		$data['volume'] = 0;
		$data['parcels'] = array();

		if (empty($products) || !is_array($products)) {
			$products = array(array('quantity' => 1, 'weight_class_id' => 0, 'length_class_id' => 0, 'weight' => 0, 'length' => 0, 'width' => 0, 'height' => 0));
		}

		if (empty($this->settings['use_parameters'])) {
			$this->settings['use_parameters'] = 'products_without_parameters';
		}

		foreach ($products as $product) {
			$w_unit = $this->weight->getUnit($product['weight_class_id']);
			$l_unit = $this->length->getUnit($product['length_class_id']);

			for ($i = 1; $i <= $product['quantity']; $i++) {
				if ($this->settings['use_parameters'] == 'products_without_parameters' && (double) $product['weight']) {
					$t_parcel['weight'] = $this->weightConvert($product['weight'] / $product['quantity'], $w_unit);
				} else {
					$t_parcel['weight'] = (double) $this->settings['weight'];
				}

				if ($this->settings['use_parameters'] == 'products_without_parameters' && (double) $product['length']) {
					$t_parcel['length'] = $this->dimensionConvert($product['length'], $l_unit);
				} else {
					$t_parcel['length'] = (double) $this->settings['dimensions_l'];
				}

				if ($this->settings['use_parameters'] == 'products_without_parameters' && (double) $product['width']) {
					$t_parcel['width'] = $this->dimensionConvert($product['width'], $l_unit);
				} else {
					$t_parcel['width'] = (double) $this->settings['dimensions_w'];
				}

				if ($this->settings['use_parameters'] == 'products_without_parameters' && (double) $product['height']) {
					$t_parcel['height'] = $this->dimensionConvert($product['height'], $l_unit);
				} else {
					$t_parcel['height'] = (double) $this->settings['dimensions_h'];
				}

				$t_parcel['length'] += (double) $this->settings['allowance_l'];
				$t_parcel['width'] += (double) $this->settings['allowance_w'];
				$t_parcel['height'] += (double) $this->settings['allowance_h'];
				$t_parcel['volume'] = ($t_parcel['length'] * $t_parcel['width'] * $t_parcel['height']) / 1000000;
				$t_parcel['volume_weight'] = $t_parcel['volume'] * 250;
				$data['weight'] += $t_parcel['weight'];
				$data['parcels'][] = $t_parcel;

				if ($this->settings['calculate_volume'] && $this->settings['calculate_volume_type'] == 'largest_product') {
					if ($data['volume'] < $t_parcel['volume']) {
						$data['length'] = $t_parcel['length'];
						$data['width'] = $t_parcel['width'];
						$data['height'] = $t_parcel['height'];
						$data['volume'] = $t_parcel['volume'];
					}
				} else {
					$data['length'] = max($data['length'], $t_parcel['length']);
					$data['width'] = max($data['width'], $t_parcel['width']);
					$data['height'] += $t_parcel['height'];
					$data['volume'] = ($data['length'] * $data['width'] * $data['height']) / 1000000;
				}

				if ($this->settings['use_parameters'] == 'whole_order') {
					break;
				}
			}

			if ($this->settings['use_parameters'] == 'whole_order') {
				break;
			}
		}
		$data['weight'] = max(round($data['weight'], 2), 0.1, $this->settings['weight_minimum']);
		$data['length'] = max(round($data['length']), 1);
		$data['width'] = max(round($data['width']), 1);
		$data['height'] = max(round($data['height']), 1);
		$data['volume'] = max(round($data['volume'], 4), 0.0004);

		return $data;
	}

	public function getDepartureType($departure)
	{
		if ($departure['length'] <= 25 && $departure['width'] <= 35 && $departure['height'] <= 2 && $departure['weight'] <= 1) {
			$type = 'Documents';
		} else {
			if ($departure['length'] <= 120 && $departure['width'] <= 120 && $departure['height'] <= 120 && $departure['weight'] <= 30) {
				$type = 'Parcel';
			} else {
				$type = 'Cargo';
			}
		}

		return $type;
	}

	public function getDepartureSeats($products = array())
	{
		$seats = 0;

		foreach ($products as $product) {
			$seats += $product['quantity'];
		}

		return $seats;
	}

	public function getDeclaredCost($totals)
	{
		$declared_cost = 0;

		foreach ($totals as $total) {
			if (isset($this->settings['declared_cost']) && in_array($total['code'], (array) $this->settings['declared_cost'])) {
				$declared_cost += $total['value'];
			}
		}

		return $declared_cost;
	}

	public function getPackType($departure)
	{
		$packs = $this->getReferences('pack_types');

		if (is_array($packs)) {
			$packs = $this->multiSort($packs, 'Length', 'Width', 'Height');
		}

		foreach ($packs as $pack) {
			if (in_array($pack['Ref'], $this->settings['pack_type']) && $departure['length'] * 10 <= $pack['Length'] && $departure['width'] * 10 <= $pack['Width'] && ($departure['height'] * 10 <= $pack['Height'] || !(double) $pack['Height'])) {
				return $pack['Ref'];
			}
		}

		return false;
	}

	public function weightConvert($value, $unit)
	{
		if (preg_match('/\\b(g|gr|gram|gramm|gramme|г|гр|грам|грамм)\\b\\.?/ui', $unit)) {
			return (double) $value / 1000;
		}

		return (double) $value;
	}

	public function dimensionConvert($value, $unit)
	{
		if (preg_match('/\\b(mm|millimeter|мм|міліметр|миллиметр)\\b\\.?/ui', $unit)) {
			return (double) $value / 10;
		}

		if (preg_match('/\\b(dm|decimetre|дц|дециметр)\\b\\.?/ui', $unit)) {
			return (double) $value * 10;
		}

		if (preg_match('/\\b(m|metre|м|метр)\\b\\.?/ui', $unit)) {
			return (double) $value * 100;
		}

		return (double) $value;
	}

	public function dateDiff($string_time)
	{
		return ceil((strtotime($string_time) - time()) / 86400);
	}

	public function multiSort()
	{
		$args = func_get_args();
		$c = count($args);

		if ($c < 2) {
			return false;
		}

		$array = array_splice($args, 0, 1);
		$array = $array[0];
		usort(
			$array,
			function($a, $b) use ($args) {
			$i = 0;
			$c = count($args);

			for ($cmp = 0; $cmp == 0 && $i < $c; $i++) {
				if ($a[$args[$i]] == $b[$args[$i]]) {
					$cmp = 0;
				} else {
					if ($a[$args[$i]] < $b[$args[$i]]) {
						$cmp = -1;
					} else {
						$cmp = 1;
					}
				}

				if (end($args) == 'DESC') {
					$cmp *= -1;
				}
			}

			return $cmp;
		}
		);

		return $array;
	}

	public function getDomain()
	{
		if (HTTP_SERVER) {
			$url = parse_url(HTTP_SERVER);
			$d_1 = str_replace('www.', '', $url['host']);
		} else {
			if (HTTPS_SERVER) {
				$url = parse_url(HTTPS_SERVER);
				$d_1 = str_replace('www.', '', $url['host']);
			} else {
				$d_1 = '';
			}
		}

		return $d_1;
	}
}
