<?php

if (is_array($_POST['id']) && count($_POST['id'])) {
	$allConfigs = Calendar_Api_Config_Mapper::getInstance()->findByColumnList(array(
		'event_type' => $_POST['id'][0],
		'connector_class' => $_POST['id'][1],
	));

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