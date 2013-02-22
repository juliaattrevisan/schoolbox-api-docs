<?php

if (is_array($_POST['id']) && count($_POST['id'])) {
	$allConfigs = Calendar_Api_Config_Mapper::getInstance()->findByColumnList(array(
		'event_type' => $_POST['id'][0],
		'connector_class' => $_POST['id'][1],
	));

	/* Ensure that each connector has at least one config
	 * If it doesn't have one, add one
	 */
	if (!$allConfigs->count()) {
		$className = 'Calendar_Api_Config_Type_' . Scrub::text($_POST['id'][1]);

		if (class_exists($className)) {
			$config = new $className();
			$config->setEventType(Scrub::text($_POST['id'][0]));
			$config->setConnectorClass(Scrub::text($_POST['id'][1]));

			$configRecord = new Calendar_Api_Config_Record();
			$configRecord->setEventType(Scrub::text($_POST['id'][0]));
			$configRecord->setConnectorClass(Scrub::text($_POST['id'][1]));
			$configRecord->updateConfig($config);

			/* Ram in this new record
			 */
			$allConfigs->set(array($configRecord));
		}
	}

	/* Change status for each config
	 */
	foreach($allConfigs as $currentConfig) {
		if ($_POST['status'] == 1) {
			$currentConfig->activate();
		}
		else {
			$currentConfig->deactivate();
		}

		Calendar_Api_Config_Mapper::getInstance()->save(array($currentConfig));
	}
}

exit();