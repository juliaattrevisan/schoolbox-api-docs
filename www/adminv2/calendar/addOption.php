<?php
print_r($_POST);
if (strlen($_POST['id'])) {
	$bits = explode('_', $_POST['id']);

	$className = 'Calendar_Api_Config_Connector_' . Scrub::text($bits[1]);

	$config = new $className();
	$config->setEventType(Scrub::text($bits[0]));
	$config->setConnectorClass(Scrub::text($bits[1]));

	$configRecord = new Calendar_Api_Config_Record();
	$configRecord->setEventType(Scrub::text($bits[0]));
	$configRecord->setConnectorClass(Scrub::text($bits[1]));
	$configRecord->updateConfig($config);

	/* This is active
	 */
	$configRecord->activate();

	/* Ram in this new record
	 */
	Calendar_Api_Config_Mapper::getInstance()->save(array($configRecord));
}

exit();