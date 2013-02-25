<?php
require_once SITE_root . '/library/user.php';
require_once SITE_root . '/library/group.php';

$groupHandler = new Group();

if (!$groupHandler->isSuperuser()) {
    error(19);
}

$this->setFile(TEMPLATE_content, 'adminv2/calendar.html');
$this->setTitle('Admin - Calendar');

// include the admin navigation
$nav = new AdminV2_Nav();
$nav->parse($this, 'calendar');

$userHandler = new User();

$this->checkList('campus_check_list', $userHandler->getCampusList(), '', TEMPLATE_content, array('id'=>'CAMPUSID','name'=>'CAMPUSNAME'));
$this->checkList('user_group_check_list', $userHandler->getGroupList(), '', TEMPLATE_content, array('id'=>'USERGROUPID','name'=>'USERGROUPNAME'));

/* Stolen from Settings
 */
$this->setBlock(TEMPLATE_content, 'setting_tab', 'setting_tab_block');
$this->setBlock(TEMPLATE_content, 'import', 'import_block');
$this->setBlock(TEMPLATE_content, 'subscribe', 'subscribe_block');
$this->setBlock('subscribe', 'eventType', 'eventType_block');
$this->setBlock('eventType', 'connector', 'connector_block');
$this->setBlock('connector', 'connectorConfig', 'connectorConfig_block');
$this->setBlock('connector', 'has_multiple_options', 'has_multiple_options_block');

$this->setVar('import_block', '');
$this->setVar('subscribe_block', '');
$this->setVar('eventType_block', '');
$this->setVar('connector_block', '');

if ($this->arg[0]) {
	$selectedType = urldecode($this->arg[0]);
}
else {
	$selectedType = 'Import';
}

$typeList = array(
	array(
		'type' => 'Import'
	),
	array(
		'type' => 'Subscribe'
	),
);

if (is_array($typeList)) {
	foreach ($typeList as $type) {

		/* Navigation stuff
 		 */
		$this->setVar(array(
			'SETTING_TAB_NAME' => $type['type'],
			'SETTING_TAB_URL' => urlencode($type['type']),
			'SETTING_TAB_CURRENT' => '',
		));

		if ($selectedType == $type['type']) {
			$this->setVar('SETTING_TAB_CURRENT', 'current');
		}

		$this->parse('setting_tab_block', 'setting_tab', true);

		/* Individual pages
		 * Import page
		 */
		if (($type['type'] == 'Import') && ($selectedType == 'Import')) {
			$this->parse('import_block', 'import');
		}
		/* Subscribe page
	 	 */
		else if (($type['type'] == 'Subscribe') && ($selectedType == 'Subscribe')) {

			foreach(glob("../library/Calendar/Api/*") as $dirname) {
				echo $dirname . '<BR>';
			}

			$allEventTypes = array('Event', 'StudentBirthday', 'Submission');
			$allConnectors = array('Schoolbox', 'Sharepoint', 'Vcal');

			/* All calendar subscription options go here
			 */
			foreach($allEventTypes as $currentEventType) {
				$this->setVar(array(
					'EVENTTYPE_NAME' => $currentEventType,
				));

				/* Inject individual templates just like before -_-
				 */
				foreach($allConnectors as $currentConnector) {

					/* Process each config for this kind of connector
					 */
					$allConfigs = Calendar_Api_Config_Mapper::getInstance()->getAllConfigs($currentEventType, $currentConnector);

					if (count($allConfigs)) {
						foreach($allConfigs as $currentConfig) {
							$this->setFile('configFragment', "adminv2/calendar/config/{$currentConnector}.html");

							$currentConfig->render($this);

							$this->parse('connectorConfig_block', 'configFragment', true);
						}

						/* Whether this connector displays at Ticked or not
						 */
						$connectorChecked = 'checked="checked"';
					}
					else {
						$connectorChecked = '';
					}

					/* Render titles etc.
					 */
					$this->setVar(array(
						'CONNECTOR_NAME' => $currentConnector,
						'CONNECTOR_CHECKED' => $connectorChecked,
					));

					$this->parse('connector_block', 'connector', true);

					/* Clear children after parsing out the parent
					 */
					$this->setVar('connectorConfig_block', '');
					$this->setVar('configFragment', '');
					$this->setVar('has_multiple_options_block', '');
				}

				$this->parse('eventType_block', 'eventType', true);

				/* Clear children after parsing out the parent
				 */
				$this->setVar('connector_block', '');
			}

			$this->parse('subscribe_block', 'subscribe');
		}
	}
}

/* Defaults
 */
//$start = 85;
//
//$config = new Calendar_Api_Config_Type_Vcal();
//$config->setId($start);
//$config->setEventType('Event');
//$config->setConnectorClass('Vcal');
//$config->setUrl('http://www.google.com');
//
//$configRecord = new Calendar_Api_Config_Record();
//$configRecord->setEventType('Event');
//$configRecord->setConnectorClass('Vcal');
//$configRecord->updateConfig($config);
//
//Calendar_Api_Config_Mapper::getInstance()->save(array($configRecord));
//
//$config = new Calendar_Api_Config_Type_Vcal();
//$config->setId($start++);
//$config->setEventType('Event');
//$config->setConnectorClass('Vcal');
//$config->setUrl('http://www.gruber.org');
//
//$configRecord = new Calendar_Api_Config_Record();
//$configRecord->setEventType('Event');
//$configRecord->setConnectorClass('Vcal');
//$configRecord->updateConfig($config);
//
//Calendar_Api_Config_Mapper::getInstance()->save(array($configRecord));
//
//$config = new Calendar_Api_Config_Type_Sharepoint();
//$config->setId($start++);
//$config->setEventType('Submission');
//$config->setConnectorClass('Schoolbox');
//
//$configRecord = new Calendar_Api_Config_Record();
//$configRecord->setEventType('Submission');
//$configRecord->setConnectorClass('Schoolbox');
//$configRecord->updateConfig($config);
//
//Calendar_Api_Config_Mapper::getInstance()->save(array($configRecord));
//
//$config = new Calendar_Api_Config_Type_Sharepoint();
//$config->setId($start++);
//$config->setEventType('StudentBirthday');
//$config->setConnectorClass('Sharepoint');
//
//$configRecord = new Calendar_Api_Config_Record();
//$configRecord->setEventType('StudentBirthday');
//$configRecord->setConnectorClass('Sharepoint');
//$configRecord->updateConfig($config);
//
//Calendar_Api_Config_Mapper::getInstance()->save(array($configRecord));